<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\LeagueTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeasonTransitionService
{
    public function __construct(
        private readonly CalendarGeneratorService $calendar,
        private readonly FinancialService         $financial,
        private readonly MarketValueService       $marketValue,
    ) {}

    /**
     * Returns per competition_type the standings, champion, runner-up,
     * relegated, and promoted teams — without persisting anything.
     *
     * Shape:
     * [
     *   'state'    => [ ...per-state entries keyed by state_id... ],
     *   'national' => [ first_division, second_division, champion, ... ],
     * ]
     *
     * For state competitions we return an array keyed by state_id, each with
     * the same inner shape as national. This handles multiple state pairs.
     */
    public function calculateTransitions(League $league): array
    {
        // Apenas a temporada corrente — sem o filtro, competições de anos
        // anteriores contaminariam o cálculo a partir da 2ª temporada
        $competitions = $league->competitions()
            ->where('season', $league->season)
            ->with(['teams.leagueTeam.team', 'state'])
            ->get();

        $result = [
            'state'    => [],
            'national' => null,
        ];

        // ── National pair ────────────────────────────────────────────────
        $nationalFirst  = $competitions->where('competition_type', Competition::COMPETITION_TYPE_NATIONAL)
            ->where('division', Competition::DIVISION_FIRST)->first();
        $nationalSecond = $competitions->where('competition_type', Competition::COMPETITION_TYPE_NATIONAL)
            ->where('division', Competition::DIVISION_SECOND)->first();

        if ($nationalFirst || $nationalSecond) {
            $result['national'] = $this->buildTransitionBlock($nationalFirst, $nationalSecond);
        }

        // ── State pairs (grouped by state_id) ────────────────────────────
        $stateComps = $competitions->where('competition_type', Competition::COMPETITION_TYPE_STATE);

        $stateIds = $stateComps->pluck('state_id')->unique()->filter();

        foreach ($stateIds as $stateId) {
            $first  = $stateComps->where('division', Competition::DIVISION_FIRST)->where('state_id', $stateId)->first();
            $second = $stateComps->where('division', Competition::DIVISION_SECOND)->where('state_id', $stateId)->first();

            $result['state'][$stateId] = $this->buildTransitionBlock($first, $second);

            // Attach state name for display
            $result['state'][$stateId]['state'] = $first?->state ?? $second?->state;
        }

        return $result;
    }

    /**
     * Advance the league to the next season:
     * - Create new Competition records (season+1)
     * - Swap teams between divisions based on promotion/relegation
     * - Generate calendars for new competitions
     * - Age all players by +1 year, reset fitness to 100 and goals_scored to 0
     * - Increment League::season
     */
    public function advanceSeason(League $league): League
    {
        $transitions = $this->calculateTransitions($league);
        $nextYear    = $league->season + 1;

        DB::transaction(function () use ($league, $transitions, $nextYear) {

            // ── National: NÃO clona competições — apenas persiste as novas
            //    divisões nos LeagueTeams. As Séries A/B da próxima temporada
            //    serão criadas pelo GlobalRoundService::transitionToNational
            //    (único criador), já respeitando promoção/rebaixamento.
            if ($transitions['national']) {
                [$firstIds, $secondIds] = $this->divisionMembers($transitions['national']);

                LeagueTeam::whereIn('id', $firstIds)->update(['national_division' => 'first']);
                LeagueTeam::whereIn('id', $secondIds)->update(['national_division' => 'second']);
            }

            // ── State pairs ───────────────────────────────────────────────
            foreach ($transitions['state'] as $stateId => $block) {
                $this->createNewSeasonPair(
                    $league,
                    $block,
                    $nextYear,
                    Competition::COMPETITION_TYPE_STATE,
                    $stateId,
                );
            }

            // ── Age players + reset fitness/goals ────────────────────────
            $leagueTeamIds = LeagueTeam::where('league_id', $league->id)->pluck('id');

            CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)
                ->increment('age');

            CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)
                ->update(['fitness' => 100, 'goals_scored' => 0]);

            // ── Valor de mercado acompanha o envelhecimento ───────────────
            CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)
                ->chunkById(500, function ($players) {
                    foreach ($players as $player) {
                        $player->update(['market_value' => $this->marketValue->calculate($player)]);
                    }
                });

            // ── Bump league season + reinício do ciclo de fases ───────────
            // (satisfação NÃO é tocada — carrega da temporada anterior)
            $league->update([
                'season'        => $nextYear,
                'current_phase' => League::PHASE_STATE,
            ]);

            // ── Auto-encerrar se atingiu o limite de temporadas ───────────
            if ($league->fresh()->isLastSeason()) {
                $league->update(['status' => League::STATUS_FINISHED, 'finished_at' => now()]);
            }
        });

        return $league->fresh();
    }

    // ── Private helpers ──────────────────────────────────────────────────

    /**
     * Build a transition block (champion, runner-up, relegated, promoted, standings)
     * from a first-division and second-division competition pair.
     */
    private function buildTransitionBlock(?Competition $first, ?Competition $second): array
    {
        $firstStandings  = $first  ? $this->standings($first)  : collect();
        $secondStandings = $second ? $this->standings($second) : collect();

        $relegationSpots = $first?->relegation_spots ?? 0;
        $promotionSpots  = $second?->promotion_spots ?? 0;

        $champion  = $firstStandings->first();
        $runnerUp  = $firstStandings->skip(1)->first();
        $relegated = $firstStandings->reverse()->take($relegationSpots)->reverse()->values();
        $promoted  = $secondStandings->take($promotionSpots)->values();

        return [
            'first_division'   => $first,
            'second_division'  => $second,
            'champion'         => $champion,
            'runner_up'        => $runnerUp,
            'relegated'        => $relegated,
            'promoted'         => $promoted,
            'first_standings'  => $firstStandings,
            'second_standings' => $secondStandings,
        ];
    }

    /**
     * Order CompetitionTeams by standings rules:
     * points DESC, wins DESC, goal_difference DESC, goals_for DESC.
     */
    private function standings(Competition $competition): Collection
    {
        return CompetitionTeam::sortStandings($competition->teams);
    }

    /**
     * Create the new-season first + second division competitions for one type/state pair,
     * applying promotion and relegation.
     */
    private function createNewSeasonPair(
        League $league,
        array $block,
        int $nextYear,
        string $compType,
        ?string $stateId,
    ): void {
        /** @var Competition|null $oldFirst */
        $oldFirst  = $block['first_division'];
        /** @var Competition|null $oldSecond */
        $oldSecond = $block['second_division'];

        [$newFirstLeagueTeamIds, $newSecondLeagueTeamIds] = $this->divisionMembers($block);

        // Build league_team_id -> CompetitionTeam map for name/team_id lookup
        $allOldCts = collect()
            ->concat($block['first_standings'])
            ->concat($block['second_standings'])
            ->keyBy('league_team_id');

        // ── Create new first division ─────────────────────────────────
        if ($oldFirst && $newFirstLeagueTeamIds->isNotEmpty()) {
            $newFirst = $this->cloneCompetition($oldFirst, $nextYear, $newFirstLeagueTeamIds->count());
            foreach ($newFirstLeagueTeamIds as $ltId) {
                $ref = $allOldCts[$ltId] ?? null;
                CompetitionTeam::create([
                    'competition_id' => $newFirst->id,
                    'league_team_id' => $ltId,
                    'team_id'        => $ref?->team_id,
                    'name'           => $ref?->name ?? LeagueTeam::find($ltId)?->name ?? '—',
                ]);
            }
            $this->calendar->generate($newFirst);
            $this->financial->payTvQuotaFor($newFirst);
        }

        // ── Create new second division ────────────────────────────────
        if ($oldSecond && $newSecondLeagueTeamIds->isNotEmpty()) {
            $newSecond = $this->cloneCompetition($oldSecond, $nextYear, $newSecondLeagueTeamIds->count());
            foreach ($newSecondLeagueTeamIds as $ltId) {
                $ref = $allOldCts[$ltId] ?? null;
                CompetitionTeam::create([
                    'competition_id' => $newSecond->id,
                    'league_team_id' => $ltId,
                    'team_id'        => $ref?->team_id,
                    'name'           => $ref?->name ?? LeagueTeam::find($ltId)?->name ?? '—',
                ]);
            }
            $this->calendar->generate($newSecond);
            $this->financial->payTvQuotaFor($newSecond);
        }
    }

    /**
     * Resolve quem fica em cada divisão na temporada seguinte a partir de um
     * transition block: [ids da 1ª divisão, ids da 2ª divisão].
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function divisionMembers(array $block): array
    {
        $relegatedIds = $block['relegated']->pluck('league_team_id')->all();
        $promotedIds  = $block['promoted']->pluck('league_team_id')->all();

        $newFirst = $block['first_standings']
            ->reject(fn(CompetitionTeam $ct) => in_array($ct->league_team_id, $relegatedIds))
            ->pluck('league_team_id')
            ->concat($block['promoted']->pluck('league_team_id'))
            ->unique()->values();

        $newSecond = $block['second_standings']
            ->reject(fn(CompetitionTeam $ct) => in_array($ct->league_team_id, $promotedIds))
            ->pluck('league_team_id')
            ->concat($block['relegated']->pluck('league_team_id'))
            ->unique()->values();

        return [$newFirst, $newSecond];
    }

    /**
     * Clone a Competition into the next year, incrementing the year in the name
     * and generating a fresh slug. Returns the persisted new record.
     */
    private function cloneCompetition(Competition $old, int $nextYear, int $teamsCount): Competition
    {
        $newName = preg_replace('/\b\d{4}\b/', (string) $nextYear, $old->name);
        if ($newName === $old->name) {
            $newName = $old->name . ' ' . $nextYear;
        }

        $baseSlug = preg_replace('/\b\d{4}\b/', (string) $nextYear, $old->slug);
        $slug     = $baseSlug . '-' . Str::lower(Str::random(4));

        return Competition::create([
            'league_id'        => $old->league_id,
            'championship_id'  => $old->championship_id,
            'name'             => $newName,
            'slug'             => $slug,
            'competition_type' => $old->competition_type,
            'division'         => $old->division,
            'state_id'         => $old->state_id,
            'format'           => $old->format,
            'legs'             => $old->legs,
            'teams_count'      => $teamsCount,
            'promotion_spots'  => $old->promotion_spots,
            'relegation_spots' => $old->relegation_spots,
            'status'           => Competition::STATUS_IN_PROGRESS,
            'current_round'    => 0,
            'total_rounds'     => null,
            'season'           => $nextYear,
        ]);
    }
}
