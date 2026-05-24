<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LeagueGeneratorService;
use Illuminate\Console\Command;

class GenerateLeagues extends Command
{
    /**
     * php artisan leagues:generate 2026
     * php artisan leagues:generate 2026 --admin=admin@futone.com
     */
    protected $signature = 'leagues:generate
                            {season : Ano da temporada (ex: 2026)}
                            {--admin= : E-mail do usuário admin dono das ligas (padrão: primeiro admin)}';

    protected $description = 'Cria um League (mundo) e gera os campeonatos estaduais e o Campeonato Brasileiro para uma temporada.';

    public function __construct(
        private readonly LeagueGeneratorService $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = (int) $this->argument('season');

        if ($year < 2020 || $year > 2100) {
            $this->error("Ano inválido: {$year}");
            return Command::FAILURE;
        }

        // ── Resolve admin ─────────────────────────────────────────────────
        $adminEmail = $this->option('admin');

        $admin = $adminEmail
            ? User::where('email', $adminEmail)->first()
            : User::first();

        if (! $admin) {
            $this->error($adminEmail
                ? "Usuário não encontrado: {$adminEmail}"
                : 'Nenhum usuário cadastrado no sistema.'
            );
            return Command::FAILURE;
        }

        $this->info("Gerando temporada <comment>{$year}</comment> com admin <comment>{$admin->email}</comment>…");
        $this->newLine();

        // ── Gera ──────────────────────────────────────────────────────────
        try {
            $result = $this->generator->generateSeason($year, $admin);
        } catch (\Throwable $e) {
            $this->error('Erro ao gerar temporada: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $league = $result['league'];

        $this->line("<fg=cyan;options=bold>✓ League (mundo) criado:</> <fg=white>{$league->name}</> <fg=gray>[{$league->id}]</>");
        $this->newLine();

        // ── Exibe resumo ──────────────────────────────────────────────────
        $this->line('<fg=green;options=bold>✓ Campeonatos estaduais criados:</>');
        $this->showCompetitionTable($result['state']);

        $this->newLine();
        $this->line('<fg=blue;options=bold>✓ Campeonatos nacionais criados:</>');
        $this->showCompetitionTable($result['national']);

        $stateCount    = count($result['state']);
        $nationalCount = count($result['national']);
        $total         = $stateCount + $nationalCount;

        $this->newLine();
        $this->info("Total: {$total} competição(ões) gerada(s) — {$stateCount} estadual(is), {$nationalCount} nacional(is).");

        return Command::SUCCESS;
    }

    private function showCompetitionTable(array $competitions): void
    {
        if (empty($competitions)) {
            $this->line('  <fg=yellow>(nenhuma)</>');
            return;
        }

        $rows = array_map(function ($competition) {
            return [
                $competition->name,
                $competition->divisionLabel(),
                $competition->teams()->count(),
                $competition->total_rounds ?? '—',
            ];
        }, $competitions);

        $this->table(
            ['Competição', 'Divisão', 'Times', 'Rodadas'],
            $rows,
        );
    }
}
