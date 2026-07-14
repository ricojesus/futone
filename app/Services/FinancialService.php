<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTransaction;
use App\Models\League;
use App\Models\LeagueMessage;
use App\Models\LeagueTeam;
use Illuminate\Support\Facades\DB;

class FinancialService
{
    public function __construct(
        private readonly MessageService $messages,
    ) {}

    private const TV_QUOTAS = [
        Competition::COMPETITION_TYPE_NATIONAL => [
            Competition::DIVISION_FIRST  => 10_000_000,
            Competition::DIVISION_SECOND =>  4_000_000,
        ],
        Competition::COMPETITION_TYPE_COPA => [
            Competition::DIVISION_FIRST  =>  5_000_000,
        ],
        Competition::COMPETITION_TYPE_STATE => [
            Competition::DIVISION_FIRST  =>  2_000_000,
            Competition::DIVISION_SECOND =>  1_000_000,
        ],
    ];

    /**
     * Paga a cota de TV de UMA competição aos seus participantes.
     * Deve ser chamada no momento em que a competição é criada
     * (estaduais na geração da temporada, Copa ao ser gerada,
     * Séries A/B na transição para a fase nacional) — spec 002.
     */
    public function payTvQuotaFor(Competition $competition): void
    {
        $quota = $this->quotaFor($competition);
        if ($quota === 0) {
            return;
        }

        DB::transaction(function () use ($competition, $quota) {
            foreach ($competition->teams()->get() as $competitionTeam) {
                $leagueTeam = LeagueTeam::find($competitionTeam->league_team_id);
                if (! $leagueTeam) {
                    continue;
                }

                $leagueTeam->increment('budget', $quota);

                CompetitionTransaction::create([
                    'competition_team_id' => $competitionTeam->id,
                    'type'                => 'prize_money',
                    'amount'              => $quota,
                    'description'         => 'Cota de TV — ' . $competition->name,
                    'round'               => 0,
                ]);

                $this->messages->sendToTeam(
                    $leagueTeam,
                    LeagueMessage::TYPE_FINANCIAL,
                    "Cota de TV — {$competition->name}",
                    "A emissora depositou {$this->money($quota)} pela participação na competição.",
                );
            }
        });
    }

    /**
     * Desconta o total de salários de jogadores ativos de cada league_team da liga.
     * Deve ser chamado a cada semana de calendário (advanceWeek).
     */
    public function deductWeeklySalaries(League $league): void
    {
        $leagueTeams = LeagueTeam::where('league_id', $league->id)->get();

        DB::transaction(function () use ($leagueTeams) {
            foreach ($leagueTeams as $leagueTeam) {
                $totalWage = CompetitionPlayer::where('league_team_id', $leagueTeam->id)
                    ->where('status', 'active')
                    ->sum('wage');

                if ($totalWage <= 0) {
                    continue;
                }

                $leagueTeam->decrement('budget', $totalWage);

                $compTeam = $this->currentCompetitionTeam($leagueTeam);
                if ($compTeam) {
                    CompetitionTransaction::create([
                        'competition_team_id' => $compTeam->id,
                        'type'                => 'wage_payment',
                        'amount'              => -$totalWage,
                        'description'         => 'Salários semanais do elenco',
                        'round'               => $compTeam->competition?->current_round ?? 0,
                    ]);
                }

                $balance = $leagueTeam->fresh()->budget;
                $body    = "Folha semanal de {$this->money($totalWage)} debitada. Saldo atual: {$this->money($balance)}.";

                // Alerta: caixa não cobre nem uma semana de folha (a calibrar)
                if ($balance < $totalWage) {
                    $body .= ' Atenção: o caixa não cobre a próxima semana de salários.';
                }

                $this->messages->sendToTeam(
                    $leagueTeam,
                    LeagueMessage::TYPE_FINANCIAL,
                    'Salários semanais pagos',
                    $body,
                );
            }
        });
    }

    /**
     * CompetitionTeam do time em uma competição ativa (para vincular transações).
     */
    private function currentCompetitionTeam(LeagueTeam $leagueTeam): ?object
    {
        return \App\Models\CompetitionTeam::where('league_team_id', $leagueTeam->id)
            ->whereHas('competition', fn($q) => $q->where('status', Competition::STATUS_IN_PROGRESS))
            ->with('competition')
            ->first();
    }

