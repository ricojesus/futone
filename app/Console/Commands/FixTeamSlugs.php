<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FixTeamSlugs extends Command
{
    protected $signature   = 'teams:fix-slugs';
    protected $description = 'Atribui slugs aos times sem slug e remove duplicatas criadas pelo CSV seeder.';

    public function handle(): int
    {
        $noSlug = DB::table('teams')
            ->where(function ($q) {
                $q->whereNull('slug')->orWhere('slug', '');
            })
            ->get(['id', 'name']);

        if ($noSlug->isEmpty()) {
            $this->info('Nenhum time sem slug encontrado. Nada a fazer.');
            return 0;
        }

        $merged  = 0;
        $updated = 0;

        foreach ($noSlug as $t) {
            $slug = Str::slug($t->name);

            // Versão do CSV com esse slug (diferente ID, não null slug)
            $csvVersion = DB::table('teams')
                ->where('slug', $slug)
                ->where('id', '!=', $t->id)
                ->first();

            if ($csvVersion) {
                // Deleta a cópia do CSV ANTES de atualizar (constraint de unicidade)
                DB::table('teams')->where('id', $csvVersion->id)->delete();

                // Copia atributos do CSV para o original (preserva ID e FKs)
                DB::table('teams')->where('id', $t->id)->update([
                    'slug'              => $slug,
                    'overall'           => $csvVersion->overall,
                    'state_division'    => $csvVersion->state_division,
                    'national_division' => $csvVersion->national_division,
                    'tolerance'         => $csvVersion->tolerance,
                    'fans_base'         => $csvVersion->fans_base,
                    'stadium_capacity'  => $csvVersion->stadium_capacity,
                ]);
                $this->line("  Merged: {$t->name} → {$slug}");
                $merged++;
            } else {
                DB::table('teams')->where('id', $t->id)->update(['slug' => $slug]);
                $this->line("  Slug set: {$t->name} → {$slug}");
                $updated++;
            }
        }

        $total = DB::table('teams')->count();
        $this->info("\n✓ {$merged} merged, {$updated} atualizados. Total times: {$total}");
        return 0;
    }
}
