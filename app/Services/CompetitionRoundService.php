<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionLineup;
use App\Models\CompetitionMatch;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Responsável por simular uma rodada de uma competição.
 *
 * Extrai a lógica que estava em CompetitionController::advanceRound para
 * que possa ser reutilizada pelo GlobalRoundService (rodada global).
 */
class CompetitionRoundService
{
    public function __construct(
        private readonly MatchSimulator     $simulator,
        private readonly LiveMatchSimulator $liveSimulator,
    ) {}

    /**
     * Avança uma rodada de uma competição.
     *
     * @return array{
     *   nextRound: int,
     *   cpuCount: int,
     *   liveCount: int,
     *   competitionFinished: bool,
     *   liveMatches: Collection,
     * }
     */
    public function advance(Competition $competition): array
    {
        $nextRound = $competition->current_round + 1;

        if ($nextRound > $competition->total_rounds) {
            return [
                'nextRound'           => $nextRound,
                'cpuCount'            => 0,
                'liveCount'           => 0,
                'competitionFinished' => $competition->isFinished(),
                'liveMatches'         => collect(),
            ];
        }

        $matches = $competition->matches()
            ->where('round', $nextRound)
            ->whereNotIn('status', ['finished', 'halftime'])
            ->with(['homeTeam.leagueTeam', 'awayTeam.leagueTeam'])
            ->get();

        if ($matches->isEmpty()) {
            return [
                'nextRound'           => $nextRound,
                'cpuCount'            => 0,
                'liveCount'           => 0,
                'competitionFinished' => $competition->isFinished(),
                'liveMatches'         => collect(),
            ];
        }

        $cpuMatches  = $matches->filter(fn($m) => $this->isCpuMatch($m));
        $liveMatches = $matches->filter(fn($m) => ! $this->isCpuMatch($m));

        DB::transaction(function () use ($cpuMatches, $liveMatches, $competition, $nextRound) {
            // 1. Recuperação de fitness antes da rodada
            $this->applyFitnessRecovery($competition);

            // 2. CPU × CPU — simulação instantânea
            foreach ($cpuMatches as $match) {
                $result = $this->simulator->simulate($match);

                $winnerId = null;
                if ($result['home_score'] !== $result['away_score']) {
                    $winnerId = $result['home_score'] > $result['away_score']
                        ? $match->home_team_id
                        : $match->away_team_id;
                }

                $match->update([
                    'home_score'     => $result['home_score'],
                    'away_score'     => $result['away_score'],
                    'winner_team_id' => $winnerId,
                    'status'         => 'finished',
                    'played_at'      => now(),
                    'data'           => [
                        'home_possession'      => $result['home_possession'],
                        'away_possession'      => $result['away_possession'],
                        'home_shots'           => $result['home_shots'],
                        'away_shots'           => $result['away_shots'],
                        'home_shots_on_target' => $result['home_shots_on_target'],
                        'away_shots_on_target' => $result['away_shots_on_target'],
                        'home_formation'       => $result['home_formation'],
                        'away_formation'       => $result['away_formation'],
                        'events'               => $result['events'],
                    ],
                ]);

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
                $homeTeam->increment('goals_against', $result['away_score']);
                $awayTeam->increment('goals_for',     $result['away_score']);
                $awayTeam->increment('goals_against', $result['home_score']);
            }

            // 3. CPU × Humano — primeiro tempo, aguarda intervalo
            foreach ($liveMatches as $match) {
                $this->liveSimulator->simulateFirstHalf($match);
                $this->applyHalftimeDegradation($match);
                $this->applyCpuHalftimeSubstitutions($match);
            }

            // 4. Avança current_round somente se não houver partidas ao vivo pendentes
            if ($liveMatches->isEmpty()) {
                $competition->increment('current_round');

                if ($competition->fresh()->current_round >= $competition->total_rounds) {
                    $competition->update(['status' => Competition::STATUS_FINISHED]);
                }
            }

            // 5. Artilharia e desgaste (apenas CPU matches finalizados)
            $this->applyGoalsScored($cpuMatches);
            $this->applyFitnessDegradation($cpuMatches);
        });

        return [
            'nextRound'           => $nextRound,
            'cpuCount'            => $cpuMatches->count(),
            'liveCount'           => $liveMatches->count(),
            'competitionFinished' => $competition->fresh()->isFinished(),
            'liveMatches'         => $liveMatches,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isCpuMatch(CompetitionMatch $match): bool
    {
        return $match->homeTeam->leagueTeam->isCpu()
            && $match->awayTeam->leagueTeam->isCpu();
    }

    private function applyFitnessRecovery(Competition $competition): void
    {
        $leagueTeamIds = $competition->teams()->pluck('league_team_id');
        $players = CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)->get();

        foreach ($players as $player) {
            if ($player->fitness >= 100) continue;

            $stamina   = max(1, $player->stamina ?? 50);
            $age       = $player->age ?? 25;
            $ageFactor = match (true) {
                $age <= 21 => 1.15,
                $age <= 25 => 1.00,
                $age <= 28 => 0.90,
                $age <= 31 => 0.78,
                $age <= 34 => 0.65,
                default    => 0.55,
            };

            $recovery   = (int) round(rand(15, 28) * ($stamina / 90) * $ageFactor);
            $newFitness = min(100, $player->fitness + $recovery);
            $player->update(['fitness' => $newFitness]);
        }
    }

    private function applyGoalsScored(Collection $matches): void
    {
        $scorerTotals = [];

        foreach ($matches as $match) {
            $events = $match->fresh()->data['events'] ?? [];
            foreach ($events as $event) {
                if (($event['type'] ?? '') === 'goal' && ! empty($event['scorer_id'])) {
                    $scorerTotals[$event['scorer_id']] = ($scorerTotals[$event['scorer_id']] ?? 0) + 1;
                }
            }
        }

        foreach ($scorerTotals as $playerId => $goals) {
            CompetitionPlayer::where('id', $playerId)->increment('goals_scored', $goals);
        }
    }

    private function applyFitnessDegradation(Collection $matches): void
    {
        $leagueTeamIds = collect();

        foreach ($matches as $match) {
            if ($match->homeTeam) $leagueTeamIds->push($match->homeTeam->league_team_id);
            if ($match->awayTeam) $leagueTeamIds->push($match->awayTeam->league_team_id);
        }

        $leagueTeamIds = $leagueTeamIds->filter()->unique()->values();
        if ($leagueTeamIds->isEmpty()) return;

        $players = CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)->get();

        foreach ($players as $player) {
            $stamina   = max(1, $player->stamina ?? 50);
            $age       = $player->age ?? 25;
            $ageFactor = match (true) {
                $age <= 21 => 0.85,
                $age <= 25 => 1.00,
                $age <= 28 => 1.10,
                $age <= 31 => 1.20,
                $age <= 34 => 1.32,
                default    => 1.45,
            };

            $loss       = (int) round(rand(14, 26) * (1.3 - $stamina / 90) * $ageFactor);
            $loss       = max(6, $loss);
            $newFitness = max(35, $player->fitness - $loss);
            $player->update(['fitness' => $newFitness]);
        }
    }

