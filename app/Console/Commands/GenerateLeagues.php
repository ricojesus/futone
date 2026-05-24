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

    protected $description = 'Gera os campeonatos estaduais e o Campeonato Brasileiro para uma temporada.';

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
            $created = $this->generator->generateSeason($year, $admin);
        } catch (\Throwable $e) {
            $this->error('Erro ao gerar temporada: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // ── Exibe resumo ──────────────────────────────────────────────────
        $this->line('<fg=green;options=bold>✓ Campeonatos estaduais criados:</>');
        $this->showLeagueTable($created['state']);

        $this->newLine();
        $this->line('<fg=blue;options=bold>✓ Campeonatos nacionais criados:</>');
        $this->showLeagueTable($created['national']);

        $stateCount    = count($created['state']);
        $nationalCount = count($created['national']);
        $total         = $stateCount + $nationalCount;

        $this->newLine();
        $this->info("Total: {$total} liga(s) gerada(s) — {$stateCount} estadual(is), {$nationalCount} nacional(is).");

        return Command::SUCCESS;
    }

    private function showLeagueTable(array $leagues): void
    {
        if (empty($leagues)) {
            $this->line('  <fg=yellow>(nenhuma)</>');
            return;
        }

        $rows = array_map(function ($league) {
            $championship = $league->championships()->first();
            $rounds       = $championship?->total_rounds ?? '—';
            $teams        = $league->teams()->count();

            return [
                $league->name,
                $league->divisionLabel(),
                $teams,
                $rounds,
            ];
        }, $leagues);

        $this->table(
            ['Liga', 'Divisão', 'Times', 'Rodadas'],
            $rows,
        );
    }
}
