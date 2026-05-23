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
        // Pastikan notif ini milik user yang sedang login
        if ($notification->notifiable_id !== auth()->id()) {
            abort(403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /** Mark semua notifikasi user yang login sebagai sudah dibaca. */
    public function markAllRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
