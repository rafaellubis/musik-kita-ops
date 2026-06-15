<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        $user = $request->user();

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
}
