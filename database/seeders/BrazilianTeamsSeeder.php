<?php

namespace Database\Seeders;

use App\Models\Coach;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa os times brasileiros do CSV template.
 * Cria o Coach padrão de cada time (coluna "coach" no CSV) e vincula via teams.coach_id.
 * Seguro para rodar múltiplas vezes (updateOrCreate por slug).
 */
class BrazilianTeamsSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = storage_path('app/csv-templates/times.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV não encontrado: {$csvPath}");
            return;
        }

        $lines  = array_map('str_getcsv', file($csvPath));
        $header = array_map('strtolower', array_map('trim', array_shift($lines)));

        // Índices de lookup
        $countryIndex = DB::table('countries')->pluck('id', 'code')
            ->map(fn($id) => (string) $id)->toArray();
        $stateIndex   = DB::table('states')->pluck('id', 'code')
            ->map(fn($id) => (string) $id)->toArray();

        $brazil = $countryIndex['BRA'] ?? null;

        if (! $brazil) {
            $this->command->error('País BRA não encontrado. Rode BrazilianStatesSeeder primeiro.');
            return;
        }

        $imported = 0;
        $errors   = [];

        foreach ($lines as $i => $line) {
            if (count($line) !== count($header)) {
                $errors[] = "Linha " . ($i + 2) . ": colunas inválidas";
                continue;
            }

            $row  = array_combine($header, $line);
            $name = trim($row['name'] ?? '');

            if ($name === '') continue;

            $stateCode = strtoupper(trim($row['state'] ?? ''));
            $stateId   = $stateIndex[$stateCode] ?? null;

            if (! $stateId) {
                $errors[] = "Linha " . ($i + 2) . ": estado '{$stateCode}' não encontrado";
                continue;
            }

            $slug             = Str::slug($name);
            $overall          = (int) ($row['overall'] ?? 70);
            $stateDivision    = in_array(trim($row['state_division'] ?? ''), ['first', 'second']) ? trim($row['state_division']) : null;
            $nationalDivision = in_array(trim($row['national_division'] ?? ''), ['first', 'second']) ? trim($row['national_division']) : null;
            $tolerance        = (int) ($row['tolerance'] ?? 50);
            $fansBase         = (int) ($row['fans_base'] ?? 10000);
            $stadiumCapacity  = (int) ($row['stadium_capacity'] ?? 10000);
            $coachName        = trim($row['coach'] ?? '');

            // Cria ou atualiza o técnico padrão do clube
            $coach = null;
            if ($coachName !== '') {
                $coach = Coach::firstOrCreate(
                    ['name' => $coachName],
                    ['country_id' => null],
                );
            }

            Team::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'              => $name,
                    'slug'              => $slug,
                    'coach_id'          => $coach?->id,
                    'state_id'          => $stateId,
                    'country_id'        => $brazil,
                    'overall'           => max(1, min(99, $overall)),
                    'state_division'    => $stateDivision,
                    'national_division' => $nationalDivision,
                    'tolerance'         => max(1, min(100, $tolerance)),
                    'fans_base'         => max(0, $fansBase),
                    'stadium_capacity'  => max(0, $stadiumCapacity),
                ]
            );

            $imported++;
        }

        $this->command->info("{$imported} time(s) importados com sucesso.");
        if ($errors) {
            foreach (array_slice($errors, 0, 10) as $e) {
                $this->command->warn("  ⚠ {$e}");
            }
        }
    }
}
