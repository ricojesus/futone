<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueInvitation;
use App\Models\LeagueMember;
use App\Models\LeagueMessage;
use App\Models\LeagueTeam;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de convites para técnicos humanos demitidos (spec 005, RF-GES-03).
 *
 * A cada rodada global: convites da rodada anterior expiram e cada usuário
 * com LeagueMember 'fired' recebe até MAX_PER_ROUND convites de times CPU
 * de divisão igual ou inferior à do clube de onde saiu. O ex-clube só volta
 * a ser elegível após EX_CLUB_COOLDOWN rodadas (~6 meses de jogo).
 * Convites não são exclusivos: o primeiro a aceitar leva o time.
 */
class InvitationService
{
    // Convites por usuário demitido por rodada (a calibrar)
    private const MAX_PER_ROUND = 3;

    // Rodadas globais até o ex-clube poder reconvidar (~6 meses de jogo, a calibrar)
    public const EX_CLUB_COOLDOWN = 12;

    public function __construct(
        private readonly MessageService $messages,
        private readonly SatisfactionService $satisfaction,
    ) {}

    /**
     * Expira os convites de rodadas anteriores e gera os da rodada vigente.
     * Chamado ao final de cada GlobalRoundService::advance.
     */
    public function expireAndGenerate(League $league): void
    {
        LeagueInvitation::where('league_id', $league->id)
            ->where('status', LeagueInvitation::STATUS_PENDING)
            ->where('global_round', '<', $league->global_round)
            ->update(['status' => LeagueInvitation::STATUS_EXPIRED]);

        if (! $league->isInProgress()) {
            return;
        }

        $firedMembers = LeagueMember::where('league_id', $league->id)
            ->where('status', LeagueMember::STATUS_FIRED)
            ->get();

        foreach ($firedMembers as $member) {
            $this->generateFor($league, $member);
        }
    }

    /**
     * Aceita um convite: assume o time, expira os convites concorrentes
     * e reativa o vínculo do usuário na liga.
     */
    public function accept(LeagueInvitation $invitation, User $user): LeagueTeam
    {
        return DB::transaction(function () use ($invitation, $user) {
            $locked = LeagueInvitation::whereKey($invitation->id)->lockForUpdate()->first();
            $league = $locked->league;
            $team   = LeagueTeam::whereKey($locked->league_team_id)->lockForUpdate()->first();

            abort_unless($locked->user_id === $user->id, 403);
            abort_unless($locked->isOpen($league), 409, 'Este convite não está mais disponível.');
            abort_unless($team->user_id === null, 409, 'Outro técnico já assumiu este clube.');

            $team->update(['user_id' => $user->id]);

            // Técnico CPU do time vai para o pool da liga
            if ($team->coach_id) {
                $this->satisfaction->releaseCoachToPool($league->id, $team->id, $team->coach_id);
                $team->update(['coach_id' => null]);
            }

            // Novo técnico começa com a diretoria zerada, independente de como
            // o CPU anterior deixou o clube.
            $this->satisfaction->resetCoachSatisfaction($team);

            $locked->update(['status' => LeagueInvitation::STATUS_ACCEPTED]);

            // Convites concorrentes: os demais do usuário e os de outros usuários para este time
            LeagueInvitation::where('league_id', $league->id)
                ->where('status', LeagueInvitation::STATUS_PENDING)
                ->where(fn ($q) => $q->where('user_id', $user->id)
                    ->orWhere('league_team_id', $team->id))
                ->update(['status' => LeagueInvitation::STATUS_EXPIRED]);

            LeagueMember::updateOrCreate(
                ['league_id' => $league->id, 'user_id' => $user->id],
                ['status' => LeagueMember::STATUS_ASSIGNED],
            );

            $this->messages->sendToTeam(
                $team->fresh(),
                LeagueMessage::TYPE_CLUB,
                "Bem-vindo ao {$team->name}!",
                "A diretoria do {$team->name} confirmou sua contratação. O elenco espera pelo novo comando — boa sorte, professor.",
            );

            return $team->fresh();
        });
    }

    /**
     * Recusa um convite pendente.
     */
    public function decline(LeagueInvitation $invitation, User $user): void
    {
        abort_unless($invitation->user_id === $user->id, 403);
        abort_unless($invitation->status === LeagueInvitation::STATUS_PENDING, 409, 'Convite não está mais pendente.');

        $invitation->update(['status' => LeagueInvitation::STATUS_DECLINED]);
    }

    // ── Internos ──────────────────────────────────────────────────────

    private function generateFor(League $league, LeagueMember $member): void
    {
        // Usuário já voltou a ter time? Nada a fazer (saneamento defensivo)
        $hasTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', $member->user_id)
            ->exists();

        if ($hasTeam) {
            $member->update(['status' => LeagueMember::STATUS_ASSIGNED]);
            return;
        }

        $candidates = $this->eligibleTeams($league, $member);

        if ($candidates->isEmpty()) {
            return;
        }

        $invited = $candidates->shuffle()->take(self::MAX_PER_ROUND);

        foreach ($invited as $team) {
            LeagueInvitation::create([
                'league_id'      => $league->id,
                'user_id'        => $member->user_id,
                'league_team_id' => $team->id,
                'status'         => LeagueInvitation::STATUS_PENDING,
                'global_round'   => $league->global_round,
            ]);
        }

        $names = $invited->pluck('name')->join(', ');

        $this->messages->sendToUser(
            $league,
            $member->user_id,
            LeagueMessage::TYPE_INVITATION,
            'Clubes interessados no seu trabalho',
            "Você recebeu convite de: {$names}. Os convites valem até a próxima rodada — responda no Escritório.",
        );
    }

    /**
     * Times CPU de divisão igual ou inferior à do clube de onde o usuário saiu.
     * O ex-clube só é elegível após a carência de EX_CLUB_COOLDOWN rodadas.
     */
    private function eligibleTeams(League $league, LeagueMember $member): Collection
    {
        $firedRank = $this->divisionRank(
            $member->fired_from_league_team_id
                ? LeagueTeam::find($member->fired_from_league_team_id)
                : null
        );

        $cooldownActive = $member->fired_at_global_round !== null
            && $league->global_round < $member->fired_at_global_round + self::EX_CLUB_COOLDOWN;

        return LeagueTeam::where('league_id', $league->id)
            ->whereNull('user_id')
            ->get()
            ->filter(function (LeagueTeam $team) use ($firedRank, $cooldownActive, $member) {
                if ($cooldownActive && $team->id === $member->fired_from_league_team_id) {
                    return false;
                }

                return $this->divisionRank($team) >= $firedRank;
            })
            ->values();
    }

    /**
     * Ranking de divisão para comparação (menor = divisão superior):
     * Série A = 1, Série B = 2, sem divisão nacional = 3.
     */
    private function divisionRank(?LeagueTeam $team): int
    {
        return match ($team?->national_division) {
            'first'  => 1,
            'second' => 2,
            default  => 3,
        };
    }
}
