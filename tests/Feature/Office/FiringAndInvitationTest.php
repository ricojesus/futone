<?php

use App\Models\LeagueInvitation;
use App\Models\LeagueMember;
use App\Models\LeagueMessage;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\SatisfactionService;

// Spec 005 / US-3 — demitido continua no jogo e recebe convites

it('demissão de humano cria o vínculo fired, mensagem e mantém a liga visível', function () {
    $user   = User::factory()->create();
    $league = makeLeague(['name' => 'Liga Da Demissão']);
    $league->update(['global_round' => 7]);

    // tolerance 50 → threshold 20; satisfação 5 demite
    $team = makeLeagueTeam($league, ['user_id' => $user->id, 'satisfaction' => 5, 'tolerance' => 50]);
    makeSquad($team, 15);

    app(SatisfactionService::class)->checkFirings($league);

    expect($team->fresh()->user_id)->toBeNull();

    $member = LeagueMember::where('league_id', $league->id)->where('user_id', $user->id)->first();
    expect($member)->not->toBeNull();
    expect($member->status)->toBe(LeagueMember::STATUS_FIRED);
    expect($member->fired_from_league_team_id)->toBe($team->id);
    expect($member->fired_at_global_round)->toBe(7);

    expect(
        LeagueMessage::where('user_id', $user->id)->where('title', 'like', 'Você foi demitido%')->exists()
    )->toBeTrue();

    // Liga segue visível no dashboard e o Escritório acessível
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Liga Da Demissão');
    $this->actingAs($user)->get(route('leagues.office', $league))->assertOk();
});

it('gera convites apenas de times CPU de divisão igual ou inferior', function () {
    $user   = User::factory()->create();
    $league = makeLeague(['global_round' => 10]);

    // Demitido de um time da Série B
    $exClub = makeLeagueTeam($league, ['national_division' => 'second']);
    LeagueMember::create([
        'league_id'                 => $league->id,
        'user_id'                   => $user->id,
        'status'                    => LeagueMember::STATUS_FIRED,
        'fired_from_league_team_id' => $exClub->id,
        'fired_at_global_round'     => 9,
    ]);

    $serieA   = makeLeagueTeam($league, ['national_division' => 'first']);
    $serieB   = makeLeagueTeam($league, ['national_division' => 'second']);
    $stateOnly = makeLeagueTeam($league, ['national_division' => null]);

    app(InvitationService::class)->expireAndGenerate($league);

    $invitedTeamIds = LeagueInvitation::where('user_id', $user->id)->pluck('league_team_id');

    // Série A (superior) e o ex-clube (carência) ficam de fora
    expect($invitedTeamIds)->not->toContain($serieA->id);
    expect($invitedTeamIds)->not->toContain($exClub->id);
    expect($invitedTeamIds)->toContain($serieB->id);
    expect($invitedTeamIds)->toContain($stateOnly->id);

    // E o demitido é avisado por mensagem
    expect(
        LeagueMessage::where('user_id', $user->id)
            ->where('type', LeagueMessage::TYPE_INVITATION)->exists()
    )->toBeTrue();
});

it('expira convites da rodada anterior e libera o ex-clube após a carência', function () {
    $user   = User::factory()->create();
    $league = makeLeague(['global_round' => 12]);

    $exClub = makeLeagueTeam($league, ['national_division' => null]);
    LeagueMember::create([
        'league_id'                 => $league->id,
        'user_id'                   => $user->id,
        'status'                    => LeagueMember::STATUS_FIRED,
        'fired_from_league_team_id' => $exClub->id,
        'fired_at_global_round'     => 0,
    ]);

    $stale = LeagueInvitation::create([
        'league_id'      => $league->id,
        'user_id'        => $user->id,
        'league_team_id' => $exClub->id,
        'status'         => LeagueInvitation::STATUS_PENDING,
        'global_round'   => 11,
    ]);

    app(InvitationService::class)->expireAndGenerate($league);

    // Convite antigo expirou
    expect($stale->fresh()->status)->toBe(LeagueInvitation::STATUS_EXPIRED);

    // Carência cumprida (0 + 12 <= 12): ex-clube volta a convidar (único CPU da liga)
    expect(
        LeagueInvitation::where('user_id', $user->id)
            ->where('league_team_id', $exClub->id)
            ->where('status', LeagueInvitation::STATUS_PENDING)
            ->where('global_round', 12)
            ->exists()
    )->toBeTrue();
});

it('não gera convites durante a carência quando o único CPU é o ex-clube', function () {
    $user   = User::factory()->create();
    $league = makeLeague(['global_round' => 5]);

    $exClub = makeLeagueTeam($league, ['national_division' => null]);
    LeagueMember::create([
        'league_id'                 => $league->id,
        'user_id'                   => $user->id,
        'status'                    => LeagueMember::STATUS_FIRED,
        'fired_from_league_team_id' => $exClub->id,
        'fired_at_global_round'     => 4,
    ]);

    app(InvitationService::class)->expireAndGenerate($league);

    expect(LeagueInvitation::where('user_id', $user->id)->count())->toBe(0);
});
