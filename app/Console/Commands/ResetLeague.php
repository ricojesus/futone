<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Services\LeagueGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetLeague extends Command
{
    protected $signature   = 'league:reset {league_id : ID da liga a resetar}';
    protected $description = 'Remove todas as competições de uma liga e regenera com os dados atuais do banco.';

    public function handle(LeagueGeneratorService $generator): int
    {
        $league = League::find($this->argument('league_id'));

        if (! $league) {
            $this->error('Liga não encontrada.');
            return 1;
        }

        $this->info("Liga: {$league->name} ({$league->id})");

        if (! $this->confirm('Isso vai apagar TODAS as competições, partidas e times desta liga e regenerar do zero. Continuar?')) {
            return 0;
        }

        DB::transaction(function () use ($league, $generator) {
            // Remove partidas e competition_teams (dependentes das competições)
            foreach ($league->competitions as $comp) {
                $comp->matches()->delete();
                $comp->teams()->delete();
            }

            // Remove competições
            $league->competitions()->delete();

            // Remove jogadores e league_teams
            // (competition_players são por league_team, não por competition)
            foreach ($league->leagueTeams as $lt) {
                $lt->players()->delete();
            }
            $league->leagueTeams()->delete();

            // Reseta a liga para estado inicial
            $league->update([
                'status'        => League::STATUS_WAITING,
                'current_phase' => League::PHASE_STATE,
                'started_at'    => null,
            ]);

            $this->info('Liga limpa. Gerando competições...');

            // Regenera
            $result = $generator->generateForLeague($league);

            $total = count($result['state']) + count($result['national']);
            $this->info("{$total} competições geradas ({$league->competitions()->count()} no banco).");
            $this->info("Estados: " . count($result['state']) . " estaduais criados.");

            // Marca como in_progress
            $league->update([
                'status'     => League::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);
        });

        $this->info("\nLiga regenerada com sucesso!");
        return 0;
    }
}
