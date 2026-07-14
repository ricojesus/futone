<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueInvitation;
use App\Models\LeagueMember;
use App\Models\LeagueMessage;
use App\Models\LeagueTeam;
use App\Services\InvitationService;

/**
 * Escritório do Técnico (spec 005): caixa de mensagens + convites pós-demissão.
 */
class OfficeController extends Controller
{
    public function index(League $league)
    {
        $userId = auth()->id();

        $myTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', $userId)
            ->with('team')
            ->first();

        $member = LeagueMember::where('league_id', $league->id)
            ->where('user_id', $userId)
            ->first();

        $isOwner = $league->owner_id === $userId;

        abort_unless($myTeam || $member || $isOwner, 403);

        $messages = LeagueMessage::where('league_id', $league->id)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $unreadCount = $messages->whereNull('read_at')->count();

        $isFired = $member?->status === LeagueMember::STATUS_FIRED && ! $myTeam;

        $invitations = $isFired
            ? LeagueInvitation::where('league_id', $league->id)
                ->where('user_id', $userId)
                ->where('status', LeagueInvitation::STATUS_PENDING)
                ->where('global_round', '>=', $league->global_round)
                ->with('leagueTeam.team')
                ->get()
            : collect();

        return view('leagues.office.index', compact(
            'league', 'myTeam', 'isOwner', 'isFired', 'messages', 'unreadCount', 'invitations',
        ));
    }

    public function readMessage(League $league, LeagueMessage $message)
    {
        abort_unless($message->league_id === $league->id, 404);
        abort_unless($message->user_id === auth()->id(), 403);

        if ($message->isUnread()) {
            $message->update(['read_at' => now()]);
        }

        if ($url = $message->subjectUrl()) {
            return redirect($url);
        }

        return redirect()->route('leagues.office', $league);
    }

    public function acceptInvitation(League $league, LeagueInvitation $invitation, InvitationService $invitations)
    {
        abort_unless($invitation->league_id === $league->id, 404);

        $team = $invitations->accept($invitation, auth()->user());

        return redirect()->route('leagues.office', $league)
            ->with('success', "Você assumiu o {$team->name}! Bom trabalho, professor.");
    }

    public function declineInvitation(League $league, LeagueInvitation $invitation, InvitationService $invitations)
    {
        abort_unless($invitation->league_id === $league->id, 404);

        $invitations->decline($invitation, auth()->user());

        return redirect()->route('leagues.office', $league)
            ->with('info', 'Convite recusado.');
    }
}
