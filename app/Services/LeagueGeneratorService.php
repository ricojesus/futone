<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeaguePlayer;
use App\Models\LeagueTeam;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gera as ligas de uma temporada completa:
 *
 *  - Campeonato Estadual A1 + A2 para cada estado com times cadastrados.
 *  - Campeonato Brasileiro Série A + Série B com os melhores times de cada estado.
 *
 * Critério de força inicial: média de strength dos LeaguePlayers de ligas anteriores.
 * Fallback (time sem histórico): fans_base / 1.000.000.
 */
class LeagueGeneratorService
{
    // ── Configuração das divisões ────────────────────────────────────────

    /** Vagas por divisão estadual */
    const STATE_A1_SLOTS = 8;
    const STATE_A2_SLOTS = 8;

    /** Vagas no campeonato nacional */
    const NATIONAL_A_SLOTS = 20;
    const NATIONAL_B_SLOTS = 20;

    /** Times de cada estado que sobem para o Série A/B (por estado) */
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
     * Gera todas as ligas de uma temporada.
     *
     * @return array{state: League[], national: League[]}
     */
    public function generateSeason(int $year, User $admin): array
    {
        $stateTeams = $this->rankTeamsByState();

        if ($stateTeams->isEmpty()) {
            throw new \RuntimeException('Nenhum time com estado definido encontrado.');
        }

        $created = ['state' => [], 'national' => []];

        // Pools para montar os brasileiros após gerar os estaduais
        $poolSerieA = collect(); // top times de A1 de cada estado
        $poolSerieB = collect(); // times restantes de A1 + top de A2

        DB::transaction(function () use (
            $year, $admin, $stateTeams,
            &$created, &$poolSerieA, &$poolSerieB,
        ) {
            foreach ($stateTeams as $stateCode => $teams) {
                $state = State::where('code', $stateCode)->first();
                if (! $state) continue;

                $a1Teams = $teams->take(self::STATE_A1_SLOTS);
                $a2Teams = $teams->skip(self::STATE_A1_SLOTS)->take(self::STATE_A2_SLOTS);

                // ── Estadual A1 ───────────────────────────────────────────
                $leagueA1 = $this->createLeague([
                    'name'             => $this->stateName($stateCode, $year, 'A1'),
                    'slug'             => $this->slug($stateCode, 'a1', $year),
                    'owner_id'         => $admin->id,
                    'state_id'         => $state->id,
                    'competition_type' => League::COMPETITION_TYPE_STATE,
                    'division'         => League::DIVISION_FIRST,
                    'season'           => $year,
                    'max_teams'        => self::STATE_A1_SLOTS,
                ]);

                $this->attachTeams($leagueA1, $a1Teams);
                $this->calendar->generate($leagueA1);
                $created['state'][] = $leagueA1;

                // Distribui para pools do nacional
                $poolSerieA = $poolSerieA->concat($a1Teams->take(self::NATIONAL_A_PER_STATE));
                $poolSerieB = $poolSerieB->concat($a1Teams->skip(self::NATIONAL_A_PER_STATE));

                // ── Estadual A2 (somente se houver times suficientes) ──────
                if ($a2Teams->count() >= 2) {
                    $leagueA2 = $this->createLeague([
                        'name'             => $this->stateName($stateCode, $year, 'A2'),
                        'slug'             => $this->slug($stateCode, 'a2', $year),
                        'owner_id'         => $admin->id,
                        'state_id'         => $state->id,
                        'competition_type' => League::COMPETITION_TYPE_STATE,
                        'division'         => League::DIVISION_SECOND,
                        'season'           => $year,
                        'max_teams'        => self::STATE_A2_SLOTS,
                    ]);

                    $this->attachTeams($leagueA2, $a2Teams);
                    $this->calendar->generate($leagueA2);
                    $created['state'][] = $leagueA2;

                    $poolSerieB = $poolSerieB->concat($a2Teams->take(self::NATIONAL_B_PER_STATE));
                }
            }

            // ── Brasileiro Série A ────────────────────────────────────────
            $serieATeams = $poolSerieA->unique('id')->take(self::NATIONAL_A_SLOTS);

            if ($serieATeams->count() >= 2) {
                $serieA = $this->createLeague([
                    'name'             => "Campeonato Brasileiro Série A {$year}",
                    'slug'             => "brasileiro-serie-a-{$year}",
                    'owner_id'         => $admin->id,
                    'state_id'         => null,
                    'competition_type' => League::COMPETITION_TYPE_NATIONAL,
                    'division'         => League::DIVISION_FIRST,
                    'season'           => $year,
                    'max_teams'        => self::NATIONAL_A_SLOTS,
                ]);

                $this->attachTeams($serieA, $serieATeams);
                $this->calendar->generate($serieA);
                $created['national'][] = $serieA;
            }

            // ── Brasileiro Série B ────────────────────────────────────────
            $serieBTeams = $poolSerieB->unique('id')->take(self::NATIONAL_B_SLOTS);

            if ($serieBTeams->count() >= 2) {
                $serieB = $this->createLeague([
                    'name'             => "Campeonato Brasileiro Série B {$year}",
                    'slug'             => "brasileiro-serie-b-{$year}",
                    'owner_id'         => $admin->id,
                    'state_id'         => null,
                    'competition_type' => League::COMPETITION_TYPE_NATIONAL,
                    'division'         => League::DIVISION_SECOND,
                    'season'           => $year,
                    'max_teams'        => self::NATIONAL_B_SLOTS,
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
        // Pré-calcula força de todos os times num único query
        $strengthByTeam = LeaguePlayer::select('league_team_id', DB::raw('AVG(strength) as avg_strength'))
            ->join('league_teams', 'league_players.league_team_id', '=', 'league_teams.id')
            ->groupBy('league_teams.team_id', 'league_players.league_team_id')
            ->pluck('avg_strength', 'league_team_id')
            ->toArray();

        // Mapeia league_team_id → team_id para resolução rápida
        $teamIdByLeagueTeam = LeagueTeam::pluck('team_id', 'id')->toArray();

        // Força por team_id (média entre ligas, se o time esteve em mais de uma)
        $strengthByTeamId = [];
        foreach ($strengthByTeam as $leagueTeamId => $avg) {
            $teamId = $teamIdByLeagueTeam[$leagueTeamId] ?? null;
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

    /** Cria o registro de League com defaults de sistema. */
    private function createLeague(array $data): League
    {
        return League::create(array_merge([
            'type'            => League::ACCESS_PUBLIC,
            'status'          => League::STATUS_WAITING,
            'team_assignment' => 'random',
            'invite_code'     => null,
        ], $data));
    }

    /**
     * Cria LeagueTeam para cada time e copia os jogadores da liga de referência.
     *
     * @param Collection<Team> $teams
     */
    private function attachTeams(League $league, Collection $teams): void
    {
        foreach ($teams as $team) {
            $leagueTeam = LeagueTeam::create([
                'league_id'        => $league->id,
                'team_id'          => $team->id,
                'user_id'          => null,          // CPU por padrão
                'name'             => $team->name,
                'tolerance'        => $team->tolerance,
                'fans'             => $team->fans_base,
                'stadium_capacity' => $team->stadium_capacity,
                'budget'           => $this->initialBudget($team),
                'ticket_price'     => $this->initialTicketPrice($team),
            ]);

            $this->copyPlayers($team, $leagueTeam, $league);
        }
    }

    /**
     * Copia jogadores do LeagueTeam mais recente como referência.
     * Reinicia fitness e form_factor para a nova temporada.
     */
    private function copyPlayers(Team $team, LeagueTeam $newLeagueTeam, League $newLeague): void
    {
        $ref = LeagueTeam::where('team_id', $team->id)
            ->where('id', '!=', $newLeagueTeam->id)
            ->latest()
            ->first();

        if (! $ref) return;

        $ref->players()
            ->whereIn('status', ['active', 'injured'])
            ->each(function (LeaguePlayer $p) use ($newLeagueTeam, $newLeague) {
                LeaguePlayer::create([
                    'league_id'       => $newLeague->id,
                    'league_team_id'  => $newLeagueTeam->id,
                    'player_id'       => $p->player_id,
                    'country_id'      => $p->country_id,
                    'name'            => $p->name,
                    'position'        => $p->position,
                    'age'             => $p->age + 1, // envelhece uma temporada
                    'strength'        => $p->strength,
                    'stamina'         => $p->stamina,
                    'potential'       => $p->potential,
                    'status'          => 'active',    // todos começam saudáveis
                    'wage'            => $p->wage,
                    'market_value'    => $p->market_value,
                    'form_factor'     => 1.0,         // reset do form
                    'fitness'         => 100,          // reset do fitness
                ]);
            });
    }

    /**
     * Orçamento inicial proporcional à base de fãs.
     * ~R$ 0,50 por fã, mínimo R$ 2 milhões, máximo R$ 100 milhões.
     */
    private function initialBudget(Team $team): int
    {
        return (int) min(100_000_000, max(2_000_000, $team->fans_base * 0.5));
    }

    /**
     * Preço do ingresso inicial: R$ 30–R$ 150 proporcional à popularidade.
     */
    private function initialTicketPrice(Team $team): int
    {
        $ratio = min(1.0, $team->fans_base / 30_000_000); // normaliza pelo maior torcedor
        return (int) (30 + $ratio * 120);
    }

    /** Nome do campeonato estadual: "Campeonato Paulista A1 2026". */
    private function stateName(string $code, int $year, string $div): string
    {
        $base = self::STATE_CHAMPIONSHIP_NAMES[$code] ?? "Campeonato Estadual ({$code})";
        return "{$base} {$div} {$year}";
    }

    /** Slug URL: "campeonato-paulista-a1-2026". */
    private function slug(string ...$parts): string
    {
        return Str::slug(implode('-', $parts));
    }
}
