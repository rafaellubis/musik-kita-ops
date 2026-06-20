<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\InvoiceReminderLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Carbon\Carbon;

class InvoicePublicPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_access_public_pdf_without_valid_signature(): void
    {
        $student = Student::factory()->create();
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/06/0001',
            'student_id' => $student->id,
            'year' => 2026,
            'month' => 6,
            'total_amount' => 340000,
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'due_date' => '2026-06-30',
            'issued_at' => '2026-06-01',
        ]);

        $response1 = $this->get(route('invoices.public-pdf', $invoice));
        $response1->assertStatus(403);
    }

    public function test_can_access_public_pdf_with_valid_signature(): void
    {
        $student = Student::factory()->create();
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/06/0001',
            'student_id' => $student->id,
            'year' => 2026,
            'month' => 6,
            'total_amount' => 340000,
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'due_date' => '2026-06-30',
            'issued_at' => '2026-06-01',
        ]);

        $signedUrl = URL::signedRoute('invoices.public-pdf', ['invoice' => $invoice->id]);
        
        $response = $this->get($signedUrl);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_invoice_reminder_status_accessor(): void
    {
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('Owner');

        $student = Student::factory()->create();
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/06/0001',
            'student_id' => $student->id,
            'year' => 2026,
            'month' => 6,
            'total_amount' => 340000,
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'due_date' => '2026-06-30',
            'issued_at' => '2026-06-01',
        ]);

        // Kasus 1: Belum pernah dikirim
        $this->assertEquals('Belum', $invoice->reminder_status);

        // Kasus 2: Terkirim Sukses
        $log = InvoiceReminderLog::create([
            'student_id' => $student->id,
            'sent_by' => $user->id,
            'phone' => '0812345678',
            'message_body' => 'Test',
            'invoice_ids' => [$invoice->id],
            'status' => InvoiceReminderLog::STATUS_SUCCESS,
            'sent_at' => now(),
        ]);

        $this->assertEquals('Terkirim', $invoice->fresh()->reminder_status);

        // Kasus 3: Pengiriman Gagal
        $log->update(['status' => InvoiceReminderLog::STATUS_FAILED]);
        $this->assertEquals('Gagal', $invoice->fresh()->reminder_status);
    }

    /** Invoice yang sudah void harus mengembalikan 410 Gone. */
    public function test_cannot_access_voided_invoice_pdf(): void
    {
        $student = Student::factory()->create();
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/06/0002',
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 6,
            'total_amount'   => 340000,
            'paid_amount'    => 0,
            'status'         => 'UNPAID',
            'due_date'       => '2026-06-30',
            'issued_at'      => '2026-06-01',
            'voided_at'      => now(), // invoice sudah di-void
        ]);

        // Signed URL valid, tapi invoice void → 410 Gone
        $signedUrl = URL::temporarySignedRoute('invoices.public-pdf', now()->addDays(30), ['invoice' => $invoice->id]);
        $response  = $this->get($signedUrl);
        $response->assertStatus(410);
    }

    /** Link yang sudah kadaluarsa harus mengembalikan 403. */
    public function test_expired_signed_url_returns_403(): void
    {
        $student = Student::factory()->create();
        $invoice = Invoice::create([
            'invoice_number' => 'INV/2026/06/0003',
            'student_id'     => $student->id,
            'year'           => 2026,
            'month'          => 6,
            'total_amount'   => 340000,
            'paid_amount'    => 0,
            'status'         => 'UNPAID',
            'due_date'       => '2026-06-30',
            'issued_at'      => '2026-06-01',
        ]);

        // Buat URL yang sudah kadaluarsa sejak 1 detik lalu
        $expiredUrl = URL::temporarySignedRoute('invoices.public-pdf', now()->subSecond(), ['invoice' => $invoice->id]);
        $response   = $this->get($expiredUrl);
        $response->assertStatus(403);
    }
}
