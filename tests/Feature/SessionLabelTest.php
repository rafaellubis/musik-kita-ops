<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionLabelTest extends TestCase
{
    use RefreshDatabase;

    // --- getSessionLabel() ---

    public function test_label_sesi_reguler_bernomor(): void
    {
        // Tidak perlu DB — hanya instansiasi model
        $session = new ClassSession();
        $session->session_date     = '2026-05-04';
        $session->session_sequence = 1;

        $this->assertSame('Sesi ke-1 Bulan Mei 2026', $session->getSessionLabel());
    }

    public function test_label_libur_dengan_replacement_adalah_dash(): void
    {
        $session = new ClassSession();
        $session->session_date     = '2026-05-11';
        $session->session_sequence = null;

        $this->assertSame('—', $session->getSessionLabel());
    }

    public function test_label_sesi_pengganti_menampilkan_asal(): void
    {
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create(['status' => 'Aktif']);
        $package = Package::factory()->create(['class_type' => 'REGULER']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'status'     => 'ACTIVE',
        ]);

        // Sesi LIBUR asal (Senin ke-2 Mei)
        $origin = ClassSession::create([
            'enrollment_id'    => $enrollment->id,
            'student_id'       => $student->id,
            'teacher_id'       => $teacher->id,
            'session_date'     => '2026-05-11',
            'start_time'       => '14:00:00',
            'end_time'         => '14:30:00',
            'status'           => 'LIBUR',
            'session_sequence' => null,
        ]);

        // Sesi pengganti (Kamis 28 Mei)
        $replacement = ClassSession::create([
            'enrollment_id'    => $enrollment->id,
            'student_id'       => $student->id,
            'teacher_id'       => $teacher->id,
            'session_date'     => '2026-05-28',
            'start_time'       => '14:00:00',
            'end_time'         => '14:30:00',
            'status'           => 'SCHEDULED',
            'session_sequence' => 2,
            'origin_session_id'=> $origin->id,
        ]);

        $replacement->load('originSession');

        $this->assertSame(
            'Reschedule dari Sesi ke-2 Bulan Mei 2026',
            $replacement->getSessionLabel()
        );
    }

    public function test_label_sesi_ke_empat(): void
    {
        $session = new ClassSession();
        $session->session_date     = '2026-05-25';
        $session->session_sequence = 4;

        $this->assertSame('Sesi ke-4 Bulan Mei 2026', $session->getSessionLabel());
    }
}
