<?php

namespace App\Providers;

use App\Models\ClassSession;
use App\Notifications\MuridOverdueNotification;
use App\Services\ProtectedDatabaseGuard;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Blokir migrate:fresh / db:wipe / db:clear-students pada database operasional.
        if ($this->app->runningInConsole()) {
            ProtectedDatabaseGuard::apply();
        }

        // [SECURITY] Force HTTPS di production — mencegah credential sniffing via HTTP.
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // View Composer: injeksi notifikasi overdue ke semua view yang di-render.
        // Dipasang ke wildcard '*' karena layout menggunakan <x-app-layout> (class-based
        // Blade component), sehingga 'layouts.app' tidak pernah di-fire oleh composer biasa.
        //
        // Cache per-request via request()->attributes agar query DB hanya jalan sekali,
        // tapi tidak memakai static variable (yang bisa "terkunci" dari request sebelumnya
        // di proses PHP-FPM yang sama).
        View::composer('*', function ($view) {
            if (! auth()->check()) {
                return;
            }

            $request = request();

            // Jika belum di-fetch di request ini, jalankan query sekali lalu simpan
            if (! $request->attributes->has('overdueNotifData')) {
                $notifs = auth()->user()
                    ->unreadNotifications()
                    ->where('type', MuridOverdueNotification::class)
                    ->latest()
                    ->take(10)
                    ->get();

                $request->attributes->set('overdueNotifData', [
                    'notifs' => $notifs,
                    'count'  => $notifs->count(),
                ]);
            }

            $data = $request->attributes->get('overdueNotifData');
            $view->with('overdueNotifs', $data['notifs']);
            $view->with('overdueNotifCount', $data['count']);
        });

        View::composer(['layouts.guru', 'guru.dashboard'], function ($view) {
            $request = request();
            if (! $request->attributes->has('guruPendingCount')) {
                $teacherId = auth()->user()?->teacher?->id;
                $request->attributes->set('guruPendingCount', $teacherId
                    ? ClassSession::where('teacher_id', $teacherId)
                        ->where('status', ClassSession::STATUS_IZIN_PENDING)
                        ->count()
                    : 0);
            }

            $guruPendingCount = $request->attributes->get('guruPendingCount');
            $view->with('guruPendingCount', $guruPendingCount);
            $view->with('jumlahPending', $guruPendingCount);
        });

        // Kustomisasi pengalihan untuk pengguna terautentikasi (middleware guest / RedirectIfAuthenticated)
        \Illuminate\Auth\Middleware\RedirectIfAuthenticated::redirectUsing(function ($request) {
            $user = auth()->user();
            if ($user) {
                return route($user->getDashboardRoute());
            }
            return route('dashboard');
        });
    }
}
