<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * UserUsernameService — generate & validasi username unik.
 *
 * Username dipakai untuk login alternatif selain email.
 * Format: lowercase, huruf/angka/titik/garis-bawah, 3–30 karakter.
 * Contoh: "Thomas" → "thomas", "T. HADI" → "t.hadi"
 */
class UserUsernameService
{
    /**
     * Buat slug dasar dari nama (sama seperti guru:create-accounts).
     */
    public static function slugFromName(string $name): string
    {
        $slug = Str::lower(str_replace(' ', '', $name));
        $slug = preg_replace('/[^a-z0-9._-]/', '', $slug) ?? '';

        return $slug !== '' ? $slug : 'user';
    }

    /**
     * Generate username unik. Jika $preferred sudah dipakai, tambahkan angka di belakang.
     */
    public static function generateUnique(?string $preferred, string $fallbackName, ?int $ignoreUserId = null): string
    {
        $base = self::slugFromName($preferred ?: $fallbackName);

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, '0');
        }

        $base = substr($base, 0, 30);

        $username = $base;
        $suffix   = 1;

        while (self::exists($username, $ignoreUserId)) {
            $suffixStr = (string) $suffix;
            $username  = substr($base, 0, 30 - strlen($suffixStr)) . $suffixStr;
            $suffix++;
        }

        return $username;
    }

    /**
     * Cek apakah username sudah dipakai user lain.
     */
    public static function exists(string $username, ?int $ignoreUserId = null): bool
    {
        $query = User::where('username', $username);

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }
}
