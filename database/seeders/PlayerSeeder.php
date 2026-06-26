<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Popula players e competition_players.
 *
 * Para times com dados no jogadores.csv, usa os jogadores reais.
 * Para os demais (ou para completar até 20), gera jogadores aleatórios.
 */
class PlayerSeeder extends Seeder
{
    private const SQUAD_SIZE = 20;

    private const FIRST_NAMES = [
        'Gabriel', 'Lucas', 'Matheus', 'João', 'Pedro', 'Rafael', 'Felipe',
        'Bruno', 'Rodrigo', 'Thiago', 'Diego', 'Marcelo', 'Leandro', 'Renato',
        'Anderson', 'Guilherme', 'Vitor', 'Eduardo', 'Carlos', 'Paulo',
        'Henrique', 'Alessandro', 'Willian', 'Douglas', 'Alexandre',
        'Danilo', 'Fabrício', 'Everton', 'Wellington', 'Rodrygo',
        'Vinícius', 'Endrick', 'Raphael', 'Richarlison', 'Fred',
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

    private const NICKNAMES = [
        'Júnior', 'Neto', 'Filho', 'Zé', 'Dinho', 'Bin',
        'Pato', 'Ganso', 'Índio', 'Gégé', 'Tubarão',
        'Xerife', 'Tita', 'Gaúcho', 'Biro', 'Cafuné',
    ];

    private const STAT_RANGES = [
        'goalkeeper' => ['strength' => [52, 85], 'stamina' => [62, 88], 'potential' => [52, 95]],
        'defender'   => ['strength' => [50, 82], 'stamina' => [65, 90], 'potential' => [50, 93]],
        'midfielder' => ['strength' => [55, 85], 'stamina' => [68, 92], 'potential' => [55, 96]],
        'forward'    => ['strength' => [58, 88], 'stamina' => [62, 88], 'potential' => [58, 97]],
    ];

    public function run(): void
    {
        $countryIndex = DB::table('countries')->pluck('id', 'code')
            ->map(fn($id) => (string) $id)->toArray();

        $defaultCountryId = $countryIndex['BRA'] ?? null;

        if (! $defaultCountryId) {
            $this->command->error('País BRA não encontrado. Rode os seeders de países primeiro.');
            return;
        }

        $leagueTeams = DB::table('league_teams')->select('id', 'name', 'league_id')->get();

        if ($leagueTeams->isEmpty()) {
            $this->command->error('Nenhum league_team encontrado. Gere as competições primeiro.');
            return;
        }

        $csvPlayers   = $this->loadCsvPlayers();
        $leagueIndexes = [];
        $playerRows   = [];
        $compPlayers  = [];
        $usedNames    = [];
        $now          = now()->toDateTimeString();

        $this->command->info("Gerando jogadores para {$leagueTeams->count()} times...");

        foreach ($leagueTeams as $lt) {
            $leagueIndexes[$lt->league_id] = ($leagueIndexes[$lt->league_id] ?? 0) + 1;
            $totalInLeague   = $leagueTeams->where('league_id', $lt->league_id)->count();
            $indexInLeague   = $leagueIndexes[$lt->league_id];
            $qualityFactor   = 1 - ($indexInLeague - 1) / max(1, $totalInLeague - 1);

            $realPlayers = $csvPlayers[$lt->name] ?? [];
            $generated   = 0;

            // ── Jogadores reais do CSV ──────────────────────────────────
            foreach ($realPlayers as $row) {
                $name      = trim($row['name']);
                $position  = $this->normalizePosition(trim($row['position']));
                $age       = max(16, min(45, (int) $row['age']));
                $strength  = max(1, min(99, (int) $row['strength']));
                $stamina   = max(1, min(99, (int) $row['stamina']));
                $potential = max(1, min(99, (int) $row['potential']));
                $countryId = $countryIndex[strtoupper(trim($row['country_code'] ?? 'BRA'))] ?? $defaultCountryId;

                $playerId = (string) Str::uuid();
                $usedNames[$name] = true;

                $wage     = $this->calcWage($strength);
                $mktValue = $this->calcMarketValue($strength, $age);

                $playerRows[] = [
                    'id'         => $playerId,
                    'name'       => $name,
                    'position'   => $position,
                    'country_id' => $countryId,
                    'age'        => $age,
                    'strength'   => $strength,
                    'stamina'    => $stamina,
                    'potential'  => $potential,
                    'photo'      => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $compPlayers[] = $this->makeCompPlayer($playerId, $lt->id, $countryId, $name, $position, $age, $strength, $stamina, $potential, $wage, $mktValue, $now);
                $generated++;
            }

            // ── Completar até SQUAD_SIZE com jogadores aleatórios ──────
            $remaining = max(0, self::SQUAD_SIZE - $generated);

            if ($remaining > 0) {
                $positions = $this->buildPositionQueue($remaining);

                foreach ($positions as $pos) {
                    $ranges = self::STAT_RANGES[$pos];

                    $strMin = (int) ($ranges['strength'][0] + ($ranges['strength'][1] - $ranges['strength'][0]) * $qualityFactor * 0.5);
                    $strMax = (int) ($ranges['strength'][0] + ($ranges['strength'][1] - $ranges['strength'][0]) * (0.5 + $qualityFactor * 0.5));
                    $staMin = (int) ($ranges['stamina'][0] + ($ranges['stamina'][1] - $ranges['stamina'][0]) * $qualityFactor * 0.4);
                    $staMax = (int) ($ranges['stamina'][0] + ($ranges['stamina'][1] - $ranges['stamina'][0]) * (0.4 + $qualityFactor * 0.6));
                    $potMin = (int) ($ranges['potential'][0] + ($ranges['potential'][1] - $ranges['potential'][0]) * $qualityFactor * 0.3);
                    $potMax = (int) ($ranges['potential'][0] + ($ranges['potential'][1] - $ranges['potential'][0]) * (0.3 + $qualityFactor * 0.7));

                    $strength  = rand(max(1, $strMin), max($strMin + 1, $strMax));
                    $stamina   = rand(max(1, $staMin), max($staMin + 1, $staMax));
                    $potential = rand(max(1, $potMin), max($potMin + 1, $potMax));
                    $age       = $this->randomAge($pos);
                    $name      = $this->generateName($usedNames);
                    $usedNames[$name] = true;

                    $playerId = (string) Str::uuid();
                    $wage     = $this->calcWage($strength);
                    $mktValue = $this->calcMarketValue($strength, $age);

                    $playerRows[] = [
                        'id'         => $playerId,
                        'name'       => $name,
                        'position'   => $pos,
                        'country_id' => $defaultCountryId,
                        'age'        => $age,
                        'strength'   => $strength,
                        'stamina'    => $stamina,
                        'potential'  => $potential,
                        'photo'      => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $compPlayers[] = $this->makeCompPlayer($playerId, $lt->id, $defaultCountryId, $name, $pos, $age, $strength, $stamina, $potential, $wage, $mktValue, $now);
                }
            }
        }

        $this->command->info('Inserindo ' . \count($playerRows) . ' jogadores na tabela players...');
        foreach (array_chunk($playerRows, 200) as $chunk) {
            DB::table('players')->insert($chunk);
        }

        $this->command->info('Inserindo ' . \count($compPlayers) . ' registros em competition_players...');
        foreach (array_chunk($compPlayers, 200) as $chunk) {
            DB::table('competition_players')->insert($chunk);
        }

        $this->command->info('Concluído! ' . \count($playerRows) . ' jogadores criados.');
    }

    // ── CSV ──────────────────────────────────────────────────────────────

    private function loadCsvPlayers(): array
    {
        $csvPath = storage_path('app/csv-templates/jogadores.csv');

        if (! file_exists($csvPath)) {
            return [];
        }

        $lines  = array_map('str_getcsv', file($csvPath));
        $header = array_map('strtolower', array_map('trim', array_shift($lines)));

        $players = [];

        foreach ($lines as $line) {
            if (\count($line) !== \count($header)) continue;

            $row      = array_combine($header, $line);
            $teamName = trim($row['team_name'] ?? '');

            if ($teamName !== '') {
                $players[$teamName][] = $row;
            }
        }

        return $players;
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeCompPlayer(string $playerId, string $leagueTeamId, string $countryId, string $name, string $position, int $age, int $strength, int $stamina, int $potential, int $wage, int $mktValue, string $now): array
    {
        return [
            'id'                      => (string) Str::uuid(),
            'league_team_id'          => $leagueTeamId,
            'player_id'               => $playerId,
            'country_id'              => $countryId,
            'name'                    => $name,
            'position'                => $position,
            'age'                     => $age,
            'strength'                => $strength,
            'stamina'                 => $stamina,
            'potential'               => $potential,
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

    private function buildPositionQueue(int $total): array
    {
        $ratio = [
            'goalkeeper' => 2,
            'defender'   => 6,
            'midfielder' => 6,
            'forward'    => 4,
        ];
        $base  = array_sum($ratio);
        $queue = [];

        foreach ($ratio as $pos => $qty) {
            $n = (int) round($total * $qty / $base);
            for ($i = 0; $i < $n; $i++) {
                $queue[] = $pos;
            }
        }

        // Ajusta para bater exatamente em $total
        while (\count($queue) < $total) {
            $queue[] = 'midfielder';
        }

        return \array_slice($queue, 0, $total);
    }

    private function normalizePosition(string $pos): string
    {
        return match (strtolower($pos)) {
            'gk', 'goalkeeper', 'goleiro'           => 'goalkeeper',
            'df', 'defender', 'zagueiro', 'lateral' => 'defender',
            'mf', 'midfielder', 'meio', 'meia'      => 'midfielder',
            'fw', 'forward', 'atacante'              => 'forward',
            default                                  => 'midfielder',
        };
    }

    private function generateName(array $used): string
    {
        $attempts = 0;
        do {
            if (rand(1, 10) <= 3) {
                $nick = self::NICKNAMES[array_rand(self::NICKNAMES)];
                $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                $name = "{$nick} {$last}";
            } else {
                $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
                $last  = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                $name  = "{$first} {$last}";
            }
            $attempts++;
        } while (isset($used[$name]) && $attempts < 50);

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
        $factor = max(0, ($strength - 50) / 50);
        return (int) (3_000 + $factor * $factor * 347_000);
    }

    private function calcMarketValue(int $strength, int $age): int
    {
        $base   = (int) (50_000 + (($strength - 50) / 40) ** 2 * 24_950_000);
        $ageMod = $age <= 30 ? 1.0 : max(0.3, 1.0 - ($age - 30) * 0.07);
        return (int) ($base * $ageMod);
    }
}
