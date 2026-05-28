<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KidsFpInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    // ===== Helper =====

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Admin');
        return $user;
    }

    private function owner(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Owner');
        return $user;
    }

    /**
     * Buat murid KIDS_CLASS aktif dengan primary enrollment.
     * Mengembalikan ['student', 'package', 'enrollment'].
     */
    private function makeKidsClassStudent(): array
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

        return compact('student', 'package', 'enrollment');
    }

    // ===== Tests =====

    public function test_admin_bisa_generate_kids_fp_invoice(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertRedirect();

        $invoice = Invoice::where('student_id', $student->id)->first();
        $this->assertNotNull($invoice, 'Invoice KIDS_FP tidak dibuat.');
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'item_code'  => 'KIDS_FP',
            'amount'     => InvoiceService::FEE_KIDS_FP,
        ]);
    }

    public function test_owner_bisa_generate_kids_fp_invoice(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        $this->actingAs($this->owner())
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'class_type' => 'KIDS_CLASS',
        ]);
    }

    public function test_auditor_tidak_bisa_generate_kids_fp(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        $auditor = User::factory()->create(['is_active' => true]);
        $auditor->assignRole('Auditor');

        $this->actingAs($auditor)
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertForbidden();
    }

    public function test_generate_gagal_jika_bukan_kids_class(): void
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $package    = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 370000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertForbidden();
    }

    public function test_generate_gagal_jika_kids_fp_sudah_ada(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        // Buat KIDS_FP pertama — harus berhasil
        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student));

        // Coba generate kedua kali — harus gagal
        $response = $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Hanya ada 1 invoice KIDS_FP untuk murid ini
        $this->assertEquals(1,
            Invoice::where('student_id', $student->id)
                ->whereHas('items', fn($q) => $q->where('item_code', 'KIDS_FP'))
                ->count()
        );
    }

    public function test_invoice_yang_dibuat_memiliki_data_benar(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->makeKidsClassStudent();

        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student));

        $invoice = Invoice::where('student_id', $student->id)->first();
        $this->assertEquals('KIDS_CLASS', $invoice->class_type);
        $this->assertEquals(InvoiceService::FEE_KIDS_FP, $invoice->total_amount);
        $this->assertEquals('UNPAID', $invoice->status);
        $this->assertEquals($enrollment->id, $invoice->enrollment_id);

        $item = $invoice->items->first();
        $this->assertEquals('KIDS_FP', $item->item_code);
        $this->assertEquals(InvoiceService::FEE_KIDS_FP, $item->amount);
        $this->assertEquals('Final Project Kids Class', $item->description);
    }

    public function test_setelah_generate_redirect_ke_invoice_show(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        $response = $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student));

        $invoice = Invoice::where('student_id', $student->id)->first();
        $response->assertRedirect(route('invoices.show', $invoice));
    }

    public function test_generate_gagal_jika_kids_class_bundle(): void
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $package    = Package::factory()->create(['class_type' => 'KIDS_CLASS_BUNDLE']);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertForbidden();
    }
}
