<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Invoice — digunakan di test feature M05.
 * Menghasilkan invoice SPP standar yang valid sesuai skema tabel invoices.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $year  = now()->year;
        $month = now()->month;

        return [
            'invoice_number' => 'INV/' . $year . '/' . str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'student_id'     => Student::factory(),
            'year'           => $year,
            'month'          => $month,
            'description'    => 'SPP Bulanan',
            'total_amount'   => 340000,
            'paid_amount'    => 0,
            'status'         => 'UNPAID',
            'due_date'       => now()->setDay(10)->toDateString(),
            'issued_at'      => now()->startOfMonth()->toDateString(),
        ];
    }
}
