<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionTeam;
use App\Models\CompetitionTransferOffer;
use App\Models\League;
use App\Models\LeagueTeam;
use App\Services\MarketValueService;
use App\Services\TransferService;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly MarketValueService $marketValue,
    ) {}

    /**
     * Pesquisa de jogadores disponíveis para transferência.
     * Exibe jogadores de todas as ligas: com clube ou free agents.
     */
    public function index(Request $request, League $league)
    {
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        abort_unless($myLeagueTeam, 403, 'Você não tem um time nesta liga.');

        $myCompTeam = $this->transfers->primaryCompetitionTeamPublic($myLeagueTeam);

        $query = CompetitionPlayer::query()
            ->with(['leagueTeam.team', 'leagueTeam.league'])
            ->where('status', 'active')
            ->where('league_team_id', '!=', $myLeagueTeam->id);

        // Filtros
        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        if ($request->filled('age_max')) {
            $query->where('age', '<=', (int) $request->age_max);
        }

        if ($request->filled('age_min')) {
            $query->where('age', '>=', (int) $request->age_min);
        }

        if ($request->filled('value_max')) {
            $query->where('market_value', '<=', (int) $request->value_max * 1_000_000);
        }

        if ($request->filled('overall_min')) {
            $query->where('strength', '>=', (int) $request->overall_min);
        }

        if ($request->filled('division')) {
            $div = $request->division;
            $query->whereHas('leagueTeam', function ($q) use ($div) {
                $q->whereHas('team', fn($t) => $t->where('national_division', $div));
            });
        }

        $players = $query
            ->orderByDesc('market_value')
            ->paginate(30)
            ->withQueryString();

        return view('leagues.transfers.index', compact(
            'league', 'myLeagueTeam', 'myCompTeam', 'players', 'request',
        ));
    }

    /**
     * Formulário para fazer proposta por um jogador.
     */
    public function show(League $league, CompetitionPlayer $player)
    {
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        abort_unless($myLeagueTeam, 403);
        abort_if($player->league_team_id === $myLeagueTeam->id, 404, 'Este jogador já é seu.');

        $myCompTeam = $this->transfers->primaryCompetitionTeamPublic($myLeagueTeam);
        abort_unless($myCompTeam, 422, 'Seu time não está em nenhuma competição ativa.');

        $sellerLeagueTeam = LeagueTeam::with('team')->find($player->league_team_id);
        $suggestedWage    = $this->marketValue->estimatedMinWage($player);
        $canBuy           = $this->transfers->canBuy($myLeagueTeam);
        $minContractAlert = $this->transfers->isInMinimumContract($player);

        return view('leagues.transfers.show', compact(
            'league', 'myLeagueTeam', 'myCompTeam',
            'player', 'sellerLeagueTeam',
            'suggestedWage', 'canBuy', 'minContractAlert',
        ));
    }

    /**
     * Submete uma proposta de transferência.
     */
    public function store(Request $request, League $league)
    {
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        abort_unless($myLeagueTeam, 403);

        $validated = $request->validate([
            'player_id'      => ['required', 'uuid', 'exists:competition_players,id'],
            'offered_fee'    => ['required', 'integer', 'min:0'],
            'offered_wage'   => ['required', 'integer', 'min:1'],
            'contract_years' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $player     = CompetitionPlayer::findOrFail($validated['player_id']);
        $myCompTeam = $this->transfers->primaryCompetitionTeamPublic($myLeagueTeam);

        abort_unless($myCompTeam, 422, 'Seu time não está em nenhuma competição ativa.');

        $offer = $this->transfers->makeDirectOffer(
            buyerCompTeam:  $myCompTeam,
            player:         $player,
            offeredFee:     $validated['offered_fee'],
            offeredWage:    $validated['offered_wage'],
            contractYears:  $validated['contract_years'],
        );

        $label = $offer->statusLabel();

        return redirect()
            ->route('leagues.transfers.offers', $league)
            ->with('success', "Proposta enviada — Status: {$label}");
    }

    /**
     * Ofertas recebidas (contra-proposta pendente) e enviadas pelo técnico.
     */
    public function offers(League $league)
    {
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        abort_unless($myLeagueTeam, 403);

        // Ofertas ENVIADAS pelo meu time
        $myCompTeamIds = $myLeagueTeam->competitionTeams()->pluck('id');

        $sent = CompetitionTransferOffer::with(['player.leagueTeam.team', 'buyerTeam.leagueTeam'])
            ->whereIn('buyer_team_id', $myCompTeamIds)
            ->orderByDesc('created_at')
            ->get();

        // Contra-propostas dos meus jogadores (jogadores do meu time que receberam oferta)
        $myPlayerIds = CompetitionPlayer::where('league_team_id', $myLeagueTeam->id)
            ->pluck('id');

        $countered = CompetitionTransferOffer::with(['player', 'buyerTeam.leagueTeam.team'])
            ->whereIn('competition_player_id', $myPlayerIds)
            ->where('status', 'countered')
            ->orderByDesc('created_at')
            ->get();

        return view('leagues.transfers.offers', compact(
            'league', 'myLeagueTeam', 'sent', 'countered',
        ));
    }

    /**
     * Técnico humano responde à contra-proposta: aceita a perda ou oferece retenção.
     */
    public function respond(Request $request, League $league, CompetitionTransferOffer $offer)
    {
        $myLeagueTeam = LeagueTeam::where('league_id', $league->id)
            ->where('user_id', auth()->id())
            ->first();

        abort_unless($myLeagueTeam, 403);
        abort_unless($offer->player->league_team_id === $myLeagueTeam->id, 403);
        abort_unless($offer->status === 'countered', 409);

        $validated = $request->validate([
            'action'          => ['required', 'in:retain,release'],
            'retention_wage'  => ['required_if:action,retain', 'nullable', 'integer', 'min:1'],
        ]);

        if ($validated['action'] === 'retain') {
            $this->transfers->retentionOffer($offer, (int) $validated['retention_wage']);
            $msg = $offer->fresh()->status === 'rejected_player'
                ? 'Jogador aceitou a retenção e permanece no elenco.'
                : 'Jogador optou por aceitar a proposta externa e foi transferido.';
        } else {
            // Libera o jogador: aceita que ele vai
            $offer->update(['status' => 'accepted']);
            $this->transfers->executeTransferPublic($offer);
            $msg = 'Transferência concluída.';
        }

        return redirect()
            ->route('leagues.transfers.offers', $league)
            ->with('success', $msg);
    }
}
