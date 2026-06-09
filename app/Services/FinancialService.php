<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTransaction;
use App\Models\League;
use App\Models\LeagueTeam;
use Illuminate\Support\Facades\DB;

class FinancialService
{
    private const TV_QUOTAS = [
        Competition::COMPETITION_TYPE_NATIONAL => [
            Competition::DIVISION_FIRST  => 10_000_000,
            Competition::DIVISION_SECOND =>  4_000_000,
        ],
        Competition::COMPETITION_TYPE_COPA => [
            Competition::DIVISION_FIRST  =>  5_000_000,
        ],
        Competition::COMPETITION_TYPE_STATE => [
            Competition::DIVISION_FIRST  =>  2_000_000,
            Competition::DIVISION_SECOND =>  1_000_000,
        ],
    ];

    /**
     * Paga cota de TV para todos os times de todas as competições da temporada atual.
     * Deve ser chamado ao gerar competições no início de cada temporada.
     */
    public function payTvQuotas(League $league): void
    {
        DB::transaction(function () use ($league) {
            $competitions = $league->competitions()
                ->where('season', $league->season)
                ->with('teams')
                ->get();

            foreach ($competitions as $competition) {
                $quota = $this->quotaFor($competition);
                if ($quota === 0) continue;

                foreach ($competition->teams as $competitionTeam) {
                    LeagueTeam::where('id', $competitionTeam->league_team_id)
                        ->increment('budget', $quota);

                    CompetitionTransaction::create([
                        'competition_team_id' => $competitionTeam->id,
                        'type'                => 'income',
                        'amount'              => $quota,
                        'description'         => 'Cota de TV — ' . $competition->name,
                        'round'               => 0,
                    ]);
                }
            }
        });
    }

    /**
     * Desconta o total de salários de jogadores ativos de cada league_team da liga.
     * Deve ser chamado a cada semana de calendário (advanceWeek).
     */
    public function deductWeeklySalaries(League $league): void
    {
        $leagueTeams = LeagueTeam::where('league_id', $league->id)->get();

        DB::transaction(function () use ($leagueTeams) {
            foreach ($leagueTeams as $leagueTeam) {
                $totalWage = CompetitionPlayer::where('league_team_id', $leagueTeam->id)
                    ->where('status', 'active')
                    ->sum('wage');

                if ($totalWage > 0) {
                    $leagueTeam->decrement('budget', $totalWage);
                }
            }
        });
    }

    /**
     * Calcula público e receita de bilheteria para uma partida finalizada.
     * Apenas o time da casa recebe receita. Chamado sem transação própria
     * para poder ser usado dentro de transações existentes.
     */
    public function processMatchRevenue(CompetitionMatch $match): void
    {
        $homeCompTeam = $match->homeTeam;
        if (! $homeCompTeam) return;

        $leagueTeam = $homeCompTeam->leagueTeam;
        if (! $leagueTeam) return;

        $capacity    = $leagueTeam->stadium_capacity;
        $satisfaction = $leagueTeam->satisfaction;
        $ticketPrice  = max(1, $leagueTeam->ticket_price);

        $compWeight = $this->competitionWeight($match->competition);

        // Ocupação base: satisfação × peso da competição
        $satFactor  = $satisfaction / 100;
        $pricePenalty = max(0, ($ticketPrice - 50) / 200) * (1 - $satFactor);
        $occupation   = $satFactor * $compWeight * (1 - $pricePenalty);
        $occupation   = max(0.05, min(1.0, $occupation));

        $attendance = (int) round($capacity * $occupation);
        $revenue    = $attendance * $ticketPrice;

        if ($revenue <= 0) return;

        LeagueTeam::where('id', $leagueTeam->id)->increment('budget', $revenue);

        CompetitionTransaction::create([
            'competition_team_id' => $homeCompTeam->id,
            'type'                => 'income',
            'amount'              => $revenue,
            'description'         => "Bilheteria — {$attendance} torcedores × R\$ {$ticketPrice}",
            'round'               => $match->round,
        ]);
    }

    private function competitionWeight(Competition $competition): float
    {
        return match ($competition->competition_type) {
            Competition::COMPETITION_TYPE_COPA    => 1.0,
            Competition::COMPETITION_TYPE_NATIONAL => $competition->division === Competition::DIVISION_FIRST ? 0.9 : 0.5,
            Competition::COMPETITION_TYPE_STATE    => $competition->division === Competition::DIVISION_FIRST ? 0.6 : 0.4,
            default => 0.5,
        };
    }

    private function quotaFor(Competition $competition): int
    {
        $type = $competition->competition_type;

        if ($type === Competition::COMPETITION_TYPE_COPA) {
            return self::TV_QUOTAS[$type][Competition::DIVISION_FIRST] ?? 0;
        }

        $division = $competition->division ?? Competition::DIVISION_FIRST;
        return self::TV_QUOTAS[$type][$division] ?? 0;
    }
}
