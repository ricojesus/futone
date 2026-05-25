<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gera as competições de uma temporada completa dentro de uma League (mundo):
 *
 *  - Campeonato Estadual A1 + A2 para cada estado com times cadastrados.
 *  - Campeonato Brasileiro Série A + Série B com os melhores times de cada estado.
 *
 * Critério de força inicial: média de strength dos CompetitionPlayers de ligas anteriores.
 * Fallback (time sem histórico): fans_base / 1.000.000.
 */
class LeagueGeneratorService
{
    // ── Configuração das divisões ────────────────────────────────────────

    const STATE_A1_SLOTS = 8;
    const STATE_A2_SLOTS = 8;

    const NATIONAL_A_SLOTS = 20;
    const NATIONAL_B_SLOTS = 20;

    const NATIONAL_A_PER_STATE = 2;
    const NATIONAL_B_PER_STATE = 2;

    // ── Nomes dos campeonatos estaduais ──────────────────────────────────

    const STATE_CHAMPIONSHIP_NAMES = [
        'AC' => 'Campeonato Acreano',
        'AL' => 'Campeonato Alagoano',
        'AM' => 'Campeonato Amazonense',
        'AP' => 'Campeonato Amapaense',
        'BA' => 'Campeonato Baiano',
        'CE' => 'Campeonato Cearense',
        'DF' => 'Campeonato Candangão',
        'ES' => 'Campeonato Capixaba',
        'GO' => 'Campeonato Goiano',
        'MA' => 'Campeonato Maranhense',
        'MG' => 'Campeonato Mineiro',
        'MS' => 'Campeonato Sul-Mato-Grossense',
        'MT' => 'Campeonato Mato-Grossense',
        'PA' => 'Campeonato Paraense',
        'PB' => 'Campeonato Paraibano',
        'PE' => 'Campeonato Pernambucano',
        'PI' => 'Campeonato Piauiense',
        'PR' => 'Campeonato Paranaense',
        'RJ' => 'Campeonato Carioca',
        'RN' => 'Campeonato Potiguar',
        'RO' => 'Campeonato Rondoniense',
        'RR' => 'Campeonato Roraimense',
        'RS' => 'Campeonato Gaúcho',
        'SC' => 'Campeonato Catarinense',
        'SE' => 'Campeonato Sergipano',
        'SP' => 'Campeonato Paulista',
        'TO' => 'Campeonato Tocantinense',
    ];

    public function __construct(
        private readonly CalendarGeneratorService $calendar,
    ) {}

    // ── API pública ───────────────────────────────────────────────────────

    /**
     * Gera todas as competições de uma temporada dentro de um League (mundo).
     *
     * Cria o League (mundo) automaticamente e popula-o com Competition records.
     *
     * @return array{league: League, state: Competition[], national: Competition[]}
     */
    /**
     * Cria um novo League (mundo) e gera todas as competições da temporada.
     * Usado pelo comando artisan `leagues:generate`.
     *
     * @return array{league: League, state: Competition[], national: Competition[]}
     */
    public function generateSeason(int $year, User $admin): array
    {
        $league = League::create([
            'name'        => "Temporada {$year}",
            'slug'        => "temporada-{$year}-" . Str::lower(Str::random(4)),
            'owner_id'    => $admin->id,
            'type'        => League::ACCESS_PUBLIC,
            'invite_code' => null,
            'status'      => League::STATUS_IN_PROGRESS,
            'season'      => $year,
            'started_at'  => now(),
        ]);

        $created = $this->generateForLeague($league);

        return array_merge(['league' => $league], $created);
    }

