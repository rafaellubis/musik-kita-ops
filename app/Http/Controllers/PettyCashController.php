<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePettyCashExpenseRequest;
use App\Http\Requests\StorePettyCashTopupRequest;
use App\Http\Requests\UpdatePettyCashExpenseRequest;
use App\Http\Requests\UpdatePettyCashTopupRequest;
use App\Models\AuditLog;
use App\Models\ExpenseCategory;
use App\Models\PettyCashExpense;
use App\Models\PettyCashTopup;
use App\Services\PettyCashService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Modul petty cash terpisah (M07).
 *
 * Top-up: Owner only (masuk P&L). Expense: Owner|Admin (hanya kurangi saldo).
 */
class PettyCashController extends Controller
{
    public function __construct(
        private PettyCashService $pettyCashService
    ) {}

    /**
     * Daftar mutasi petty cash per bulan + saldo tersedia.
     */
    public function index(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $balance      = $this->pettyCashService->getCurrentBalance();
        $mutations    = $this->pettyCashService->getMutations($year, $month);
        $topupTotal   = (int) PettyCashTopup::forMonth($year, $month)->sum('amount');
        $expenseTotal = (int) PettyCashExpense::forMonth($year, $month)->sum('amount');
        $monthName    = Carbon::create($year, $month, 1)->format('F Y');

        return view('petty-cash.index', compact(
            'balance',
            'mutations',
            'year',
            'month',
            'monthName',
            'topupTotal',
            'expenseTotal',
        ));
    }

    // ===== TOP-UP (Owner only via route middleware) =====

    public function createTopup()
    {
        return view('petty-cash.topups.create');
    }

