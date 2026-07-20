<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Varre public/images/badges/*.png e vincula cada arquivo ao time correspondente
 * via comparação de nome (normalizado) ou pelo mapa de exceções.
 *
 * Uso:
 *   php artisan teams:sync-badges           # dry-run (mostra o que faria)
 *   php artisan teams:sync-badges --apply   # aplica as alterações
 */
class SyncTeamBadges extends Command
{
    protected $signature   = 'teams:sync-badges {--apply : Aplica as alterações no banco}';
    protected $description = 'Vincula arquivos de escudo em public/images/badges/ aos times cadastrados';

    /**
     * Mapa de exceções: parte normalizada do filename → slug do time no banco.
     * Usado quando o nome do arquivo não casa automaticamente com o nome do time.
     */
    private array $overrides = [
        'remo'                  => 'clube-do-remo',
        'mirassol-sp'           => 'mirassol',
        'vitoria-ba'            => 'vitoria',
        'nautico-pe'            => 'nautico',
        'atletico-mineiro'      => 'atletico-mineiro',   // garante sem acento
        'gremio'                => 'gremio',
        'sao-paulo'             => 'sao-paulo',
        'fluminense'            => 'fluminense',          // evita pegar Fluminense de Feira
        'flamengo'              => 'flamengo',            // evita pegar Flamengo-PI
        'botafogo'              => 'botafogo',            // evita pegar Botafogo-SP
    ];

    /** Arquivos a ignorar (não são times jogáveis). */
    private array $ignore = [
        'copa-do-brasil',
    ];

    public function handle(): int
    {
        $badgesDir = public_path('images/badges');

        if (! is_dir($badgesDir)) {
            $this->error("Pasta não encontrada: {$badgesDir}");
            return self::FAILURE;
        }

        $files   = glob("{$badgesDir}/*.{png,jpg,jpeg,svg,webp}", GLOB_BRACE);
        $apply   = $this->option('apply');
        $matched = 0;
        $skipped = 0;

        // Índice de times: slug → Team
        $teams = Team::all()->keyBy(fn(Team $t) => $t->slug);

        $this->newLine();
        $this->line($apply
            ? '<fg=yellow>Modo APPLY — alterações serão gravadas no banco.</>'
            : '<fg=cyan>Modo DRY-RUN — nenhuma alteração será feita. Use --apply para gravar.</>');
        $this->newLine();

        foreach ($files as $file) {
            $filename   = pathinfo($file, PATHINFO_FILENAME);  // ex: "Atlético Mineiro"
            $normalized = $this->normalize($filename);          // ex: "atletico-mineiro"
            $relativePath = 'images/badges/' . basename($file);

            // Ignora arquivos não relacionados a times
            if (in_array($normalized, $this->ignore, true)) {
                $this->line("  <fg=gray>IGNORADO  {$filename}</>");
                continue;
            }

            // Resolve o slug: override explícito ou normalização direta
            $targetSlug = $this->overrides[$normalized] ?? $normalized;
            $team = $teams->get($targetSlug);

            if (! $team) {
                $this->line("  <fg=red>✗ SEM MATCH  {$filename}  (slug tentado: {$targetSlug})</>");
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                "  <fg=green>✓</> %-40s → %s%s",
                $filename,
                $team->name,
                $team->badge ? " <fg=yellow>[já tinha badge]</>" : '',
            ));

            if ($apply) {
                $team->update(['badge' => $relativePath]);
            }

            $matched++;
        }

        $this->newLine();
        $this->info("{$matched} escudo(s) " . ($apply ? 'gravado(s)' : 'identificado(s)') . ". {$skipped} sem correspondência.");

        if (! $apply) {
            $this->comment('Rode com --apply para gravar no banco.');
        }

        return self::SUCCESS;
    }

    /**
     * Normaliza um nome de arquivo para slug comparável ao slug do time no banco.
     * Remove acentos, substitui espaços por hífens e converte para minúsculas.
     */
    private function normalize(string $name): string
    {
        return Str::slug($name);
    }
}
