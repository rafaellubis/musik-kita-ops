<?php

namespace App\Services;

use App\Models\PettyCashExpense;
use App\Models\PettyCashTopup;
use Carbon\Carbon;
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

    /**
     * Saldo awal bulan = total top-up sebelum bulan ini − total expense sebelum bulan ini.
     */
    public function getOpeningBalanceForMonth(int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $in    = (int) PettyCashTopup::where('topup_date', '<', $start)->sum('amount');
        $out   = (int) PettyCashExpense::where('expense_date', '<', $start)->sum('amount');

        return $in - $out;
    }

    /**
     * Ringkasan petty cash per bulan: saldo awal, mutasi, saldo akhir.
     *
     * @return array{opening_balance:int,total_topup:int,total_expense:int,closing_balance:int}
     */
    public function getMonthSummary(int $year, int $month): array
    {
        $opening = $this->getOpeningBalanceForMonth($year, $month);
        $topup   = (int) PettyCashTopup::forMonth($year, $month)->sum('amount');
        $expense = (int) PettyCashExpense::forMonth($year, $month)->sum('amount');

        return [
            'opening_balance' => $opening,
            'total_topup'     => $topup,
            'total_expense'   => $expense,
            'closing_balance' => $opening + $topup - $expense,
        ];
    }

    /**
     * Mutasi bulan berurutan kronologis dengan kolom debit/kredit dan saldo berjalan.
     */
    public function getMutationsWithRunningBalance(int $year, int $month): Collection
    {
        $balance = $this->getOpeningBalanceForMonth($year, $month);

        return $this->getMutations($year, $month)
            ->sortBy('date')
            ->values()
            ->map(function ($row) use (&$balance) {
                if ($row->type === 'topup') {
                    $balance += $row->amount;
                    $row->debit  = $row->amount;
                    $row->credit = 0;
                } else {
                    $balance -= $row->amount;
                    $row->debit  = 0;
                    $row->credit = $row->amount;
                }
                $row->running_balance = $balance;

                return $row;
            });
    }
}
