<?php

use App\Models\LeagueInvitation;
use App\Models\LeagueMember;
use App\Models\LeagueMessage;
use App\Models\User;

// Spec 005 / US-4 — aceitar um convite (primeiro a aceitar leva)

function makeFiredWithInvitation(\App\Models\League $league, User $user, \App\Models\LeagueTeam $club): LeagueInvitation
{
    LeagueMember::firstOrCreate(
        ['league_id' => $league->id, 'user_id' => $user->id],
        ['status' => LeagueMember::STATUS_FIRED, 'fired_at_global_round' => $league->global_round],
    );

    return LeagueInvitation::create([
        'league_id'      => $league->id,
        'user_id'        => $user->id,
        'league_team_id' => $club->id,
        'status'         => LeagueInvitation::STATUS_PENDING,
        'global_round'   => $league->global_round,
    ]);
}

it('aceitar convite assume o time, expira concorrentes e dá boas-vindas', function () {
    $user   = User::factory()->create();
    $league = makeLeague(['global_round' => 3]);

    $clubA = makeLeagueTeam($league);
    $clubB = makeLeagueTeam($league);

    $invitationA = makeFiredWithInvitation($league, $user, $clubA);
    $invitationB = makeFiredWithInvitation($league, $user, $clubB);

    $this->actingAs($user)
        ->post(route('leagues.office.invitations.accept', [$league, $invitationA]))
        ->assertRedirect(route('leagues.office', $league));

    expect($clubA->fresh()->user_id)->toBe($user->id);
    expect($invitationA->fresh()->status)->toBe(LeagueInvitation::STATUS_ACCEPTED);

    // O outro convite do usuário expira junto
    expect($invitationB->fresh()->status)->toBe(LeagueInvitation::STATUS_EXPIRED);

    // Vínculo volta a assigned + mensagem de boas-vindas
    $member = LeagueMember::where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($member->status)->toBe(LeagueMember::STATUS_ASSIGNED);
    expect(
        LeagueMessage::where('user_id', $user->id)->where('title', 'like', 'Bem-vindo%')->exists()
    )->toBeTrue();
});

it('primeiro a aceitar leva: o segundo recebe erro e o time não muda de dono', function () {
    $userA  = User::factory()->create();
    $userB  = User::factory()->create();
    $league = makeLeague(['global_round' => 3]);

    $club = makeLeagueTeam($league);

    $invitationA = makeFiredWithInvitation($league, $userA, $club);
    $invitationB = makeFiredWithInvitation($league, $userB, $club);

    $this->actingAs($userA)
        ->post(route('leagues.office.invitations.accept', [$league, $invitationA]))
        ->assertRedirect();

    // O convite de B para o mesmo time já expirou no aceite de A
    $this->actingAs($userB)
        ->post(route('leagues.office.invitations.accept', [$league, $invitationB]))
        ->assertStatus(409);

    expect($club->fresh()->user_id)->toBe($userA->id);
});

it('convite expirado não pode ser aceito e recusa marca como declined', function () {
    $user   = User::factory()->create();
    $league = makeLeague(['global_round' => 5]);

    $club    = makeLeagueTeam($league);
    $expired = makeFiredWithInvitation($league, $user, $club);
    $expired->update(['global_round' => 4]); // rodada anterior

    $this->actingAs($user)
        ->post(route('leagues.office.invitations.accept', [$league, $expired]))
        ->assertStatus(409);

    expect($club->fresh()->user_id)->toBeNull();

    $pending = makeFiredWithInvitation($league, $user, $club);

    $this->actingAs($user)
        ->post(route('leagues.office.invitations.decline', [$league, $pending]))
        ->assertRedirect();

    expect($pending->fresh()->status)->toBe(LeagueInvitation::STATUS_DECLINED);
});

it('demitido é observador: vê a liga mas não acessa ações de gestão', function () {
    $user   = User::factory()->create();
    $league = makeLeague(['global_round' => 2]);
    makeLeagueTeam($league); // time CPU qualquer

    LeagueMember::create([
        'league_id'             => $league->id,
        'user_id'               => $user->id,
        'status'                => LeagueMember::STATUS_FIRED,
        'fired_at_global_round' => 1,
    ]);

    // Vê o Escritório e a página clássica da liga
    $this->actingAs($user)->get(route('leagues.office', $league))->assertOk();
    $this->actingAs($user)
        ->get(route('leagues.show', ['league' => $league, 'classic' => 1]))
        ->assertOk();

    // Mercado (ação de gestão) bloqueado sem time
    $this->actingAs($user)->get(route('leagues.transfers.index', $league))->assertForbidden();
});
