<?php

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\LeagueTeam;
use App\Models\Team;
use App\Models\User;

// Fix lobby (2026-07-14) — dono entra na fila e iniciar a liga dispara o sorteio

function makeCatalogTeam(): Team
{
    return Team::create([
        'name'             => 'Time Catálogo ' . \Illuminate\Support\Str::random(6),
        'overall'          => 70,
        'tolerance'        => 50,
        'fans_base'        => 100_000,
        'stadium_capacity' => 20_000,
    ]);
}

it('coloca o dono na fila do lobby ao criar liga de sorteio automático', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('leagues.store'), [
        'name'            => 'Liga Sorteio',
        'access'          => 'private',
        'season'          => 2026,
        'team_assignment' => 'auto',
    ])->assertRedirect();

    $league = League::where('name', 'Liga Sorteio')->first();

    expect(
        LeagueMember::where('league_id', $league->id)
            ->where('user_id', $user->id)
            ->where('status', LeagueMember::STATUS_WAITING)
            ->exists()
    )->toBeTrue();

    // Liga manual não cria membro
    $this->actingAs($user)->post(route('leagues.store'), [
        'name'            => 'Liga Manual',
        'access'          => 'private',
        'season'          => 2026,
        'team_assignment' => 'manual',
    ])->assertRedirect();

    $manual = League::where('name', 'Liga Manual')->first();
    expect(LeagueMember::where('league_id', $manual->id)->count())->toBe(0);
});

it('sorteia todos da fila (incluindo o dono) ao iniciar a liga', function () {
    $league = makeLeague(['status' => League::STATUS_WAITING, 'team_assignment' => 'auto']);
    $owner  = $league->owner;
    $friend = User::factory()->create();

    foreach ([$owner, $friend] as $u) {
        LeagueMember::create([
            'league_id' => $league->id,
            'user_id'   => $u->id,
            'status'    => LeagueMember::STATUS_WAITING,
        ]);
    }

    // 3 times CPU vinculados ao catálogo (o sorteio exige team_id preenchido)
    foreach (range(1, 3) as $i) {
        makeLeagueTeam($league, ['team_id' => makeCatalogTeam()->id]);
    }

    $this->actingAs($owner)
        ->post(route('leagues.start', $league))
        ->assertRedirect(route('leagues.show', $league));

    expect($league->fresh()->status)->toBe(League::STATUS_IN_PROGRESS);

    foreach ([$owner, $friend] as $u) {
        expect(
            LeagueTeam::where('league_id', $league->id)->where('user_id', $u->id)->exists()
        )->toBeTrue();

        expect(
            LeagueMember::where('league_id', $league->id)
                ->where('user_id', $u->id)
                ->value('status')
        )->toBe(LeagueMember::STATUS_ASSIGNED);
    }
});

it('sorteio manual pelo botão do lobby continua funcionando', function () {
    $league = makeLeague(['status' => League::STATUS_WAITING, 'team_assignment' => 'auto']);
    $owner  = $league->owner;

    LeagueMember::create([
        'league_id' => $league->id,
        'user_id'   => $owner->id,
        'status'    => LeagueMember::STATUS_WAITING,
    ]);

    makeLeagueTeam($league, ['team_id' => makeCatalogTeam()->id]);

    $this->actingAs($owner)
        ->post(route('leagues.lobby.draw', $league))
        ->assertRedirect(route('leagues.show', $league));

    expect(
        LeagueTeam::where('league_id', $league->id)->where('user_id', $owner->id)->exists()
    )->toBeTrue();
});
