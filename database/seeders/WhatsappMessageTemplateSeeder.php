<?php

namespace Database\Seeders;

use App\Models\WhatsappMessageTemplate;
use Illuminate\Database\Seeder;

class WhatsappMessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        WhatsappMessageTemplate::ensureSystemDefaults();
    }
}
