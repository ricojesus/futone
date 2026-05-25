<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Popula as tabelas `players` e `competition_players`.
 *
 * Cada league_team recebe um elenco de 20 jogadores:
 *   2 goleiros · 6 defensores · 6 meias · 4 atacantes · 2 curinga
 *
 * Os atributos variam conforme a força (fans_base / reputação) do time,
 * inferida a partir do índice do time dentro da liga.
 */
class PlayerSeeder extends Seeder
{
    // ── Nomes brasileiros ────────────────────────────────────────────────

    private const FIRST_NAMES = [
        'Gabriel', 'Lucas', 'Matheus', 'João', 'Pedro', 'Rafael', 'Felipe',
        'Bruno', 'Rodrigo', 'Thiago', 'Diego', 'Marcelo', 'Leandro', 'Renato',
        'Anderson', 'Guilherme', 'Vitor', 'Eduardo', 'Carlos', 'Paulo',
        'Henrique', 'Alessandro', 'Willian', 'Douglas', 'Alexandre',
        'Danilo', 'Fabrício', 'Everton', 'Neymar', 'Rodrygo',
        'Vinícius', 'Endrick', 'Raphinha', 'Richarlison', 'Fred',
        'Casemiro', 'Firmino', 'Hulk', 'Robinho', 'Kaká',
        'Adriano', 'Ronaldinho', 'Rivaldo', 'Romário', 'Bebeto',
        'Taffarel', 'Júnior', 'Cafu', 'Roberto', 'Dunga',
    ];

    private const LAST_NAMES = [
        'Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira',
        'Alves', 'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro',
        'Martins', 'Carvalho', 'Araújo', 'Melo', 'Barbosa', 'Rocha',
        'Nascimento', 'Cardoso', 'Lopes', 'Batista', 'Moreira', 'Dias',
        'Nunes', 'Tavares', 'Azevedo', 'Borges', 'Moura', 'Teixeira',
        'Pinto', 'Ramos', 'Mendes', 'Coelho', 'Machado', 'Freitas',
        'Campos', 'Cavalcanti', 'Andrade', 'Correia', 'Fonseca',
        'Bastos', 'Peixoto', 'Vieira', 'Cunha', 'Castro', 'Soares',
        'Marques', 'Figueiredo', 'Miranda',
    ];

    // Apelidos / nomes de guerra (50 % de chance de usar só um nome)
    private const NICKNAMES = [
        'Júnior', 'Neto', 'Filho', 'Zé', 'Dinho', 'Gol', 'Bin',
        'Bacca', 'Pato', 'Hulk', 'Ganso', 'Índio', 'Gégé', 'Tubarão',
        'Xerife', 'Maradona', 'Romeu', 'Biro-Biro', 'Tita', 'Gaúcho',
    ];

    // ── Estrutura do elenco ──────────────────────────────────────────────

    // [posição => quantidade]
    private const SQUAD = [
        'goalkeeper' => 2,
        'defender'   => 6,
        'midfielder' => 6,
        'forward'    => 4,
        // 2 curingas (midfielder ou defender) → gerados no loop
    ];

    private const WILDCARDS = 2; // meio/zagueiro extras

    // ── Stats por posição (min, max) ────────────────────────────────────

    private const STAT_RANGES = [
        'goalkeeper' => ['strength' => [52, 85], 'stamina' => [62, 88], 'potential' => [52, 95]],
        'defender'   => ['strength' => [50, 82], 'stamina' => [65, 90], 'potential' => [50, 93]],
        'midfielder' => ['strength' => [55, 85], 'stamina' => [68, 92], 'potential' => [55, 96]],
        'forward'    => ['strength' => [58, 88], 'stamina' => [62, 88], 'potential' => [58, 97]],
    ];

    // ── Seeder principal ─────────────────────────────────────────────────

