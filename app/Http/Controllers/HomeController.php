<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Player;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        // Limit to 3 active banners (carousel cap)
        $banners = Banner::active()->take(3)->get();

        // "Próximos torneos a predecir": the admin opts in via featured_on_home.
        // We exclude finished tournaments so completed events (e.g. Madrid
        // after it ends) stop appearing in the "next up" section.
        $featuredTournaments = Tournament::where('is_active', true)
            ->where('featured_on_home', true)
            ->where('status', '!=', 'finished')
            ->whereHas('matches')
            ->withCount(['matches as pending_matches_count' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->orderBy('start_date')
            ->get();

        if ($featuredTournaments->isEmpty()) {
            $featuredTournaments = Tournament::where('is_active', true)
                ->where('status', '!=', 'finished')
                ->where('start_date', '>=', '2026-01-01')
                ->whereHas('matches')
                ->withCount(['matches as pending_matches_count' => function ($q) {
                    $q->where('status', 'pending');
                }])
                ->having('pending_matches_count', '>', 0)
                ->orderBy('start_date')
                ->take(2)
                ->get();
        }

        // Group by family so ATP+WTA editions of the same event become one
        // card. We pick the "primary" tournament of each family (prefers ATP
        // when both exist, falls back to WTA / GS otherwise) as the row data.
        $featuredTournaments = $this->groupByFamily($featuredTournaments);

        // Backwards-compat for sections still expecting nextTournament
        $nextTournament = $featuredTournaments->first();

        // Próximos torneos (con partidos, excluyendo los destacados Y los terminados)
        $featuredIds = $featuredTournaments->pluck('id')->all();
        $upcomingTournaments = Tournament::where('is_active', true)
            ->where('status', '!=', 'finished')
            ->where('start_date', '>=', '2026-01-01')
            ->where('end_date', '>=', now()->subDays(7))
            ->whereHas('matches')
            ->withCount(['matches as pending_matches_count' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->when(!empty($featuredIds), fn($q) => $q->whereNotIn('id', $featuredIds))
            ->orderBy('start_date')
            ->take(12) // take more before dedup; we'll trim post-grouping
            ->get();
        $upcomingTournaments = $this->groupByFamily($upcomingTournaments)->take(6);

        // Torneo en vivo (con partidos live)
        $liveTournament = Tournament::where('is_active', true)
            ->whereHas('matches', function ($q) {
                $q->where('status', 'live');
            })
            ->with(['matches' => function ($q) {
                $q->where('status', 'live')->with(['player1', 'player2']);
            }])
            ->first();

        // Resultados recientes
        $recentResults = TennisMatch::with(['player1', 'player2', 'winner', 'tournament'])
            ->where('status', 'finished')
            ->whereNotNull('winner_id')
            ->orderBy('scheduled_at', 'desc')
            ->take(6)
            ->get();

        // Rankings per active tournament (instead of general ranking)
        $activeTournaments = Tournament::where('is_active', true)
            ->where('start_date', '>=', '2026-01-01')
            ->whereIn('status', ['in_progress', 'live'])
            ->whereHas('matches')
            ->orderBy('start_date')
            ->take(4)
            ->get();

        $tournamentRankings = [];
        foreach ($activeTournaments as $at) {
            $ranking = User::select(
                    'users.id', 'users.name',
                    DB::raw('SUM(bracket_predictions.points_earned) as tournament_points'),
                    DB::raw('SUM(CASE WHEN bracket_predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_count')
                )
                ->join('bracket_predictions', 'users.id', '=', 'bracket_predictions.user_id')
                ->where('bracket_predictions.tournament_id', $at->id)
                ->groupBy('users.id', 'users.name')
                ->having('tournament_points', '>', 0)
                ->orderByDesc('tournament_points')
                ->take(5)
                ->get();

            if ($ranking->isNotEmpty()) {
                $tournamentRankings[] = [
                    'tournament' => $at,
                    'ranking' => $ranking,
                ];
            }
        }

        // Stats dinámicos
        $stats = [
            'tournaments' => Tournament::where('is_active', true)->where('start_date', '>=', '2026-01-01')->whereHas('matches')->count(),
            'players' => Player::count(),
            'total_points' => User::where('is_admin', false)->sum('points'),
            'users' => User::where('is_admin', false)->count(),
        ];

        return view('home', compact(
            'banners', 'featuredTournaments', 'nextTournament', 'upcomingTournaments',
            'liveTournament', 'recentResults', 'tournamentRankings', 'stats'
        ));
    }

    /**
     * Collapse ATP+WTA siblings into a single representative tournament so the
     * home cards render one entry per event. Attaches the sibling list via
     * `family_tours` so the view can show chips like ['ATP', 'WTA'].
     *
     * Tournaments without family_slug are passed through unchanged.
     */
    private function groupByFamily(\Illuminate\Support\Collection $tournaments): \Illuminate\Support\Collection
    {
        // Tournaments with a family slug get grouped; others stay individual.
        [$withFamily, $solo] = $tournaments->partition(fn($t) => !empty($t->family_slug));

        $grouped = $withFamily->groupBy('family_slug')->map(function ($siblings) {
            // Prefer ATP as the "primary" row (it tends to start one day before
            // WTA in many events) but fall back to whatever exists.
            $primary = $siblings->sortBy(fn($t) =>
                str_starts_with($t->type, 'ATP') ? 0
                    : (str_starts_with($t->type, 'WTA') ? 1 : 2)
            )->first();

            // Attach the list of tour codes for chip rendering in the view.
            $primary->setAttribute('family_tours', $siblings
                ->map(fn($t) => $t->tour_code)
                ->unique()
                ->values()
                ->all());
            // Stash sibling IDs so links can pick the right URL on click.
            $primary->setAttribute('family_ids', $siblings->pluck('id')->all());
            return $primary;
        })->values();

        return $grouped->merge($solo)->sortBy('start_date')->values();
    }
}
