<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nomor top-up penyesuaian saldo awal hasil migrasi legacy CASH.
     */
    private const MIGRATION_TOPUP_NUMBER = 'PCU/2026/06/0001';

    public function up(): void
    {
        DB::transaction(function (): void {
            $cashExpenses = DB::table('expenses')
                ->where('payment_method', 'CASH')
                ->orderBy('id')
                ->get();

            $totalMigrated = 0;

            foreach ($cashExpenses as $row) {
                DB::table('petty_cash_expenses')->insert([
                    'expense_number'      => str_replace('EXP/', 'PCE/', $row->expense_number),
                    'expense_category_id' => $row->expense_category_id,
                    'amount'              => $row->amount,
                    'description'         => $row->description,
                    'expense_date'        => $row->expense_date,
                    'receipt_image'       => $row->receipt_image,
                    'notes'               => $row->notes,
                    'created_by'          => $row->created_by,
                    'created_at'          => $row->created_at,
                    'updated_at'          => $row->updated_at,
                ]);

                $totalMigrated += (int) $row->amount;
            }

            if ($totalMigrated > 0) {
                // Top-up penyesuaian agar saldo petty cash tidak negatif setelah migrasi
                DB::table('petty_cash_topups')->insert([
                    'topup_number'  => self::MIGRATION_TOPUP_NUMBER,
                    'amount'        => $totalMigrated,
                    'topup_date'    => now()->toDateString(),
                    'description'   => 'Saldo awal migrasi dari expenses CASH',
                    'notes'         => 'Auto-generated migration',
                    'created_by'    => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            DB::table('expenses')->where('payment_method', 'CASH')->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $topup = DB::table('petty_cash_topups')
                ->where('topup_number', self::MIGRATION_TOPUP_NUMBER)
                ->where('notes', 'Auto-generated migration')
                ->first();

            if ($topup === null) {
                return;
            }

            $hasOtherTopups = DB::table('petty_cash_topups')
                ->where('id', '!=', $topup->id)
                ->exists();

            $pettyExpenseTotal = (int) DB::table('petty_cash_expenses')->sum('amount');

            // Rollback aman hanya jika belum ada data petty cash baru setelah migrasi
            if ($hasOtherTopups || $pettyExpenseTotal !== (int) $topup->amount) {
                throw new RuntimeException(
                    'Rollback migrasi petty cash ditolak: data petty cash sudah berubah setelah migrasi.'
                );
            }

            $pettyExpenses = DB::table('petty_cash_expenses')
                ->orderBy('id')
                ->get();

            foreach ($pettyExpenses as $row) {
                $expenseNumber = str_replace('PCE/', 'EXP/', $row->expense_number);

                if (DB::table('expenses')->where('expense_number', $expenseNumber)->exists()) {
                    continue;
                }

                DB::table('expenses')->insert([
                    'expense_number'      => $expenseNumber,
                    'expense_category_id' => $row->expense_category_id,
                    'amount'              => $row->amount,
                    'description'         => $row->description,
                    'expense_date'        => $row->expense_date,
                    'payment_method'      => 'CASH',
                    'receipt_image'       => $row->receipt_image,
                    'notes'               => $row->notes,
                    'created_by'          => $row->created_by,
                    'created_at'          => $row->created_at,
                    'updated_at'          => $row->updated_at,
                ]);
            }

            DB::table('petty_cash_expenses')->delete();
            DB::table('petty_cash_topups')->where('id', $topup->id)->delete();
        });
    }
};
