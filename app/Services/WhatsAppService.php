<?php

namespace App\Services;

class WhatsAppService
{
    public const STUDIO_WA_DISPLAY = '0816-92-05-92';

    public function __construct(
        private readonly FonnteService $fonnte,
        private readonly GowaService $gowa,
        private ?array $configuration = null,
    ) {}

    /** Snapshot hanya milik satu batch, bukan instance gateway pada worker. */
    public function forBatch(): self
    {
        return new self($this->fonnte, $this->gowa, IntegrationSettings::whatsappConfiguration());
    }

    private function configuration(): array
    {
        return $this->configuration ?? IntegrationSettings::whatsappConfiguration();
    }

    public function provider(): string
    {
        return $this->configuration()['provider'];
    }

    public function isConfigured(): bool
    {
        $config = $this->configuration();
        $parts = parse_url($config['base_url']);
        if ($parts === false || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        return match ($config['provider']) {
            'fonnte' => filled($config['token']),
            'gowa' => filled($config['username']) && filled($config['password']) && filled($config['device_id']),
            default => false,
        };
    }

    /** Normalisasi Indonesia dipertahankan sama dengan alur sebelum multi-provider. */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    public function isValidPhone(?string $phone): bool
    {
        return $this->normalizePhone($phone) !== null;
    }

    /** @return array{provider:string,ok:bool,message_ids:array,status:int,body:array,error:?string,auth_invalid:bool} */
    public function sendText(string $phone, string $message): array
    {
        $sender = $this->configuration === null ? $this->forBatch() : $this;
        $phone = $sender->normalizePhone($phone);
        if (! $sender->isConfigured() || $phone === null) {
            return [
                'provider' => $sender->provider(), 'ok' => false, 'message_ids' => [],
                'status' => 0, 'body' => [], 'auth_invalid' => false,
                'error' => $phone === null ? 'Nomor telepon tidak valid.' : 'Konfigurasi provider WhatsApp belum lengkap. Periksa Pengaturan Scheduler & Reminder.',
            ];
        }
        $config = $sender->configuration();

        return $config['provider'] === 'gowa'
            ? $this->gowa->sendText($phone, $message, $config)
            : $this->fonnte->sendText($phone, $message, $config);
    }

    public function checkConnection(): array
    {
        $sender = $this->configuration === null ? $this->forBatch() : $this;
        if (! $sender->isConfigured()) {
            return ['provider' => $sender->provider(), 'state' => 'not_configured', 'message' => 'Konfigurasi provider WhatsApp belum lengkap.'];
        }
        if ($sender->provider() === 'fonnte') {
            return ['provider' => 'fonnte', 'state' => 'configuration_present', 'message' => 'Konfigurasi Fonnte tersedia. Koneksi belum diverifikasi; gunakan Kirim Pesan Uji.'];
        }

        return $this->gowa->checkConnection($sender->configuration());
    }
}
