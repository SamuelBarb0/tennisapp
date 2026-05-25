<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TournamentPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('is_admin', false);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Optional filter chips
        if ($request->filled('status')) {
            if ($request->status === 'blocked')   $query->where('is_blocked', true);
            if ($request->status === 'active')    $query->where('is_blocked', false);
        }

        $users = $query->withCount(['bracketPredictions', 'redemptions'])
            ->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        // Bracket predictions are the real ones — the legacy per-match
        // Prediction model is no longer used. We join each prediction to
        // its corresponding TennisMatch via (tournament_id, round, position)
        // because BracketPrediction doesn't carry a match_id FK.
        $bracketPredictions = $user->bracketPredictions()
            ->with(['tournament', 'predictedWinner'])
            ->latest()->take(20)->get();

        // Hydrate each prediction with the actual match record (player1,
        // player2, winner) so the admin table can show "Sinner vs Tabur".
        $matchKeys = $bracketPredictions->map(fn($p) => [
            'tournament_id'    => $p->tournament_id,
            'round'            => $p->round,
            'bracket_position' => $p->position,
        ])->all();

        $matches = collect();
        if (!empty($matchKeys)) {
            $q = \App\Models\TennisMatch::with(['player1', 'player2', 'winner']);
            foreach ($matchKeys as $k) {
                $q->orWhere(function ($w) use ($k) {
                    $w->where('tournament_id', $k['tournament_id'])
                      ->where('round', $k['round'])
                      ->where('bracket_position', $k['bracket_position']);
                });
            }
            $matches = $q->get()->keyBy(fn($m) => $m->tournament_id . '|' . $m->round . '|' . $m->bracket_position);
        }

        // Attach the match to each prediction object so the view can read it.
        foreach ($bracketPredictions as $p) {
            $key = $p->tournament_id . '|' . $p->round . '|' . $p->position;
            $p->setRelation('match', $matches->get($key));
        }

        $stats = [
            'total_predictions' => $user->bracketPredictions()->count(),
            'correct'           => $user->bracketPredictions()->where('is_correct', true)->count(),
        ];

        $redemptions = $user->redemptions()->with('prize')->latest()->get();
        $payments    = TournamentPayment::where('user_id', $user->id)
            ->with('tournament')
            ->latest()->take(20)->get();

        return view('admin.users.show', compact('user', 'bracketPredictions', 'stats', 'redemptions', 'payments'));
    }

    public function toggleBlock(User $user)
    {
        if ($user->is_admin) {
            return back()->with('error', 'No puedes bloquear a un administrador.');
        }
        $user->update(['is_blocked' => !$user->is_blocked]);
        $status = $user->is_blocked ? 'bloqueado' : 'desbloqueado';
        return back()->with('success', "Usuario {$status}.");
    }

    /** Toggle the admin flag. Cannot demote yourself. */
    public function toggleAdmin(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes cambiar tu propio rol de administrador.');
        }
        $user->update(['is_admin' => !$user->is_admin]);
        $msg = $user->is_admin ? 'ahora es administrador' : 'ya no es administrador';
        return back()->with('success', "Usuario {$msg}.");
    }

    /** Set points balance directly. Useful for prizes / corrections. */
    public function updatePoints(Request $request, User $user)
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $user->update(['points' => $data['points']]);
        return back()->with('success', "Puntos actualizados a {$data['points']}.");
    }

    /** Edit basic profile data from admin. */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'last_name'    => ['nullable', 'string', 'max:120'],
            'email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'        => ['nullable', 'string', 'max:32'],
            'city'         => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);
        $user->update($data);
        return back()->with('success', 'Usuario actualizado.');
    }

    /** Force a password reset (admin sets a new one). */
    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);
        $user->update(['password' => Hash::make($data['password'])]);
        return back()->with('success', 'Contraseña restablecida.');
    }

    /** Soft destroy — cascades to predictions, payments, etc. via FK rules. */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }
        if ($user->is_admin) {
            return back()->with('error', 'No puedes eliminar a un administrador.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Usuario eliminado.');
    }
}
