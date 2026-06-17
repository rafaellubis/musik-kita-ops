<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceReminderLog;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsappMessageTemplate;
use App\Services\InvoiceReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledInvoiceReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->owner = User::factory()->create()->assignRole('Owner');

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

        Setting::set('scheduler_invoice_reminder_enabled', 'true');
        Setting::set('scheduler_invoice_reminder_days_before_due', '3');
        Setting::set('scheduler_invoice_reminder_time', '10:00');
    }

    private function fakeWablasTextOnly(): void
    {
        Http::fake([
            'https://solo.wablas.com/api/send-message' => Http::response(['status' => true, 'id' => 'text-1'], 200),
            'https://solo.wablas.com/api/send-document-from-local' => Http::response(['status' => true, 'id' => 'doc-1'], 200),
        ]);
    }

    private function createEligibleInvoice(Student $student, string $dueDate): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/06/0' . $student->id,
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 6,
            'total_amount'   => 340_000,
            'paid_amount'    => 0,
            'status'         => Invoice::STATUS_UNPAID,
            'due_date'       => $dueDate,
            'issued_at'      => now()->toDateString(),
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'item_code'   => 'SPP',
            'description' => 'SPP Juni',
            'amount'      => 340_000,
        ]);

        return $invoice;
    }

    public function test_due_date_today_plus_n_eligible_hari_ini(): void
    {
        $this->fakeWablasTextOnly();
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_phone' => '0816920592',
        ]);

        $this->createEligibleInvoice($student, '2026-06-18'); // today + 3

        $this->artisan('invoice-reminders:send-scheduled')->assertSuccessful();

        $this->assertDatabaseHas('invoice_reminder_logs', [
            'student_id'      => $student->id,
            'sent_by'         => $this->owner->id,
            'status'          => InvoiceReminderLog::STATUS_SUCCESS,
            'documents_sent'  => 0,
            'documents_total' => 0,
        ]);

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'send-document'));

        Carbon::setTestNow();
    }

    public function test_due_date_today_plus_5_tidak_eligible_saat_n_3(): void
    {
        $this->fakeWablasTextOnly();
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_phone' => '0816920592',
        ]);

        $this->createEligibleInvoice($student, '2026-06-20'); // today + 5

        $this->artisan('invoice-reminders:send-scheduled')->assertSuccessful();

        $this->assertDatabaseMissing('invoice_reminder_logs', [
            'student_id' => $student->id,
        ]);

        Http::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_run_kedua_bulan_sama_dilewati(): void
    {
        $this->fakeWablasTextOnly();
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_phone' => '0816920592',
        ]);

        $this->createEligibleInvoice($student, '2026-06-18');

        $this->artisan('invoice-reminders:send-scheduled')->assertSuccessful();
        $this->artisan('invoice-reminders:send-scheduled')->assertSuccessful();

        $this->assertEquals(1, InvoiceReminderLog::where('student_id', $student->id)->count());
        Http::assertSentCount(1);

        Carbon::setTestNow();
    }

    public function test_dry_run_tidak_kirim_wa(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_phone' => '0816920592',
        ]);

        $this->createEligibleInvoice($student, '2026-06-18');

        $this->artisan('invoice-reminders:send-scheduled --dry-run')->assertSuccessful();

        $this->assertDatabaseMissing('invoice_reminder_logs', [
            'student_id' => $student->id,
        ]);

        Http::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_bukan_jam_konfigurasi_dilewati(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));

        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_phone' => '0816920592',
        ]);

        $this->createEligibleInvoice($student, '2026-06-18');

        $service = app(InvoiceReminderService::class);
        $summary = $service->sendScheduledReminders(now());

        $this->assertTrue($summary['skipped_time']);
        $this->assertEquals(0, $summary['sent']);

        Carbon::setTestNow();
    }

    public function test_kirim_berhasil_mencatat_audit_log(): void
    {
        $this->fakeWablasTextOnly();
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_phone' => '0816920592',
        ]);

        $this->createEligibleInvoice($student, '2026-06-18');

        $this->artisan('invoice-reminders:send-scheduled')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'entity_label' => 'Scheduler Reminder WA Tagihan',
            'action'       => AuditLog::ACTION_CREATE,
        ]);

        Carbon::setTestNow();
    }
}
