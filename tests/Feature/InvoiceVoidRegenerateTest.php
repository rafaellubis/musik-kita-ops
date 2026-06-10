<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guard idempotency harus mengabaikan invoice VOID agar regenerate bisa dilakukan.
 */
class InvoiceVoidRegenerateTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->service = app(InvoiceService::class);
        $this->admin   = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('Admin');
    }

    public function test_generate_spp_bisa_setelah_invoice_spp_di_void(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg     = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
        $e1      = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id, 'status' => 'ACTIVE', 'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $e1->id]);

        $this->service->generateMonthlySPP(2026, 6);
        $invoice = Invoice::where('student_id', $student->id)->first();
        $this->assertNotNull($invoice);

        $this->service->voidInvoice($invoice, $this->admin, 'Duplikat SPP Juni');

        $report = $this->service->generateMonthlySPP(2026, 6);

        $this->assertEquals(1, $report['created']);
        $this->assertCount(2, Invoice::where('student_id', $student->id)->get());
        $this->assertEquals(1, Invoice::where('student_id', $student->id)->notVoid()->count());
    }

    public function test_generate_kids_fp_bisa_setelah_invoice_di_void(): void
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $package    = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS',
            'price_per_month' => 340000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin)
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertRedirect();

        $invoice = Invoice::where('student_id', $student->id)->first();
        $this->service->voidInvoice($invoice, $this->admin, 'Salah generate KIDS_FP');

        $this->actingAs($this->admin)
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->assertEquals(2, Invoice::where('student_id', $student->id)->count());
        $this->assertEquals(1, Invoice::where('student_id', $student->id)->notVoid()->count());
    }

    public function test_generate_bundle_bisa_setelah_semua_cicilan_di_void(): void
    {
        [$student, $enrollment, $invoices] = $this->buatMuridDenganCicilan();

        foreach ($invoices as $invoice) {
            $this->service->voidInvoice($invoice, $this->admin, 'Reset cicilan import salah');
        }

        $this->actingAs($this->admin)
            ->post(route('invoices.generate-bundle', $student), [
                'program_start_date' => '2026-03-01',
            ])
            ->assertRedirect(route('students.show', $student))
            ->assertSessionHas('success');

        $activeInstallments = Invoice::where('student_id', $student->id)
            ->where('payment_mode', 'INSTALLMENT')
            ->notVoid()
            ->get();

        $this->assertCount(3, $activeInstallments);
        $this->assertEquals($enrollment->id, $activeInstallments->first()->enrollment_id);
    }

    /** @return array{0: Student, 1: Enrollment, 2: Invoice[]} */
    private function buatMuridDenganCicilan(): array
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg     = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS_BUNDLE',
            'price_per_month' => 340000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $groupId  = Str::uuid()->toString();
        $invoices = [];
        $offsets  = [0, 1, 3];
        $amounts  = [113333, 113333, 113334];

        foreach ($offsets as $i => $offset) {
            $no     = $i + 1;
            $issued = now()->addMonths($offset)->startOfMonth();
            $invoices[] = Invoice::create([
                'invoice_number'       => 'INV/' . $issued->format('Y/m/') . str_pad((string) $no, 4, '0', STR_PAD_LEFT),
                'student_id'           => $student->id,
                'enrollment_id'        => $enrollment->id,
                'year'                 => $issued->year,
                'month'                => $issued->month,
                'class_type'           => 'KIDS_CLASS_BUNDLE',
                'payment_mode'         => 'INSTALLMENT',
                'installment_number'   => $no,
                'installment_group_id' => $groupId,
                'total_amount'         => $amounts[$i],
                'paid_amount'          => 0,
                'status'               => 'UNPAID',
                'due_date'             => $issued->copy()->setDay(10)->toDateString(),
                'issued_at'            => $issued->toDateString(),
            ]);
        }

        return [$student, $enrollment, $invoices];
    }
}
