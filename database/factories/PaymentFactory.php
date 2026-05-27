<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Payment — digunakan di test feature M05 dan laporan keuangan.
 * Menghasilkan satu baris pembayaran valid sesuai skema tabel payments.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $year  = now()->year;
        $month = now()->month;

        $monthPad = str_pad($month, 2, '0', STR_PAD_LEFT);
        $seq      = str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            // Format: KW/YYYY/MM/NNNN — sama dengan receipt_number di sistem nyata
            'receipt_number' => "KW/{$year}/{$monthPad}/{$seq}",
            'invoice_id'     => Invoice::factory(),
            'amount'         => $this->faker->numberBetween(100000, 500000),
            'method'         => $this->faker->randomElement(['CASH', 'TRANSFER', 'QRIS', 'DEBIT']),
            'payment_date'   => now()->toDateString(),
            'proof_image'    => null,
            'notes'          => null,
            'voided_at'      => null,
            'voided_by'      => null,
            'voided_reason'  => null,
            'created_by'     => null,
        ];
    }
}
