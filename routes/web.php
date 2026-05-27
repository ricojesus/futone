<?php

use App\Http\Controllers\Admin\ChampionshipController;
use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\Admin\StateController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\LeagueJoinController;
use App\Http\Controllers\LeagueTeamController;
use App\Http\Controllers\LineupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserDashboardController;
use App\Models\LeagueTeam;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [UserDashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// ── Ligas (área do jogador) ──────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/leagues',         [LeagueController::class, 'index'])->name('leagues.index');
    Route::get('/leagues/create',  [LeagueController::class, 'create'])->name('leagues.create');
    Route::post('/leagues',        [LeagueController::class, 'store'])->name('leagues.store');

    // Static segments before {league} to avoid collision
    Route::get('/leagues/join',    [LeagueJoinController::class, 'create'])->name('leagues.join');
    Route::post('/leagues/join',   [LeagueJoinController::class, 'store'])->name('leagues.join.store');

    Route::get('/leagues/{league}',        [LeagueController::class, 'show'])->name('leagues.show');
    Route::post('/leagues/{league}/start',    [LeagueController::class, 'start'])->name('leagues.start');
    Route::post('/leagues/{league}/generate', [LeagueController::class, 'generate'])->name('leagues.generate');
    Route::post('/leagues/{league}/advance-week',   [LeagueController::class, 'advanceWeek'])->name('leagues.advance-week');
    Route::get('/leagues/{league}/season-summary',  [LeagueController::class, 'seasonSummary'])->name('leagues.season-summary');
    Route::post('/leagues/{league}/advance-season', [LeagueController::class, 'advanceSeason'])->name('leagues.advance-season');

    // Team enrollment
    Route::get('/leagues/{league}/join',   [LeagueTeamController::class, 'create'])->name('leagues.teams.create');
    Route::post('/leagues/{league}/teams', [LeagueTeamController::class, 'store'])->name('leagues.teams.store');

    // Lineup management — {leagueTeam} resolves to a CompetitionTeam record
    Route::get('/leagues/{league}/teams/{leagueTeam}/lineup',  [LineupController::class, 'edit'])->name('leagues.lineup.edit');
    Route::put('/leagues/{league}/teams/{leagueTeam}/lineup',  [LineupController::class, 'update'])->name('leagues.lineup.update');

    // Competitions (dentro de uma liga)
    Route::get( '/leagues/{league}/competitions/{competition}',                    [CompetitionController::class, 'show'])->name('competitions.show');
    Route::get( '/leagues/{league}/competitions/{competition}/round-status',       [CompetitionController::class, 'roundStatus'])->name('competitions.round-status');
    Route::get( '/leagues/{league}/competitions/{competition}/join',               [CompetitionController::class, 'join'])->name('competitions.join');
    Route::post('/leagues/{league}/competitions/{competition}/join',               [CompetitionController::class, 'joinStore'])->name('competitions.join.store');
    Route::post('/leagues/{league}/competitions/{competition}/advance-round',      [CompetitionController::class, 'advanceRound'])->name('competitions.advance-round');
    Route::get(  '/leagues/{league}/competitions/{competition}/matches/{match}',          [MatchController::class, 'show'])->name('matches.show');
    Route::get(  '/leagues/{league}/competitions/{competition}/matches/{match}/halftime', [MatchController::class, 'halftime'])->name('matches.halftime');
    Route::post( '/leagues/{league}/competitions/{competition}/matches/{match}/halftime', [MatchController::class, 'resumeSecondHalf'])->name('matches.halftime.resume');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Download de templates CSV
    Route::get('/csv-template/{file}', function (string $file) {
        $allowed = ['times', 'campeonatos', 'jogadores'];
        abort_unless(in_array($file, $allowed), 404);
        $path = storage_path("app/csv-templates/{$file}.csv");
        abort_unless(file_exists($path), 404);
        return response()->download($path, "{$file}.csv", ['Content-Type' => 'text/csv']);
    })->name('csv-template');

    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('/teams', [TeamController::class, 'index'])->name('teams');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::post('/teams/upload', [TeamController::class, 'upload'])->name('teams.upload');
    Route::post('/teams/upload-logos', [TeamController::class, 'uploadLogos'])->name('teams.upload-logos');
    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
    Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    Route::get('/championships', [ChampionshipController::class, 'index'])->name('championships');
    Route::get('/championships/create', [ChampionshipController::class, 'create'])->name('championships.create');
    Route::post('/championships', [ChampionshipController::class, 'store'])->name('championships.store');
    Route::post('/championships/upload', [ChampionshipController::class, 'upload'])->name('championships.upload');
    Route::get('/championships/{championship}/edit', [ChampionshipController::class, 'edit'])->name('championships.edit');
    Route::patch('/championships/{championship}', [ChampionshipController::class, 'update'])->name('championships.update');
    Route::delete('/championships/{championship}', [ChampionshipController::class, 'destroy'])->name('championships.destroy');

    Route::get('/states', [StateController::class, 'index'])->name('states');
    Route::get('/states/create', [StateController::class, 'create'])->name('states.create');
    Route::post('/states', [StateController::class, 'store'])->name('states.store');
    Route::post('/states/upload', [StateController::class, 'upload'])->name('states.upload');
    Route::get('/states/{state}/edit', [StateController::class, 'edit'])->name('states.edit');
    Route::patch('/states/{state}', [StateController::class, 'update'])->name('states.update');
    Route::delete('/states/{state}', [StateController::class, 'destroy'])->name('states.destroy');

    Route::get('/countries', [CountryController::class, 'index'])->name('countries');
    Route::get('/countries/create', [CountryController::class, 'create'])->name('countries.create');
    Route::post('/countries', [CountryController::class, 'store'])->name('countries.store');
    Route::post('/countries/upload', [CountryController::class, 'upload'])->name('countries.upload');
    Route::get('/countries/{country}/edit', [CountryController::class, 'edit'])->name('countries.edit');
    Route::patch('/countries/{country}', [CountryController::class, 'update'])->name('countries.update');
    Route::delete('/countries/{country}', [CountryController::class, 'destroy'])->name('countries.destroy');

    Route::get('/players', [PlayerController::class, 'index'])->name('players');
    Route::get('/players/create', [PlayerController::class, 'create'])->name('players.create');
    Route::post('/players', [PlayerController::class, 'store'])->name('players.store');
    Route::post('/players/upload', [PlayerController::class, 'upload'])->name('players.upload');

    Route::get('/coaches', [CoachController::class, 'index'])->name('coaches');
    Route::get('/coaches/create', [CoachController::class, 'create'])->name('coaches.create');
    Route::post('/coaches', [CoachController::class, 'store'])->name('coaches.store');
    Route::get('/coaches/{coach}/edit', [CoachController::class, 'edit'])->name('coaches.edit');
    Route::patch('/coaches/{coach}', [CoachController::class, 'update'])->name('coaches.update');
    Route::delete('/coaches/{coach}', [CoachController::class, 'destroy'])->name('coaches.destroy');
});

require __DIR__.'/auth.php';
