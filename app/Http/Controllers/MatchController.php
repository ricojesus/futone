<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Models\MatchState;
use App\Services\LiveMatchSimulator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    // ── Partida encerrada ─────────────────────────────────────────────

    public function show(League $league, Competition $competition, CompetitionMatch $match)
    {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($match->competition_id === $competition->id, 404);
        abort_unless($match->status === 'finished', 404, 'Partida ainda não disputada.');

        $match->load(['homeTeam', 'awayTeam']);

        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        $isMyMatch = $myLeagueTeam && (
            $match->homeTeam->league_team_id === $myLeagueTeam->id ||
            $match->awayTeam->league_team_id === $myLeagueTeam->id
        );

        $side = null;
        if ($isMyMatch) {
            $side = $match->homeTeam->league_team_id === $myLeagueTeam->id ? 'home' : 'away';
        }

        $roundMatches = $competition->matches()
            ->where('round', $match->round)
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $replayMode = request()->boolean('replay');

        return view('leagues.competitions.matches.show', compact(
            'league', 'competition', 'match',
            'myLeagueTeam', 'isMyMatch', 'side', 'roundMatches', 'replayMode'
        ));
    }

    // ── Intervalo ─────────────────────────────────────────────────────

    public function halftime(League $league, Competition $competition, CompetitionMatch $match)
    {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($match->competition_id === $competition->id, 404);
        abort_unless($match->status === 'halftime', 404, 'Esta partida não está no intervalo.');

        $match->load(['homeTeam.leagueTeam', 'awayTeam.leagueTeam']);

        $matchState = MatchState::where('competition_match_id', $match->id)->firstOrFail();
        $state      = $matchState->state;

        // Time do usuário nesta liga
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        // Determina de que lado o usuário está
        $side = null;
        $myCompTeam = null;
        if ($myLeagueTeam) {
            if ($match->homeTeam->league_team_id === $myLeagueTeam->id) {
                $side       = 'home';
                $myCompTeam = $match->homeTeam;
            } elseif ($match->awayTeam->league_team_id === $myLeagueTeam->id) {
                $side       = 'away';
                $myCompTeam = $match->awayTeam;
            }
        }

        $canResume = $myLeagueTeam && $side !== null;

        // Escalação do time do usuário (titulares + reservas) para substituições
        $lineup  = null;
        $starters = collect();
        $bench    = collect();

        if ($myCompTeam) {
            $lineup = $myLeagueTeam->lineups()
                ->where('status', 'active')
                ->whereIn('round', [$match->round, 0])
                ->orderByDesc('round')
                ->first();

            if ($lineup) {
                $allPlayers = $lineup->players()
                    ->orderBy('competition_lineup_players.slot')
                    ->get();

                $starters = $allPlayers->filter(fn($p) => $p->pivot->is_starter);
                $bench    = $allPlayers->filter(fn($p) => ! $p->pivot->is_starter);
            }
        }

        // Eventos e stats do primeiro tempo
        $events         = $state['events']           ?? [];
        $homeScore      = $state['homeScore']         ?? 0;
        $awayScore      = $state['awayScore']         ?? 0;
        $homeShots      = $state['homeShots']         ?? 0;
        $awayShots      = $state['awayShots']         ?? 0;
        $homeOnTarget   = $state['homeShotsOnTarget'] ?? 0;
        $awayOnTarget   = $state['awayShotsOnTarget'] ?? 0;
        $homePossCount  = $state['homePossCount']     ?? 0;
        $homePossession = $homePossCount > 0
            ? (int) round($homePossCount / 45 * 100)
            : 50;

        return view('leagues.competitions.matches.halftime', compact(
            'league', 'competition', 'match', 'matchState',
            'myLeagueTeam', 'side', 'canResume',
            'lineup', 'starters', 'bench',
            'events', 'homeScore', 'awayScore',
            'homeShots', 'awayShots', 'homeOnTarget', 'awayOnTarget',
            'homePossession',
        ));
    }

    // ── Iniciar segundo tempo ─────────────────────────────────────────

    public function resumeSecondHalf(
        Request            $request,
        League             $league,
        Competition        $competition,
        CompetitionMatch   $match,
        LiveMatchSimulator $liveSimulator,
    ) {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($match->competition_id === $competition->id, 404);
        abort_unless($match->status === 'halftime', 409, 'Partida não está no intervalo.');

        $match->load(['homeTeam.leagueTeam', 'awayTeam.leagueTeam']);

        // Só o técnico do time (home ou away) pode iniciar o segundo tempo
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        $isParticipant = $myLeagueTeam && (
            $match->homeTeam->league_team_id === $myLeagueTeam->id ||
            $match->awayTeam->league_team_id === $myLeagueTeam->id
        );

        abort_unless($isParticipant, 403, 'Apenas o técnico do time pode iniciar o segundo tempo.');

        // ── Aplicar substituições ────────────────────────────────────
        // Formato: substitutions[0][out] = player_id, substitutions[0][in] = player_id
        $substitutions = $request->input('substitutions', []);

        if (! empty($substitutions)) {
            $this->applySubstitutions($myLeagueTeam, $match->round, $substitutions);
        }

        // ── Simular segundo tempo ────────────────────────────────────
        DB::transaction(function () use ($match, $liveSimulator, $competition) {
            $result = $liveSimulator->simulateSecondHalf($match);

            // Atualizar standings
            $homeTeam = $match->homeTeam;
            $awayTeam = $match->awayTeam;

            if ($result['home_score'] > $result['away_score']) {
                $homeTeam->increment('wins');
                $homeTeam->increment('points', 3);
                $awayTeam->increment('losses');
            } elseif ($result['home_score'] < $result['away_score']) {
                $awayTeam->increment('wins');
                $awayTeam->increment('points', 3);
                $homeTeam->increment('losses');
            } else {
                $homeTeam->increment('draws');
                $homeTeam->increment('points', 1);
                $awayTeam->increment('draws');
                $awayTeam->increment('points', 1);
            }

            $homeTeam->increment('goals_for',     $result['home_score']);
            $homeTeam->increment('goals_against',  $result['away_score']);
            $awayTeam->increment('goals_for',     $result['away_score']);
            $awayTeam->increment('goals_against',  $result['home_score']);

            // Artilharia e fitness (reutiliza o match fresh com eventos completos)
            $this->applyGoalsScored($match->fresh());
            $this->applyFitnessDegradation($homeTeam, $awayTeam);

            // Avança a rodada se todos os jogos daquela rodada estão finalizados
            $pending = CompetitionMatch::where('competition_id', $competition->id)
                ->where('round', $match->round)
                ->whereNotIn('status', ['finished'])
                ->count();

            if ($pending === 0) {
                $competition->increment('current_round');

                if ($competition->fresh()->current_round >= $competition->total_rounds) {
                    $competition->update(['status' => Competition::STATUS_FINISHED]);
                }
            }
        });

        return redirect()
            ->route('matches.show', [$league, $competition, $match->fresh(), 'replay' => 1])
            ->with('success', 'Segundo tempo concluído! Assista ao replay completo.');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Aplica substituições do intervalo na escalação do time.
     * Cria (ou atualiza) um override de escalação para a rodada específica.
     */
    private function applySubstitutions(LeagueTeam $leagueTeam, int $round, array $substitutions): void
    {
        // Busca escalação ativa (padrão ou desta rodada)
        $baseLineup = $leagueTeam->lineups()
            ->where('status', 'active')
            ->whereIn('round', [$round, 0])
            ->orderByDesc('round')
            ->first();

        if (! $baseLineup) {
            return;
        }

        // Se o override da rodada não existe ainda, clona a escalação padrão
        if ($baseLineup->round !== $round) {
            $override = $baseLineup->replicate(['id', 'created_at', 'updated_at']);
            $override->round = $round;
            $override->save();

            // Copia todos os jogadores para o override
            foreach ($baseLineup->lineupPlayers as $lp) {
                $override->lineupPlayers()->create([
                    'competition_player_id' => $lp->competition_player_id,
                    'role'                  => $lp->role,
                    'is_starter'            => $lp->is_starter,
                    'slot'                  => $lp->slot,
                ]);
            }

            $baseLineup = $override->fresh(['lineupPlayers']);
        }

        // Aplica cada substituição: swap is_starter entre out e in
        foreach (array_slice($substitutions, 0, 5) as $sub) {
            $outId = $sub['out'] ?? null;
            $inId  = $sub['in']  ?? null;

            if (! $outId || ! $inId || $outId === $inId) {
                continue;
            }

            $outRecord = $baseLineup->lineupPlayers()
                ->where('competition_player_id', $outId)
                ->where('is_starter', true)
                ->first();

            $inRecord = $baseLineup->lineupPlayers()
                ->where('competition_player_id', $inId)
                ->where('is_starter', false)
                ->first();

            if (! $outRecord || ! $inRecord) {
                continue;
            }

            // Swap: o que estava fora entra; o que estava dentro sai
            [$outRecord->is_starter, $inRecord->is_starter] = [false, true];
            [$outRecord->slot, $inRecord->slot]             = [$inRecord->slot, $outRecord->slot];
            $outRecord->save();
            $inRecord->save();
        }
    }

    /**
     * Incrementa goals_scored para cada goleador nos eventos da partida.
     */
    private function applyGoalsScored(CompetitionMatch $match): void
    {
        $events = $match->data['events'] ?? [];
        $totals = [];

        foreach ($events as $event) {
            if (($event['type'] ?? '') === 'goal' && ! empty($event['scorer_id'])) {
                $totals[$event['scorer_id']] = ($totals[$event['scorer_id']] ?? 0) + 1;
            }
        }

        foreach ($totals as $playerId => $goals) {
            \App\Models\CompetitionPlayer::where('id', $playerId)->increment('goals_scored', $goals);
        }
    }

    /**
     * Aplica desgaste físico aos jogadores dos times desta partida ao vivo.
     */
    private function applyFitnessDegradation(CompetitionTeam $homeTeam, CompetitionTeam $awayTeam): void
    {
        $leagueTeamIds = collect([$homeTeam->league_team_id, $awayTeam->league_team_id])->filter();
        $players       = \App\Models\CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)->get();

        foreach ($players as $player) {
            $stamina = max(1, $player->stamina ?? 50);
            $age     = $player->age ?? 25;

            $ageFactor = match (true) {
                $age <= 21 => 0.85,
                $age <= 25 => 1.00,
                $age <= 28 => 1.10,
                $age <= 31 => 1.20,
                $age <= 34 => 1.32,
                default    => 1.45,
            };

            $loss       = (int) round(rand(5, 14) * (1.3 - $stamina / 90) * $ageFactor);
            $loss       = max(3, $loss);
            $newFitness = max(40, $player->fitness - $loss);

            $player->update(['fitness' => $newFitness]);
        }
    }
}
