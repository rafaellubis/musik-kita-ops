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
}
