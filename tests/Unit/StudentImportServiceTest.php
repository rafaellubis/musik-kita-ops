<?php

namespace Tests\Unit;

use App\Models\Package;
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
}
