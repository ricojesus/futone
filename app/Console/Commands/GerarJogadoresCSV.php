<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Popula jogadores.csv com 20 jogadores por time listado em times.csv.
 * Times já presentes no CSV são preservados (não duplicados).
 * Stats de cada jogador são calibrados pelo `overall` do time.
 */
class GerarJogadoresCSV extends Command
{
    protected $signature   = 'jogadores:gerar {--force : Sobrescreve jogadores já existentes no CSV}';
    protected $description = 'Gera jogadores para todos os times do times.csv e salva em jogadores.csv';

    private const SQUAD = [
        'goalkeeper' => 2,
        'defender'   => 6,
        'midfielder' => 7,
        'forward'    => 5,
    ];

    private const FIRST_NAMES = [
        'Gabriel', 'Lucas', 'Matheus', 'João', 'Pedro', 'Rafael', 'Felipe',
        'Bruno', 'Rodrigo', 'Thiago', 'Diego', 'Marcelo', 'Leandro', 'Renato',
        'Anderson', 'Guilherme', 'Vitor', 'Eduardo', 'Carlos', 'Paulo',
        'Henrique', 'Alessandro', 'Willian', 'Douglas', 'Alexandre',
        'Danilo', 'Fabrício', 'Everton', 'Wellington', 'Caio',
        'Vinícius', 'Endrick', 'Raphael', 'Richarlison', 'Fred',
        'Casemiro', 'Firmino', 'Robson', 'Robinho', 'Adriano',
        'Ronaldinho', 'Rivaldo', 'Romário', 'Bebeto', 'Júnior',
        'Cafu', 'Roberto', 'Dunga', 'Zé', 'Edinho',
        'Alan', 'Andrey', 'Kayke', 'Léo', 'Renan',
        'Igor', 'Gustavo', 'Neto', 'Alex', 'Sandro',
        'Welington', 'Kauan', 'Breno', 'Nathan', 'Arthur',
        'Luan', 'Isaque', 'Jhon', 'Cristian', 'Michel',
        'Erick', 'Yago', 'Brayan', 'Kleber', 'Nilton',
        'Marlon', 'Tiago', 'Dener', 'Cleyton', 'Evandro',
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
        'Marques', 'Figueiredo', 'Miranda', 'Brito', 'Monteiro',
        'Braga', 'Viana', 'Xavier', 'Assis', 'Dantas',
        'Pacheco', 'Bezerra', 'Leite', 'Henrique', 'Nogueira',
        'Fernandes', 'Lacerda', 'Guimarães', 'Pires', 'Queiroz',
        'Gonçalves', 'Medeiros', 'Mota', 'Rezende', 'Lustosa',
        'Menezes', 'Amorim', 'Pinheiro', 'Siqueira', 'Luz',
    ];

