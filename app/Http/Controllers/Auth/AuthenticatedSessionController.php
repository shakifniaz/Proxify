<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'firebaseConfig' => app()->environment('local') ? [] : config('services.firebase'),
            'legacyLoginEmails' => app()->environment('local')
                ? User::query()
                    ->whereNull('firebase_uid')
                    ->pluck('email')
                    ->map(fn (string $email) => strtolower($email))
                    ->values()
                : [],
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request, FirebaseTokenVerifier $firebase): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string'],
            'id_token' => ['nullable', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! $request->filled('id_token')) {
            if (! app()->environment('local')) {
                throw ValidationException::withMessages(['email' => 'Firebase sign-in is required.']);
            }

            if (! $request->filled('password')) {
                throw ValidationException::withMessages(['password' => 'Enter your password.']);
            }

            $user = User::query()
                ->where('email', strtolower($request->string('email')->toString()))
                ->whereNull('firebase_uid')
                ->first();

            if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
                throw ValidationException::withMessages(['password' => 'These credentials do not match an existing local account.']);
            }

            Auth::login($user, $request->boolean('remember'));

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        }

        $payload = $firebase->verify($request->string('id_token')->toString());
        $email = strtolower((string) ($payload['email'] ?? ''));
        $uid = (string) ($payload['sub'] ?? '');

        if ($email !== strtolower($request->string('email')->toString())) {
            throw ValidationException::withMessages(['email' => 'Firebase account does not match this email address.']);
        }

        $user = User::query()
            ->where('firebase_uid', $uid)
            ->orWhere(function ($query) use ($email) {
                $query->where('email', $email)->whereNull('firebase_uid');
            })
            ->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'No Scholarly account is connected to this Firebase user.']);
        }

        if (! $user->firebase_uid) {
            $user->update(['firebase_uid' => $uid]);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
