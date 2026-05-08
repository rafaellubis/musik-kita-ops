<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Manajemen pengeluaran studio (M07).
 *
 * Route:
 *   GET  /expenses           → index  (list + filter + ringkasan + petty cash)
 *   GET  /expenses/create    → create (form input)
 *   POST /expenses           → store
 *   GET  /expenses/{expense} → show   (detail + foto bukti)
 *   GET  /expenses/{expense}/edit → edit
 *   PATCH /expenses/{expense}     → update
 *   DELETE /expenses/{expense}    → destroy [Owner only]
 */
class ExpenseController extends Controller
{
    /**
     * Daftar pengeluaran per bulan, lengkap dengan ringkasan per kategori
     * dan saldo petty cash hari ini.
     */
    public function index(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = Expense::with('category', 'createdBy')
            ->forMonth($year, $month)
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }
        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $expenses = $query->paginate(50)->withQueryString();

        // Ringkasan per kategori untuk bulan ini
        $summary = Expense::forMonth($year, $month)
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select('expense_categories.name as cat_name', 'expense_categories.code as cat_code',
                     DB::raw('SUM(expenses.amount) as total'),
                     DB::raw('COUNT(*) as cnt'))
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.code')
            ->orderBy('total', 'desc')
            ->get();

        $totalBulan = $summary->sum('total');

        // ===== Petty Cash — saldo kas hari ini =====
        // Kas masuk  : semua payment CASH dari murid yang valid (tidak void)
        // Kas keluar : semua pengeluaran CASH
        $today = now()->toDateString();

        $kasmasukHariIni = Payment::where('method', 'CASH')
            ->whereNull('voided_at')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        $kaskeluarHariIni = Expense::cash()
            ->whereDate('expense_date', $today)
            ->sum('amount');

        // Saldo berjalan bulan ini (kumulatif)
        $kasmasukBulan = Payment::where('method', 'CASH')
            ->whereNull('voided_at')
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->sum('amount');

        $kaskeluarBulan = Expense::forMonth($year, $month)->cash()->sum('amount');

        $categories  = ExpenseCategory::active()->orderBy('sort_order')->get(['id', 'code', 'name']);
        $monthName   = Carbon::create($year, $month, 1)->format('F Y');

        return view('expenses.index', compact(
            'expenses', 'summary', 'totalBulan', 'categories',
            'kasmasukHariIni', 'kaskeluarHariIni',
            'kasmasukBulan', 'kaskeluarBulan',
            'year', 'month', 'monthName'
        ));
    }

    public function create()
    {
        $categories = ExpenseCategory::active()->orderBy('sort_order')->get();
        return view('expenses.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount'              => 'required|integer|min:1|max:999999999',
            'description'         => 'required|string|max:255',
            'expense_date'        => 'required|date|before_or_equal:today',
            'payment_method'      => 'required|in:CASH,TRANSFER',
            'receipt_image'       => 'nullable|image|max:2048',
            'notes'               => 'nullable|string|max:1000',
        ], [
            'expense_category_id.required' => 'Kategori pengeluaran wajib dipilih.',
            'expense_category_id.exists'   => 'Kategori tidak ditemukan.',
            'amount.required'              => 'Jumlah pengeluaran wajib diisi.',
            'amount.min'                   => 'Jumlah pengeluaran harus lebih dari 0.',
            'description.required'         => 'Keterangan pengeluaran wajib diisi.',
            'expense_date.required'        => 'Tanggal pengeluaran wajib diisi.',
            'expense_date.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'payment_method.required'      => 'Metode pembayaran wajib dipilih.',
            'receipt_image.image'          => 'File harus berupa gambar (JPG/PNG).',
            'receipt_image.max'            => 'Ukuran foto maksimal 2 MB.',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt_image')) {
            $receiptPath = $request->file('receipt_image')
                ->store('receipts/expenses', 'public');
        }

        $date = Carbon::parse($data['expense_date']);

        Expense::create([
            'expense_number'      => $this->generateNumber($date->year, $date->month),
            'expense_category_id' => $data['expense_category_id'],
            'amount'              => $data['amount'],
            'description'         => $data['description'],
            'expense_date'        => $data['expense_date'],
            'payment_method'      => $data['payment_method'],
            'receipt_image'       => $receiptPath,
            'notes'               => $data['notes'] ?? null,
            'created_by'          => auth()->id(),
        ]);

        return redirect()->route('expenses.index', [
            'year' => $date->year, 'month' => $date->month,
        ])->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function show(Expense $expense)
    {
        $expense->load('category', 'createdBy');
        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense)
    {
        $categories = ExpenseCategory::active()->orderBy('sort_order')->get();
        return view('expenses.edit', compact('expense', 'categories'));
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount'              => 'required|integer|min:1|max:999999999',
            'description'         => 'required|string|max:255',
            'expense_date'        => 'required|date|before_or_equal:today',
            'payment_method'      => 'required|in:CASH,TRANSFER',
            'receipt_image'       => 'nullable|image|max:2048',
            'notes'               => 'nullable|string|max:1000',
        ], [
            'expense_category_id.required' => 'Kategori pengeluaran wajib dipilih.',
            'amount.required'              => 'Jumlah pengeluaran wajib diisi.',
            'amount.min'                   => 'Jumlah pengeluaran harus lebih dari 0.',
            'description.required'         => 'Keterangan pengeluaran wajib diisi.',
            'expense_date.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'receipt_image.image'          => 'File harus berupa gambar (JPG/PNG).',
            'receipt_image.max'            => 'Ukuran foto maksimal 2 MB.',
        ]);

        // Ganti foto bukti jika ada upload baru
        if ($request->hasFile('receipt_image')) {
            if ($expense->receipt_image) {
                Storage::disk('public')->delete($expense->receipt_image);
            }
            $data['receipt_image'] = $request->file('receipt_image')
                ->store('receipts/expenses', 'public');
        } else {
            unset($data['receipt_image']);
        }

        $expense->update($data);

        $date = Carbon::parse($expense->expense_date);
        return redirect()->route('expenses.index', [
            'year' => $date->year, 'month' => $date->month,
        ])->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    /**
     * Hapus pengeluaran. Hanya Owner.
     * Hapus foto bukti dari storage sekalian.
     */
    public function destroy(Expense $expense)
    {
        $date = Carbon::parse($expense->expense_date);

        if ($expense->receipt_image) {
            Storage::disk('public')->delete($expense->receipt_image);
        }

        $expense->delete();

        return redirect()->route('expenses.index', [
            'year' => $date->year, 'month' => $date->month,
        ])->with('success', 'Pengeluaran berhasil dihapus.');
    }

    /**
     * Generate nomor EXP/YYYY/MM/NNNN (reset per bulan).
     */
    private function generateNumber(int $year, int $month): string
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
}
