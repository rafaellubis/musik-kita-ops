<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = auth()->user();

        // Bersihkan url.intended jika tidak cocok dengan peran user
        $intended = $request->session()->get('url.intended');
        if ($intended) {
            if ($user->hasRole('Guru')) {
                if (!str_contains($intended, '/guru/') && !str_contains($intended, '/profile')) {
                    $request->session()->forget('url.intended');
                }
            } else {
                if (str_contains($intended, '/guru/')) {
                    $request->session()->forget('url.intended');
                }
            }
        }

        return redirect()->intended(route($user->getDashboardRoute(), absolute: false));
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
