<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use App\Models\Teacher;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StudentImportService();
    }

    public function test_valid_row_returns_data_array(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Budi Santoso',
            'gender'     => 'L',
            'status'     => 'Aktif',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('Budi Santoso', $result['full_name']);
    }

    public function test_missing_full_name_returns_error_string(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name' => '',
            'gender'    => 'L',
            'status'    => 'Aktif',
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('full_name', $result);
    }

    public function test_invalid_gender_returns_error_string(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name' => 'Budi',
            'gender'    => 'X',
            'status'    => 'Aktif',
        ]);

        $this->assertIsString($result);
    }

    public function test_invalid_status_returns_error_string(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name' => 'Budi',
            'gender'    => 'L',
            'status'    => 'StatusTidakValid',
        ]);

        $this->assertIsString($result);
    }

    public function test_invalid_package_code_returns_error_string(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'    => 'Budi',
            'gender'       => 'L',
            'status'       => 'Aktif',
            'package_code' => 'KODE_TIDAK_ADA',
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('package_code', $result);
    }

    public function test_existing_student_detected_as_overwrite(): void
    {
        Student::factory()->create([
            'full_name' => 'Budi Santoso',
            'phone'     => '08111111111',
            'gender'    => 'L',
            'status'    => 'Aktif',
        ]);

        $existing = $this->service->findExisting('Budi Santoso', '08111111111');
        $this->assertNotNull($existing);
    }

    public function test_confirm_inserts_valid_students(): void
    {
        $validRows = [
            [
                'row'  => 2,
                'data' => [
                    'full_name'  => 'Test Import Confirm',
                    'gender'     => 'L',
                    'status'     => 'Aktif',
                    'nickname'   => null,
                    'birth_date' => null,
                    'phone'      => null,
                    'email'      => null,
                    'address'    => null,
                    'notes'      => null,
                    'parent_name'         => null,
                    'parent_phone'        => null,
                    'parent_email'        => null,
                    'parent_relationship' => null,
                    'package_id'          => null,
                    'assigned_teacher_id' => null,
                    'preferred_day'       => null,
                    'preferred_time'      => null,
                    'active_since'        => null,
                    'trial_date'          => null,
                ],
            ],
        ];

        $result = $this->service->confirm($validRows, []);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertDatabaseHas('students', ['full_name' => 'Test Import Confirm']);
    }

    public function test_confirm_updates_existing_student(): void
    {
        $student = Student::factory()->create([
            'full_name' => 'Murid Existing',
            'gender'    => 'L',
            'status'    => 'Calon',
        ]);

        $overwriteRows = [
            [
                'row'  => 2,
                'data' => [
                    '_existing_id' => $student->id,
                    'full_name'    => 'Murid Existing',
                    'gender'       => 'P',  // berubah dari L ke P
                    'status'       => 'Aktif',
                    'nickname'     => null,
                    'birth_date'   => null,
                    'phone'        => null,
                    'email'        => null,
                    'address'      => null,
                    'notes'        => null,
                    'parent_name'          => null,
                    'parent_phone'         => null,
                    'parent_email'         => null,
                    'parent_relationship'  => null,
                    'package_id'           => null,
                    'assigned_teacher_id'  => null,
                    'preferred_day'        => null,
                    'preferred_time'       => null,
                    'active_since'         => null,
                    'trial_date'           => null,
                ],
            ],
        ];

        $result = $this->service->confirm([], $overwriteRows);

        $this->assertEquals(1, $result['imported']);
        $student->refresh();
        $this->assertEquals('P', $student->gender);
        $this->assertEquals('Aktif', $student->status);

        // Status berubah ke Aktif → StatusHistory dibuat (dengan reason migrasi, skip trial)
        $this->assertEquals(1, StudentStatusHistory::count());
        // Tidak ada package_id → tidak ada enrollment
        $this->assertEquals(0, Enrollment::count());
    }

    // ============= HELPER =============

    /**
     * Buat fixture ruangan untuk test kode_ruangan.
     * Return array berisi roomCodes, roomInstrumentsMap, dan objek piano instrument.
     */
    private function makeRoomMaps(): array
    {
        $piano = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $room  = Room::create([
            'code' => 'R2', 'name' => 'Studio 2', 'capacity' => 1,
            'supported_instruments' => ['Piano', 'Gitar'], 'is_active' => true,
        ]);
        $drumRoom = Room::create([
            'code' => 'R8', 'name' => 'Studio 8', 'capacity' => 1,
            'supported_instruments' => ['Drum'], 'is_active' => true,
        ]);

        return [
            'roomCodes'          => ['R2' => $room->id, 'R8' => $drumRoom->id],
            'roomInstrumentsMap' => ['R2' => ['Piano', 'Gitar'], 'R8' => ['Drum']],
            'piano'              => $piano,
        ];
    }

    // ============= TES VALIDASI KODE RUANGAN =============

    public function test_kode_ruangan_tidak_ditemukan_return_error(): void
    {
        $result = $this->service->validateRow(5, [
            'full_name'    => 'Budi', 'gender' => 'L', 'status' => 'Aktif',
            'kode_ruangan' => 'X99',
        ], [], [], [], [], []);

        $this->assertIsString($result);
        $this->assertStringContainsString('X99', $result);
        $this->assertStringContainsString('tidak ditemukan', $result);
    }

    public function test_kode_ruangan_instrumen_tidak_cocok_return_warning(): void
    {
        ['roomCodes' => $roomCodes, 'roomInstrumentsMap' => $roomInstrumentsMap, 'piano' => $piano] = $this->makeRoomMaps();

        $package = Package::create([
            'code'           => 'REG-PIANO',
            'instrument_id'  => $piano->id,
            'class_type'     => 'REGULER',
            'grade'          => 'Basic',
            'duration_min'   => 30,
            'price_per_month' => 340000,
            'is_active'      => true,
            'sort_order'     => 1,
        ]);

        $result = $this->service->validateRow(6, [
            'full_name'    => 'Ani', 'gender' => 'P', 'status' => 'Aktif',
            'package_code' => 'REG-PIANO',
            'kode_ruangan' => 'R8',  // R8 hanya Drum — Piano tidak cocok
        ], ['REG-PIANO' => $package->id], [], $roomCodes, ['REG-PIANO' => 'Piano'], $roomInstrumentsMap);

        // Bukan error — tetap return array (warning, bukan block)
        $this->assertIsArray($result);
        $this->assertTrue($result['_has_warning']);
        $this->assertStringContainsString('Piano', $result['_warning_message']);
    }

    public function test_kode_ruangan_valid_dan_cocok_tidak_ada_warning(): void
    {
        ['roomCodes' => $roomCodes, 'roomInstrumentsMap' => $roomInstrumentsMap, 'piano' => $piano] = $this->makeRoomMaps();

        $package = Package::create([
            'code'            => 'REG-PIANO',
            'instrument_id'   => $piano->id,
            'class_type'      => 'REGULER',
            'grade'           => 'Basic',
            'duration_min'    => 30,
            'price_per_month' => 340000,
            'is_active'       => true,
            'sort_order'      => 1,
        ]);

        $result = $this->service->validateRow(7, [
            'full_name'    => 'Cici', 'gender' => 'P', 'status' => 'Aktif',
            'package_code' => 'REG-PIANO',
            'kode_ruangan' => 'R2',  // R2 support Piano — cocok
        ], ['REG-PIANO' => $package->id], [], $roomCodes, ['REG-PIANO' => 'Piano'], $roomInstrumentsMap);

        $this->assertIsArray($result);
        $this->assertFalse($result['_has_warning']);
        $this->assertNull($result['_warning_message']);
        $this->assertEquals($roomCodes['R2'], $result['room_id']);
    }

    public function test_kode_ruangan_kosong_tidak_error(): void
    {
        $result = $this->service->validateRow(8, [
            'full_name'    => 'Dodi', 'gender' => 'L', 'status' => 'Aktif',
            'kode_ruangan' => '',
        ], [], [], [], [], []);

        $this->assertIsArray($result);
        $this->assertNull($result['room_id']);
        $this->assertFalse($result['_has_warning']);
        $this->assertNull($result['_warning_message']);
    }

    // ============= TES ENROLLMENT, SCHEDULE, DAN STATUS HISTORY =============

    public function test_confirm_aktif_buat_enrollment_schedule_dan_status_history(): void
    {
        $piano   = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'REG-PIANO', 'instrument_id' => $piano->id,
            'class_type' => 'REGULER', 'grade' => 'Basic',
            'duration_min' => 30, 'price_per_month' => 340000,
            'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = Teacher::create(['code' => 'TCH-ADI', 'name' => 'Adi', 'phone' => '08123456789', 'is_active' => true]);
        $room    = Room::create([
            'code' => 'R2', 'name' => 'Studio 2', 'capacity' => 1,
            'supported_instruments' => ['Piano'], 'is_active' => true,
        ]);
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->service->confirm([
            [
                'row'  => 2,
                'data' => [
                    'full_name'           => 'Budi Santoso',
                    'gender'              => 'L',
                    'status'              => 'Aktif',
                    'package_id'          => $package->id,
                    'assigned_teacher_id' => $teacher->id,
                    'room_id'             => $room->id,
                    'preferred_day'       => 'Senin',
                    'preferred_time'      => '15:00',
                    'active_since'        => '2026-01-15',
                    '_has_warning'        => false,
                    '_warning_message'    => null,
                    'nickname' => null, 'birth_date' => null, 'phone' => null,
                    'email' => null, 'address' => null, 'notes' => null,
                    'parent_name' => null, 'parent_phone' => null, 'parent_email' => null,
                    'parent_relationship' => null,
                ],
            ],
        ], []);

        $student = \App\Models\Student::where('full_name', 'Budi Santoso')->first();
        $this->assertNotNull($student);

        $enrollment = Enrollment::where('student_id', $student->id)->first();
        $this->assertNotNull($enrollment);
        $this->assertEquals('ACTIVE', $enrollment->status);
        $this->assertEquals($package->id, $enrollment->package_id);
        $this->assertEquals($teacher->id, $enrollment->teacher_id);

        $schedule = Schedule::where('enrollment_id', $enrollment->id)->first();
        $this->assertNotNull($schedule);
        $this->assertEquals(1, $schedule->day_of_week); // Senin = 1
        $this->assertEquals('15:00:00', $schedule->start_time);
        $this->assertEquals('15:30:00', $schedule->end_time); // +30 menit
        $this->assertEquals($room->id, $schedule->room_id);

        $history = StudentStatusHistory::where('student_id', $student->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals('migrasi', $history->reason);
        $this->assertTrue((bool)$history->skipped_trial);
        $this->assertNull($history->from_status);
        $this->assertEquals('Aktif', $history->to_status);
    }

    public function test_confirm_aktif_tanpa_preferred_day_skip_schedule_tapi_buat_history(): void
    {
        $piano   = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'REG-PIANO', 'instrument_id' => $piano->id,
            'class_type' => 'REGULER', 'grade' => 'Basic',
            'duration_min' => 30, 'price_per_month' => 340000,
            'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = Teacher::create(['code' => 'TCH-ADI', 'name' => 'Adi', 'phone' => '08123456789', 'is_active' => true]);
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->service->confirm([
            [
                'row'  => 3,
                'data' => [
                    'full_name'           => 'Tanpa Jadwal',
                    'gender'              => 'L',
                    'status'              => 'Aktif',
                    'package_id'          => $package->id,
                    'assigned_teacher_id' => $teacher->id,
                    'room_id'             => null,
                    'preferred_day'       => null,
                    'preferred_time'      => null,
                    'active_since'        => null,
                    '_has_warning' => false, '_warning_message' => null,
                    'nickname' => null, 'birth_date' => null, 'phone' => null,
                    'email' => null, 'address' => null, 'notes' => null,
                    'parent_name' => null, 'parent_phone' => null, 'parent_email' => null,
                    'parent_relationship' => null,
                ],
            ],
        ], []);

        $student = \App\Models\Student::where('full_name', 'Tanpa Jadwal')->first();
        $this->assertNotNull($student);
        $this->assertEquals(0, Schedule::count());
        $this->assertEquals(0, Enrollment::count());
        $this->assertEquals(1, StudentStatusHistory::where('student_id', $student->id)->count());
    }

    public function test_confirm_calon_tidak_buat_enrollment_atau_history(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->service->confirm([
            [
                'row'  => 4,
                'data' => [
                    'full_name' => 'Calon Murid', 'gender' => 'L', 'status' => 'Calon',
                    'package_id' => null, 'assigned_teacher_id' => null, 'room_id' => null,
                    'preferred_day' => null, 'preferred_time' => null, 'active_since' => null,
                    '_has_warning' => false, '_warning_message' => null,
                    'nickname' => null, 'birth_date' => null, 'phone' => null,
                    'email' => null, 'address' => null, 'notes' => null,
                    'parent_name' => null, 'parent_phone' => null, 'parent_email' => null,
                    'parent_relationship' => null,
                ],
            ],
        ], []);

        $student = \App\Models\Student::where('full_name', 'Calon Murid')->first();
        $this->assertNotNull($student);
        $this->assertEquals(0, Enrollment::count());
        $this->assertEquals(0, StudentStatusHistory::count());
    }

    public function test_end_time_dihitung_dari_duration_min(): void
    {
        $piano   = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'HOBBY-PIANO-45', 'instrument_id' => $piano->id,
            'class_type' => 'HOBBY', 'grade' => null,
            'duration_min' => 45, 'price_per_month' => 450000,
            'is_active' => true, 'sort_order' => 2,
        ]);
        $teacher = Teacher::create(['code' => 'TCH-ADI', 'name' => 'Adi', 'phone' => '08123456789', 'is_active' => true]);
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->service->confirm([
            [
                'row'  => 5,
                'data' => [
                    'full_name' => 'Budi Hobby', 'gender' => 'L', 'status' => 'Aktif',
                    'package_id' => $package->id, 'assigned_teacher_id' => $teacher->id,
                    'room_id' => null, 'preferred_day' => 'Rabu', 'preferred_time' => '10:15',
                    'active_since' => null,
                    '_has_warning' => false, '_warning_message' => null,
                    'nickname' => null, 'birth_date' => null, 'phone' => null,
                    'email' => null, 'address' => null, 'notes' => null,
                    'parent_name' => null, 'parent_phone' => null, 'parent_email' => null,
                    'parent_relationship' => null,
                ],
            ],
        ], []);

        $schedule = Schedule::first();
        $this->assertNotNull($schedule);
        $this->assertEquals('10:15:00', $schedule->start_time);
        $this->assertEquals('11:00:00', $schedule->end_time); // 10:15 + 45 = 11:00
        $this->assertEquals(3, $schedule->day_of_week); // Rabu = 3
    }
}
