<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceReminderLog;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsappMessageTemplate;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->admin = User::factory()->create()->assignRole('Admin');
        $this->auditor = User::factory()->create()->assignRole('Auditor');

        WhatsappMessageTemplate::create([
            'code'       => WhatsappMessageTemplate::CODE_INVOICE_REMINDER,
            'name'       => 'Reminder',
            'body'       => 'Halo {nama_ortu}, tagihan {nama_murid} total {total_tagihan}',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        config([
            'services.wablas.token'      => 'test-token',
            'services.wablas.secret_key' => 'test-secret',
            'services.wablas.base_url'   => 'https://solo.wablas.com',
        ]);
    }

    private function fakeWablas(): void
    {
        Http::fake([
            'https://solo.wablas.com/api/send-message' => Http::sequence()
                ->push(['status' => true, 'id' => 'text-1'], 200)
                ->push(['status' => true, 'id' => 'text-2'], 200),
            'https://solo.wablas.com/api/send-document-from-local' => Http::sequence()
                ->push(['status' => true, 'id' => 'doc-1'], 200)
                ->push(['status' => true, 'id' => 'doc-2'], 200)
                ->push(['status' => true, 'id' => 'doc-3'], 200),
        ]);
    }

    private function studentWithUnpaidInvoice(string $phone = '0816920592'): array
    {
        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_name'  => 'Budi Ortu',
            'parent_phone' => $phone,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/06/0100',
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 6,
            'total_amount'   => 340000,
            'paid_amount'    => 0,
            'status'         => Invoice::STATUS_UNPAID,
            'due_date'       => now()->addDays(5)->toDateString(),
            'issued_at'      => now()->toDateString(),
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'item_code'   => 'SPP',
            'description' => 'SPP Juni',
            'amount'      => 340000,
        ]);

        return [$student, $invoice];
    }

    public function test_index_200_for_admin(): void
    {
        $this->studentWithUnpaidInvoice();

        $this->actingAs($this->admin)
            ->get(route('invoice-reminders.index'))
            ->assertOk()
            ->assertSee('Reminder WA Tagihan');
    }

    public function test_index_403_for_auditor(): void
    {
        $this->actingAs($this->auditor)
            ->get(route('invoice-reminders.index'))
            ->assertForbidden();
    }

    public function test_send_creates_log_and_audit(): void
    {
        $this->fakeWablas();
        [$student] = $this->studentWithUnpaidInvoice();

        $response = $this->actingAs($this->admin)
            ->post(route('invoice-reminders.send'), [
                'student_ids' => [$student->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('invoice_reminder_logs', [
            'student_id' => $student->id,
            'sent_by'    => $this->admin->id,
            'status'     => InvoiceReminderLog::STATUS_SUCCESS,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => AuditLog::ACTION_CREATE,
            'entity_label' => 'Batch Reminder WA Tagihan',
        ]);

        Http::assertSentCount(2); // 1 text + 1 pdf
    }

    public function test_send_multi_class_sends_one_text_and_two_pdfs(): void
    {
        Http::fake([
            'https://solo.wablas.com/api/send-message' => Http::response(['status' => true, 'id' => 't1'], 200),
            'https://solo.wablas.com/api/send-document-from-local' => Http::response(['status' => true, 'id' => 'd1'], 200),
        ]);

        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_phone' => '081234567890',
            'parent_name'  => 'Ortu',
        ]);

        $pkg1 = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
        $pkg2 = Package::factory()->create(['class_type' => 'HOBBY', 'price_per_month' => 390000]);
        $e1 = Enrollment::factory()->for($student)->create(['package_id' => $pkg1->id, 'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($student)->create(['package_id' => $pkg2->id, 'status' => 'ACTIVE']);

        foreach ([
            ['INV/2026/06/0001', $e1->id, 340000],
            ['INV/2026/06/0002', $e2->id, 390000],
        ] as [$num, $enrollmentId, $amount]) {
            $inv = Invoice::create([
                'invoice_number' => $num,
                'student_id'     => $student->id,
                'enrollment_id'  => $enrollmentId,
                'year'           => 2026,
                'month'          => 6,
                'total_amount'   => $amount,
                'paid_amount'    => 0,
                'status'         => Invoice::STATUS_UNPAID,
                'due_date'       => now()->addDays(10)->toDateString(),
                'issued_at'      => now()->toDateString(),
            ]);
            InvoiceItem::create([
                'invoice_id' => $inv->id,
                'item_code'  => 'SPP',
                'description' => 'SPP',
                'amount'     => $amount,
            ]);
        }

        $this->actingAs($this->owner)
            ->post(route('invoice-reminders.send'), ['student_ids' => [$student->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'send-message');
        });
        Http::assertSentCount(3);

        $log = InvoiceReminderLog::where('student_id', $student->id)->first();
        $this->assertEquals(2, $log->documents_total);
        $this->assertEquals(2, $log->documents_sent);
    }

    public function test_pdf_service_returns_non_empty_bytes(): void
    {
        [, $invoice] = $this->studentWithUnpaidInvoice();

        $pdf = app(InvoicePdfService::class)->renderPdf(
            $invoice->load(['student', 'items' => fn ($q) => $q->whereNull('parent_item_id')])
        );

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_whatsapp_template_crud_owner_only(): void
    {
        $this->actingAs($this->owner)
            ->get(route('whatsapp-templates.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('whatsapp-templates.create'))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('whatsapp-templates.store'), [
                'code'       => 'CUSTOM_REMINDER',
                'name'       => 'Custom',
                'body'       => 'Pesan {nama_murid}',
                'sort_order' => 2,
                'is_active'  => 1,
            ])
            ->assertRedirect(route('whatsapp-templates.index'));

        $this->assertDatabaseHas('whatsapp_message_templates', ['code' => 'CUSTOM_REMINDER']);
    }

    public function test_send_rejects_student_without_valid_phone(): void
    {
        [$student] = $this->studentWithUnpaidInvoice('invalid');

        $this->actingAs($this->admin)
            ->post(route('invoice-reminders.send'), ['student_ids' => [$student->id]])
            ->assertSessionHasErrors('student_ids');
    }
}
