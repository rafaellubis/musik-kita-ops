<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Integrasi Wablas API — kirim teks & dokumen PDF ke WhatsApp ortu.
 */
class WablasService
{
    private const MAX_PDF_BYTES = 2 * 1024 * 1024; // 2 MB

    /** Nomor studio untuk placeholder {studio_wa}. */
    public const STUDIO_WA_DISPLAY = '0816-92-05-92';

    public function isConfigured(): bool
    {
        $token = config('services.wablas.token');
        $secret = config('services.wablas.secret_key');

        return filled($token) && filled($secret);
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
     * Kirim pesan teks via POST /api/send-message.
     *
     * @return array{ok: bool, message_id: ?string, status: int, body: array, error: ?string, auth_invalid: bool}
     */
    public function sendText(string $phone, string $message): array
    {
        return $this->post('/api/send-message', [
            'phone'   => $this->normalizePhone($phone),
            'message' => $message,
        ]);
    }

    /**
     * Kirim dokumen PDF (base64) via POST /api/send-document-from-local.
     *
     * @return array{ok: bool, message_id: ?string, status: int, body: array, error: ?string, auth_invalid: bool, skipped_size: bool}
     */
    public function sendDocumentFromLocal(
        string $phone,
        string $pdfBytes,
        string $filename,
        ?string $caption = null,
    ): array {
        if (strlen($pdfBytes) > self::MAX_PDF_BYTES) {
            return [
                'ok'            => false,
                'message_id'    => null,
                'status'        => 0,
                'body'          => [],
                'error'         => 'PDF melebihi 2 MB.',
                'auth_invalid'  => false,
                'skipped_size'  => true,
            ];
        }

        $payload = [
            'phone' => $this->normalizePhone($phone),
            'file'  => base64_encode($pdfBytes),
            'data'  => json_encode(['name' => $filename]),
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        $result = $this->post('/api/send-document-from-local', $payload);
        $result['skipped_size'] = false;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message_id: ?string, status: int, body: array, error: ?string, auth_invalid: bool}
     */
    private function post(string $path, array $payload): array
    {
        if (! $this->isConfigured()) {
            return $this->fail('Kredensial Wablas belum dikonfigurasi.', 0, [], false);
        }

        $normalized = $payload['phone'] ?? null;
        if (! $normalized) {
            return $this->fail('Nomor telepon tidak valid.', 0, [], false);
        }
        $payload['phone'] = $normalized;

        $url = rtrim(config('services.wablas.base_url'), '/') . $path;
        $auth = config('services.wablas.token') . '.' . config('services.wablas.secret_key');

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => $auth])
                ->post($url, $payload);

            $body = $response->json() ?? [];
            $status = $response->status();

            if (in_array($status, [401, 403], true)) {
                return $this->fail('Token Wablas tidak valid.', $status, $body, true);
            }

            if (! $response->successful()) {
                $msg = $body['message'] ?? $body['error'] ?? "HTTP {$status}";

                return $this->fail((string) $msg, $status, $body, false);
            }

            // Wablas biasanya return status: true di body
            $apiOk = ($body['status'] ?? true) === true || ($body['success'] ?? true) === true;

            if (! $apiOk) {
                $msg = $body['message'] ?? 'Wablas menolak permintaan.';

                return $this->fail((string) $msg, $status, $body, false);
            }

            return [
                'ok'           => true,
                'message_id'   => $this->extractMessageId($body),
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
    private function extractMessageId(array $body): ?string
    {
        if (isset($body['id'])) {
            return (string) $body['id'];
        }
        if (isset($body['message_id'])) {
            return (string) $body['message_id'];
        }
        if (isset($body['data']['id'])) {
            return (string) $body['data']['id'];
        }
        if (isset($body['data']['messages'][0]['id'])) {
            return (string) $body['data']['messages'][0]['id'];
        }
        if (isset($body['data'][0]['id'])) {
            return (string) $body['data'][0]['id'];
        }

        return null;
    }

    /** @return array{ok: bool, message_id: ?string, status: int, body: array, error: ?string, auth_invalid: bool} */
    private function fail(string $error, int $status, array $body, bool $authInvalid): array
    {
        return [
            'ok'           => false,
            'message_id'   => null,
            'status'       => $status,
            'body'         => $body,
            'error'        => $error,
            'auth_invalid' => $authInvalid,
        ];
    }
}
