<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\RescheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * RescheduleService — Opsi 2 untuk Kids Class:
 * - Kids boleh overlap sesi Kids lain (guru + jam sama)
 * - Kids diblokir jika slot sudah ada kelas privat
 * - Ruang Kids pakai kapasitas ruang, bukan overlap pertama
 */
class KidsClassRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private RescheduleService $service;
    private Teacher $teacher;
    private Package $kidsBundlePackage;
    private Package $regularPackage;
    private Room $kidsRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RescheduleService::class);
        $this->teacher = Teacher::factory()->create(['is_active' => true]);

        $kidsInstrument = Instrument::factory()->create(['name' => 'Kids Class', 'code' => 'KIDS']);
        $pianoInstrument = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);

        $this->kidsBundlePackage = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS_BUNDLE',
            'instrument_id'   => $kidsInstrument->id,
            'duration_min'    => 45,
            'price_per_month' => 2180000,
        ]);
        $this->regularPackage = Package::factory()->create([
            'class_type'      => 'REGULER',
            'instrument_id'   => $pianoInstrument->id,
            'duration_min'    => 30,
            'price_per_month' => 370000,
        ]);
        $this->kidsRoom = Room::factory()->create([
            'code'     => 'R1',
            'name'     => 'Studio 1',
            'capacity' => 4,
        ]);
    }

    private function makeKidsSession(array $override = []): ClassSession
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->kidsBundlePackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create(array_merge([
            'teacher_id'    => $this->teacher->id,
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'session_date'  => '2026-06-02',
            'start_time'    => '11:00:00',
            'end_time'      => '11:45:00',
            'room_id'       => $this->kidsRoom->id,
            'status'        => ClassSession::STATUS_IZIN_RESCHEDULE,
        ], $override));
    }

    private function seedKidsSessionAt(string $date, string $start, string $end): ClassSession
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->kidsBundlePackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create([
            'teacher_id'    => $this->teacher->id,
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'session_date'  => $date,
            'start_time'    => $start,
            'end_time'      => $end,
            'room_id'       => $this->kidsRoom->id,
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);
    }

    public function test_kids_reschedule_lolos_jika_slot_sudah_ada_sesi_kids_lain(): void
    {
        $this->seedKidsSessionAt('2026-06-10', '11:00:00', '11:45:00');
        $original = $this->makeKidsSession();

        $replacement = $this->service->createReplacement(
            $original,
            '2026-06-10',
            '11:00',
            $this->kidsRoom->id,
        );

        $this->assertSame('2026-06-10', $replacement->session_date);
        $this->assertSame('11:00:00', $replacement->start_time);
        $this->assertSame($this->teacher->id, $replacement->teacher_id);
    }

    public function test_kids_reschedule_gagal_jika_slot_sudah_ada_sesi_reguler(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->regularPackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'teacher_id'    => $this->teacher->id,
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'session_date'  => '2026-06-10',
            'start_time'    => '11:00:00',
            'end_time'      => '11:30:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        $original = $this->makeKidsSession();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Guru/');

        $this->service->createReplacement(
            $original,
            '2026-06-10',
            '11:00',
            $this->kidsRoom->id,
        );
    }

    public function test_reguler_reschedule_gagal_jika_slot_sudah_ada_sesi_kids(): void
    {
        $this->seedKidsSessionAt('2026-06-10', '11:00:00', '11:45:00');

        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->regularPackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);
        $original = ClassSession::factory()->create([
            'teacher_id'    => $this->teacher->id,
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'session_date'  => '2026-06-02',
            'start_time'    => '14:00:00',
            'end_time'      => '14:30:00',
            'status'        => ClassSession::STATUS_IZIN_RESCHEDULE,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Guru/');

        $this->service->createReplacement(
            $original,
            '2026-06-10',
            '11:00',
            $this->kidsRoom->id,
        );
    }

    public function test_kids_reschedule_lolos_ke_r1_dengan_tiga_sesi_kids_lain(): void
    {
        foreach (range(1, 3) as $_) {
            $this->seedKidsSessionAt('2026-06-10', '11:00:00', '11:45:00');
        }

        $original = $this->makeKidsSession();

        $replacement = $this->service->createReplacement(
            $original,
            '2026-06-10',
            '11:00',
            $this->kidsRoom->id,
        );

        $this->assertSame($this->kidsRoom->id, $replacement->room_id);
    }

    public function test_kids_reschedule_gagal_jika_r1_sudah_penuh_empat_sesi(): void
    {
        foreach (range(1, 4) as $_) {
            $this->seedKidsSessionAt('2026-06-10', '11:00:00', '11:45:00');
        }

        $original = $this->makeKidsSession();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Kapasitas ruangan/');

        $this->service->createReplacement(
            $original,
            '2026-06-10',
            '11:00',
            $this->kidsRoom->id,
        );
    }
}