    public function storeTopup(StorePettyCashTopupRequest $request)
    {
        $data = $request->validated();

        $request->validate([
            'notes'         => 'nullable|string|max:1000',
            'receipt_image' => 'nullable|image|max:2048',
        ], [
            'receipt_image.image' => 'File harus berupa gambar (JPG/PNG).',
            'receipt_image.max'   => 'Ukuran foto maksimal 2 MB.',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt_image')) {
            $receiptPath = $request->file('receipt_image')
                ->store('receipts/petty-cash', 'public');
        }

        $date = Carbon::parse($data['topup_date']);

        $topup = PettyCashTopup::create([
            'topup_number'  => $this->pettyCashService->generateTopupNumber($date->year, $date->month),
            'amount'        => $data['amount'],
            'topup_date'    => $data['topup_date'],
            'description'   => $data['description'],
            'notes'         => $request->input('notes'),
            'receipt_image' => $receiptPath,
            'created_by'    => auth()->id(),
        ]);

        AuditLog::record(
            AuditLog::ACTION_CREATE,
            $topup,
            $topup->topup_number,
            null,
            $topup->only(['topup_number', 'amount', 'topup_date', 'description']),
        );

        return redirect()->route('petty-cash.index', [
            'year' => $date->year,
            'month' => $date->month,
        ])->with('success', 'Isi saldo petty cash berhasil dicatat.');
    }

    public function showTopup(PettyCashTopup $topup)
    {
        $topup->load('createdBy');

        return view('petty-cash.topups.show', compact('topup'));
    }

    public function editTopup(PettyCashTopup $topup)
    {
        return view('petty-cash.topups.edit', compact('topup'));
    }

    public function updateTopup(UpdatePettyCashTopupRequest $request, PettyCashTopup $topup)
    {
        $data = $request->validated();

        if ($request->hasFile('receipt_image')) {
            if ($topup->receipt_image) {
                Storage::disk('public')->delete($topup->receipt_image);
            }
            $data['receipt_image'] = $request->file('receipt_image')
                ->store('receipts/petty-cash', 'public');
        } else {
            unset($data['receipt_image']);
        }

        $topup->update($data);

        $date = Carbon::parse($topup->topup_date);

        return redirect()->route('petty-cash.index', [
            'year' => $date->year,
            'month' => $date->month,
        ])->with('success', 'Isi saldo petty cash berhasil diperbarui.');
    }

    public function destroyTopup(PettyCashTopup $topup)
    {
        $date = Carbon::parse($topup->topup_date);

        AuditLog::record(
            AuditLog::ACTION_DELETE,
            $topup,
            $topup->topup_number,
        );

        if ($topup->receipt_image) {
            Storage::disk('public')->delete($topup->receipt_image);
        }

        $topup->delete();

        return redirect()->route('petty-cash.index', [
            'year' => $date->year,
            'month' => $date->month,
        ])->with('success', 'Isi saldo petty cash berhasil dihapus.');
    }

    // ===== EXPENSE (Owner|Admin via route middleware) =====

    public function createExpense()
    {
        $categories = ExpenseCategory::active()->orderBy('sort_order')->get();
        $balance    = $this->pettyCashService->getCurrentBalance();

        return view('petty-cash.expenses.create', compact('categories', 'balance'));
    }

    public function storeExpense(StorePettyCashExpenseRequest $request)
    {
        $data = $request->validated();

        $receiptPath = null;
        if ($request->hasFile('receipt_image')) {
            $receiptPath = $request->file('receipt_image')
                ->store('receipts/petty-cash', 'public');
        }

        $date = Carbon::parse($data['expense_date']);

        $expense = PettyCashExpense::create([
            'expense_number'      => $this->pettyCashService->generateExpenseNumber($date->year, $date->month),
            'expense_category_id' => $data['expense_category_id'],
            'amount'              => $data['amount'],
            'description'         => $data['description'],
            'expense_date'        => $data['expense_date'],
            'receipt_image'       => $receiptPath,
            'notes'               => $data['notes'] ?? null,
            'created_by'          => auth()->id(),
        ]);

        AuditLog::record(
            AuditLog::ACTION_CREATE,
            $expense,
            $expense->expense_number,
            null,
            $expense->only(['expense_number', 'amount', 'expense_date', 'description']),
        );

        return redirect()->route('petty-cash.index', [
            'year' => $date->year,
            'month' => $date->month,
        ])->with('success', 'Pengeluaran petty cash berhasil dicatat.');
    }

    public function showExpense(PettyCashExpense $expense)
    {
        $expense->load('category', 'createdBy');

        return view('petty-cash.expenses.show', compact('expense'));
    }

    public function editExpense(PettyCashExpense $expense)
    {
        $categories = ExpenseCategory::active()->orderBy('sort_order')->get();
        $balance    = $this->pettyCashService->getCurrentBalance() + $expense->amount;

        return view('petty-cash.expenses.edit', compact('expense', 'categories', 'balance'));
    }

    public function updateExpense(UpdatePettyCashExpenseRequest $request, PettyCashExpense $expense)
    {
        $data = $request->validated();

        if ($request->hasFile('receipt_image')) {
            if ($expense->receipt_image) {
                Storage::disk('public')->delete($expense->receipt_image);
            }
            $data['receipt_image'] = $request->file('receipt_image')
                ->store('receipts/petty-cash', 'public');
        } else {
            unset($data['receipt_image']);
        }

        $expense->update($data);

        $date = Carbon::parse($expense->expense_date);

        return redirect()->route('petty-cash.index', [
            'year' => $date->year,
            'month' => $date->month,
        ])->with('success', 'Pengeluaran petty cash berhasil diperbarui.');
    }

    public function destroyExpense(PettyCashExpense $expense)
    {
        $date = Carbon::parse($expense->expense_date);

        AuditLog::record(
            AuditLog::ACTION_DELETE,
            $expense,
            $expense->expense_number,
        );

        if ($expense->receipt_image) {
            Storage::disk('public')->delete($expense->receipt_image);
        }

        $expense->delete();

        return redirect()->route('petty-cash.index', [
            'year' => $date->year,
            'month' => $date->month,
        ])->with('success', 'Pengeluaran petty cash berhasil dihapus.');
    }
}
