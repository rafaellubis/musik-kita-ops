<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Notifications\MuridOverdueNotification;
use Tests\TestCase;

class MuridOverdueNotificationTest extends TestCase
{
    public function test_via_returns_database_channel(): void
    {
        $student = new Student();
        $student->id = 1;
        $student->full_name = 'Budi Santoso';
        $student->student_code = 'M-2025-0042';

        $notif = new MuridOverdueNotification($student, 340000, 'Mei 2026');

        $this->assertEquals(['database'], $notif->via(null));
    }

    public function test_to_database_returns_correct_payload(): void
    {
        $student = new Student();
        $student->id = 42;
        $student->full_name = 'Budi Santoso';
        $student->student_code = 'M-2025-0042';

        $notif = new MuridOverdueNotification($student, 340000, 'Mei 2026');
        $data  = $notif->toDatabase(null);

        $this->assertEquals(42, $data['student_id']);
        $this->assertEquals('Budi Santoso', $data['student_name']);
        $this->assertEquals('M-2025-0042', $data['student_code']);
        $this->assertEquals(340000, $data['total_overdue']);
        $this->assertEquals('Mei 2026', $data['invoice_month']);
        $this->assertStringContainsString('/students/42', $data['student_url']);
    }
}
