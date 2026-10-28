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
        config([
            'services.fonnte.token'      => 'test-token',
            'services.fonnte.base_url'   => 'https://api.fonnte.com',
        ]);
    }

    private function fakeFonnte(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::sequence()
                ->push(['status' => true, 'id' => ['text-1']], 200)
                ->push(['status' => true, 'id' => ['text-2']], 200)
                ->push(['status' => true, 'id' => ['text-3']], 200),
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

    public function test_manual_reminder_uses_gowa_and_records_provider_ids(): void
    {
        foreach (['integration_whatsapp_provider' => 'gowa', 'integration_gowa_base_url' => 'http://gowa.test',
            'integration_gowa_username' => 'studio', 'integration_gowa_device_id' => 'studio'] as $key => $value) {
            \App\Models\Setting::set($key, $value);
        }
        \App\Services\IntegrationSettings::set('integration_gowa_password', 'secret');
        Http::preventStrayRequests();
        Http::fake(['http://gowa.test/send/message' => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'invoice-gowa']])]);
        [$student] = $this->studentWithUnpaidInvoice();
        $summary = app(\App\Services\InvoiceReminderService::class)->sendBatch([$student->id], $this->owner);
        $this->assertSame(1, $summary['success']);
        $log = InvoiceReminderLog::firstOrFail();
        $this->assertSame('gowa', $log->provider);
        $this->assertSame(['invoice-gowa'], $log->provider_message_ids);
    }

    public function test_preparation_uses_parent_and_explicit_template_without_sending(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        [$student, $invoice] = $this->studentWithUnpaidInvoice();
        $template = new WhatsappMessageTemplate(['body' => 'Khusus {nama_murid}: {total_tagihan}']);

        $prepared = app(\App\Services\InvoiceReminderService::class)
            ->prepareReminder($student, collect([$invoice]), $template);

        $this->assertSame('62816920592', $prepared['phone']);
        $this->assertSame("Khusus {$student->full_name}: Rp 340.000", $prepared['message']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('invoice_reminder_logs', 0);
    }

    public function test_preview_parent_contains_complete_message_without_side_effects(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fake();
        [$student, $invoice] = $this->studentWithUnpaidInvoice();
        WhatsappMessageTemplate::where('code', WhatsappMessageTemplate::CODE_INVOICE_REMINDER)
            ->update(['body' => 'Halo {nama_ortu}, {nama_murid}: {total_tagihan}' . "\n{daftar_invoice}"]);

        $row = $this->actingAs($this->admin)->get(route('invoice-reminders.index'))
            ->assertOk()->viewData('rows')->first();

        $this->assertSame('62816920592', $row->phone_normalized);
        $this->assertNull($row->preview_error);
        $this->assertStringContainsString("Halo Budi Ortu, {$student->full_name}: Rp 340.000", $row->preview_message);
        $this->assertStringContainsString($invoice->invoice_number, $row->preview_message);
        $this->assertStringContainsString(\Illuminate\Support\Facades\URL::temporarySignedRoute(
            'invoices.public-pdf', now()->addDays(30), ['invoice' => $invoice->id],
        ), $row->preview_message);
        Http::assertNothingSent();
        $this->assertDatabaseCount('invoice_reminder_logs', 0);
    }

    public function test_preview_student_fallback_matches_manual_send(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $this->fakeFonnte();
        [$student] = $this->studentWithUnpaidInvoice('invalid');
        $student->update(['phone' => '081234567890']);
        WhatsappMessageTemplate::create([
            'code' => WhatsappMessageTemplate::CODE_INVOICE_REMINDER_STUDENT,
            'name' => 'Sapaan murid',
            'body' => 'Hai {nama_murid}, tagihanmu {total_tagihan}' . "\n{daftar_invoice}",
            'is_active' => true,
        ]);
        $row = $this->actingAs($this->admin)->get(route('invoice-reminders.index'))
            ->assertOk()->viewData('rows')->first();
        $this->assertSame('6281234567890', $row->phone_normalized);
        $this->assertStringStartsWith("Hai {$student->full_name}, tagihanmu Rp 340.000", $row->preview_message);
        Http::assertNothingSent();
        $this->post(route('invoice-reminders.send'), ['student_ids' => [$student->id]])
            ->assertSessionHas('success');
        $log = InvoiceReminderLog::where('student_id', $student->id)->sole();
        $this->assertSame($row->preview_message, $log->message_body);
        $this->assertSame($row->phone_normalized, $log->phone);
    }

    public function test_overdue_preview_includes_all_unpaid_but_not_paid_invoices(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $this->fakeFonnte();
        [$student, $overdue] = $this->studentWithUnpaidInvoice();
        $overdue->update(['due_date' => now()->subDay(), 'paid_amount' => 40000, 'status' => Invoice::STATUS_PARTIAL]);
        $future = $overdue->replicate();
        $future->fill(['invoice_number' => 'INV/2026/06/0101', 'due_date' => now()->addDays(5),
            'total_amount' => 390000, 'paid_amount' => 0, 'status' => Invoice::STATUS_UNPAID])->save();
        $paid = $overdue->replicate();
        $paid->fill(['invoice_number' => 'INV/2026/06/0102', 'paid_amount' => 340000,
            'status' => Invoice::STATUS_PAID])->save();
        WhatsappMessageTemplate::where('code', WhatsappMessageTemplate::CODE_INVOICE_REMINDER)
            ->update(['body' => '{jumlah_invoice} invoice, total {total_tagihan}' . "\n{daftar_invoice}"]);
        $row = $this->actingAs($this->owner)->get(route('invoice-reminders.index', ['overdue_only' => 1]))
            ->assertOk()->viewData('rows')->first();
        $this->assertSame(1, $row->invoice_count);
        $this->assertStringStartsWith('2 invoice, total Rp 690.000', $row->preview_message);
        $this->assertStringContainsString('sisa Rp 300.000', $row->preview_message);
        $this->assertStringContainsString($future->invoice_number, $row->preview_message);
        $this->assertStringNotContainsString($paid->invoice_number, $row->preview_message);
        $this->post(route('invoice-reminders.send'), ['student_ids' => [$student->id]])
            ->assertSessionHas('success');
        $this->assertSame($row->preview_message, InvoiceReminderLog::where('student_id', $student->id)->sole()->message_body);
    }

    public function test_preview_missing_or_inactive_template_keeps_row_without_sending(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->studentWithUnpaidInvoice();
        WhatsappMessageTemplate::query()->update(['is_active' => false]);
        foreach ([false, true] as $missing) {
            if ($missing) {
                WhatsappMessageTemplate::query()->delete();
            }
            $row = $this->actingAs($this->owner)->get(route('invoice-reminders.index'))
                ->assertOk()->viewData('rows')->first();
            $this->assertNull($row->preview_message);
            $this->assertSame('Template reminder aktif tidak ditemukan.', $row->preview_error);
            $this->assertSame($missing ? 0 : 1, WhatsappMessageTemplate::count());
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('invoice_reminder_logs', 0);
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
        $this->fakeFonnte();
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

        Http::assertSentCount(1);
    }

    public function test_send_multi_class_sends_one_text(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['t1']], 200),
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
            return str_contains($request->url(), '/send');
        });
        Http::assertSentCount(1);

        $log = InvoiceReminderLog::where('student_id', $student->id)->first();
        $this->assertEquals(0, $log->documents_total);
        $this->assertEquals(0, $log->documents_sent);
    }

    public function test_pdf_service_returns_non_empty_bytes(): void
    {
        [, $invoice] = $this->studentWithUnpaidInvoice();

        $pdf = app(InvoicePdfService::class)->renderPdf(
            $invoice->load(['student', 'items' => fn ($q) => $q->whereNull('parent_item_id')])
        );

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        // Font subsetting aktif — invoice sederhana harus jauh di bawah 200 KB
        $this->assertLessThan(200_000, strlen($pdf), 'PDF invoice terlalu besar; cek font subsetting DomPDF.');
    }

    public function test_index_provisions_system_templates_when_database_empty(): void
    {
        WhatsappMessageTemplate::query()->delete();

        $this->actingAs($this->owner)
            ->get(route('whatsapp-templates.index'))
            ->assertOk()
            ->assertSee('SESSION_REPORT')
            ->assertSee('SESSION_REPORT_STUDENT')
            ->assertSee('INVOICE_REMINDER');

        $this->assertDatabaseCount('whatsapp_message_templates', 5);
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
