<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Integrasi Fonnte API — kirim pesan teks WhatsApp (pengingat jadwal ke ortu).
 *
 * @see https://docs.fonnte.com/api-send-message/
 */
class FonnteService
{
    /** Nomor studio untuk placeholder {studio_wa}. */
    public const STUDIO_WA_DISPLAY = '0816-92-05-92';

    public function isConfigured(): bool
    {
        return filled(config('services.fonnte.token'));
    }

    /**
     * Normalisasi nomor HP Indonesia ke format internasional tanpa +.
     * Contoh: 0812xxx → 62812xxx
     */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (Str::startsWith($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (Str::startsWith($digits, '8')) {
            $digits = '62' . $digits;
        }

        if (strlen($digits) < 10) {
            return null;
        }

        return $digits;
    }

    /** Nomor valid untuk dikirim? */
    public function isValidPhone(?string $phone): bool
    {
        return $this->normalizePhone($phone) !== null;
    }

    /**
     * Format nomor untuk parameter Fonnte `target` (format lokal 08xxx).
     */
    public function formatForTarget(?string $phone): ?string
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === null) {
            return null;
        }

        if (Str::startsWith($normalized, '62')) {
            return '0' . substr($normalized, 2);
        }

        return $normalized;
    }

    /**
     * Kirim pesan teks via POST /send.
     *
     * @return array{ok: bool, message_ids: array<int, string>, status: int, body: array, error: ?string, auth_invalid: bool}
     */
    public function sendText(string $phone, string $message): array
    {
        $target = $this->formatForTarget($phone);
        if ($target === null) {
            return $this->fail('Nomor telepon tidak valid.', 0, [], false);
        }

        return $this->post([
            'target'      => $target,
            'message'     => $message,
            'countryCode' => (string) config('services.fonnte.country_code', '62'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message_ids: array<int, string>, status: int, body: array, error: ?string, auth_invalid: bool}
     */
    private function post(array $payload): array
    {
        if (! $this->isConfigured()) {
            return $this->fail('Kredensial Fonnte belum dikonfigurasi.', 0, [], false);
        }

        $url = rtrim(config('services.fonnte.base_url'), '/') . '/send';
        $token = config('services.fonnte.token');

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => $token])
                ->asForm()
                ->post($url, $payload);

            $body = $response->json() ?? [];
            $status = $response->status();

            $apiStatus = $body['status'] ?? $body['Status'] ?? false;
            $reason = $body['reason'] ?? $body['detail'] ?? null;

            if (is_string($reason) && str_contains(strtolower($reason), 'token invalid')) {
                return $this->fail('Token Fonnte tidak valid.', $status, $body, true);
            }

            if (! $response->successful() || $apiStatus !== true) {
                $msg = $reason ?? $body['message'] ?? "HTTP {$status}";

                return $this->fail((string) $msg, $status, $body, false);
            }

            return [
                'ok'           => true,
                'message_ids'  => $this->extractMessageIds($body),
                'status'       => $status,
                'body'         => $body,
                'error'        => null,
                'auth_invalid' => false,
            ];
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 0, [], false);
        }
    }

    /** @param  array<string, mixed>  $body */
    private function extractMessageIds(array $body): array
    {
        $ids = $body['id'] ?? $body['ids'] ?? [];

        if (! is_array($ids)) {
            return filled($ids) ? [(string) $ids] : [];
        }

        return array_values(array_map('strval', $ids));
    }

    /** @return array{ok: bool, message_ids: array<int, string>, status: int, body: array, error: ?string, auth_invalid: bool} */
    private function fail(string $error, int $status, array $body, bool $authInvalid): array
    {
        return [
            'ok'           => false,
            'message_ids'  => [],
            'status'       => $status,
            'body'         => $body,
            'error'        => $error,
            'auth_invalid' => $authInvalid,
        ];
    }
}
