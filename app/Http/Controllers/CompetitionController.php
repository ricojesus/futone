<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTeam;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Models\Team;
use App\Services\LiveMatchSimulator;
use App\Services\MatchSimulator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionController extends Controller
{
    /**
     * Página da competição: tabela de classificação + agenda de partidas.
     */
    public function show(League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);

        $competition->load(['state', 'teams.leagueTeam']);

        // Partidas agrupadas por rodada
        $matchesByRound = $competition->matches()
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('round')
            ->orderBy('created_at')
            ->get()
            ->groupBy('round');

        // LeagueTeam do usuário nesta liga (se houver)
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        // CompetitionTeam (stats) correspondente nesta competição específica
        $myTeam = $myLeagueTeam
            ? $competition->teams->firstWhere('league_team_id', $myLeagueTeam->id)
            : null;

        // É dono da liga?
        $isOwner = $league->owner_id === auth()->id();

        // Classificação: times ordenados por pontos
        $standings = $competition->teams
            ->sortByDesc('points')
            ->sortByDesc('wins')
            ->values();

        // Artilheiros: jogadores dos times desta competição, com gols, top 10
        $leagueTeamIds = $competition->teams->pluck('league_team_id')->filter();
        $topScorers = CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)
            ->where('goals_scored', '>', 0)
            ->with('leagueTeam')
            ->orderByDesc('goals_scored')
            ->limit(10)
            ->get();

        return view('leagues.competitions.show', compact(
            'league', 'competition', 'matchesByRound', 'myLeagueTeam', 'myTeam', 'standings', 'isOwner', 'topScorers'
        ));
    }

    /**
     * Retorna o estado atual da competição para polling do cliente.
     * Resposta leve: rodada atual, status e (se o usuário tiver time) URL da sua partida na rodada mais recente.
     */
    public function roundStatus(League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);

        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        $myMatchUrl = null;

        if ($myLeagueTeam && $competition->current_round > 0) {
            $myCompTeam = CompetitionTeam::where('competition_id', $competition->id)
                ->where('league_team_id', $myLeagueTeam->id)
                ->first();

            if ($myCompTeam) {
                $myMatch = CompetitionMatch::where('competition_id', $competition->id)
                    ->where('round', $competition->current_round)
                    ->where('status', 'finished')
                    ->where(function ($q) use ($myCompTeam) {
                        $q->where('home_team_id', $myCompTeam->id)
                          ->orWhere('away_team_id', $myCompTeam->id);
                    })->first();

                if ($myMatch) {
                    $myMatchUrl = route('matches.show', [$league, $competition, $myMatch, 'replay' => 1]);
                }
            }
        }

        return response()->json([
            'current_round' => $competition->current_round,
            'status'        => $competition->status,
            'my_match_url'  => $myMatchUrl,
        ]);
    }

    /**
     * Formulário para escolher time na liga.
     * Exibe todos os times CPU disponíveis em qualquer competição da liga.
     */
    public function join(League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);
        $this->guardEntry($league, $competition);

        $coaches = Coach::orderBy('name')->get();
        $teams   = $this->availableTeams($league);

        return view('leagues.competitions.join', compact('league', 'competition', 'teams', 'coaches'));
    }

    /**
     * Assume o controle de um time na liga inteira.
     *
     * Atualiza o LeagueTeam CPU com aquele team_id nesta liga,
     * transferindo o user_id para o usuário logado. Assim, o jogador
     * gerencia o mesmo clube em todas as competições em que ele participa.
     */
    public function joinStore(Request $request, League $league, Competition $competition)
    {
        abort_unless($competition->league_id === $league->id, 404);
        $this->guardEntry($league, $competition);

        $validated = $request->validate([
            'team_id'  => 'required|uuid|exists:teams,id',
            'coach_id' => 'nullable|uuid|exists:coaches,id',
        ]);

        $team = Team::findOrFail($validated['team_id']);

        // Verifica se existe um LeagueTeam CPU para este time nesta liga
        $leagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('team_id', $team->id)
            ->whereNull('user_id')
            ->first();

        if (! $leagueTeam) {
            return back()->withErrors(['team_id' => 'Este time não está disponível nesta liga.']);
        }

        // Assume o controle do LeagueTeam (único registro de identidade)
        DB::transaction(function () use ($leagueTeam, $validated) {
            $leagueTeam->update([
                'user_id'  => auth()->id(),
                'coach_id' => $validated['coach_id'] ?? null,
            ]);
        });

        $compsCount = $leagueTeam->competitionTeams()->count();
        $msg = $compsCount > 1
            ? "{$team->name} é seu! Você gerencia este clube em {$compsCount} competições desta liga."
            : "{$team->name} inscrito com sucesso! Boa sorte!";

        return redirect()->route('competitions.show', [$league, $competition])
            ->with('success', $msg);
    }

    /**
     * Simula todas as partidas da próxima rodada desta competição.
     * Só o dono da liga pode acionar.
     *
     * Partidas CPU × CPU  → simuladas instantaneamente (MatchSimulator)
     * Partidas CPU × Humano → primeiro tempo simulado, aguarda intervalo (LiveMatchSimulator)
     */
    public function advanceRound(
        Request             $request,
        League              $league,
        Competition         $competition,
        MatchSimulator      $simulator,
        LiveMatchSimulator  $liveSimulator,
    ) {
        abort_unless($competition->league_id === $league->id, 404);
        abort_unless($league->owner_id === auth()->id(), 403);
        abort_unless($competition->isInProgress(), 409, 'Competição não está em andamento.');

        $nextRound = $competition->current_round + 1;

        if ($nextRound > $competition->total_rounds) {
            return back()->with('error', 'Todas as rodadas já foram executadas.');
        }

        $matches = $competition->matches()
            ->where('round', $nextRound)
            ->whereNotIn('status', ['finished', 'halftime'])
            ->with(['homeTeam.leagueTeam', 'awayTeam.leagueTeam'])
            ->get();

        if ($matches->isEmpty()) {
            return back()->with('info', 'Todas as partidas desta rodada já foram simuladas.');
        }

        // Separa partidas por tipo de motor
        $cpuMatches  = $matches->filter(fn($m) => $this->isCpuMatch($m));
        $liveMatches = $matches->filter(fn($m) => ! $this->isCpuMatch($m));

        DB::transaction(function () use ($cpuMatches, $liveMatches, $simulator, $liveSimulator, $competition, $nextRound) {
            // 1. Recuperação parcial de fitness antes da rodada
            $this->applyFitnessRecovery($competition);

            // ── CPU × CPU: simula tudo agora ────────────────────────
            foreach ($cpuMatches as $match) {
                $result = $simulator->simulate($match);

                $winnerId = null;
                if ($result['home_score'] !== $result['away_score']) {
                    $winnerId = $result['home_score'] > $result['away_score']
                        ? $match->home_team_id
                        : $match->away_team_id;
                }

                $match->update([
                    'home_score'     => $result['home_score'],
                    'away_score'     => $result['away_score'],
                    'winner_team_id' => $winnerId,
                    'status'         => 'finished',
                    'played_at'      => now(),
                    'data'           => [
                        'home_possession'      => $result['home_possession'],
                        'away_possession'      => $result['away_possession'],
                        'home_shots'           => $result['home_shots'],
                        'away_shots'           => $result['away_shots'],
                        'home_shots_on_target' => $result['home_shots_on_target'],
                        'away_shots_on_target' => $result['away_shots_on_target'],
                        'home_formation'       => $result['home_formation'],
                        'away_formation'       => $result['away_formation'],
                        'events'               => $result['events'],
                    ],
                ]);

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

                $homeTeam->increment('goals_for',      $result['home_score']);
                $homeTeam->increment('goals_against',  $result['away_score']);
                $awayTeam->increment('goals_for',      $result['away_score']);
                $awayTeam->increment('goals_against',  $result['home_score']);
            }

            // ── CPU × Humano: simula primeiro tempo, aguarda intervalo ──
            foreach ($liveMatches as $match) {
                $liveSimulator->simulateFirstHalf($match);
                // Status da partida → 'halftime' (salvo dentro do simulador)
                // A rodada SÓ avança quando o segundo tempo for concluído
            }

            // A rodada avança apenas se todos os jogos da rodada estão finalizados
            // (sem partidas em halftime pendentes)
            $hasPendingLive = $liveMatches->isNotEmpty();

            if (! $hasPendingLive) {
                $competition->increment('current_round');

                if ($competition->current_round >= $competition->total_rounds) {
                    $competition->update(['status' => Competition::STATUS_FINISHED]);
                }
            }

            // 2. Contabilizar gols por jogador (artilharia) — apenas CPU matches finalizados
            $this->applyGoalsScored($cpuMatches);

            // 3. Desgaste físico para os times que jogaram (CPU matches)
            $this->applyFitnessDegradation($cpuMatches);
        });

        // Se o dono tem um time que joga ao vivo nesta rodada, redireciona para o intervalo
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($myLeagueTeam) {
            $myCompTeam = CompetitionTeam::where('competition_id', $competition->id)
                ->where('league_team_id', $myLeagueTeam->id)
                ->first();

            if ($myCompTeam) {
                $myMatch = CompetitionMatch::where('competition_id', $competition->id)
                    ->where('round', $nextRound)
                    ->where(function ($q) use ($myCompTeam) {
                        $q->where('home_team_id', $myCompTeam->id)
                          ->orWhere('away_team_id', $myCompTeam->id);
                    })->first();

                if ($myMatch) {
                    $route = $myMatch->status === 'halftime'
                        ? route('matches.halftime', [$league, $competition, $myMatch])
                        : route('matches.show', [$league, $competition, $myMatch, 'replay' => 1]);

                    return redirect($route);
                }
            }
        }

        $liveCount = $liveMatches->count();
        $cpuCount  = $cpuMatches->count();
        $msg = $liveCount > 0
            ? "{$cpuCount} partidas simuladas. {$liveCount} partida(s) aguardando o intervalo."
            : "Rodada {$nextRound} simulada! {$cpuCount} partidas jogadas.";

        return redirect()->route('competitions.show', [$league, $competition])->with('success', $msg);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Retorna true se ambos os times da partida são CPU (sem usuário humano).
     * Partidas com pelo menos um lado humano usam o LiveMatchSimulator.
     */
    private function isCpuMatch(CompetitionMatch $match): bool
    {
        return $match->homeTeam->leagueTeam->isCpu()
            && $match->awayTeam->leagueTeam->isCpu();
    }

    /**
     * Bloqueia o acesso ao formulário de inscrição se:
     *  - a competição não está mais aguardando; ou
     *  - o usuário já tem LeagueTeam em qualquer competição desta liga.
     */
    private function guardEntry(League $league, Competition $competition): void
    {
        // Bloqueia apenas competições encerradas (waiting e in_progress aceitam inscrição)
        if ($competition->isFinished()) {
            redirect()->route('competitions.show', [$league, $competition])
                ->with('error', 'Esta competição já foi encerrada.')
                ->throwResponse();
        }

        // Checa na liga inteira via league_teams (única identidade por liga)
        $alreadyHasTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyHasTeam) {
            redirect()->route('leagues.show', $league)
                ->with('info', 'Você já gerencia um time nesta liga.')
                ->throwResponse();
        }
    }

    /**
     * Incrementa goals_scored para cada jogador que marcou nesta rodada.
     * Lê os eventos armazenados nos matches já atualizados.
     */
    private function applyGoalsScored(Collection $matches): void
    {
        // Acumula: player_id → quantidade de gols
        $scorerTotals = [];

        foreach ($matches as $match) {
            $events = $match->fresh()->data['events'] ?? [];
            foreach ($events as $event) {
                if (($event['type'] ?? '') === 'goal' && ! empty($event['scorer_id'])) {
                    $scorerTotals[$event['scorer_id']] = ($scorerTotals[$event['scorer_id']] ?? 0) + 1;
                }
            }
        }

        foreach ($scorerTotals as $playerId => $goals) {
            CompetitionPlayer::where('id', $playerId)->increment('goals_scored', $goals);
        }
    }

    /**
     * Recuperação parcial de fitness antes de cada rodada.
     * Jogadores descansam entre rodadas, recuperando entre 8–18 pts,
     * proporcional ao stamina (quanto mais stamina, mais rápido se recupera).
     * Cap: 100.
     */
    private function applyFitnessRecovery(Competition $competition): void
    {
        // Todos os league_team_ids desta competição
        $leagueTeamIds = $competition->teams()->pluck('league_team_id');

        $players = CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)->get();

        foreach ($players as $player) {
            if ($player->fitness >= 100) {
                continue;
            }

            $stamina = max(1, $player->stamina ?? 50);
            $age     = $player->age ?? 25;

            // Veteranos (≥30) recuperam mais devagar; jovens (≤21) recuperam mais rápido
            // Fator: 1.15 aos 18, 1.0 aos 25, 0.75 aos 32, 0.60 aos 36+
            $ageFactor = match(true) {
                $age <= 21 => 1.15,
                $age <= 25 => 1.00,
                $age <= 28 => 0.90,
                $age <= 31 => 0.78,
                $age <= 34 => 0.65,
                default    => 0.55,
            };

            $recovery   = (int) round(rand(8, 18) * ($stamina / 90) * $ageFactor);
            $newFitness = min(100, $player->fitness + $recovery);

            $player->update(['fitness' => $newFitness]);
        }
    }

    /**
     * Desgaste físico após os jogos de uma rodada.
     * Cada time que jogou tem seus jogadores desgastados.
     * Perda = rand(5,14) escalado pelo inverso do stamina (baixo stamina = mais cansaço).
     * Floor: 40 (ninguém fica completamente inapto por cansaço).
     */
    private function applyFitnessDegradation(Collection $matches): void
    {
        // Coleta todos os league_team_ids que jogaram nesta rodada
        $leagueTeamIds = collect();

        foreach ($matches as $match) {
            if ($match->homeTeam) {
                $leagueTeamIds->push($match->homeTeam->league_team_id);
            }
            if ($match->awayTeam) {
                $leagueTeamIds->push($match->awayTeam->league_team_id);
            }
        }

        $leagueTeamIds = $leagueTeamIds->filter()->unique()->values();

        if ($leagueTeamIds->isEmpty()) {
            return;
        }

        $players = CompetitionPlayer::whereIn('league_team_id', $leagueTeamIds)->get();

        foreach ($players as $player) {
            $stamina = max(1, $player->stamina ?? 50);
            $age     = $player->age ?? 25;

            // Veteranos se desgastam mais; jovens aguentam melhor
            // Fator: 0.85 aos 18, 1.0 aos 25, 1.15 aos 30, 1.35 aos 34+
            $ageFactor = match(true) {
                $age <= 21 => 0.85,
                $age <= 25 => 1.00,
                $age <= 28 => 1.10,
                $age <= 31 => 1.20,
                $age <= 34 => 1.32,
                default    => 1.45,
            };

            // Quanto menor o stamina, mais o jogador se desgasta (1.3 − stamina/90)
            $loss = (int) round(rand(5, 14) * (1.3 - $stamina / 90) * $ageFactor);
            $loss = max(3, $loss); // mínimo de 3 pts de desgaste sempre
            $newFitness = max(40, $player->fitness - $loss);

            $player->update(['fitness' => $newFitness]);
        }
    }

    /**
     * Times CPU disponíveis para assumir nesta liga.
     * Retorna os times do catálogo (Team) que possuem um LeagueTeam CPU nesta liga.
     */
    private function availableTeams(League $league)
    {
        $availableTeamIds = LeagueTeam::where('league_id', $league->id)
            ->whereNull('user_id')
            ->whereNotNull('team_id')
            ->pluck('team_id');

        return Team::whereIn('id', $availableTeamIds)
            ->orderBy('name')
            ->get();
    }
}
