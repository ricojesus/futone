<?php

namespace App\Console\Commands;

use App\Models\LeagueTeam;
use App\Services\PlayerGeneratorService;
use Illuminate\Console\Command;

class BackfillMissingPlayers extends Command
{
    protected $signature   = 'players:backfill-missing';
    protected $description = 'Gera elenco para LeagueTeams que ficaram sem jogadores (bug do copyPlayers sem referência).';

    public function __construct(
        private readonly PlayerGeneratorService $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $leagueTeams = LeagueTeam::withCount('players')
            ->having('players_count', 0)
            ->with('team')
            ->get();

        if ($leagueTeams->isEmpty()) {
            $this->info('Nenhum LeagueTeam sem jogadores encontrado.');
            return 0;
        }

        $this->info("Gerando elenco para {$leagueTeams->count()} time(s) sem jogadores...");

        $fixed = 0;

        foreach ($leagueTeams as $leagueTeam) {
            if (! $leagueTeam->team) {
                $this->warn("  Pulado: {$leagueTeam->name} (sem Team vinculado)");
                continue;
            }

            $this->generator->generateForTeam($leagueTeam->team, $leagueTeam);
            $fixed++;
        }

        $this->info("Concluído! {$fixed} time(s) com elenco gerado.");
        return 0;
    }
}
