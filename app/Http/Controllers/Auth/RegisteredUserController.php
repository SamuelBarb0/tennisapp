<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Support\Countries;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'countries' => Countries::options(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'last_name'    => ['required', 'string', 'max:120'],
            'email'        => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone'        => ['required', 'string', 'max:32'],
            'city'         => ['required', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'size:2', 'in:'.implode(',', array_keys(Countries::ALL))],
            // Must be 18+ years old. The before_or_equal date is computed against today.
            'birth_date'   => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->format('Y-m-d')],
            'password'     => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'birth_date.before_or_equal' => 'Debes ser mayor de 18 años para registrarte.',
            'country_code.in'            => 'Selecciona un país válido.',
        ]);

        $user = User::create([
            'name'         => trim($request->name),
            'last_name'    => trim($request->last_name),
            'email'        => $request->email,
            'phone'        => $request->phone,
            'city'         => trim($request->city),
            'country_code' => strtoupper($request->country_code),
            'birth_date'   => $request->birth_date,
            'password'     => Hash::make($request->password),
        ]);

        event(new Registered($user));

        // Welcome email — best-effort, don't break registration if mail fails.
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Throwable $e) {
            Log::warning('Welcome mail failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }

        Auth::login($user);

        return redirect(route('home', absolute: false));
    }
}
