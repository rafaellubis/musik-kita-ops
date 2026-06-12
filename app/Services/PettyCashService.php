<?php

namespace App\Services;

use App\Models\PettyCashExpense;
use App\Models\PettyCashTopup;
use Illuminate\Support\Collection;

/**
 * Logika bisnis petty cash terpisah (M07).
 *
 * Saldo = SUM(topup) − SUM(expense). Top-up masuk P&L; expense petty tidak double-hit.
 */
class PettyCashService
{
    /**
     * Saldo petty cash saat ini (semua periode).
     */
    public function getCurrentBalance(): int
    {
        $in  = (int) PettyCashTopup::query()->sum('amount');
        $out = (int) PettyCashExpense::query()->sum('amount');

        return $in - $out;
    }

    /**
     * Saldo sebelum expense tertentu — dipakai saat edit dengan adjustment di task berikutnya.
     */
    public function getBalanceBeforeExpense(?int $excludeExpenseId = null): int
    {
        return $this->getCurrentBalance();
    }

    /**
     * Generate nomor top-up unik: PCU/YYYY/MM/NNNN (reset per bulan).
     */
    public function generateTopupNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $latest = PettyCashTopup::where('topup_number', 'like', "PCU/{$year}/{$monthStr}/%")
            ->orderByDesc('topup_number')
            ->value('topup_number');

        $next = 1;
        if ($latest) {
            $next = ((int) last(explode('/', $latest))) + 1;
        }

        return sprintf('PCU/%d/%s/%04d', $year, $monthStr, $next);
    }

    /**
     * Generate nomor expense unik: PCE/YYYY/MM/NNNN (reset per bulan).
     */
    public function generateExpenseNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $latest = PettyCashExpense::where('expense_number', 'like', "PCE/{$year}/{$monthStr}/%")
            ->orderByDesc('expense_number')
            ->value('expense_number');

        $next = 1;
        if ($latest) {
            $next = ((int) last(explode('/', $latest))) + 1;
        }

        return sprintf('PCE/%d/%s/%04d', $year, $monthStr, $next);
    }

    /**
     * Mutasi petty cash per bulan: top-up + expense, urut tanggal terbaru dulu.
     *
     * @return Collection<int, object{type:string,date:string,number:string,description:string,amount:int,model:PettyCashTopup|PettyCashExpense}>
     */
    public function getMutations(int $year, int $month): Collection
    {
        $topups = PettyCashTopup::forMonth($year, $month)->get()->map(fn ($t) => (object) [
            'type'        => 'topup',
            'date'        => $t->topup_date->toDateString(),
            'number'      => $t->topup_number,
            'description' => $t->description,
            'amount'      => $t->amount,
            'model'       => $t,
        ]);

        $expenses = PettyCashExpense::forMonth($year, $month)->with('category')->get()->map(fn ($e) => (object) [
            'type'        => 'expense',
            'date'        => $e->expense_date->toDateString(),
            'number'      => $e->expense_number,
            'description' => $e->description,
            'amount'      => $e->amount,
            'model'       => $e,
        ]);

        return $topups->concat($expenses)->sortByDesc('date')->values();
    }
}
