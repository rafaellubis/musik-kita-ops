<?php

namespace App\Providers;

use App\Notifications\MuridOverdueNotification;
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
    }
}