    /**
     * Calcula e grava o público esperado no início da partida.
     * Chamado no simulateFirstHalf para que a info apareça já no 1º tempo.
     */
    public function calculateAndStoreAttendance(CompetitionMatch $match): void
    {
        $homeCompTeam = $match->homeTeam;
        if (! $homeCompTeam) return;

        $leagueTeam = $homeCompTeam->leagueTeam;
        if (! $leagueTeam) return;

        $attendance  = $this->attendanceFor($leagueTeam, $match->competition);
        $ticketPrice = max(1, $leagueTeam->ticket_price);

        CompetitionMatch::where('id', $match->id)->update([
            'attendance'    => $attendance,
            'match_revenue' => $attendance * $ticketPrice,
        ]);
    }

    /**
     * Calcula receita de bilheteria para uma partida finalizada e credita ao time da casa.
     * Usa o público já calculado no 1º tempo (ou recalcula se ausente).
     * Chamado sem transação própria para poder ser usado dentro de transações existentes.
     */
    public function processMatchRevenue(CompetitionMatch $match): void
    {
        $homeCompTeam = $match->homeTeam;
        if (! $homeCompTeam) return;

        $leagueTeam = $homeCompTeam->leagueTeam;
        if (! $leagueTeam) return;

        $ticketPrice = max(1, $leagueTeam->ticket_price);
        $attendance  = $match->attendance ?? $this->attendanceFor($leagueTeam, $match->competition);
        $revenue     = $attendance * $ticketPrice;

        if ($revenue <= 0) return;

        LeagueTeam::where('id', $leagueTeam->id)->increment('budget', $revenue);

        CompetitionTransaction::create([
            'competition_team_id' => $homeCompTeam->id,
            'type'                => 'match_revenue',
            'amount'              => $revenue,
            'description'         => "Bilheteria — {$attendance} torcedores × R\$ {$ticketPrice}",
            'round'               => $match->round,
        ]);

        CompetitionMatch::where('id', $match->id)->update([
            'attendance'    => $attendance,
            'match_revenue' => $revenue,
        ]);

        $this->messages->sendToTeam(
            $leagueTeam,
            LeagueMessage::TYPE_FINANCIAL,
            'Renda de bilheteria',
            "{$attendance} torcedores renderam {$this->money($revenue)} como mandante.",
            $match,
        );
    }

    private function money(int $value): string
    {
        return 'R$ ' . number_format($value, 0, ',', '.');
    }

    private function attendanceFor(LeagueTeam $leagueTeam, Competition $competition): int
    {
        $capacity     = $leagueTeam->stadium_capacity;
        $satisfaction = $leagueTeam->satisfaction;
        $ticketPrice  = max(1, $leagueTeam->ticket_price);
        $compWeight   = $this->competitionWeight($competition);

        $satFactor    = $satisfaction / 100;
        $pricePenalty = max(0, ($ticketPrice - 50) / 200) * (1 - $satFactor);
        $occupation   = $satFactor * $compWeight * (1 - $pricePenalty);
        $occupation   = max(0.05, min(1.0, $occupation));

        return (int) round($capacity * $occupation);
    }

    private function competitionWeight(Competition $competition): float
    {
        return match ($competition->competition_type) {
            Competition::COMPETITION_TYPE_COPA    => 1.0,
            Competition::COMPETITION_TYPE_NATIONAL => $competition->division === Competition::DIVISION_FIRST ? 0.9 : 0.5,
            Competition::COMPETITION_TYPE_STATE    => $competition->division === Competition::DIVISION_FIRST ? 0.6 : 0.4,
            default => 0.5,
        };
    }

    private function quotaFor(Competition $competition): int
    {
        $type = $competition->competition_type;

        if ($type === Competition::COMPETITION_TYPE_COPA) {
            return self::TV_QUOTAS[$type][Competition::DIVISION_FIRST] ?? 0;
        }

        $division = $competition->division ?? Competition::DIVISION_FIRST;
        return self::TV_QUOTAS[$type][$division] ?? 0;
    }
}
