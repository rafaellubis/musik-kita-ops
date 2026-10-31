<?php

namespace App\Console\Commands;

use App\Services\EnrollmentTransferService;
use Illuminate\Console\Command;

class ApplyEnrollmentTransfers extends Command
{
    protected $signature = 'enrollments:apply-transfers';
    protected $description = 'Terapkan pengalihan guru kelas yang tanggal berlakunya sudah tiba';

    public function handle(EnrollmentTransferService $service): int
    {
        $this->info('Pengalihan guru diterapkan: '.$service->applyDue());
        return self::SUCCESS;
    }
}
