<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Template pesan WhatsApp (Master Data).
 */
class WhatsappMessageTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'body',
        'encouragement_lines',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
        'encouragement_lines' => 'array',
    ];

    public const CODE_INVOICE_REMINDER = 'INVOICE_REMINDER';

    public const CODE_SCHEDULE_REMINDER = 'SCHEDULE_REMINDER';

    public const CODE_SESSION_REPORT = 'SESSION_REPORT';

    public const CODE_SESSION_REPORT_STUDENT = 'SESSION_REPORT_STUDENT';

    public const SESSION_REPORT_CODES = [
        self::CODE_SESSION_REPORT,
        self::CODE_SESSION_REPORT_STUDENT,
    ];

    /** Kode template laporan sesi? */
    public function isSessionReportTemplate(): bool
    {
        return in_array($this->code, self::SESSION_REPORT_CODES, true);
    }

    /** Kalimat bawaan pesan semangat per rating (generik, tanpa nama murid). */
    public static function defaultEncouragementLines(string $code): array
    {
        return match ($code) {
            self::CODE_SESSION_REPORT_STUDENT => [
                'rating_5' => 'Kamu tampil sangat antusias dan fokus hari ini — keren banget! Pertahankan ya!',
                'rating_4' => 'Kemajuanmu hari ini kelihatan banget. Terus semangat latihannya ya!',
                'rating_3' => 'Kamu sudah berusaha dengan baik hari ini. Latihan singkat tiap hari akan bikin hasil makin terasa.',
                'rating_2' => 'Kamu bisa lebih fokus lagi di sesi berikutnya. Latihan singkat di rumah akan banyak membantu!',
                'rating_1' => 'Hari ini agak kurang fokus — tidak apa-apa. Setiap proses punya naik turunnya. Semangat lagi minggu depan ya!',
                'default'  => 'Setiap sesi adalah langkah berharga — terus semangat ya!',
            ],
            default => [
                'rating_5' => 'Hari ini tampil sangat antusias dan fokus — perkembangannya terlihat jelas!',
                'rating_4' => 'Menunjukkan kemajuan yang baik hari ini. Pertahankan semangatnya!',
                'rating_3' => 'Sudah berusaha dengan baik. Sedikit latihan rutin di rumah akan membuat hasilnya makin terasa.',
                'rating_2' => 'Perlu sedikit lebih fokus di sesi berikutnya. Dukungan latihan di rumah akan sangat membantu.',
                'rating_1' => 'Hari ini agak kurang fokus — tidak apa-apa, setiap proses punya naik turunnya. Mari coba lagi dengan semangat baru.',
                'default'  => 'Setiap sesi adalah langkah berharga. Mari terus mendampingi dengan sabar dan konsisten.',
            ],
        };
    }

    /** Pesan semangat untuk rating sesi; fallback ke default bawaan jika kosong. */
    public function encouragementForRating(?int $rating): string
    {
        $key = match (true) {
            $rating === 5 => 'rating_5',
            $rating === 4 => 'rating_4',
            $rating === 3 => 'rating_3',
            $rating === 2 => 'rating_2',
            $rating === 1 => 'rating_1',
            default       => 'default',
        };

        $lines = $this->encouragement_lines ?? self::defaultEncouragementLines($this->code);
        $text = trim((string) ($lines[$key] ?? ''));

        if ($text !== '') {
            return $text;
        }

        return self::defaultEncouragementLines($this->code)[$key];
    }

    /** Template aktif default untuk reminder tagihan. */
    public static function defaultInvoiceReminder(): ?self
    {
        return static::query()
            ->where('code', self::CODE_INVOICE_REMINDER)
            ->where('is_active', true)
            ->first();
    }

    /** Template aktif default untuk pengingat jadwal. */
    public static function defaultScheduleReminder(): ?self
    {
        return static::query()
            ->where('code', self::CODE_SCHEDULE_REMINDER)
            ->where('is_active', true)
            ->first();
    }

    /** Template aktif default untuk laporan sesi ke ortu. */
    public static function defaultSessionReport(): ?self
    {
        return static::query()
            ->where('code', self::CODE_SESSION_REPORT)
            ->where('is_active', true)
            ->first();
    }

    /** Template aktif default untuk laporan sesi ke murid (fallback jika ortu tanpa nomor). */
    public static function defaultSessionReportStudent(): ?self
    {
        return static::query()
            ->where('code', self::CODE_SESSION_REPORT_STUDENT)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Pastikan 4 template sistem bawaan ada di DB (firstOrCreate + backfill encouragement).
     * Dipanggil dari seeder dan halaman index agar template selalu tampil tanpa seed manual.
     */
    public static function ensureSystemDefaults(): void
    {
        static::firstOrCreate(
            ['code' => self::CODE_INVOICE_REMINDER],
            [
                'name'       => 'Reminder Tagihan SPP',
                'sort_order' => 1,
                'is_active'  => true,
                'body'       => <<<'TEXT'
Yth. {nama_ortu},

Berikut pengingat tagihan les musik untuk *{nama_murid}* ({kode_murid}).

{daftar_invoice}

*Total sisa tagihan:* {total_tagihan}
*Jatuh tempo terdekat:* {tempo_terdekat}

Mohon pembayaran dilakukan sebelum tanggal tempo. Terima kasih.

Musik KITA
WhatsApp: {studio_wa}
TEXT,
            ],
        );

        static::firstOrCreate(
            ['code' => self::CODE_SCHEDULE_REMINDER],
            [
                'name'       => 'Pengingat Jadwal Les',
                'sort_order' => 2,
                'is_active'  => true,
                'body'       => <<<'TEXT'
Yth. {nama_ortu},

Pengingat jadwal les *{nama_murid}* ({kode_murid}) pada *{tanggal}*:

{daftar_jadwal}

Mohon hadir tepat waktu. Terima kasih.

Musik KITA
WhatsApp: {studio_wa}
TEXT,
            ],
        );

        static::firstOrCreate(
            ['code' => self::CODE_SESSION_REPORT],
            [
                'name'                => 'Laporan Sesi ke Ortu',
                'sort_order'          => 3,
                'is_active'           => true,
                'encouragement_lines' => self::defaultEncouragementLines(self::CODE_SESSION_REPORT),
                'body'                => <<<'TEXT'
Halo, Yth. {nama_ortu} 👋

Les musik *{nama_murid}* hari ini sudah selesai. Terima kasih sudah mempercayakan perjalanan musiknya kepada kami di Musik KITA 🎵

📅 *{tanggal_sesi}*
🎹 Instrumen: {instrumen}
👨‍🏫 Guru: {nama_guru}

*Materi hari ini:*
{materi}

*Latihan minggu ini:*
{tugas}

{blok_catatan}

{pesan_semangat}

Kami senang melihat langkah-langkah kecil {nama_murid} menuju kemampuan bermusik yang lebih baik. Dukungan Bapak/Ibu di rumah sangat berarti — semangat latihan ya! 💪🎶

Salam hangat,
Musik KITA
WA: {studio_wa}
TEXT,
            ],
        );

        static::firstOrCreate(
            ['code' => self::CODE_SESSION_REPORT_STUDENT],
            [
                'name'                => 'Laporan Sesi ke Murid',
                'sort_order'          => 4,
                'is_active'           => true,
                'encouragement_lines' => self::defaultEncouragementLines(self::CODE_SESSION_REPORT_STUDENT),
                'body'                => <<<'TEXT'
Halo, {nama_murid}! 👋

Les *{instrumen}* kamu hari ini sudah selesai. Berikut ringkasan dari guru *{nama_guru}*:

📅 *{tanggal_sesi}*
🎹 Instrumen: {instrumen}
👨‍🏫 Guru: {nama_guru}

*Materi hari ini:*
{materi}

*Latihan di rumah:*
{tugas}

{blok_catatan}

{pesan_semangat}

Latihan rutin walau sebentar bikin beda besar — semangat ya! 💪🎶
Kalau ada yang bingung soal materi atau tugas, kabari guru lewat admin studio.

Salam,
Musik KITA
WA: {studio_wa}
TEXT,
            ],
        );

        foreach (self::SESSION_REPORT_CODES as $code) {
            $template = static::query()->where('code', $code)->first();
            if ($template && empty($template->encouragement_lines)) {
                $template->update([
                    'encouragement_lines' => self::defaultEncouragementLines($code),
                ]);
            }
        }
    }
}
