<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pembaca/tulis kredensial integrasi WA dari tabel settings (encrypted).
 * Fallback ke config/services.php jika DB kosong.
 */
class IntegrationSettings
{
    private const ENCRYPTED_KEYS = [
        'integration_fonnte_token',
        'integration_gowa_password',
        'integration_wablas_token',
        'integration_wablas_secret_key',
    ];

    private static function getRaw(string $key, mixed $default = ''): mixed
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            return Setting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private static function decryptOrPlain(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    /** Ambil satu snapshot agar pilihan provider dan kredensial tidak tercampur. */
    public static function whatsappConfiguration(): array
    {
        $values = Setting::query()->whereIn('key', [
            'integration_whatsapp_provider', 'integration_fonnte_base_url',
            'integration_fonnte_token', 'integration_gowa_base_url',
            'integration_gowa_username', 'integration_gowa_password', 'integration_gowa_device_id',
        ])->pluck('value', 'key')->all();
        $provider = $values['integration_whatsapp_provider'] ?? 'fonnte';
        $fonnte = $provider === 'fonnte';

        return [
            'provider' => $provider,
            'base_url' => $fonnte
                ? ($values['integration_fonnte_base_url'] ?? '') ?: (string) config('services.fonnte.base_url', 'https://api.fonnte.com')
                : ($values['integration_gowa_base_url'] ?? ''),
            'token' => $fonnte ? (self::decryptOrPlain($values['integration_fonnte_token'] ?? '') ?: config('services.fonnte.token')) : null,
            'country_code' => (string) config('services.fonnte.country_code', '62'),
            'username' => $values['integration_gowa_username'] ?? null,
            'password' => self::decryptOrPlain($values['integration_gowa_password'] ?? ''),
            'device_id' => $values['integration_gowa_device_id'] ?? null,
        ];
    }

    /** Pembandingan rahasia harus berdasarkan nilai asli, bukan empat karakter mask. */
    public static function secretMatches(?string $storedValue, string $value): bool
    {
        return hash_equals(self::decryptOrPlain($storedValue) ?? '', $value);
    }

    public static function fonnteToken(): ?string
    {
        $stored = self::decryptOrPlain((string) self::getRaw('integration_fonnte_token', ''));

        return filled($stored) ? $stored : config('services.fonnte.token');
    }

    public static function fonnteBaseUrl(): string
    {
        $stored = (string) self::getRaw('integration_fonnte_base_url', '');

        return filled($stored)
            ? $stored
            : (string) config('services.fonnte.base_url', 'https://api.fonnte.com');
    }

    public static function wablasToken(): ?string
    {
        $stored = self::decryptOrPlain((string) self::getRaw('integration_wablas_token', ''));

        return filled($stored) ? $stored : config('services.wablas.token');
    }

    public static function wablasSecretKey(): ?string
    {
        $stored = self::decryptOrPlain((string) self::getRaw('integration_wablas_secret_key', ''));

        return filled($stored) ? $stored : config('services.wablas.secret_key');
    }

    public static function wablasBaseUrl(): string
    {
        $stored = (string) self::getRaw('integration_wablas_base_url', '');

        return filled($stored)
            ? $stored
            : (string) config('services.wablas.base_url', 'https://solo.wablas.com');
    }

    /**
     * Simpan nilai setting; field secret di-encrypt otomatis.
     */
    public static function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            Setting::set($key, '');

            return;
        }

        if (in_array($key, self::ENCRYPTED_KEYS, true)) {
            Setting::set($key, Crypt::encryptString($value));

            return;
        }

        Setting::set($key, $value);
    }

    /**
     * Mask secret untuk tampilan UI: ********abcd (ASCII agar terbaca di dark mode).
     */
    public static function maskSecret(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        $visible = Str::length($secret) > 4 ? Str::substr($secret, -4) : '';

        return '********' . $visible;
    }

    /** Token Fonnte tersimpan di DB (bukan fallback .env)? */
    public static function isFonnteTokenStoredInDb(): bool
    {
        return filled(self::decryptOrPlain((string) self::getRaw('integration_fonnte_token', '')));
    }

    /** Token Wablas tersimpan di DB? */
    public static function isWablasTokenStoredInDb(): bool
    {
        return filled(self::decryptOrPlain((string) self::getRaw('integration_wablas_token', '')));
    }

    /** Secret Wablas tersimpan di DB? */
    public static function isWablasSecretStoredInDb(): bool
    {
        return filled(self::decryptOrPlain((string) self::getRaw('integration_wablas_secret_key', '')));
    }

    /** Nilai mask yang dikirim ulang dari UI — jangan treat sebagai token baru. */
    public static function isMaskedPlaceholder(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return Str::startsWith($value, '********')
            || Str::startsWith($value, '••••');
    }

    /** @return list<string> */
    public static function encryptedKeys(): array
    {
        return self::ENCRYPTED_KEYS;
    }

    public static function isEncryptedKey(string $key): bool
    {
        return in_array($key, self::ENCRYPTED_KEYS, true);
    }

    /** Mask nilai untuk audit log (decrypt dulu jika terenkripsi). */
    public static function maskValueForAudit(string $key, ?string $storedValue): ?string
    {
        if ($storedValue === null || $storedValue === '') {
            return $storedValue;
        }

        if (! self::isEncryptedKey($key)) {
            return $storedValue;
        }

        return self::maskSecret(self::decryptOrPlain($storedValue) ?? '');
    }
}
