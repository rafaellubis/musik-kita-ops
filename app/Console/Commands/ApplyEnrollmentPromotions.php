<?php

namespace App\Console\Commands;

use App\Services\EnrollmentPromotionService;
use Illuminate\Console\Command;

class ApplyEnrollmentPromotions extends Command
{
    protected $signature = 'enrollments:apply-promotions';
    protected $description = 'Terapkan kenaikan level kelas yang sudah jatuh tanggal efektif.';

    public function handle(EnrollmentPromotionService $service): int
    {
        $this->info('Kenaikan level diterapkan: '.$service->applyDue());
        return self::SUCCESS;
    }
}
