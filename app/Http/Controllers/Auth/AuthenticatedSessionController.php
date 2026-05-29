<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserLoginGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'turnstile_token' => ['required', 'string'],
        ]);

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        $turnstileSecret = config('services.turnstile.secret_key');

        if (!$turnstileSecret) {
            throw ValidationException::withMessages([
                'turnstile_token' => 'Security check is not configured.',
            ]);
        }

        $turnstileResponse = Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret' => $turnstileSecret,
                'response' => $validated['turnstile_token'],
                'remoteip' => $request->ip(),
            ],
        );

        if (!$turnstileResponse->ok() || !$turnstileResponse->json('success')) {
            throw ValidationException::withMessages([
                'turnstile_token' => 'Security check failed. Please try again.',
            ]);
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->onlyInput('email');
        }

        $loginError = UserLoginGuard::webLoginError(Auth::user());

        if ($loginError) {
            Auth::logout();

            return back()->withErrors([
                'email' => $loginError,
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
