<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
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

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
