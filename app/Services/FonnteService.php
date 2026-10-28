<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FonnteService
{
    /** Nomor sudah dinormalisasi gateway; transport hanya menerjemahkan kontrak API. */
    public function sendText(string $normalizedPhone, string $message, array $configuration): array
    {
        $result = ['provider' => 'fonnte', 'ok' => false, 'message_ids' => [], 'status' => 0,
            'body' => [], 'error' => null, 'auth_invalid' => false];
        $target = str_starts_with($normalizedPhone, '62') ? '0'.substr($normalizedPhone, 2) : $normalizedPhone;
        try {
            $response = Http::timeout(30)->connectTimeout(10)->withoutRedirecting()
                ->withHeaders(['Authorization' => $configuration['token']])->asForm()
                ->post(rtrim($configuration['base_url'], '/').'/send', [
                    'target' => $target, 'message' => $message, 'countryCode' => $configuration['country_code'],
                ]);
            $body = $response->json();
            $body = is_array($body) ? $body : [];
            $result['status'] = $response->status();
            $reason = $body['reason'] ?? $body['detail'] ?? '';
            $reason = is_string($reason) ? strtolower($reason) : '';
            $result['auth_invalid'] = in_array($response->status(), [401, 403], true) || str_contains($reason, 'token invalid');
            if ($response->successful() && ($body['status'] ?? $body['Status'] ?? false) === true && ! $result['auth_invalid']) {
                $ids = $body['id'] ?? $body['ids'] ?? [];
                $ids = is_array($ids) ? $ids : [$ids];
                $result['ok'] = true;
                $result['message_ids'] = array_values(array_map('strval', array_filter($ids, fn ($id) => is_scalar($id) && (string) $id !== '')));
            } else {
                $result['error'] = match (true) {
                    $result['auth_invalid'] => 'Autentikasi Fonnte ditolak. Periksa token di Pengaturan Scheduler & Reminder.',
                    str_contains($reason, 'insufficient quota') => 'Kuota pesan Fonnte habis.',
                    str_contains($reason, 'target invalid') => 'Nomor WhatsApp tujuan tidak valid.',
                    str_contains($reason, 'device') => 'Device Fonnte belum terhubung. Scan QR di dashboard Fonnte.',
                    default => 'Fonnte menolak pengiriman atau mengembalikan respons tidak valid (HTTP '.$response->status().').',
                };
            }
        } catch (ConnectionException) {
            $result['error'] = 'Fonnte tidak dapat dijangkau atau waktu koneksi habis. Periksa status pesan sebelum mengirim ulang.';
        }

        return $result;
    }
}
