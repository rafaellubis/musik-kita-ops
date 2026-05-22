<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Student;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiKelasInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceService::class);
    }

    public function test_generate_spp_dua_invoice_untuk_murid_dua_kelas(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);

        $pkg1 = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);
        $pkg2 = Package::factory()->create([
            'class_type'      => 'HOBBY',
            'price_per_month' => 390000,
        ]);

        $e1 = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg1->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $e2 = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg2->id,
            'status'     => 'ACTIVE',
            'is_primary' => false,
        ]);
        $student->update(['primary_enrollment_id' => $e1->id]);

        $report = $this->service->generateMonthlySPP(2026, 6);

        $this->assertEquals(2, $report['created']);

        $invoices = Invoice::where('student_id', $student->id)->get();
        $this->assertCount(2, $invoices);
        $this->assertEquals(340000, $invoices->firstWhere('enrollment_id', $e1->id)->total_amount);
        $this->assertEquals(390000, $invoices->firstWhere('enrollment_id', $e2->id)->total_amount);
    }

    public function test_generate_spp_idempotent_per_enrollment(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg     = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
        $e1      = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id, 'status' => 'ACTIVE', 'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $e1->id]);

        // Jalankan dua kali — invoice kedua harus di-skip
        $this->service->generateMonthlySPP(2026, 6);
        $report = $this->service->generateMonthlySPP(2026, 6);

        $this->assertEquals(0, $report['created']);
        $this->assertEquals(1, $report['skipped']);
    }

    public function test_enrollment_inactive_tidak_dapat_spp(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg     = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
        $e1      = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id, 'status' => 'ACTIVE', 'is_primary' => true,
        ]);
        Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id, 'status' => 'INACTIVE', 'is_primary' => false,
        ]);
        $student->update(['primary_enrollment_id' => $e1->id]);

        $report = $this->service->generateMonthlySPP(2026, 6);

        // Hanya 1 invoice — enrollment INACTIVE tidak dapat SPP
        $this->assertEquals(1, $report['created']);
    }
}
