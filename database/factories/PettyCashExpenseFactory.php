<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\PettyCashExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model PettyCashExpense — pengeluaran petty cash (M07).
 *
 * @extends Factory<PettyCashExpense>
 */
class PettyCashExpenseFactory extends Factory
{
    protected $model = PettyCashExpense::class;

    public function definition(): array
    {
        $category = ExpenseCategory::firstOrCreate(
            ['code' => 'ATK'],
            ['name' => 'Alat Tulis', 'is_active' => true, 'sort_order' => 7]
        );

        $year     = now()->year;
        $month    = now()->month;
        $monthPad = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $seq      = str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'expense_number'       => "PCE/{$year}/{$monthPad}/{$seq}",
            'expense_category_id'  => $category->id,
            'amount'               => $this->faker->numberBetween(10_000, 500_000),
            'description'          => $this->faker->sentence(3),
            'expense_date'         => now()->toDateString(),
            'receipt_image'        => null,
            'notes'                => null,
            'created_by'           => null,
        ];
    }
}
