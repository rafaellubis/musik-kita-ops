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
        // Optimasi: static variable memastikan query DB hanya dijalankan SEKALI per request,
        // meskipun closure dipanggil berkali-kali (untuk setiap partial/component/sub-view).
        // Closure PHP mempertahankan static variable di seluruh pemanggilan dalam request yang sama.
        View::composer('*', function ($view) {
            static $fetched = false;
            static $notifs = null;
            static $count = 0;

            if (! $fetched) {
                $fetched = true;
                if (auth()->check()) {
                    // Ambil maks 10 notifikasi overdue yang belum dibaca untuk user saat ini
                    $notifs = auth()->user()
                        ->unreadNotifications()
                        ->where('type', MuridOverdueNotification::class)
                        ->latest()
                        ->take(10)
                        ->get();
                    $count = $notifs->count();
                }
            }

            // Hanya inject ke view jika sudah di-fetch DAN user login (notifs tidak null).
            // $fetched memastikan kita sudah melewati blok auth()->check() di atas,
            // $notifs !== null memastikan user authenticated (bukan guest).
            if ($fetched && $notifs !== null) {
                $view->with('overdueNotifs', $notifs);
                $view->with('overdueNotifCount', $count);
            }
        });
    }
}
