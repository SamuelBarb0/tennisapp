<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BracketPrediction;
use App\Models\Tournament;
use App\Models\TournamentRoundPoints;
use App\Models\TournamentTiebreak;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = Tournament::latest()->paginate(15);
        return view('admin.tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return view('admin.tournaments.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            // Allow both the legacy short codes (ATP / WTA / GrandSlam) and the
            // full tier strings produced by the api-tennis sync ("ATP Masters 1000",
            // "WTA 1000", "ATP Grand Slam", "WTA Grand Slam"). If we only accept
            // the short codes, editing a synced tournament rewrites its tier.
            'type' => 'required|in:ATP,WTA,GrandSlam,ATP Masters 1000,WTA 1000,ATP Grand Slam,WTA Grand Slam',
            'location' => 'required|string',
            'city' => 'required|string',
            'country' => 'required|string',
            'surface' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'featured_on_home' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'matchstat_tournament_id' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
        ]);
        $data['is_premium'] = $request->boolean('is_premium');
        $data['is_active'] = $request->boolean('is_active');
        $data['featured_on_home'] = $request->boolean('featured_on_home');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('tournaments', 'public');
        }
        $tournament = Tournament::create($data);

        // Guardar puntos por ronda
        if ($request->has('round_points')) {
            foreach ($request->input('round_points', []) as $round => $points) {
                if ($points !== null && $points !== '') {
                    TournamentRoundPoints::create([
                        'tournament_id' => $tournament->id,
                        'round' => $round,
                        'points' => (int) $points,
                    ]);
                }
            }
        }

        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo creado exitosamente.');
    }

    public function edit(Tournament $tournament)
    {
        $tournament->load('roundPoints');
        return view('admin.tournaments.edit', compact('tournament'));
    }

    public function update(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            // See store(): accept both legacy short codes and full tier strings.
            'type' => 'required|in:ATP,WTA,GrandSlam,ATP Masters 1000,WTA 1000,ATP Grand Slam,WTA Grand Slam',
            'location' => 'required|string',
            'city' => 'required|string',
            'country' => 'required|string',
            'surface' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'featured_on_home' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'matchstat_tournament_id' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
        ]);
        $data['is_premium'] = $request->boolean('is_premium');
        $data['is_active'] = $request->boolean('is_active');
        $data['featured_on_home'] = $request->boolean('featured_on_home');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('tournaments', 'public');
        }
        $tournament->update($data);

        // Guardar puntos por ronda
        if ($request->has('round_points')) {
            foreach ($request->input('round_points', []) as $round => $points) {
                if ($points !== null && $points !== '') {
                    TournamentRoundPoints::updateOrCreate(
                        ['tournament_id' => $tournament->id, 'round' => $round],
                        ['points' => (int) $points]
                    );
                } else {
                    TournamentRoundPoints::where('tournament_id', $tournament->id)
                        ->where('round', $round)
                        ->delete();
                }
            }
        }

        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo actualizado.');
    }

    public function destroy(Tournament $tournament)
    {
        $tournament->delete();
        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo eliminado.');
    }

    /**
     * Manage seed/badge overrides for the starting round of a tournament.
     * Used to assign Q / WC / LL / PR / SE / seed numbers when
     * bracket.tennis didn't capture them.
     *
     * The override is applied by ApiTennisSyncService::applySeedOverrides()
     * on every sync, and the seed propagates structurally to later rounds
     * via propagateWinners() — so a single override here cascades through
     * the whole bracket.
     */
    public function badges(Tournament $tournament)
    {
        $startingRound = $tournament->matches()
            ->whereIn('round', ['R128', 'R64', 'R32', 'R16'])
            ->orderByRaw("FIELD(round, 'R128', 'R64', 'R32', 'R16')")
            ->value('round') ?? 'R128';

        $matches = $tournament->matches()
            ->where('round', $startingRound)
            ->with(['player1', 'player2'])
            ->orderBy('bracket_position')
            ->get();

        // Clean up orphan overrides: badges that point to players no longer
        // present anywhere in this tournament's bracket (withdrawal + Lucky
        // Loser substitution leaves the old player's override dangling).
        $playersInBracket = $tournament->matches()
            ->get(['player1_id', 'player2_id'])
            ->flatMap(fn($m) => [$m->player1_id, $m->player2_id])
            ->filter()
            ->unique()
            ->all();

        \App\Models\PlayerSeedOverride::where('tournament_id', $tournament->id)
            ->whereNotIn('player_id', $playersInBracket)
            ->delete();

        $overrides = \App\Models\PlayerSeedOverride::where('tournament_id', $tournament->id)
            ->get()
            ->keyBy('player_id');

        $countries = self::flagCountries();

        return view('admin.tournaments.badges', compact('tournament', 'matches', 'overrides', 'startingRound', 'countries'));
    }

    /**
     * ISO alpha-2 → display name list for the flag selector on the Marcas page.
     * Restricted to the codes flagcdn.com serves (matches Player::getIso2Attribute).
     * Sorted by display name for a friendly dropdown.
     */
    public static function flagCountries(): array
    {
        $list = [
            'ad'=>'Andorra','ae'=>'Emiratos Árabes Unidos','ao'=>'Angola','ar'=>'Argentina',
            'am'=>'Armenia','au'=>'Australia','at'=>'Austria','bb'=>'Barbados','by'=>'Bielorrusia',
            'be'=>'Bélgica','bj'=>'Benín','bm'=>'Bermudas','ba'=>'Bosnia y Herzegovina','bo'=>'Bolivia',
            'br'=>'Brasil','bg'=>'Bulgaria','bf'=>'Burkina Faso','kh'=>'Camboya','ca'=>'Canadá',
            'cl'=>'Chile','cn'=>'China','co'=>'Colombia','cr'=>'Costa Rica','hr'=>'Croacia',
            'cy'=>'Chipre','cz'=>'Chequia','dk'=>'Dinamarca','do'=>'República Dominicana','ec'=>'Ecuador',
            'eg'=>'Egipto','es'=>'España','ee'=>'Estonia','fj'=>'Fiyi','fi'=>'Finlandia','fr'=>'Francia',
            'gb'=>'Reino Unido','ge'=>'Georgia','de'=>'Alemania','gh'=>'Ghana','gr'=>'Grecia',
            'gt'=>'Guatemala','hn'=>'Honduras','hu'=>'Hungría','in'=>'India','ir'=>'Irán','ie'=>'Irlanda',
            'il'=>'Israel','it'=>'Italia','ci'=>'Costa de Marfil','jm'=>'Jamaica','jo'=>'Jordania',
            'jp'=>'Japón','kz'=>'Kazajistán','ke'=>'Kenia','kr'=>'Corea del Sur','kp'=>'Corea del Norte',
            'xk'=>'Kosovo','kw'=>'Kuwait','lv'=>'Letonia','lb'=>'Líbano','li'=>'Liechtenstein',
            'lt'=>'Lituania','lu'=>'Luxemburgo','my'=>'Malasia','md'=>'Moldavia','mx'=>'México',
            'me'=>'Montenegro','mc'=>'Mónaco','ma'=>'Marruecos','nl'=>'Países Bajos','np'=>'Nepal',
            'nz'=>'Nueva Zelanda','ni'=>'Nicaragua','no'=>'Noruega','pk'=>'Pakistán','py'=>'Paraguay',
            'pe'=>'Perú','ph'=>'Filipinas','pl'=>'Polonia','pt'=>'Portugal','pr'=>'Puerto Rico',
            'ro'=>'Rumania','ru'=>'Rusia','za'=>'Sudáfrica','sa'=>'Arabia Saudita','sn'=>'Senegal',
            'sg'=>'Singapur','si'=>'Eslovenia','rs'=>'Serbia','ch'=>'Suiza','sk'=>'Eslovaquia',
            'se'=>'Suecia','sy'=>'Siria','th'=>'Tailandia','tw'=>'Taipéi Chino','tn'=>'Túnez',
            'tr'=>'Turquía','ua'=>'Ucrania','uy'=>'Uruguay','us'=>'Estados Unidos','uz'=>'Uzbekistán',
            've'=>'Venezuela','vn'=>'Vietnam','zw'=>'Zimbabue',
        ];
        asort($list);
        return $list;
    }

    public function updateBadges(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'badges'           => 'nullable|array',
            'badges.*'         => 'nullable|string|max:8',
            'flags'            => 'nullable|array',
            'flags.*'          => 'nullable|string|max:2',
        ]);

        // ── Flag (country) overrides ───────────────────────────────────────
        // The flag a player shows is derived from their nationality_code (see
        // Player::getIso2Attribute). When api-tennis / bracket.tennis leave it
        // blank or wrong (e.g. Serena Williams showing the "unknown" flag),
        // the admin can pick the correct country here. We write it straight to
        // the player record so the right flag shows in EVERY tournament — a
        // player's nationality doesn't change per event. Stored as the ISO
        // alpha-2 code flagcdn expects, so getIso2Attribute's 2-letter fast
        // path returns it untouched.
        $flags = $data['flags'] ?? [];
        $validIso2 = array_keys(self::flagCountries());
        foreach ($flags as $playerId => $iso2) {
            $iso2 = strtolower(trim((string) $iso2));
            if ($iso2 === '' || !in_array($iso2, $validIso2, true)) {
                continue; // blank = leave the current value as-is
            }
            $player = \App\Models\Player::find((int) $playerId);
            if ($player && strtolower((string) $player->nationality_code) !== $iso2) {
                $player->update([
                    'nationality_code' => $iso2,
                    // Keep `country` readable if it was empty/Unknown.
                    'country' => (!$player->country || $player->country === 'Unknown')
                        ? strtoupper($iso2) : $player->country,
                ]);
            }
        }

        $badges = $data['badges'] ?? [];

        // Allowed badge values. Numeric seeds 1-64 + the symbolic ones.
        $allowed = ['', 'Q', 'WC', 'LL', 'PR', 'SE'];

        foreach ($badges as $playerId => $badge) {
            $playerId = (int) $playerId;
            $badge = trim((string) $badge);

            if ($badge === '') {
                \App\Models\PlayerSeedOverride::where('tournament_id', $tournament->id)
                    ->where('player_id', $playerId)
                    ->delete();
                continue;
            }

            // Accept numeric seeds (1-64) or symbolic codes.
            $isNumericSeed = ctype_digit($badge) && (int) $badge >= 1 && (int) $badge <= 64;
            $upper = strtoupper($badge);
            if (!$isNumericSeed && !in_array($upper, $allowed, true)) {
                continue;
            }

            \App\Models\PlayerSeedOverride::updateOrCreate(
                ['tournament_id' => $tournament->id, 'player_id' => $playerId],
                ['badge' => $isNumericSeed ? $badge : $upper, 'reason' => 'Admin manual override'],
            );
        }

        // Trigger an immediate sync so the badges propagate now.
        try {
            app(\App\Services\Tennis\ApiTennisSyncService::class)->syncTournamentLive($tournament);
        } catch (\Throwable $e) {
            // Sync failure shouldn't block the admin save — they can re-trigger from elsewhere.
        }

        return redirect()->route('admin.tournaments.badges', $tournament)
            ->with('success', 'Marcas actualizadas y aplicadas al bracket.');
    }

    /**
     * Show tiebreak resolution panel — only surfaces groups of users tied on points.
     */
    public function tiebreaks(Tournament $tournament)
    {
        $tiebreaksLocked = TournamentTiebreak::where('tournament_id', $tournament->id)->exists();

        // Aggregate per-user points for this tournament
        $rows = User::select(
                'users.id', 'users.name',
                DB::raw('COALESCE(SUM(bracket_predictions.points_earned), 0) as tournament_points'),
                DB::raw('SUM(CASE WHEN bracket_predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions')
            )
            ->join('bracket_predictions', 'users.id', '=', 'bracket_predictions.user_id')
            ->where('bracket_predictions.tournament_id', $tournament->id)
            ->groupBy('users.id', 'users.name')
            ->having('tournament_points', '>', 0)
            ->orderByDesc('tournament_points')
            ->get();

        // Load each user's final-score prediction (row F/position=1)
        $finalScores = BracketPrediction::where('tournament_id', $tournament->id)
            ->where('round', 'F')
            ->where('position', 1)
            ->whereNotNull('final_score_prediction')
            ->pluck('final_score_prediction', 'user_id');

        // Load the real final result (if the tournament has finished)
        $finalMatch = $tournament->matches()
            ->where('round', 'F')
            ->orderBy('bracket_position')
            ->first();

        // Existing manual ordering
        $manualRanks = TournamentTiebreak::where('tournament_id', $tournament->id)
            ->pluck('manual_rank', 'user_id');

        // Group by points (every group — even singletons — so admin sees full ranking)
        // and flag which groups actually have a tie that needs resolving.
        $tieGroups = $rows
            ->groupBy('tournament_points')
            ->map(function ($group) use ($finalScores, $manualRanks) {
                return $group->map(function ($u) use ($finalScores, $manualRanks) {
                    $u->final_score_prediction = $finalScores[$u->id] ?? null;
                    $u->manual_rank = $manualRanks[$u->id] ?? null;
                    return $u;
                })->sortBy(fn($u) => $u->manual_rank ?? PHP_INT_MAX)->values();
            });

        $hasAnyTie = $tieGroups->contains(fn($g) => $g->count() >= 2);

        return view('admin.tournaments.tiebreaks', compact(
            'tournament', 'tieGroups', 'finalMatch', 'tiebreaksLocked', 'hasAnyTie'
        ));
    }

    /**
     * Persist the admin's chosen tiebreak ordering for one or more tied groups.
     * Expects: order[user_id] = rank (1-based) — lower rank wins.
     */
    public function saveTiebreaks(Request $request, Tournament $tournament)
    {
        // Once a tiebreak order exists it is final — further edits are rejected.
        if (TournamentTiebreak::where('tournament_id', $tournament->id)->exists()) {
            return redirect()->route('admin.tournaments.tiebreaks', $tournament)
                ->with('error', 'El orden de desempate ya fue guardado y no se puede modificar.');
        }

        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|min:1',
        ]);

        foreach ($data['order'] as $userId => $rank) {
            TournamentTiebreak::create([
                'tournament_id' => $tournament->id,
                'user_id' => (int) $userId,
                'manual_rank' => (int) $rank,
            ]);
        }

        return redirect()->route('admin.tournaments.tiebreaks', $tournament)
            ->with('success', 'Orden de desempate guardado. Esta decisión es final.');
    }
}
