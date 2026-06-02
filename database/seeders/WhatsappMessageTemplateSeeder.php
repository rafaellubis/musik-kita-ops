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
    }
}
