<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoicePrintPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_uses_recorded_payment_method_and_excludes_voided_payments(): void
    {
        foreach (['Owner', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $this->actingAs(User::factory()->create()->assignRole($role));

            foreach (['CASH', 'TRANSFER', 'QRIS', 'DEBIT'] as $method) {
                $invoice = Invoice::create([
                    'invoice_number' => 'INV/2026/10/' . ($role === 'Owner' ? 'O' : 'A') . $method,
                    'student_id' => Student::factory()->create()->id,
                    'year' => 2026,
                    'month' => 10,
                    'total_amount' => 340000,
                    'paid_amount' => 0,
                    'status' => 'UNPAID',
                    'due_date' => now()->toDateString(),
                    'issued_at' => now()->toDateString(),
                ]);

                $this->get(route('invoices.print', $invoice))
                    ->assertOk()
                    ->assertSee('Metode Pembayaran: <strong>—</strong>', false);

                $this->post(route('payments.store', $invoice), [
                    'amount' => 340000,
                    'method' => $method,
                    'payment_date' => now()->toDateString(),
                ])->assertSessionHasNoErrors();

                $payment = $invoice->payments()->sole();
                $this->assertSame($method, $payment->method);
                $this->get(route('invoices.print', $invoice))
                    ->assertOk()
                    ->assertSee("Metode Pembayaran: <strong>{$method}</strong>", false);

                // Pembayaran yang dibatalkan tidak boleh dianggap sebagai metode pembayaran invoice.
                $payment->update(['voided_at' => now()]);
                $this->get(route('invoices.print', $invoice))
                    ->assertOk()
                    ->assertSee('Metode Pembayaran: <strong>—</strong>', false);
            }
        }
    }
}
