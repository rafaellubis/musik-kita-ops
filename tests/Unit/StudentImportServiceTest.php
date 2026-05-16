<?php

namespace Tests\Unit;

use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
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
}
