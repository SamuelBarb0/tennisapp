<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
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
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
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

// Mercado Pago — webhook is public (CSRF is excluded in bootstrap/app.php)
Route::post('/payments/mp/webhook', [PaymentController::class, 'webhook'])->name('payments.mp.webhook');
Route::get('/payments/mp/return', [PaymentController::class, 'returnFromMp'])->name('payments.mp.return');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/payments/tournaments/{tournament}/checkout', [PaymentController::class, 'checkout'])
        ->name('payments.tournaments.checkout');

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
    Route::get('tournaments/{tournament}/tiebreaks', [AdminTournamentController::class, 'tiebreaks'])->name('tournaments.tiebreaks');
    Route::post('tournaments/{tournament}/tiebreaks', [AdminTournamentController::class, 'saveTiebreaks'])->name('tournaments.tiebreaks.save');
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
    Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [AdminSettingController::class, 'update'])->name('settings.update');

    // API Sync (Matchstat / Tennis API)
    Route::get('api-sync', [ApiSyncController::class, 'index'])->name('api-sync.index');
    Route::post('api-sync/rankings', [ApiSyncController::class, 'syncRankings'])->name('api-sync.rankings');
    Route::post('api-sync/live', [ApiSyncController::class, 'syncLive'])->name('api-sync.live');

    // Simulation (test tournaments)
    Route::post('simulate/next-round', [SimulationController::class, 'simulateNextRound'])->name('simulate.next-round');
    Route::post('simulate/all', [SimulationController::class, 'simulateAll'])->name('simulate.all');
    Route::post('simulate/reset', [SimulationController::class, 'reset'])->name('simulate.reset');
});

require __DIR__.'/auth.php';

// ─────────────────────────────────────────────────────────────────────────────
// Mail previews — only enabled in local/dev so production users can't open them.
// Visit /preview/mail/welcome, /preview/mail/opening, etc. to inspect each
// template with seed data.
// ─────────────────────────────────────────────────────────────────────────────
if (app()->environment('local')) {
    Route::get('/preview/mail/{kind}', function (string $kind) {
        $user = \App\Models\User::first() ?: new \App\Models\User([
            'name' => 'Carlos', 'last_name' => 'Rodríguez',
            'email' => 'preview@example.com',
        ]);
        $tournament = \App\Models\Tournament::whereNotNull('api_tournament_key')->first();

        return match ($kind) {
            'welcome'   => new \App\Mail\WelcomeMail($user),
            'opening'   => new \App\Mail\TournamentOpeningMail($user, $tournament),
            'countdown' => new \App\Mail\TournamentCountdownMail($user, $tournament, 6, now()->addHours(6)),
            'predicted' => new \App\Mail\PredictionConfirmedMail($user, $tournament, 'Jannik Sinner'),
            'closed'    => new \App\Mail\TournamentClosedMail($user, $tournament, 3, 127, 84, 12, 15, '🥉 3er lugar'),
            default     => abort(404, 'Mail kind not found. Try: welcome, opening, countdown, predicted, closed'),
        };
    })->name('preview.mail');
}
