<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula os 27 estados brasileiros (26 estados + DF).
 * Seguro para rodar múltiplas vezes (updateOrCreate por code).
 */
class BrazilianStatesSeeder extends Seeder
{
    public function run(): void
    {
        $brazil = DB::table('countries')->where('code', 'BRA')->value('id');

        if (! $brazil) {
            $this->command->error('País BRA não encontrado. Cadastre o Brasil primeiro.');
            return;
        }

        $states = [
            ['code' => 'AC', 'name' => 'Acre'],
            ['code' => 'AL', 'name' => 'Alagoas'],
            ['code' => 'AM', 'name' => 'Amazonas'],
            ['code' => 'AP', 'name' => 'Amapá'],
            ['code' => 'BA', 'name' => 'Bahia'],
            ['code' => 'CE', 'name' => 'Ceará'],
            ['code' => 'DF', 'name' => 'Distrito Federal'],
            ['code' => 'ES', 'name' => 'Espírito Santo'],
            ['code' => 'GO', 'name' => 'Goiás'],
            ['code' => 'MA', 'name' => 'Maranhão'],
            ['code' => 'MG', 'name' => 'Minas Gerais'],
            ['code' => 'MS', 'name' => 'Mato Grosso do Sul'],
            ['code' => 'MT', 'name' => 'Mato Grosso'],
            ['code' => 'PA', 'name' => 'Pará'],
            ['code' => 'PB', 'name' => 'Paraíba'],
            ['code' => 'PE', 'name' => 'Pernambuco'],
            ['code' => 'PI', 'name' => 'Piauí'],
            ['code' => 'PR', 'name' => 'Paraná'],
            ['code' => 'RJ', 'name' => 'Rio de Janeiro'],
            ['code' => 'RN', 'name' => 'Rio Grande do Norte'],
            ['code' => 'RO', 'name' => 'Rondônia'],
            ['code' => 'RR', 'name' => 'Roraima'],
            ['code' => 'RS', 'name' => 'Rio Grande do Sul'],
            ['code' => 'SC', 'name' => 'Santa Catarina'],
            ['code' => 'SE', 'name' => 'Sergipe'],
            ['code' => 'SP', 'name' => 'São Paulo'],
            ['code' => 'TO', 'name' => 'Tocantins'],
        ];

        $now = now();
        $created = 0;
        $skipped = 0;

        foreach ($states as $s) {
            $exists = DB::table('states')->where('code', $s['code'])->exists();
            if ($exists) {
                // Garante que o country_id está correto
                DB::table('states')->where('code', $s['code'])
                    ->update(['country_id' => $brazil, 'name' => $s['name']]);
                $skipped++;
            } else {
                DB::table('states')->insert([
                    'id'         => \Illuminate\Support\Str::orderedUuid()->toString(),
                    'code'       => $s['code'],
                    'name'       => $s['name'],
                    'country_id' => $brazil,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $created++;
            }
        }

        $this->command->info("Estados: {$created} criados, {$skipped} atualizados.");
    }
}
