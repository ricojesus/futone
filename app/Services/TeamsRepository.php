<?php

namespace App\Services;

class TeamsRepository
{
    public static function all(): array
    {
        return [
            self::santos(),
            self::saoPaulo(),
            self::realMadrid(), // 👈 novo time
        ];
    }

    public static function santos(): array
    {
        return [
            'name' => 'Santos FC',
            'players' => [
                ['name' => 'João Paulo', 'position' => 'goleiro', 'strength' => 82],

                ['name' => 'Joaquim', 'position' => 'defesa', 'strength' => 78],
                ['name' => 'Gil', 'position' => 'defesa', 'strength' => 80],
                ['name' => 'Felipe Jonatan', 'position' => 'defesa', 'strength' => 79],
                ['name' => 'Aderlan', 'position' => 'defesa', 'strength' => 77],

                ['name' => 'Tomás Rincón', 'position' => 'meio', 'strength' => 81],
                ['name' => 'Jean Lucas', 'position' => 'meio', 'strength' => 80],
                ['name' => 'Lucas Lima', 'position' => 'meio', 'strength' => 82],

                ['name' => 'Soteldo', 'position' => 'ataque', 'strength' => 84],
                ['name' => 'Julio Furch', 'position' => 'ataque', 'strength' => 83],
                ['name' => 'Guilherme', 'position' => 'ataque', 'strength' => 81],
            ],
        ];
    }

    public static function saoPaulo(): array
    {
        return [
            'name' => 'São Paulo FC',
            'players' => [
                ['name' => 'Rafael', 'position' => 'goleiro', 'strength' => 83],

                ['name' => 'Arboleda', 'position' => 'defesa', 'strength' => 83],
                ['name' => 'Diego Costa', 'position' => 'defesa', 'strength' => 80],
                ['name' => 'Rafinha', 'position' => 'defesa', 'strength' => 79],
                ['name' => 'Welington', 'position' => 'defesa', 'strength' => 78],

                ['name' => 'Pablo Maia', 'position' => 'meio', 'strength' => 82],
                ['name' => 'Alisson', 'position' => 'meio', 'strength' => 80],
                ['name' => 'Rodrigo Nestor', 'position' => 'meio', 'strength' => 83],

                ['name' => 'Luciano', 'position' => 'ataque', 'strength' => 84],
                ['name' => 'Calleri', 'position' => 'ataque', 'strength' => 85],
                ['name' => 'Ferreira', 'position' => 'ataque', 'strength' => 82],
            ],
        ];
    }

    // =========================
    // ⭐ REAL MADRID
    // =========================
    public static function realMadrid(): array
    {
        return [
            'name' => 'Real Madrid',
            'players' => [
                ['name' => 'Courtois', 'position' => 'goleiro', 'strength' => 91],

                ['name' => 'Carvajal', 'position' => 'defesa', 'strength' => 86],
                ['name' => 'Rüdiger', 'position' => 'defesa', 'strength' => 87],
                ['name' => 'Alaba', 'position' => 'defesa', 'strength' => 86],
                ['name' => 'Mendy', 'position' => 'defesa', 'strength' => 85],

                ['name' => 'Valverde', 'position' => 'meio', 'strength' => 88],
                ['name' => 'Tchouaméni', 'position' => 'meio', 'strength' => 87],
                ['name' => 'Bellingham', 'position' => 'meio', 'strength' => 90],

                ['name' => 'Rodrygo', 'position' => 'ataque', 'strength' => 88],
                ['name' => 'Vinícius Júnior', 'position' => 'ataque', 'strength' => 91],
                ['name' => 'Mbappé', 'position' => 'ataque', 'strength' => 92],
            ],
        ];
    }
}
