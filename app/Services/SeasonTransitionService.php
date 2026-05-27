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
        $competitions = $league->competitions()
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

            // ── National ─────────────────────────────────────────────────
            if ($transitions['national']) {
                $this->createNewSeasonPair(
                    $league,
                    $transitions['national'],
                    $nextYear,
                    Competition::COMPETITION_TYPE_NATIONAL,
                    null,
                );
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

            // ── Bump league season ────────────────────────────────────────
            $league->update(['season' => $nextYear]);
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
        return $competition->teams
            ->sortByDesc(fn(CompetitionTeam $ct) => [
                $ct->points,
                $ct->wins,
                $ct->goals_for - $ct->goals_against,
                $ct->goals_for,
            ])
            ->values();
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

        /** @var Collection $relegated */
        $relegated = $block['relegated'];
        /** @var Collection $promoted */
        $promoted  = $block['promoted'];

        // ── Teams for new first division ──────────────────────────────
        // Stay: first division teams not relegated
        $relegatedLeagueTeamIds = $relegated->pluck('league_team_id')->all();

        $firstStaying = $block['first_standings']
            ->reject(fn(CompetitionTeam $ct) => in_array($ct->league_team_id, $relegatedLeagueTeamIds))
            ->values();

        // Promoted from second division come up
        $newFirstLeagueTeamIds = $firstStaying->pluck('league_team_id')
            ->concat($promoted->pluck('league_team_id'))
            ->unique()->values();

        // ── Teams for new second division ─────────────────────────────
        // Stay: second division teams not promoted
        $promotedLeagueTeamIds = $promoted->pluck('league_team_id')->all();

        $secondStaying = $block['second_standings']
            ->reject(fn(CompetitionTeam $ct) => in_array($ct->league_team_id, $promotedLeagueTeamIds))
            ->values();

        // Relegated teams from first division go down
        $newSecondLeagueTeamIds = $secondStaying->pluck('league_team_id')
            ->concat($relegated->pluck('league_team_id'))
            ->unique()->values();

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
        }
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
