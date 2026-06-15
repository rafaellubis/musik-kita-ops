<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
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

        return view('auth.verify-email');
    }
}
