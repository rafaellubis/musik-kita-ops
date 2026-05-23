<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Kelola status baca notifikasi user yang sedang login.
 * Hanya bisa mark as read notif milik sendiri.
 */
class NotificationController extends Controller
{
    /** Mark satu notifikasi sebagai sudah dibaca. */
    public function markRead(DatabaseNotification $notification): JsonResponse
    {
        // Pastikan notif ini milik user yang sedang login.
        // Cast ke string karena notifiable_id dari DB bisa berupa string ("5")
        // sementara auth()->id() mengembalikan integer (5) — strict !== akan selalu tidak sama.
        // Return 404 (bukan 403) agar tidak bocorkan bahwa ID notifikasi ini ada di sistem.
        if ((string) $notification->notifiable_id !== (string) auth()->id()) {
            abort(404);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /** Mark semua notifikasi user yang login sebagai sudah dibaca. */
    public function markAllRead(): JsonResponse
    {
        // Gunakan query builder (bukan koleksi) agar tidak load semua notif ke memori
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
