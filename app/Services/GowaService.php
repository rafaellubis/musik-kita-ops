<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GowaService
{
    private function request(array $configuration, int $timeout): PendingRequest
    {
        return Http::timeout($timeout)->connectTimeout(10)->withoutRedirecting()
            ->acceptJson()->withBasicAuth($configuration['username'], $configuration['password'])
            ->withHeaders(['X-Device-Id' => $configuration['device_id']]);
    }

    /** Respons provider mentah tidak boleh menjadi pesan error pengguna. */
    public function sendText(string $normalizedPhone, string $message, array $configuration): array
    {
        $result = ['provider' => 'gowa', 'ok' => false, 'message_ids' => [], 'status' => 0,
            'body' => [], 'error' => null, 'auth_invalid' => false];
        try {
            $response = $this->request($configuration, 30)->post(rtrim($configuration['base_url'], '/').'/send/message', [
                'phone' => $normalizedPhone.'@s.whatsapp.net', 'message' => $message,
            ]);
            $body = $response->json();
            $result['status'] = $response->status();
            $result['auth_invalid'] = in_array($response->status(), [401, 403], true);
            $id = is_array($body) ? ($body['results']['message_id'] ?? null) : null;
            if ($response->successful() && ($body['code'] ?? null) === 'SUCCESS' && is_string($id) && $id !== '') {
                $result['ok'] = true;
                $result['message_ids'] = [$id];
            } else {
                $result['error'] = $result['auth_invalid']
                    ? 'Autentikasi GOWA ditolak. Periksa kredensial di Pengaturan Scheduler & Reminder.'
                    : 'GOWA menolak pengiriman atau mengembalikan respons tidak valid (HTTP '.$response->status().').';
            }
        } catch (ConnectionException) {
            $result['error'] = 'GOWA tidak dapat dijangkau atau waktu koneksi habis. Periksa koneksi Tailscale dan status perangkat sebelum mengirim ulang.';
        }

        return $result;
    }

    public function checkConnection(array $configuration): array
    {
        $result = ['provider' => 'gowa', 'state' => 'invalid_response', 'message' => 'Respons status perangkat GOWA tidak valid.'];
        try {
            $url = rtrim($configuration['base_url'], '/').'/devices/'.rawurlencode($configuration['device_id']).'/status';
            $response = $this->request($configuration, 10)->get($url);
            if (in_array($response->status(), [401, 403], true)) {
                return array_replace($result, ['state' => 'auth_failed', 'message' => 'Autentikasi GOWA ditolak. Periksa username dan password.']);
            }
            if ($response->status() === 404) {
                return array_replace($result, ['state' => 'device_not_found', 'message' => 'Device GOWA tidak ditemukan. Periksa Device ID di dashboard GOWA.']);
            }
            $body = $response->json();
            $device = is_array($body) ? ($body['results'] ?? null) : null;
            if ($response->successful() && ($body['code'] ?? null) === 'SUCCESS' && is_array($device)
                && is_bool($device['is_connected'] ?? null) && is_bool($device['is_logged_in'] ?? null)) {
                $connected = $device['is_connected'] && $device['is_logged_in'];
                return array_replace($result, [
                    'state' => $connected ? 'connected' : 'disconnected',
                    'message' => $connected ? 'WhatsApp GOWA terhubung dan sudah login.' : 'API GOWA terjangkau, tetapi WhatsApp belum terhubung. Login/scan QR di dashboard GOWA.',
                ]);
            }
        } catch (ConnectionException) {
            return array_replace($result, ['state' => 'unreachable', 'message' => 'GOWA tidak dapat dijangkau atau waktu koneksi habis. Periksa jaringan Tailscale.']);
        }

        return $result;
    }
}
