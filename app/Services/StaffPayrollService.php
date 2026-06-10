<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\StaffPayrollItem;
use App\Models\StaffPayrollSlip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logika bisnis slip gaji karyawan non-guru (M12).
 */
class StaffPayrollService
{
    /**
     * Generate slip DRAFT untuk semua karyawan aktif di bulan tertentu.
     * Idempotent — slip PAID tidak diubah.
     */
    public function generateAllSlips(int $year, int $month, int $userId): array
    {
        $created = 0;
        $skipped = 0;
        $updated = 0;

        $employees = Employee::active()->orderBy('full_name')->get();

        foreach ($employees as $employee) {
            $existing = StaffPayrollSlip::where('employee_id', $employee->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existing) {
                if ($existing->status === StaffPayrollSlip::STATUS_PAID) {
                    $skipped++;
                    continue;
                }

                $existing->base_salary = $employee->base_salary;
                $existing->recalcNet();
                $existing->save();
                $updated++;
                continue;
            }

            $slip = StaffPayrollSlip::create([
                'slip_number'      => $this->generateSlipNumber($year, $month),
                'employee_id'      => $employee->id,
                'month'            => $month,
                'year'             => $year,
                'base_salary'      => $employee->base_salary,
                'total_allowances' => 0,
                'total_deductions' => 0,
                'net_salary'       => $employee->base_salary,
                'status'           => StaffPayrollSlip::STATUS_DRAFT,
                'created_by'       => $userId,
            ]);
            $slip->recalcNet();
            $slip->save();
            $created++;
        }

        return compact('created', 'updated', 'skipped');
    }

    /**
     * Tambah baris komponen ke slip (tunjangan/lembur/potongan).
     */
    public function addItem(StaffPayrollSlip $slip, array $data): StaffPayrollItem
    {
        $this->assertEditable($slip);

        $item = $slip->items()->create([
            'item_type'   => $data['item_type'],
            'item_code'   => $data['item_code'],
            'description' => $data['description'],
            'amount'      => $data['amount'],
            'metadata'    => $data['metadata'] ?? null,
        ]);

        $slip->load('items');
        $slip->recalcNet();
        $slip->save();

        return $item;
    }

    public function deleteItem(StaffPayrollItem $item): void
    {
        $slip = $item->slip;
        $this->assertEditable($slip);

        $item->delete();

        $slip->load('items');
        $slip->recalcNet();
        $slip->save();
    }

    /**
     * Tandai slip PAID + buat pengeluaran GAJI_STAFF terlink.
     */
    public function markPaid(StaffPayrollSlip $slip, int $userId): StaffPayrollSlip
    {
        if ($slip->status === StaffPayrollSlip::STATUS_PAID) {
            return $slip;
        }

        if ($slip->status === StaffPayrollSlip::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Slip masih DRAFT — tambahkan komponen atau pastikan gaji pokok sudah benar sebelum dibayar.');
        }

        if ($slip->expense_id) {
            throw new \InvalidArgumentException('Slip sudah punya pengeluaran terlink.');
        }

        $category = ExpenseCategory::where('code', 'GAJI_STAFF')->first();
        if (!$category) {
            throw new \RuntimeException('Kategori pengeluaran GAJI_STAFF belum ada. Jalankan seeder expense categories.');
        }

        return DB::transaction(function () use ($slip, $userId, $category) {
            $slip->load('employee');
            $monthName = Carbon::create($slip->year, $slip->month, 1)->locale('id')->translatedFormat('F Y');
            $expenseDate = Carbon::create($slip->year, $slip->month, 1)->endOfMonth()->toDateString();

            $expense = Expense::create([
                'expense_number'      => $this->generateExpenseNumber($slip->year, $slip->month),
                'expense_category_id' => $category->id,
                'amount'              => $slip->net_salary,
                'description'         => "Gaji {$slip->employee->full_name} — {$monthName}",
                'expense_date'        => $expenseDate,
                'payment_method'      => Expense::METHOD_TRANSFER,
                'notes'               => "Auto dari slip gaji {$slip->slip_number}",
                'created_by'          => $userId,
            ]);

            $slip->update([
                'status'     => StaffPayrollSlip::STATUS_PAID,
                'paid_at'    => now(),
                'paid_by'    => $userId,
                'expense_id' => $expense->id,
            ]);

            return $slip->fresh(['employee', 'expense', 'items', 'paidBy']);
        });
    }

    /**
     * Void pembayaran: hapus expense terlink, kembalikan slip ke CALCULATED.
     */
    public function voidPaid(StaffPayrollSlip $slip): StaffPayrollSlip
    {
        if ($slip->status !== StaffPayrollSlip::STATUS_PAID) {
            throw new \InvalidArgumentException('Hanya slip PAID yang bisa di-void.');
        }

        return DB::transaction(function () use ($slip) {
            if ($slip->expense_id) {
                $expense = Expense::find($slip->expense_id);
                if ($expense) {
                    $expense->delete();
                }
            }

            $slip->update([
                'status'     => StaffPayrollSlip::STATUS_CALCULATED,
                'paid_at'    => null,
                'paid_by'    => null,
                'expense_id' => null,
            ]);

            return $slip->fresh(['employee', 'items']);
        });
    }

    public function generateSlipNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $latest = StaffPayrollSlip::where('slip_number', 'like', "GAJI/{$year}/{$monthStr}/%")
            ->orderBy('slip_number', 'desc')
            ->value('slip_number');

        $nextSeq = 1;
        if ($latest) {
            $parts   = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('GAJI/%d/%s/%04d', $year, $monthStr, $nextSeq);
    }

    public function generateEmployeeCode(): string
    {
        $latest = Employee::where('employee_code', 'like', 'STAFF-%')
            ->orderBy('employee_code', 'desc')
            ->value('employee_code');

        $nextSeq = 1;
        if ($latest && preg_match('/STAFF-(\d+)/', $latest, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        return sprintf('STAFF-%03d', $nextSeq);
    }

    private function generateExpenseNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $latest = Expense::where('expense_number', 'like', "EXP/{$year}/{$monthStr}/%")
            ->orderBy('expense_number', 'desc')
            ->value('expense_number');

        $nextSeq = 1;
        if ($latest) {
            $parts   = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('EXP/%d/%s/%04d', $year, $monthStr, $nextSeq);
    }

    private function assertEditable(StaffPayrollSlip $slip): void
    {
        if ($slip->isLocked()) {
            throw new \InvalidArgumentException('Slip sudah PAID dan tidak bisa diubah.');
        }
    }
}
