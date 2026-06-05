<?php

namespace Database\Seeders;

use App\Models\Coach;
use Illuminate\Database\Seeder;

/**
 * Semeia 30 técnicos sem clube (free agents).
 * Estes técnicos não estão vinculados a nenhum team no catálogo — ficam
 * disponíveis para serem atribuídos ao pool de livres quando uma liga é gerada.
 *
 * Seguro para rodar múltiplas vezes (firstOrCreate por nome).
 */
class FreeAgentCoachesSeeder extends Seeder
{
    public function run(): void
    {
        $coaches = [
            'Dorival Júnior',
            'Tite',
            'Luiz Felipe Scolari',
            'Vanderlei Luxemburgo',
            'Abel Braga',
            'Cuca',
            'Diniz',
            'Cláudio Tencati',
            'Lisca Doido',
            'Geninho',
            'Guto Ferreira',
            'Marcelo Oliveira',
            'Ney Franco',
            'Sérgio Soares',
            'Rogério Lourenço',
            'Paulo Autuori',
            'Zé Ricardo',
            'Vinícius Eutrópio',
            'Claudinho Gaúcho',
            'Roberto Fernandes',
            'Hemerson Maria',
            'Itamar Schülle',
            'Fábio Zanon',
            'Felipe Surian',
            'Marcelo Chamusca',
            'Paulo Roberto Falcão',
            'Antonio Lopes',
            'Eduardo Baptista',
            'Júnior Lopes',
            'Mazola Júnior',
        ];

        $created = 0;
        foreach ($coaches as $name) {
            $new = Coach::firstOrCreate(
                ['name' => $name],
                ['country_id' => null],
            );
            if ($new->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info("{$created} técnico(s) free agent criado(s).");
    }
}