    /**
     * Gera todas as competições de uma temporada dentro de um League já existente.
     * Usado quando o usuário cria a liga manualmente e aciona "Gerar competições".
     *
     * @return array{state: Competition[], national: Competition[]}
     */
    public function generateForLeague(League $league): array
    {
        $year       = (int) $league->season;
        $stateTeams = $this->rankTeamsByState();

        if ($stateTeams->isEmpty()) {
            throw new \RuntimeException('Nenhum time com estado definido encontrado.');
        }

        $created = ['state' => [], 'national' => []];

        $poolSerieA = collect();
        $poolSerieB = collect();

        DB::transaction(function () use (
            $year, $league, $stateTeams,
            &$created, &$poolSerieA, &$poolSerieB,
        ) {
            foreach ($stateTeams as $stateCode => $teams) {
                $state = State::where('code', $stateCode)->first();
                if (! $state) continue;

                $a1Teams = $teams->take(self::STATE_A1_SLOTS);
                $a2Teams = $teams->skip(self::STATE_A1_SLOTS)->take(self::STATE_A2_SLOTS);

                // ── Estadual A1 ───────────────────────────────────────────
                $compA1 = $this->createCompetition($league, [
                    'name'             => $this->stateName($stateCode, $year, 'A1'),
                    'slug'             => $this->slug($stateCode, 'a1', $year),
                    'competition_type' => Competition::COMPETITION_TYPE_STATE,
                    'division'         => Competition::DIVISION_FIRST,
                    'state_id'         => $state->id,
                    'season'           => $year,
                    'teams_count'      => $a1Teams->count(),
                ]);

                $this->attachTeams($compA1, $a1Teams);
                $this->calendar->generate($compA1);
                $created['state'][] = $compA1;

                $poolSerieA = $poolSerieA->concat($a1Teams->take(self::NATIONAL_A_PER_STATE));
                $poolSerieB = $poolSerieB->concat($a1Teams->skip(self::NATIONAL_A_PER_STATE));

                // ── Estadual A2 ───────────────────────────────────────────
                if ($a2Teams->count() >= 2) {
                    $compA2 = $this->createCompetition($league, [
                        'name'             => $this->stateName($stateCode, $year, 'A2'),
                        'slug'             => $this->slug($stateCode, 'a2', $year),
                        'competition_type' => Competition::COMPETITION_TYPE_STATE,
                        'division'         => Competition::DIVISION_SECOND,
                        'state_id'         => $state->id,
                        'season'           => $year,
                        'teams_count'      => $a2Teams->count(),
                    ]);

                    $this->attachTeams($compA2, $a2Teams);
                    $this->calendar->generate($compA2);
                    $created['state'][] = $compA2;

                    $poolSerieB = $poolSerieB->concat($a2Teams->take(self::NATIONAL_B_PER_STATE));
                }
            }

            // ── Brasileiro Série A ────────────────────────────────────────
            $serieATeams = $poolSerieA->unique('id')->take(self::NATIONAL_A_SLOTS);

            if ($serieATeams->count() >= 2) {
                $serieA = $this->createCompetition($league, [
                    'name'             => "Campeonato Brasileiro Série A {$year}",
                    'slug'             => "brasileiro-serie-a-{$year}",
                    'competition_type' => Competition::COMPETITION_TYPE_NATIONAL,
                    'division'         => Competition::DIVISION_FIRST,
                    'state_id'         => null,
                    'season'           => $year,
                    'teams_count'      => $serieATeams->count(),
                ]);

                $this->attachTeams($serieA, $serieATeams);
                $this->calendar->generate($serieA);
                $created['national'][] = $serieA;
            }

            // ── Brasileiro Série B ────────────────────────────────────────
            $serieBTeams = $poolSerieB->unique('id')->take(self::NATIONAL_B_SLOTS);

            if ($serieBTeams->count() >= 2) {
                $serieB = $this->createCompetition($league, [
                    'name'             => "Campeonato Brasileiro Série B {$year}",
                    'slug'             => "brasileiro-serie-b-{$year}",
                    'competition_type' => Competition::COMPETITION_TYPE_NATIONAL,
                    'division'         => Competition::DIVISION_SECOND,
                    'state_id'         => null,
                    'season'           => $year,
                    'teams_count'      => $serieBTeams->count(),
                ]);

                $this->attachTeams($serieB, $serieBTeams);
                $this->calendar->generate($serieB);
                $created['national'][] = $serieB;
            }
        });

        return $created;
    }

    // ── Lógica interna ────────────────────────────────────────────────────

