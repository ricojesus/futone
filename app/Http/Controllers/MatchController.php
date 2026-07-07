<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Models\MatchState;
use App\Services\FinancialService;
use App\Services\LiveMatchSimulator;
use App\Services\SatisfactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    // ── Partida encerrada ─────────────────────────────────────────────

    public function show(League $league, Competition $competition, CompetitionMatch $match)
    {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($match->competition_id === $competition->id, 404);
        abort_unless($match->status === 'finished', 404, 'Partida ainda não disputada.');

        $match->load(['homeTeam.leagueTeam.team', 'awayTeam.leagueTeam.team']);

        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        $isMyMatch = $myLeagueTeam && (
            $match->homeTeam->league_team_id === $myLeagueTeam->id ||
            $match->awayTeam->league_team_id === $myLeagueTeam->id
        );

        $side = null;
        if ($isMyMatch) {
            $side = $match->homeTeam->league_team_id === $myLeagueTeam->id ? 'home' : 'away';
        }

        $roundMatches = $competition->matches()
            ->where('round', $match->round)
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $replayMode  = request()->boolean('replay');
        $secondHalf  = request()->boolean('second_half');

        // Quando é replay do 2º tempo, o placar inicial do replay é o do intervalo
        $events = $match->data['events'] ?? [];
        $halftimeHomeScore = 0;
        $halftimeAwayScore = 0;
        if ($secondHalf) {
            foreach ($events as $e) {
                if (($e['type'] ?? '') === 'goal' && ($e['play'] ?? 0) <= 45) {
                    if (($e['team'] ?? '') === 'home') $halftimeHomeScore++;
                    else $halftimeAwayScore++;
                }
            }
        }

        return view('leagues.competitions.matches.show', compact(
            'league', 'competition', 'match',
            'myLeagueTeam', 'isMyMatch', 'side', 'roundMatches',
            'replayMode', 'secondHalf', 'halftimeHomeScore', 'halftimeAwayScore'
        ));
    }

    // ── Intervalo ─────────────────────────────────────────────────────

    public function halftime(League $league, Competition $competition, CompetitionMatch $match)
    {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($match->competition_id === $competition->id, 404);
        abort_unless($match->status === 'halftime', 404, 'Esta partida não está no intervalo.');

        $match->load(['homeTeam.leagueTeam.team', 'awayTeam.leagueTeam.team']);

        $matchState = MatchState::where('competition_match_id', $match->id)->firstOrFail();
        $state      = $matchState->state;

        // Time do usuário nesta liga
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        // Determina de que lado o usuário está
        $side = null;
        $myCompTeam = null;
        if ($myLeagueTeam) {
            if ($match->homeTeam->league_team_id === $myLeagueTeam->id) {
                $side       = 'home';
                $myCompTeam = $match->homeTeam;
            } elseif ($match->awayTeam->league_team_id === $myLeagueTeam->id) {
                $side       = 'away';
                $myCompTeam = $match->awayTeam;
            }
        }

        $canResume = $myLeagueTeam && $side !== null;

        // Escalação do time do usuário (titulares + reservas) para substituições
        $lineup  = null;
        $starters = collect();
        $bench    = collect();

        if ($myCompTeam) {
            $lineup = $myLeagueTeam->lineups()
                ->where('status', 'active')
                ->whereIn('round', [$match->round, 0])
                ->orderByDesc('round')
                ->first();

            if ($lineup) {
                $positionOrder = ['goalkeeper' => 0, 'defender' => 1, 'midfielder' => 2, 'forward' => 3];

                // Titulares: vêm da escalação salva
                $starters = $lineup->players()
                    ->orderBy('competition_lineup_players.slot')
                    ->get()
                    ->filter(fn($p) => $p->pivot->is_starter)
                    ->sortBy(fn($p) => $positionOrder[$p->position] ?? 99)
                    ->values();

                // Reservas: todos os jogadores ativos do time que NÃO estão entre os titulares
                // (o save de lineup só persiste titulares, então bench vem direto do elenco)
                $starterIds = $starters->pluck('id')->map(fn($id) => (string) $id)->all();

                $bench = $myLeagueTeam->players()
                    ->where('status', 'active')
                    ->whereNotIn('id', $starterIds)
                    ->orderByRaw("FIELD(position, 'goalkeeper','defender','midfielder','forward')")
                    ->orderByDesc('strength')
                    ->get();
            }
        }

        // Detecta Human vs Human (ambos os times têm dono)
        $homeIsHuman    = ($match->homeTeam->leagueTeam?->user_id) !== null;
        $awayIsHuman    = ($match->awayTeam->leagueTeam?->user_id) !== null;
        $isHumanVsHuman = $homeIsHuman && $awayIsHuman;

        // Metadados de coordenação HvH
        $myReady    = false;
        $otherReady = false;
        $secondsLeft = 60;
        $statusUrl  = null;

        if ($isHumanVsHuman && $side) {
            $otherSide   = $side === 'home' ? 'away' : 'home';
            $myReady     = (bool) ($state["{$side}_ready"]      ?? false);
            $otherReady  = (bool) ($state["{$otherSide}_ready"] ?? false);
            $halftimeAt  = $state['halftime_at'] ?? null;
            $elapsed     = $halftimeAt
                ? now()->diffInSeconds(\Carbon\Carbon::parse($halftimeAt))
                : 0;
            $secondsLeft = max(0, 60 - $elapsed);
            $statusUrl   = route('matches.halftime.status', [$league, $competition, $match]);
        }

        // Eventos e stats do primeiro tempo
        $events         = $state['events']           ?? [];
        $homeScore      = $state['homeScore']         ?? 0;
        $awayScore      = $state['awayScore']         ?? 0;
        $homeShots      = $state['homeShots']         ?? 0;
        $awayShots      = $state['awayShots']         ?? 0;
        $homeOnTarget   = $state['homeShotsOnTarget'] ?? 0;
        $awayOnTarget   = $state['awayShotsOnTarget'] ?? 0;
        $homePossCount  = $state['homePossCount']     ?? 0;
        $homePossession = $homePossCount > 0
            ? (int) round($homePossCount / 45 * 100)
            : 50;

        return view('leagues.competitions.matches.halftime', compact(
            'league', 'competition', 'match', 'matchState',
            'myLeagueTeam', 'side', 'canResume',
            'lineup', 'starters', 'bench',
            'events', 'homeScore', 'awayScore',
            'homeShots', 'awayShots', 'homeOnTarget', 'awayOnTarget',
            'homePossession',
            'isHumanVsHuman', 'myReady', 'otherReady', 'secondsLeft', 'statusUrl',
        ));
    }

    // ── Iniciar segundo tempo ─────────────────────────────────────────

    public function resumeSecondHalf(
        Request            $request,
        League             $league,
        Competition        $competition,
        CompetitionMatch   $match,
        LiveMatchSimulator $liveSimulator,
    ) {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($match->competition_id === $competition->id, 404);
        abort_unless($match->status === 'halftime', 409, 'Partida não está no intervalo.');

        $match->load(['homeTeam.leagueTeam', 'awayTeam.leagueTeam']);

        // Só o técnico do time (home ou away) pode iniciar o segundo tempo
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        $isParticipant = $myLeagueTeam && (
            $match->homeTeam->league_team_id === $myLeagueTeam->id ||
            $match->awayTeam->league_team_id === $myLeagueTeam->id
        );

        abort_unless($isParticipant, 403, 'Apenas o técnico do time pode iniciar o segundo tempo.');

        $mySide = $match->homeTeam->league_team_id === $myLeagueTeam->id ? 'home' : 'away';

        // ── Detecta Human vs Human ───────────────────────────────────
        $homeIsHuman    = ($match->homeTeam->leagueTeam?->user_id) !== null;
        $awayIsHuman    = ($match->awayTeam->leagueTeam?->user_id) !== null;
        $isHumanVsHuman = $homeIsHuman && $awayIsHuman;

        $substitutions = $request->input('substitutions', []);

        if ($isHumanVsHuman) {
            // ── Fluxo Human × Human ──────────────────────────────────
            $matchState = MatchState::where('competition_match_id', $match->id)->firstOrFail();
            $state      = $matchState->state;

            // Persiste substituições e marca este lado como pronto
            $state["{$mySide}_ready"]         = true;
            $state["{$mySide}_substitutions"] = $substitutions;

            // Verifica se o adversário já confirmou ou se o tempo esgotou
            $otherSide      = $mySide === 'home' ? 'away' : 'home';
            $otherReady     = (bool) ($state["{$otherSide}_ready"] ?? false);
            $force          = $request->boolean('force');
            $halftimeAt     = $state['halftime_at'] ?? null;
            $elapsed        = $halftimeAt
                ? now()->diffInSeconds(\Carbon\Carbon::parse($halftimeAt))
                : 999;
            $timeoutExpired = $elapsed >= 60;

            // Salva o estado parcial (meu lado pronto)
            $matchState->update(['state' => $state]);

            if (! $otherReady && ! $timeoutExpired && ! $force) {
                // Adversário ainda não confirmou e o tempo não esgotou — aguarda
                return redirect()
                    ->route('matches.halftime', [$league, $competition, $match])
                    ->with('info', 'Confirmado! Aguardando o adversário terminar o intervalo…');
            }

            // Ambos prontos (ou tempo esgotou): aplica substituições dos dois lados
            $homeLeagueTeam = $match->homeTeam->leagueTeam;
            $awayLeagueTeam = $match->awayTeam->leagueTeam;
            $homeSubs       = $state['home_substitutions'] ?? [];
            $awaySubs       = $state['away_substitutions'] ?? [];

            if ($homeLeagueTeam && ! empty($homeSubs)) {
                $this->applySubstitutions($homeLeagueTeam, $match->round, $homeSubs);
            }
            if ($awayLeagueTeam && ! empty($awaySubs)) {
                $this->applySubstitutions($awayLeagueTeam, $match->round, $awaySubs);
            }
        } else {
            // ── Fluxo Human × CPU ────────────────────────────────────
            if (! empty($substitutions)) {
                $this->applySubstitutions($myLeagueTeam, $match->round, $substitutions);
            }
        }

        // ── Simular segundo tempo ────────────────────────────────────
        DB::transaction(function () use ($match, $liveSimulator, $competition) {
            $result = $liveSimulator->simulateSecondHalf($match);

            // Atualizar standings
            $homeTeam = $match->homeTeam;
            $awayTeam = $match->awayTeam;

            if ($result['home_score'] > $result['away_score']) {
                $homeTeam->increment('wins');
                $homeTeam->increment('points', 3);
                $awayTeam->increment('losses');
            } elseif ($result['home_score'] < $result['away_score']) {
                $awayTeam->increment('wins');
                $awayTeam->increment('points', 3);
                $homeTeam->increment('losses');
            } else {
                $homeTeam->increment('draws');
                $homeTeam->increment('points', 1);
                $awayTeam->increment('draws');
                $awayTeam->increment('points', 1);
            }

            $homeTeam->increment('goals_for',     $result['home_score']);
            $homeTeam->increment('goals_against',  $result['away_score']);
            $awayTeam->increment('goals_for',     $result['away_score']);
            $awayTeam->increment('goals_against',  $result['home_score']);

            // Artilharia e fitness (reutiliza o match fresh com eventos completos)
            $this->applyGoalsScored($match->fresh());
            $this->applyFitnessDegradation($homeTeam, $awayTeam, $match->round);

            // Avança a rodada se todos os jogos daquela rodada estão finalizados
            $pending = CompetitionMatch::where('competition_id', $competition->id)
                ->where('round', $match->round)
                ->whereNotIn('status', ['finished'])
                ->count();

            if ($pending === 0) {
                $competition->increment('current_round');

                if ($competition->fresh()->current_round >= $competition->total_rounds) {
                    $competition->update(['status' => Competition::STATUS_FINISHED]);
                }
            }
        });

        // Atualiza satisfação com o resultado da partida ao vivo (que estava em halftime
        // quando GlobalRoundService::advance() chamou SatisfactionService)
        $freshMatch = $match->fresh(['homeTeam.leagueTeam', 'awayTeam.leagueTeam', 'competition']);
        app(SatisfactionService::class)->applyLiveMatchResult($freshMatch, $league);
        app(FinancialService::class)->processMatchRevenue($freshMatch);

        return redirect()
            ->route('matches.show', [$league, $competition, $match->fresh(), 'replay' => 1, 'second_half' => 1])
            ->with('success', 'Segundo tempo concluído! Assista ao replay completo.');
    }

    // ── Status do intervalo (polling HvH) ─────────────────────────────

    public function halftimeStatus(League $league, Competition $competition, CompetitionMatch $match)
    {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($match->competition_id === $competition->id, 404);

        // Partida já encerrada — redireciona o cliente para o replay
        if ($match->status === 'finished') {
            return response()->json([
                'finished'  => true,
                'match_url' => route('matches.show', [$league, $competition, $match, 'replay' => 1, 'second_half' => 1]),
            ]);
        }

        abort_unless($match->status === 'halftime', 404);

        $matchState = MatchState::where('competition_match_id', $match->id)->firstOrFail();
        $state      = $matchState->state;

        $halftimeAt  = $state['halftime_at'] ?? null;
        $elapsed     = $halftimeAt
            ? now()->diffInSeconds(\Carbon\Carbon::parse($halftimeAt))
            : 0;
        $secondsLeft = max(0, 60 - $elapsed);

        return response()->json([
            'finished'     => false,
            'home_ready'   => (bool) ($state['home_ready'] ?? false),
            'away_ready'   => (bool) ($state['away_ready'] ?? false),
            'seconds_left' => $secondsLeft,
            'can_force'    => $secondsLeft === 0,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Aplica substituições do intervalo na escalação do time.
     * Cria (ou atualiza) um override de escalação para a rodada específica.
     */
    private function applySubstitutions(LeagueTeam $leagueTeam, int $round, array $substitutions): void
    {
        // Busca escalação ativa (padrão ou desta rodada)
        $baseLineup = $leagueTeam->lineups()
            ->where('status', 'active')
            ->whereIn('round', [$round, 0])
            ->orderByDesc('round')
            ->first();

        if (! $baseLineup) {
            return;
        }

        // Se o override da rodada não existe ainda, clona a escalação padrão
        if ($baseLineup->round !== $round) {
            $override = $baseLineup->replicate(['id', 'created_at', 'updated_at']);
            $override->round = $round;
            $override->save();

            // Copia todos os jogadores para o override
            foreach ($baseLineup->lineupPlayers as $lp) {
                $override->lineupPlayers()->create([
                    'competition_player_id' => $lp->competition_player_id,
                    'role'                  => $lp->role,
                    'is_starter'            => $lp->is_starter,
                    'slot'                  => $lp->slot,
                ]);
            }

            $baseLineup = $override->fresh(['lineupPlayers']);
        }

        // IDs de jogadores do time (para validação)
        $teamPlayerIds = $leagueTeam->players()
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->all();

        // Aplica cada substituição
        foreach (array_slice($substitutions, 0, 5) as $sub) {
            $outId = (string) ($sub['out'] ?? '');
            $inId  = (string) ($sub['in']  ?? '');

            if (! $outId || ! $inId || $outId === $inId) {
                continue;
            }

            // Valida que o reserva pertence ao time
            if (! in_array($inId, $teamPlayerIds, true)) {
                continue;
            }

            // Titular que sai
            $outRecord = $baseLineup->lineupPlayers()
                ->where('competition_player_id', $outId)
                ->where('is_starter', true)
                ->first();

            if (! $outRecord) {
                continue;
            }

            // Reserva que entra: pode já estar na lineup (is_starter=false) ou não estar
            $inRecord = $baseLineup->lineupPlayers()
                ->where('competition_player_id', $inId)
                ->first();

            if ($inRecord) {
                // Já está na lineup — faz o swap normal
                $inRecord->is_starter = true;
                $inRecord->slot       = $outRecord->slot;
                $inRecord->role       = $outRecord->role;
                $inRecord->save();
            } else {
                // Não está na lineup — cria como titular no slot do jogador que saiu
                $baseLineup->lineupPlayers()->create([
                    'competition_player_id' => $inId,
                    'role'                  => $outRecord->role,
                    'slot'                  => $outRecord->slot,
                    'is_starter'            => true,
                ]);
            }

            // Titular sai
            $outRecord->is_starter = false;
            $outRecord->save();
        }
    }

    /**
     * Incrementa goals_scored para cada goleador nos eventos da partida.
     */
    private function applyGoalsScored(CompetitionMatch $match): void
    {
        $events = $match->data['events'] ?? [];
        $totals = [];

        foreach ($events as $event) {
            if (($event['type'] ?? '') === 'goal' && ! empty($event['scorer_id'])) {
                $totals[$event['scorer_id']] = ($totals[$event['scorer_id']] ?? 0) + 1;
            }
        }

        foreach ($totals as $playerId => $goals) {
            \App\Models\CompetitionPlayer::where('id', $playerId)->increment('goals_scored', $goals);
        }
    }

    /**
     * Aplica desgaste físico aos jogadores dos times desta partida ao vivo.
     */
    /**
     * Degrada apenas os titulares que jogaram o segundo tempo (após substituições).
     * Reservas que não entraram em campo ficam intactos.
     */
    private function applyFitnessDegradation(CompetitionTeam $homeTeam, CompetitionTeam $awayTeam, int $round): void
    {
        $starterIds = collect();

        foreach ([$homeTeam, $awayTeam] as $compTeam) {
            if (! $compTeam?->league_team_id) {
                continue;
            }

            // Busca escalação ativa para esta rodada (override com subs ou padrão).
            // Usa a rodada da PARTIDA — current_round ainda não foi incrementado
            // e apontaria para a lineup errada, ignorando as subs do intervalo.
            $lineup = \App\Models\CompetitionLineup::where('league_team_id', $compTeam->league_team_id)
                ->where('status', 'active')
                ->whereIn('round', [$round, 0])
                ->orderByDesc('round')
                ->first();

            if (! $lineup) {
                continue;
            }

            $lineup->lineupPlayers()
                ->where('is_starter', true)
                ->pluck('competition_player_id')
                ->each(fn($id) => $starterIds->push($id));
        }

        if ($starterIds->isEmpty()) {
            return;
        }

        $players = \App\Models\CompetitionPlayer::whereIn('id', $starterIds->unique())->get();

        foreach ($players as $player) {
            $stamina = max(1, $player->stamina ?? 50);
            $age     = $player->age ?? 25;

            $ageFactor = match (true) {
                $age <= 21 => 0.85,
                $age <= 25 => 1.00,
                $age <= 28 => 1.10,
                $age <= 31 => 1.20,
                $age <= 34 => 1.32,
                default    => 1.45,
            };

            $loss       = (int) round(rand(10, 18) * (1.3 - $stamina / 90) * $ageFactor);
            $loss       = max(5, $loss);
            $newFitness = max(35, $player->fitness - $loss);

            $player->update(['fitness' => $newFitness]);
        }
    }
}
