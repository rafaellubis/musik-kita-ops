<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KidsBundleInstallmentUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    // ─── Helper: buat murid + enrollment KIDS_CLASS_BUNDLE + 3 invoice cicilan ───

    // Helper: buat murid + enrollment + 3 invoice cicilan INSTALLMENT
    // amounts: 340.000 / 3 → 113.333 + 113.333 + 113.334 (sisa 1 ke termin ke-3)
    private function buatMuridDenganCicilan(): array
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS_BUNDLE',
            'price_per_month' => 340000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $groupId = Str::uuid()->toString();
        $invoices = [];
        $offsets  = [0, 1, 3];
        $amounts  = [113333, 113333, 113334];
        foreach ($offsets as $i => $offset) {
            $no     = $i + 1;
            $issued = now()->addMonths($offset)->startOfMonth();
            $invoices[] = Invoice::create([
                'invoice_number'       => 'INV/' . $issued->format('Y') . '/' . $issued->format('m') . '/' . fake()->unique()->numerify('####'),
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

    // Helper: buat murid + enrollment + 1 invoice FULL (bukan cicilan)
    private function buatMuridDenganBundleFull(): array
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS_BUNDLE',
            'price_per_month' => 340000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV/' . now()->format('Y') . '/' . now()->format('m') . '/' . fake()->unique()->numerify('####'),
            'student_id'     => $student->id,
            'enrollment_id'  => $enrollment->id,
            'year'           => now()->year,
            'month'          => now()->month,
            'class_type'     => 'KIDS_CLASS_BUNDLE',
            'payment_mode'   => 'FULL',
            'total_amount'   => 340000,
            'paid_amount'    => 0,
            'status'         => 'UNPAID',
            'due_date'       => now()->setDay(10)->toDateString(),
            'issued_at'      => now()->startOfMonth()->toDateString(),
        ]);

        return [$student, $enrollment, $invoice];
    }

    /** Invoice index: badge Termin X/3 muncul untuk invoice cicilan */
    public function test_invoice_index_menampilkan_badge_termin_untuk_cicilan(): void
    {
        [$student, , $invoices] = $this->buatMuridDenganCicilan();

        $response = $this->actingAs($this->admin)
            ->get(route('invoices.index', [
                'year'  => now()->year,
                'month' => now()->month,
            ]));

        $response->assertOk();
        $response->assertSee('Kids Bundle');
        $response->assertSee('Termin 1/3');
    }

    /** Invoice index: invoice KIDS_CLASS_BUNDLE FULL menampilkan badge "Kids Bundle – Lunas" */
    public function test_invoice_index_menampilkan_badge_lunas_untuk_bundle_full(): void
    {
        $this->buatMuridDenganBundleFull();

        $response = $this->actingAs($this->admin)
            ->get(route('invoices.index', [
                'year'  => now()->year,
                'month' => now()->month,
            ]));

        $response->assertOk();
        $response->assertSee('Kids Bundle – Lunas');
    }

    /** Invoice show: panel progress cicilan muncul untuk invoice installment */
    public function test_invoice_show_menampilkan_panel_progress_cicilan(): void
    {
        [$student, , $invoices] = $this->buatMuridDenganCicilan();

        $response = $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoices[1]->id)); // buka Termin 2

        $response->assertOk();
        $response->assertSee('Cicilan Kids Class Bundle');
        $response->assertSee('Termin 1/3');
        $response->assertSee('Termin 2/3');
        $response->assertSee('Termin 3/3');
        $response->assertViewHas('siblings');
    }

    /** Student show: kartu cicilan muncul di tab tagihan untuk murid Kids Bundle */
    public function test_student_show_menampilkan_kartu_cicilan_kids_bundle(): void
    {
        [$student, , $invoices] = $this->buatMuridDenganCicilan();

        $response = $this->actingAs($this->admin)
            ->get(route('students.show', $student->id));

        $response->assertOk();
        $response->assertSee('Cicilan Kids Class Bundle');
        $response->assertViewHas('kidsInstallments');

        // Pastikan 3 invoice ada di collection
        $kidsInstallments = $response->viewData('kidsInstallments');
        $this->assertCount(3, $kidsInstallments);
    }
}