    /**
     * Agrupa times por estado e os ordena por força média (descendente).
     *
     * @return Collection<string, Collection<Team>>
     */
    private function rankTeamsByState(): Collection
    {
        $strengthByTeam = CompetitionPlayer::select('competition_team_id', DB::raw('AVG(strength) as avg_strength'))
            ->join('competition_teams', 'competition_players.competition_team_id', '=', 'competition_teams.id')
            ->groupBy('competition_teams.team_id', 'competition_players.competition_team_id')
            ->pluck('avg_strength', 'competition_team_id')
            ->toArray();

        $teamIdByCompTeam = CompetitionTeam::pluck('team_id', 'id')->toArray();

        $strengthByTeamId = [];
        foreach ($strengthByTeam as $compTeamId => $avg) {
            $teamId = $teamIdByCompTeam[$compTeamId] ?? null;
            if ($teamId) {
                $strengthByTeamId[$teamId][] = (float) $avg;
            }
        }
        $avgByTeamId = array_map(fn($vals) => array_sum($vals) / count($vals), $strengthByTeamId);

        return Team::whereNotNull('state_id')
            ->with('state')
            ->get()
            ->groupBy(fn(Team $t) => $t->state?->code)
            ->filter(fn($g, $code) => $code !== null)
            ->map(fn(Collection $teams) =>
                $teams->sortByDesc(fn(Team $t) =>
                    $avgByTeamId[(string) $t->id] ?? ($t->fans_base / 1_000_000)
                )->values()
            )
            ->sortKeys();
    }

    /** Cria o registro de Competition dentro de um League com defaults de sistema. */
    private function createCompetition(League $league, array $data): Competition
    {
        return Competition::create(array_merge([
            'league_id'      => $league->id,
            'championship_id' => null,
            'format'         => 'league',
            'legs'           => 'double',
            'teams_count'    => 0,
            'status'         => Competition::STATUS_WAITING,
            'current_round'  => 0,
            'total_rounds'   => null,
        ], $data));
    }

    /**
     * Cria CompetitionTeam para cada time e copia os jogadores de referência.
     *
     * @param Collection<Team> $teams
     */
    private function attachTeams(Competition $competition, Collection $teams): void
    {
        foreach ($teams as $team) {
            $compTeam = CompetitionTeam::create([
                'competition_id'   => $competition->id,
                'team_id'          => $team->id,
                'user_id'          => null,
                'name'             => $team->name,
                'tolerance'        => $team->tolerance,
                'fans'             => $team->fans_base,
                'stadium_capacity' => $team->stadium_capacity,
                'budget'           => $this->initialBudget($team),
                'ticket_price'     => $this->initialTicketPrice($team),
            ]);

            $this->copyPlayers($team, $compTeam, $competition);
        }
    }

    /**
     * Copia jogadores do CompetitionTeam mais recente como referência.
     */
    private function copyPlayers(Team $team, CompetitionTeam $newCompTeam, Competition $newComp): void
    {
        $ref = CompetitionTeam::where('team_id', $team->id)
            ->where('id', '!=', $newCompTeam->id)
            ->latest()
            ->first();

        if (! $ref) return;

        $ref->players()
            ->whereIn('status', ['active', 'injured'])
            ->each(function (CompetitionPlayer $p) use ($newCompTeam, $newComp) {
                CompetitionPlayer::create([
                    'competition_id'      => $newComp->id,
                    'competition_team_id' => $newCompTeam->id,
                    'player_id'           => $p->player_id,
                    'country_id'          => $p->country_id,
                    'name'                => $p->name,
                    'position'            => $p->position,
                    'age'                 => $p->age + 1,
                    'strength'            => $p->strength,
                    'stamina'             => $p->stamina,
                    'potential'           => $p->potential,
                    'status'              => 'active',
                    'wage'                => $p->wage,
                    'market_value'        => $p->market_value,
                    'form_factor'         => 1.0,
                    'fitness'             => 100,
                ]);
            });
    }

    private function initialBudget(Team $team): int
    {
        return (int) min(100_000_000, max(2_000_000, $team->fans_base * 0.5));
    }

    private function initialTicketPrice(Team $team): int
    {
        $ratio = min(1.0, $team->fans_base / 30_000_000);
        return (int) (30 + $ratio * 120);
    }

    private function stateName(string $code, int $year, string $div): string
    {
        $base = self::STATE_CHAMPIONSHIP_NAMES[$code] ?? "Campeonato Estadual ({$code})";
        return "{$base} {$div} {$year}";
    }

    private function slug(string ...$parts): string
    {
        return Str::slug(implode('-', $parts));
    }
}
