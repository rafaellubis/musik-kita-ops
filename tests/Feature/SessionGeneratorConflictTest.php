<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionGeneratorService;
use App\Services\StudentImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionGeneratorConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_fase2_skip_regular_session_jika_guru_sudah_terpakai(): void
    {
        $teacher  = Teacher::factory()->create(['is_active' => true]);
        $room1    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $room2    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $package  = Package::factory()->create(['duration_min' => 30, 'class_type' => 'REGULER', 'is_active' => true]);

        // Murid 1 — sudah punya sesi SCHEDULED di Senin 15:00 bulan target
        $student1    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule1 = Schedule::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'day_of_week'   => 1, // Senin
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room1->id,
            'is_active'     => true,
        ]);

        // Buat sesi konkret murid 1 di Senin pertama bulan depan
        $targetMonth = Carbon::now()->addMonth()->startOfMonth();
        $senin       = $targetMonth->copy()->next('Monday');
        ClassSession::factory()->create([
            'schedule_id'   => $schedule1->id,
            'enrollment_id' => $enrollment1->id,
            'student_id'    => $student1->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => $senin->toDateString(),
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room1->id,
            'status'        => 'SCHEDULED',
        ]);

        // Murid 2 — guru sama, jam sama, ruang beda — belum punya sesi
        $student2    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule2 = Schedule::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room2->id,
            'is_active'     => true,
        ]);

        // Jalankan generator untuk bulan target
        $report = app(SessionGeneratorService::class)->generateForMonth(
            $targetMonth->year,
            $targetMonth->month
        );

        // Sesi murid 2 di Senin tsb tidak boleh terbuat — konflik guru
        $konflikTerbuat = ClassSession::where('schedule_id', $schedule2->id)
            ->whereDate('session_date', $senin)
            ->exists();

        $this->assertFalse($konflikTerbuat, 'Sesi konflik seharusnya tidak dibuat');
        $this->assertGreaterThan(0, $report['skipped_conflict'], 'skipped_conflict harus bertambah');
    }

    public function test_fase2_skip_session_jika_ada_overlap_durasi(): void
    {
        $teacher  = Teacher::factory()->create(['is_active' => true]);
        $room1    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $room2    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $package1 = Package::factory()->create(['duration_min' => 45, 'class_type' => 'REGULER', 'is_active' => true]);
        $package2 = Package::factory()->create(['duration_min' => 30, 'class_type' => 'REGULER', 'is_active' => true]);

        // Murid 1 — sesi 15:00-15:45 sudah ada
        $student1    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package1->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule1 = Schedule::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'day_of_week'   => 2, // Selasa
            'start_time'    => '15:00',
            'end_time'      => '15:45',
            'room_id'       => $room1->id,
            'is_active'     => true,
        ]);

        $targetMonth = Carbon::now()->addMonth()->startOfMonth();
        $selasa      = $targetMonth->copy()->next('Tuesday');
        ClassSession::factory()->create([
            'schedule_id'   => $schedule1->id,
            'enrollment_id' => $enrollment1->id,
            'student_id'    => $student1->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => $selasa->toDateString(),
            'start_time'    => '15:00',
            'end_time'      => '15:45',
            'room_id'       => $room1->id,
            'status'        => 'SCHEDULED',
        ]);

        // Murid 2 — guru sama, jam BERBEDA tapi overlap: 15:30-16:00
        $student2    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package2->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule2 = Schedule::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'day_of_week'   => 2,
            'start_time'    => '15:30',
            'end_time'      => '16:00',
            'room_id'       => $room2->id,
            'is_active'     => true,
        ]);

        $report = app(SessionGeneratorService::class)->generateForMonth(
            $targetMonth->year,
            $targetMonth->month
        );

        // Sesi murid 2 di Selasa tsb tidak boleh terbuat — overlap dengan murid 1 (15:00-15:45 vs 15:30-16:00)
        $konflikTerbuat = ClassSession::where('schedule_id', $schedule2->id)
            ->whereDate('session_date', $selasa)
            ->exists();

        $this->assertFalse($konflikTerbuat, 'Sesi overlap seharusnya tidak dibuat');
        $this->assertGreaterThan(0, $report['skipped_conflict']);
    }

    /**
     * Skenario FRESH bulan baru — tidak ada sesi pre-existing.
     * Ini adalah skenario nyata setelah import: dua murid guru/jam sama, generator jalan pertama kali.
     * Generator harus hanya buat sesi untuk SATU murid, bukan dua-duanya.
     */
    public function test_generator_fresh_tidak_buat_duplikat_sesi_guru_sama(): void
    {
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $room1   = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $room2   = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $package = Package::factory()->create(['duration_min' => 30, 'class_type' => 'REGULER', 'is_active' => true]);

        // Dua murid berbeda, guru + hari + jam SAMA — seperti kondisi post-import
        $student1    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id, 'teacher_id' => $teacher->id,
            'package_id' => $package->id, 'status' => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $enrollment1->id, 'day_of_week' => 1,
            'start_time' => '15:00:00', 'end_time' => '15:30:00',
            'room_id' => $room1->id, 'is_active' => true,
        ]);

        $student2    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id, 'teacher_id' => $teacher->id,
            'package_id' => $package->id, 'status' => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $enrollment2->id, 'day_of_week' => 1,
            'start_time' => '15:00:00', 'end_time' => '15:30:00',
            'room_id' => $room2->id, 'is_active' => true,
        ]);

        // TIDAK ada sesi pre-existing — murni fresh run
        $targetMonth = Carbon::now()->addMonth()->startOfMonth();
        $report = app(SessionGeneratorService::class)->generateForMonth(
            $targetMonth->year,
            $targetMonth->month
        );

        // Cari tanggal yang punya lebih dari 1 sesi SCHEDULED untuk guru ini
        $duplikat = ClassSession::where('teacher_id', $teacher->id)
            ->whereYear('session_date', $targetMonth->year)
            ->whereMonth('session_date', $targetMonth->month)
            ->where('status', 'SCHEDULED')
            ->get()
            ->groupBy('session_date')
            ->filter(fn ($sesi) => $sesi->count() > 1);

        $this->assertEmpty($duplikat,
            'Generator tidak boleh buat 2 sesi SCHEDULED pada tanggal + guru yang sama. ' .
            'Tanggal bentrok: ' . $duplikat->keys()->implode(', '));

        $this->assertGreaterThan(0, $report['skipped_conflict'],
            'Generator harus mendeteksi dan skip konflik');
    }

    /**
     * Saat ada holiday dan dua schedule punya guru+hari+jam sama,
     * generator hanya boleh buat SATU sesi LIBUR — bukan dua.
     * Honor guru tidak boleh double-count di hari libur.
     */
    public function test_libur_tidak_dibuat_duplikat_saat_guru_sama(): void
    {
        $teacher  = Teacher::factory()->create(['is_active' => true]);
        $room1    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $room2    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $package  = Package::factory()->create(['duration_min' => 30, 'class_type' => 'REGULER', 'is_active' => true, 'price_per_month' => 340000]);

        $targetMonth = Carbon::now()->addMonth()->startOfMonth();
        $senin       = $targetMonth->copy()->next('Monday');

        // Buat holiday tepat di hari Senin pertama bulan target
        Holiday::create([
            'date'           => $senin->toDateString(),
            'name'           => 'Hari Libur Test',
            'type'           => 'Nasional',
            'is_active'      => true,
            'is_honor_paid'  => true,
            'replacement_date' => null,
        ]);

        // Dua murid, guru sama, hari sama (Senin), jam sama
        $student1    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id, 'teacher_id' => $teacher->id,
            'package_id' => $package->id, 'status' => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $enrollment1->id, 'day_of_week' => 1,
            'start_time' => '15:00:00', 'end_time' => '15:30:00',
            'room_id' => $room1->id, 'is_active' => true,
        ]);

        $student2    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id, 'teacher_id' => $teacher->id,
            'package_id' => $package->id, 'status' => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $enrollment2->id, 'day_of_week' => 1,
            'start_time' => '15:00:00', 'end_time' => '15:30:00',
            'room_id' => $room2->id, 'is_active' => true,
        ]);

        $report = app(SessionGeneratorService::class)->generateForMonth(
            $targetMonth->year,
            $targetMonth->month
        );

        $liburPadaSenin = ClassSession::where('teacher_id', $teacher->id)
            ->whereDate('session_date', $senin)
            ->where('status', 'LIBUR')
            ->count();

        $this->assertEquals(1, $liburPadaSenin,
            "Hanya satu sesi LIBUR yang boleh dibuat untuk guru+tanggal yang sama. " .
            "Actual: {$liburPadaSenin}");

        $this->assertGreaterThan(0, $report['skipped_conflict'],
            'Generator harus mendeteksi konflik dan skip sesi LIBUR kedua');
    }

    /**
     * Import harus blocking saat dua baris Excel punya guru + hari + jam yang sama.
     * Saat confirm(), schedule kedua tidak boleh dibuat karena bentrok dengan schedule pertama.
     */
    public function test_import_confirm_tidak_buat_dua_schedule_bentrok(): void
    {
        $package = Package::factory()->create([
            'duration_min' => 30, 'class_type' => 'REGULER', 'is_active' => true,
        ]);
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $room    = Room::factory()->create(['is_active' => true, 'code' => 'R-TEST']);

        // Simulasi data hasil validate() — dua murid, guru + hari + jam sama
        $baseData = [
            'gender'              => 'L',
            'status'              => 'Aktif',
            'package_id'          => $package->id,
            'assigned_teacher_id' => $teacher->id,
            'preferred_day'       => 'Senin',
            'preferred_time'      => '15:00',
            '_duration_min'       => 30,
            'room_id'             => $room->id,
            '_room_code'          => 'R-TEST',
            '_has_warning'        => false,
            '_warning_message'    => null,
            '_conflict_warning'   => null,
            'active_since'        => '2026-01-01',
        ];

        $valid = [
            ['row' => 2, 'data' => array_merge($baseData, [
                'full_name' => 'Murid Import Satu', 'phone' => '+6281111111111',
            ])],
            ['row' => 3, 'data' => array_merge($baseData, [
                'full_name' => 'Murid Import Dua',  'phone' => '+6281111111112',
            ])],
        ];

        app(StudentImportService::class)->confirm($valid, []);

        // Hanya satu schedule yang boleh terbuat untuk guru + hari + jam ini
        $jumlahSchedule = Schedule::whereHas('enrollment', fn ($q) =>
            $q->where('teacher_id', $teacher->id)
        )
        ->where('day_of_week', 1)
        ->where('start_time', '15:00:00')
        ->count();

        $this->assertEquals(1, $jumlahSchedule,
            "Hanya satu schedule yang boleh dibuat untuk guru/hari/jam yang sama saat import. " .
            "Actual: {$jumlahSchedule}");
    }
}