    private function applyHalftimeDegradation(CompetitionMatch $match): void
    {
        $starterIds = collect();

        foreach ([$match->homeTeam, $match->awayTeam] as $compTeam) {
            if (! $compTeam?->league_team_id) continue;

            $lineup = CompetitionLineup::where('league_team_id', $compTeam->league_team_id)
                ->where('status', 'active')
                ->whereIn('round', [$match->round, 0])
                ->orderByDesc('round')
                ->first();

            if (! $lineup) continue;

            $lineup->lineupPlayers()
                ->where('is_starter', true)
                ->pluck('competition_player_id')
                ->each(fn($id) => $starterIds->push($id));
        }

        if ($starterIds->isEmpty()) return;

        $players = CompetitionPlayer::whereIn('id', $starterIds->unique())->get();

        foreach ($players as $player) {
            $stamina   = max(1, $player->stamina ?? 50);
            $age       = $player->age ?? 25;
            $ageFactor = match (true) {
                $age <= 21 => 0.85,
                $age <= 25 => 1.00,
                $age <= 28 => 1.10,
                $age <= 31 => 1.20,
                $age <= 34 => 1.32,
                default    => 1.45,
            };

            $loss       = (int) round(rand(20, 32) * (1.0 - $stamina / 90 * 0.3) * $ageFactor);
            $loss       = max(15, $loss);
            $newFitness = max(35, $player->fitness - $loss);
            $player->update(['fitness' => $newFitness]);
        }
    }

    private function applyCpuHalftimeSubstitutions(CompetitionMatch $match): void
    {
        foreach ([$match->homeTeam, $match->awayTeam] as $compTeam) {
            if (! $compTeam?->leagueTeam?->isCpu()) continue;

            $lineup = CompetitionLineup::where('league_team_id', $compTeam->league_team_id)
                ->where('status', 'active')
                ->whereIn('round', [$match->round, 0])
                ->orderByDesc('round')
                ->first();

            if (! $lineup) continue;

            $all = $lineup->lineupPlayers()->with('competitionPlayer')->get();

            $tiredStarters = $all
                ->filter(fn($lp) => $lp->is_starter && ($lp->competitionPlayer->fitness ?? 100) < 55)
                ->sortBy(fn($lp) => $lp->competitionPlayer->fitness ?? 100)
                ->values();

            $bench     = $all->filter(fn($lp) => ! $lp->is_starter)
                ->sortByDesc(fn($lp) => $lp->competitionPlayer->fitness ?? 100)
                ->values();

            $subsCount = 0;

            foreach ($tiredStarters as $out) {
                if ($subsCount >= 3 || $bench->isEmpty()) break;

                $outPos = $out->competitionPlayer->position ?? 'midfielder';
                $in     = $bench->first(fn($lp) => ($lp->competitionPlayer->position ?? '') === $outPos);

                if (! $in) {
                    if ($outPos === 'goalkeeper') continue;
                    $in = $bench->first(fn($lp) => ($lp->competitionPlayer->position ?? '') !== 'goalkeeper');
                }

                if (! $in) continue;

                [$out->is_starter, $in->is_starter] = [false, true];
                [$out->slot,       $in->slot]        = [$in->slot, $out->slot];
                $out->save();
                $in->save();

                $bench     = $bench->reject(fn($lp) => $lp->id === $in->id)->values();
                $subsCount++;
            }
        }
    }
}
