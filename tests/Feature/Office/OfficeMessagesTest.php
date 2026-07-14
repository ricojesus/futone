<?php

use App\Models\League;
use App\Models\LeagueMessage;
use App\Models\User;
use App\Services\MessageService;

// Spec 005 / US-1 — caixa de mensagens do Escritório

it('lista as mensagens do técnico e marca como lida', function () {
    $user   = User::factory()->create();
    $league = makeLeague();
    $team   = makeLeagueTeam($league, ['user_id' => $user->id]);

    $message = app(MessageService::class)->sendToTeam(
        $team,
        LeagueMessage::TYPE_FINANCIAL,
        'Cota de TV — Estadual',
        'A emissora depositou R$ 2.000.000.',
    );

    $this->actingAs($user)
        ->get(route('leagues.office', $league))
        ->assertOk()
        ->assertSee('Cota de TV — Estadual')
        ->assertSee('1 nova(s)');

    $this->actingAs($user)
        ->post(route('leagues.office.messages.read', [$league, $message]))
        ->assertRedirect();

    expect($message->fresh()->read_at)->not->toBeNull();
});

it('não vaza mensagens de outro técnico nem deixa marcá-las como lidas', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $league    = makeLeague();
    $myTeam    = makeLeagueTeam($league, ['user_id' => $user->id]);
    $otherTeam = makeLeagueTeam($league, ['user_id' => $other->id]);

    $messages = app(MessageService::class);
    $messages->sendToTeam($myTeam, LeagueMessage::TYPE_CLUB, 'Recado Para Mim', 'corpo');
    $foreign = $messages->sendToTeam($otherTeam, LeagueMessage::TYPE_CLUB, 'Recado Do Rival', 'corpo');

    $this->actingAs($user)
        ->get(route('leagues.office', $league))
        ->assertOk()
        ->assertSee('Recado Para Mim')
        ->assertDontSee('Recado Do Rival');

    $this->actingAs($user)
        ->post(route('leagues.office.messages.read', [$league, $foreign]))
        ->assertForbidden();

    expect($foreign->fresh()->read_at)->toBeNull();
});

it('não envia mensagem a time CPU e poda a caixa nas últimas 100', function () {
    $user   = User::factory()->create();
    $league = makeLeague();
    $team   = makeLeagueTeam($league, ['user_id' => $user->id]);
    $cpu    = makeLeagueTeam($league);

    $messages = app(MessageService::class);

    expect($messages->sendToTeam($cpu, LeagueMessage::TYPE_CLUB, 'x', 'y'))->toBeNull();
    expect(LeagueMessage::count())->toBe(0);

    for ($i = 1; $i <= 105; $i++) {
        $messages->sendToTeam($team, LeagueMessage::TYPE_CLUB, "Mensagem {$i}", 'corpo');
    }

    expect(LeagueMessage::where('user_id', $user->id)->count())->toBe(100);
    // As mais antigas foram podadas
    expect(LeagueMessage::where('title', 'Mensagem 1')->exists())->toBeFalse();
    expect(LeagueMessage::where('title', 'Mensagem 105')->exists())->toBeTrue();
});

it('redireciona o técnico para o Escritório como home da liga, exceto dono e ?classic', function () {
    $user   = User::factory()->create();
    $league = makeLeague();
    makeLeagueTeam($league, ['user_id' => $user->id]);

    // Técnico não-dono: cai no Escritório
    $this->actingAs($user)
        ->get(route('leagues.show', $league))
        ->assertRedirect(route('leagues.office', $league));

    // ?classic=1 escapa do redirect
    $this->actingAs($user)
        ->get(route('leagues.show', ['league' => $league, 'classic' => 1]))
        ->assertOk();

    // Dono não é redirecionado
    $owner = $league->owner;
    $this->actingAs($owner)
        ->get(route('leagues.show', $league))
        ->assertOk();
});
