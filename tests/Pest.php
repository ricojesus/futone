<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
|--------------------------------------------------------------------------
| Fábrica de cenários de jogo
|--------------------------------------------------------------------------
|
| Helpers para montar o mundo mínimo (liga → times → competição → elenco)
| usado pelos testes de regra de negócio.
|
*/

function makeLeague(array $attrs = []): \App\Models\League
{
    $owner = \App\Models\User::factory()->create();

    return \App\Models\League::create(array_merge([
        'name'            => 'Liga Teste',
        'slug'            => 'liga-teste-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)),
        'owner_id'        => $owner->id,
        'type'            => 'private',
        'status'          => \App\Models\League::STATUS_IN_PROGRESS,
        'season'          => 2026,
        'season_start'    => 2026,
        'current_phase'   => \App\Models\League::PHASE_STATE,
        'team_assignment' => 'manual',
    ], $attrs));
}

function makeLeagueTeam(\App\Models\League $league, array $attrs = []): \App\Models\LeagueTeam
{
    return \App\Models\LeagueTeam::create(array_merge([
        'league_id'         => $league->id,
        'name'              => 'Time ' . \Illuminate\Support\Str::random(6),
        'national_division' => null,
        'budget'           => 10_000_000,
        'satisfaction'     => 50,
        'stadium_capacity' => 20_000,
        'ticket_price'     => 20,
        'tolerance'        => 50,
    ], $attrs));
}

function makePlayer(\App\Models\LeagueTeam $team, array $attrs = []): \App\Models\CompetitionPlayer
{
    return \App\Models\CompetitionPlayer::create(array_merge([
        'league_team_id'          => $team->id,
        'name'                    => 'Jogador ' . \Illuminate\Support\Str::random(6),
        'position'                => 'midfielder',
        'age'                     => 24,
        'strength'                => 70,
        'stamina'                 => 70,
        'status'                  => 'active',
        'wage'                    => 10_000,
        'market_value'            => 1_000_000,
        'wage_expectation_factor' => 1.00,
        'form_factor'             => 1.00,
        'fitness'                 => 100,
        // Fora do período mínimo de contrato (hoje medido em tempo real — ver spec 004)
        'joined_at'               => now()->subMonths(7),
    ], $attrs));
}

/**
 * Elenco mínimo: $total jogadores ativos, sendo 2 goleiros.
 */
function makeSquad(\App\Models\LeagueTeam $team, int $total = 17): \Illuminate\Support\Collection
{
    $players = collect();
    for ($i = 0; $i < $total; $i++) {
        $players->push(makePlayer($team, [
            'position' => $i < 2 ? 'goalkeeper' : ['defender', 'midfielder', 'forward'][$i % 3],
        ]));
    }

    return $players;
}

function makeCompetition(\App\Models\League $league, array $attrs = []): \App\Models\Competition
{
    return \App\Models\Competition::create(array_merge([
        'league_id'        => $league->id,
        'name'             => 'Competição Teste',
        'slug'             => 'competicao-teste-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)),
        'competition_type' => \App\Models\Competition::COMPETITION_TYPE_STATE,
        'division'         => \App\Models\Competition::DIVISION_FIRST,
        'format'           => 'league',
        'teams_count'      => 2,
        'status'           => \App\Models\Competition::STATUS_IN_PROGRESS,
        'current_round'    => 1,
        'total_rounds'     => 10,
        'season'           => 2026,
    ], $attrs));
}

function makeCompetitionTeam(
    \App\Models\Competition $competition,
    \App\Models\LeagueTeam $leagueTeam,
    array $attrs = [],
): \App\Models\CompetitionTeam {
    return \App\Models\CompetitionTeam::create(array_merge([
        'competition_id' => $competition->id,
        'league_team_id' => $leagueTeam->id,
        'name'           => $leagueTeam->name,
    ], $attrs));
}

/**
 * Persiste uma escalação com os jogadores dados como titulares (round 0 = padrão).
 */
function makeLineup(
    \App\Models\LeagueTeam $team,
    \Illuminate\Support\Collection $starters,
    int $round = 0,
): \App\Models\CompetitionLineup {
    $lineup = \App\Models\CompetitionLineup::create([
        'league_team_id' => $team->id,
        'formation'      => '4-4-2',
        'round'          => $round,
        'status'         => 'active',
    ]);

    $starters->values()->each(fn($player, $i) => $lineup->lineupPlayers()->create([
        'competition_player_id' => $player->id,
        'role'                  => $player->position,
        'is_starter'            => true,
        'slot'                  => $i + 1,
    ]));

    return $lineup;
}
