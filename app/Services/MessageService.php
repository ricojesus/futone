<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueMessage;
use App\Models\LeagueTeam;
use Illuminate\Database\Eloquent\Model;

/**
 * Caixa de mensagens do Escritório do Técnico (spec 005).
 *
 * Toda mensagem é endereçada a UM usuário dentro de UMA liga. Mensagens
 * dirigidas a times CPU são silenciosamente ignoradas — só humanos leem.
 */
class MessageService
{
    // Mensagens mantidas por usuário/liga; acima disso as mais antigas são podadas (a calibrar)
    private const KEEP_LAST = 100;

    /**
     * Envia uma mensagem ao técnico humano de um time. No-op para times CPU.
     */
    public function sendToTeam(
        LeagueTeam $team,
        string $type,
        string $title,
        string $body,
        ?Model $subject = null,
    ): ?LeagueMessage {
        if ($team->user_id === null) {
            return null;
        }

        return $this->sendToUser($team->league, $team->user_id, $type, $title, $body, $team, $subject);
    }

    /**
     * Envia uma mensagem a um usuário da liga (com ou sem time — ex.: demitido).
     */
    public function sendToUser(
        League $league,
        int $userId,
        string $type,
        string $title,
        string $body,
        ?LeagueTeam $team = null,
        ?Model $subject = null,
    ): LeagueMessage {
        $message = LeagueMessage::create([
            'league_id'      => $league->id,
            'user_id'        => $userId,
            'league_team_id' => $team?->id,
            'type'           => $type,
            'title'          => $title,
            'body'           => $body,
            'subject_type'   => $subject ? $subject::class : null,
            'subject_id'     => $subject?->getKey(),
            'global_round'   => $league->global_round ?? 0,
        ]);

        $this->prune($league, $userId);

        return $message;
    }

    /**
     * Mantém só as últimas KEEP_LAST mensagens do usuário na liga.
     */
    private function prune(League $league, int $userId): void
    {
        $staleIds = LeagueMessage::where('league_id', $league->id)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip(self::KEEP_LAST)
            ->take(500)
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            LeagueMessage::whereIn('id', $staleIds)->delete();
        }
    }
}
