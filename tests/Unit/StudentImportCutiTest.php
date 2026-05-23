<?php

namespace Tests\Unit;

use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentImportCutiTest extends TestCase
{
    use RefreshDatabase;

    private StudentImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StudentImportService::class);
    }

    public function test_status_cuti_tanpa_cuti_until_error(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Sari Dewi',
            'gender'     => 'P',
            'status'     => 'Cuti',
            'cuti_until' => '',
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('cuti_until', $result);
    }

    public function test_status_cuti_format_cuti_until_salah_error(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Sari Dewi',
            'gender'     => 'P',
            'status'     => 'Cuti',
            'cuti_until' => '31-07-2026', // format salah — harus YYYY-MM-DD
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('cuti_until', $result);
    }

    public function test_status_cuti_dengan_cuti_until_valid_lulus(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Sari Dewi',
            'gender'     => 'P',
            'status'     => 'Cuti',
            'cuti_until' => '2026-07-31',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('2026-07-31', $result['cuti_until']);
    }

    public function test_status_aktif_cuti_until_kosong_tidak_error(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Budi Santoso',
            'gender'     => 'L',
            'status'     => 'Aktif',
            'cuti_until' => '',
        ]);

        // Status Aktif — cuti_until boleh kosong, tidak boleh error
        $this->assertIsArray($result);
    }
}
