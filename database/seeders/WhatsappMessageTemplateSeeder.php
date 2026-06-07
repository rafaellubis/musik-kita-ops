<?php

namespace Database\Seeders;

use App\Models\WhatsappMessageTemplate;
use Illuminate\Database\Seeder;

class WhatsappMessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        WhatsappMessageTemplate::firstOrCreate(
            ['code' => WhatsappMessageTemplate::CODE_INVOICE_REMINDER],
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

        WhatsappMessageTemplate::firstOrCreate(
            ['code' => WhatsappMessageTemplate::CODE_SCHEDULE_REMINDER],
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

        WhatsappMessageTemplate::firstOrCreate(
            ['code' => WhatsappMessageTemplate::CODE_SESSION_REPORT],
            [
                'name'                => 'Laporan Sesi ke Ortu',
                'sort_order'          => 3,
                'is_active'           => true,
                'encouragement_lines' => WhatsappMessageTemplate::defaultEncouragementLines(
                    WhatsappMessageTemplate::CODE_SESSION_REPORT,
                ),
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

        WhatsappMessageTemplate::firstOrCreate(
            ['code' => WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT],
            [
                'name'                => 'Laporan Sesi ke Murid',
                'sort_order'          => 4,
                'is_active'           => true,
                'encouragement_lines' => WhatsappMessageTemplate::defaultEncouragementLines(
                    WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT,
                ),
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

        // Backfill pesan semangat untuk record lama yang belum punya JSON
        foreach (WhatsappMessageTemplate::SESSION_REPORT_CODES as $code) {
            $template = WhatsappMessageTemplate::query()->where('code', $code)->first();
            if ($template && empty($template->encouragement_lines)) {
                $template->update([
                    'encouragement_lines' => WhatsappMessageTemplate::defaultEncouragementLines($code),
                ]);
            }
        }
    }
}