    public function handle(): int
    {
        $timesPath     = storage_path('app/csv-templates/times.csv');
        $jogadoresPath = storage_path('app/csv-templates/jogadores.csv');

        if (! file_exists($timesPath)) {
            $this->error("Arquivo não encontrado: {$timesPath}");
            return 1;
        }

        // ── Carrega times do CSV ─────────────────────────────────────────
        $timesLines  = array_map('str_getcsv', file($timesPath));
        $timesHeader = array_map('strtolower', array_map('trim', array_shift($timesLines)));
        $times       = [];

        foreach ($timesLines as $line) {
            if (\count($line) !== \count($timesHeader)) continue;
            $row  = array_combine($timesHeader, $line);
            $name = trim($row['name'] ?? '');
            if ($name !== '') {
                $times[$name] = $row;
            }
        }

        $this->info(\count($times) . ' times encontrados em times.csv');

        // ── Carrega jogadores já existentes no CSV ───────────────────────
        $existingTeams = [];

        if (file_exists($jogadoresPath) && ! $this->option('force')) {
            $jogLines  = array_map('str_getcsv', file($jogadoresPath));
            $jogHeader = array_map('strtolower', array_map('trim', array_shift($jogLines)));

            foreach ($jogLines as $line) {
                if (\count($line) !== \count($jogHeader)) continue;
                $row      = array_combine($jogHeader, $line);
                $teamName = trim($row['team_name'] ?? '');
                if ($teamName !== '') {
                    $existingTeams[$teamName] = true;
                }
            }

            $this->info(\count($existingTeams) . ' times já têm jogadores no CSV (serão preservados)');
        }

        // ── Gera jogadores para os times que faltam ──────────────────────
        $newTeams = array_filter($times, fn($name) => ! isset($existingTeams[$name]), ARRAY_FILTER_USE_KEY);

        if (empty($newTeams)) {
            $this->info('Todos os times já têm jogadores. Use --force para regenerar.');
            return 0;
        }

        $this->info(\count($newTeams) . ' times precisam de jogadores. Gerando...');

        $usedNames = [];
        $rows      = [];

        foreach ($newTeams as $teamName => $teamData) {
            $overall = (int) ($teamData['overall'] ?? 60);
            $players = $this->generateSquad($teamName, $overall, $usedNames);
            foreach ($players as $p) {
                $rows[] = $p;
            }
        }

        // ── Salva no CSV ─────────────────────────────────────────────────
        $fp = fopen($jogadoresPath, file_exists($jogadoresPath) && ! $this->option('force') ? 'a' : 'w');

        if (! $fp) {
            $this->error("Não foi possível abrir {$jogadoresPath} para escrita.");
            return 1;
        }

        // Cabeçalho apenas se arquivo novo ou --force
        if (! file_exists($jogadoresPath) || $this->option('force')) {
            fputcsv($fp, ['name', 'team_name', 'position', 'country_code', 'age', 'strength', 'stamina', 'potential']);
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);

        $total = \count($rows);
        $this->info("✓ {$total} jogadores gerados para " . \count($newTeams) . " times.");
        $this->info("Arquivo salvo em: {$jogadoresPath}");

        return 0;
    }

    // ── Geração do elenco ─────────────────────────────────────────────────

    private function generateSquad(string $teamName, int $overall, array &$usedNames): array
    {
        $players = [];

        foreach (self::SQUAD as $position => $qty) {
            for ($i = 0; $i < $qty; $i++) {
                $age      = $this->randomAge($position);
                $strength = $this->randomStat($overall, $age, $position);
                $stamina  = $this->randomStamina($strength, $position);
                $potential = $this->randomPotential($strength, $age);
                $name     = $this->generateName($usedNames);
                $usedNames[$name] = true;

                $players[] = [
                    $name,
                    $teamName,
                    $position,
                    'BRA',
                    $age,
                    $strength,
                    $stamina,
                    $potential,
                ];
            }
        }

        return $players;
    }

    private function randomStat(int $overall, int $age, string $position): int
    {
        $spread = match ($position) {
            'goalkeeper' => 8,
            'defender'   => 9,
            'midfielder' => 9,
            'forward'    => 10,
        };

        $base = $overall + rand(-$spread, $spread);

        // Penalidade por idade
        if ($age >= 34) $base -= rand(2, 5);
        if ($age <= 20) $base -= rand(3, 8);

        return max(40, min(95, $base));
    }

    private function randomStamina(int $strength, string $position): int
    {
        $bonus = match ($position) {
            'midfielder' => rand(2, 8),
            'defender'   => rand(0, 6),
            'forward'    => rand(-3, 5),
            'goalkeeper' => rand(-5, 3),
        };
        return max(40, min(99, $strength + $bonus + rand(-4, 4)));
    }

    private function randomPotential(int $strength, int $age): int
    {
        if ($age <= 20)      $bonus = rand(8, 18);
        elseif ($age <= 24)  $bonus = rand(3, 12);
        elseif ($age <= 28)  $bonus = rand(0, 6);
        elseif ($age <= 32)  $bonus = rand(-3, 3);
        else                 $bonus = rand(-8, 0);

        return max($strength, min(99, $strength + $bonus));
    }

    private function randomAge(string $position): int
    {
        return match ($position) {
            'goalkeeper' => rand(18, 37),
            'defender'   => rand(17, 35),
            'midfielder' => rand(17, 34),
            'forward'    => rand(17, 33),
        };
    }

    private function generateName(array $used): string
    {
        $attempts = 0;
        do {
            $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $last  = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
            $name  = "{$first} {$last}";
            $attempts++;
        } while (isset($used[$name]) && $attempts < 100);

        if (isset($used[$name])) {
            $name .= ' ' . rand(2, 99);
        }

        return $name;
    }
}
