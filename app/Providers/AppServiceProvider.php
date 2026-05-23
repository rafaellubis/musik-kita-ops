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
        // View Composer: injeksi notifikasi overdue ke semua view yang di-render melalui layouts.app.
        // Dipasang ke wildcard '*' agar variabel tersedia di response level atas (dashboard, dll.)
        // yang kemudian di-wrap oleh layouts.app sebagai Blade component.
        // Tanpa ini, assertViewHas di test hanya melihat data view controller langsung,
        // bukan data view yang di-share ke nested component.
        View::composer('*', function ($view) {
            if (auth()->check()) {
                // Ambil maks 10 notifikasi overdue yang belum dibaca untuk user saat ini
                $notifs = auth()->user()
                    ->unreadNotifications()
                    ->where('type', MuridOverdueNotification::class)
                    ->latest()
                    ->take(10)
                    ->get();

                $view->with('overdueNotifs', $notifs);
                $view->with('overdueNotifCount', $notifs->count());
            }
        });
    }
}
