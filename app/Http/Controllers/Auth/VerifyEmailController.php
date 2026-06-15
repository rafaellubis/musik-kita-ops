<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
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

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route($user->getDashboardRoute(), absolute: false).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route($user->getDashboardRoute(), absolute: false).'?verified=1');
    }
}
