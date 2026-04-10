<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\PrizeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\BracketPredictionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TournamentController as AdminTournamentController;
use App\Http\Controllers\Admin\PlayerController as AdminPlayerController;
use App\Http\Controllers\Admin\MatchController as AdminMatchController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\PrizeController as AdminPrizeController;
use App\Http\Controllers\Admin\RedemptionController as AdminRedemptionController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\ApiSyncController;
use App\Http\Controllers\Admin\SimulationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
Route::get('/tournaments/{tournament:slug}', [TournamentController::class, 'show'])->name('tournaments.show');
Route::get('/rankings', [RankingController::class, 'index'])->name('rankings.index');
Route::get('/prizes', [PrizeController::class, 'index'])->name('prizes.index');
Route::get('/rules', [RulesController::class, 'index'])->name('rules');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/tournaments/{tournament:slug}/predict', [TournamentController::class, 'predict'])->name('tournaments.predict');
    Route::post('/predictions', [PredictionController::class, 'store'])->name('predictions.store');
    Route::post('/predictions/debug-resolve', [PredictionController::class, 'debugResolve'])->name('predictions.debug-resolve');
    Route::post('/bracket-predictions/{tournament}', [BracketPredictionController::class, 'store'])->name('bracket-predictions.store');
    Route::get('/bracket-predictions/{tournament}', [BracketPredictionController::class, 'show'])->name('bracket-predictions.show');
    Route::post('/prizes/{prize}/redeem', [PrizeController::class, 'redeem'])->name('prizes.redeem');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('tournaments', AdminTournamentController::class)->except('show');
    Route::resource('players', AdminPlayerController::class)->except('show');
    Route::resource('matches', AdminMatchController::class)->except('show');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('users/{user}/toggle-block', [AdminUserController::class, 'toggleBlock'])->name('users.toggle-block');
    Route::resource('prizes', AdminPrizeController::class)->except('show');
    Route::get('redemptions', [AdminRedemptionController::class, 'index'])->name('redemptions.index');
    Route::patch('redemptions/{redemption}', [AdminRedemptionController::class, 'updateStatus'])->name('redemptions.update');
    Route::resource('banners', AdminBannerController::class)->except('show');
    Route::patch('banners/{banner}/toggle', [AdminBannerController::class, 'toggle'])->name('banners.toggle');
    Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [AdminSettingController::class, 'update'])->name('settings.update');

    // API Sync
    Route::get('api-sync', [ApiSyncController::class, 'index'])->name('api-sync.index');
    Route::post('api-sync/tournaments', [ApiSyncController::class, 'syncTournaments'])->name('api-sync.tournaments');
    Route::post('api-sync/players', [ApiSyncController::class, 'syncPlayers'])->name('api-sync.players');
    Route::post('api-sync/fixtures', [ApiSyncController::class, 'syncFixtures'])->name('api-sync.fixtures');
    Route::post('api-sync/livescores', [ApiSyncController::class, 'syncLivescores'])->name('api-sync.livescores');
    Route::post('api-sync/all', [ApiSyncController::class, 'syncAll'])->name('api-sync.all');

    // Simulation (test tournaments)
    Route::post('simulate/next-round', [SimulationController::class, 'simulateNextRound'])->name('simulate.next-round');
    Route::post('simulate/all', [SimulationController::class, 'simulateAll'])->name('simulate.all');
    Route::post('simulate/reset', [SimulationController::class, 'reset'])->name('simulate.reset');
});

require __DIR__.'/auth.php';
