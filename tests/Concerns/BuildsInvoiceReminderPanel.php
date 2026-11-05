<?php

namespace Tests\Concerns;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceReminderLog;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

trait BuildsInvoiceReminderPanel
{
    protected User $owner;
    protected Student $student;
    protected Invoice $source;
    protected Invoice $sibling;

    protected function setUpPanel(): void
    {
        $this->freezeTime();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->student = Student::factory()->create(['parent_name' => 'Ibu Budi', 'parent_phone' => '081234567890', 'phone' => null]);
        $this->source = $this->panelInvoice($this->student, 340000);
        $this->sibling = $this->panelInvoice($this->student, 390000);
        foreach (['INVOICE_REMINDER' => 'Yth {nama_ortu}', 'INVOICE_REMINDER_STUDENT' => 'Halo {nama_murid}'] as $code => $greeting) {
            WhatsappMessageTemplate::create(['code' => $code, 'name' => $code, 'is_active' => true,
                'body' => $greeting."\n{daftar_invoice}\nTotal {total_tagihan}"]);
        }
        config(['services.fonnte.token' => 'test-token', 'services.fonnte.base_url' => 'https://api.fonnte.com']);
        Http::preventStrayRequests();
        Http::fake(['https://api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['panel-1']])]);
    }

    protected function panelInvoice(Student $student, int $amount): Invoice
    {
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);
        $invoice = Invoice::create(['student_id' => $student->id, 'enrollment_id' => $enrollment->id,
            'invoice_number' => 'INV/2026/11/'.sprintf('%04d', $enrollment->id), 'month' => 11, 'year' => 2026,
            'total_amount' => $amount, 'paid_amount' => 0, 'status' => 'UNPAID',
            'due_date' => '2026-11-10', 'issued_at' => '2026-11-01']);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_code' => 'SPP', 'description' => 'SPP November', 'amount' => $amount]);
        return $invoice;
    }

    protected function panelLog(array $ids, string $status = 'SUCCESS', string $message = 'Pesan historis'): InvoiceReminderLog
    {
        return InvoiceReminderLog::create(['student_id' => $this->student->id, 'sent_by' => $this->owner->id,
            'phone' => '6281234567890', 'message_body' => $message, 'invoice_ids' => $ids,
            'provider' => 'fonnte', 'status' => $status, 'sent_at' => now()]);
    }

    protected function panelPreview(array $data = []): array
    {
        return $this->actingAs($this->owner)->postJson(route('invoices.reminder.preview', $this->source), $data)->assertOk()->json();
    }
}
