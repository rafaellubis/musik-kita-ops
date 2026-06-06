<?php

use App\Services\ProgressReportService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(ProgressReportService::class)->backfillReportNumbers();
    }

    public function down(): void
    {
        // Data backfill — tidak di-rollback otomatis.
    }
};