    public function run(): void
    {
        $countryId = DB::table('countries')->value('id');

        if (! $countryId) {
            $this->command->error('Nenhum país cadastrado. Cadastre ao menos um país primeiro.');
            return;
        }

        // Use league_teams instead of competition_teams
        $leagueTeams = DB::table('league_teams')
            ->select('id', 'name', 'league_id')
            ->get();

        if ($leagueTeams->isEmpty()) {
            $this->command->error('Nenhum league_team encontrado. Gere as competições primeiro.');
            return;
        }

        $this->command->info("Gerando jogadores para {$leagueTeams->count()} times...");

        $now         = now()->toDateTimeString();
        $playerRows  = [];
        $compPlayers = [];
        $usedNames   = [];

        // Índice do time dentro de cada liga (para calibrar força)
        $leagueIndexes = [];

        foreach ($leagueTeams as $lt) {
            $leagueIndexes[$lt->league_id] = ($leagueIndexes[$lt->league_id] ?? 0) + 1;
            $indexInLeague = $leagueIndexes[$lt->league_id];

            // Times melhores (menor índice) têm stats mais altos
            // Fator 0 = melhor, 1 = pior dentro da liga
            $totalInLeague = $leagueTeams->where('league_id', $lt->league_id)->count();
            $qualityFactor = 1 - ($indexInLeague - 1) / max(1, $totalInLeague - 1); // 0..1

            $squad = $this->buildSquad($qualityFactor);

            foreach ($squad as $playerDef) {
                $playerId = (string) Str::uuid();
                $name     = $this->generateName($usedNames);
                $usedNames[$name] = true;

                $strength  = $playerDef['strength'];
                $wage      = $this->calcWage($strength);
                $mktValue  = $this->calcMarketValue($strength, $playerDef['age']);

                // Tabela players (registro mestre)
                $playerRows[] = [
                    'id'         => $playerId,
                    'name'       => $name,
                    'position'   => $playerDef['position'],
                    'country_id' => $countryId,
                    'age'        => $playerDef['age'],
                    'strength'   => $strength,
                    'stamina'    => $playerDef['stamina'],
                    'potential'  => $playerDef['potential'],
                    'photo'      => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Tabela competition_players (associado ao league_team, não a uma competição específica)
                $compPlayers[] = [
                    'id'                      => (string) Str::uuid(),
                    'league_team_id'          => $lt->id,
                    'player_id'               => $playerId,
                    'country_id'              => $countryId,
                    'name'                    => $name,
                    'position'                => $playerDef['position'],
                    'age'                     => $playerDef['age'],
                    'strength'                => $strength,
                    'stamina'                 => $playerDef['stamina'],
                    'potential'               => $playerDef['potential'],
                    'form_factor'             => 1.00,
                    'fitness'                 => rand(85, 100),
                    'status'                  => 'active',
                    'wage'                    => $wage,
                    'market_value'            => $mktValue,
                    'contract_until'          => date('Y') + rand(1, 4),
                    'wage_expectation_factor' => 1.00,
                    'joined_at'               => $now,
                    'released_at'             => null,
                    'injured_until'           => null,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ];
            }
        }

        // Bulk insert em chunks para não travar
        $this->command->info('Inserindo ' . count($playerRows) . ' jogadores na tabela players...');
        foreach (array_chunk($playerRows, 200) as $chunk) {
            DB::table('players')->insert($chunk);
        }

        $this->command->info('Inserindo ' . count($compPlayers) . ' registros em competition_players...');
        foreach (array_chunk($compPlayers, 200) as $chunk) {
            DB::table('competition_players')->insert($chunk);
        }

        $this->command->info('Concluído! ' . count($playerRows) . ' jogadores criados.');
    }

    // ── Geração do elenco ────────────────────────────────────────────────

    /**
     * Monta a lista de jogadores para um time com base em seu nível de qualidade.
     *
     * @param  float $quality  0.0 (pior) .. 1.0 (melhor)
     * @return array[]
     */
    private function buildSquad(float $quality): array
    {
        $players = [];

        $positions = [];
        foreach (self::SQUAD as $pos => $qty) {
            for ($i = 0; $i < $qty; $i++) {
                $positions[] = $pos;
            }
        }

        // Curingas: defender ou midfielder
        for ($i = 0; $i < self::WILDCARDS; $i++) {
            $positions[] = rand(0, 1) ? 'defender' : 'midfielder';
        }

        foreach ($positions as $pos) {
            $ranges = self::STAT_RANGES[$pos];

            // Ajusta a janela de stats pelo fator de qualidade
            // quality=1 → top 70% da faixa; quality=0 → bottom 70%
            $strMin = (int) ($ranges['strength'][0] + ($ranges['strength'][1] - $ranges['strength'][0]) * $quality * 0.5);
            $strMax = (int) ($ranges['strength'][0] + ($ranges['strength'][1] - $ranges['strength'][0]) * (0.5 + $quality * 0.5));

            $staMin = (int) ($ranges['stamina'][0] + ($ranges['stamina'][1] - $ranges['stamina'][0]) * $quality * 0.4);
            $staMax = (int) ($ranges['stamina'][0] + ($ranges['stamina'][1] - $ranges['stamina'][0]) * (0.4 + $quality * 0.6));

            $potMin = (int) ($ranges['potential'][0] + ($ranges['potential'][1] - $ranges['potential'][0]) * $quality * 0.3);
            $potMax = (int) ($ranges['potential'][0] + ($ranges['potential'][1] - $ranges['potential'][0]) * (0.3 + $quality * 0.7));

            $strength = rand(max(1, $strMin), max($strMin + 1, $strMax));
            $stamina  = rand(max(1, $staMin), max($staMin + 1, $staMax));
            $age      = $this->randomAge($pos);

            $players[] = [
                'position'  => $pos,
                'age'       => $age,
                'strength'  => $strength,
                'stamina'   => $stamina,
                'potential' => rand(max(1, $potMin), max($potMin + 1, $potMax)),
            ];
        }

        return $players;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function generateName(array $used): string
    {
        $attempts = 0;
        do {
            // 30 % de chance de apelido (nome único)
            if (rand(1, 10) <= 3) {
                $nick    = self::NICKNAMES[array_rand(self::NICKNAMES)];
                $surname = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                $name    = "{$nick} {$surname}";
            } else {
                $first   = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
                $last    = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                $name    = "{$first} {$last}";
            }
            $attempts++;
        } while (isset($used[$name]) && $attempts < 50);

        // Se colidiu muito, adiciona sufixo numérico
        if (isset($used[$name])) {
            $name .= ' ' . rand(2, 99);
        }

        return $name;
    }

    private function randomAge(string $position): int
    {
        return match ($position) {
            'goalkeeper' => rand(18, 37),
            'defender'   => rand(17, 35),
            'midfielder' => rand(17, 34),
            'forward'    => rand(17, 33),
            default      => rand(17, 34),
        };
    }

    private function calcWage(int $strength): int
    {
        // R$ 3.000 .. R$ 350.000 / mês, escala quadrática com strength
        $factor = ($strength - 50) / 50; // -1 .. 1
        $factor = max(0, $factor);
        return (int) (3_000 + $factor * $factor * 347_000);
    }

    private function calcMarketValue(int $strength, int $age): int
    {
        // R$ 50.000 .. R$ 25.000.000, age penalty após 30
        $base   = (int) (50_000 + (($strength - 50) / 40) ** 2 * 24_950_000);
        $ageMod = $age <= 30 ? 1.0 : max(0.3, 1.0 - ($age - 30) * 0.07);
        return (int) ($base * $ageMod);
    }
}
