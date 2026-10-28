<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceReminderProviderMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rename_and_rollback_preserve_historical_message_ids(): void
    {
        $migration = require database_path('migrations/2026_10_28_100000_normalize_invoice_reminder_provider_columns.php');
        $migration->down();
        $id = DB::table('invoice_reminder_logs')->insertGetId([
            'student_id' => Student::factory()->create()->id,
            'sent_by' => \App\Models\User::factory()->create()->id,
            'phone' => '6281234567890', 'message_body' => 'historical',
            'invoice_ids' => '[]', 'wablas_message_ids' => '["old-1","old-2"]',
            'status' => 'SUCCESS', 'sent_at' => now(),
        ]);
        $migration->up();
        $row = DB::table('invoice_reminder_logs')->find($id);
        $this->assertSame('legacy', $row->provider);
        $this->assertSame(['old-1', 'old-2'], json_decode($row->provider_message_ids, true));
        $this->assertSame('SUCCESS', $row->status);
        $migration->down();
        $this->assertSame(['old-1', 'old-2'], json_decode(DB::table('invoice_reminder_logs')->find($id)->wablas_message_ids, true));
        $migration->up();
    }
}
