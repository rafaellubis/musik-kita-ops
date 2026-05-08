<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

/**
 * Manajemen kategori pengeluaran (M07 — master data).
 * Hanya Owner yang bisa CRUD. Admin hanya bisa lihat saat input expense.
 */
class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::orderBy('sort_order')->orderBy('name')->get();
        return view('expense-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('expense-categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:expense_categories,code|regex:/^[A-Z0-9_]+$/',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
        ], [
            'code.required'  => 'Kode kategori wajib diisi.',
            'code.unique'    => 'Kode sudah digunakan.',
            'code.regex'     => 'Kode hanya boleh huruf kapital, angka, dan underscore.',
            'name.required'  => 'Nama kategori wajib diisi.',
        ]);

        ExpenseCategory::create([
            'code'        => strtoupper($data['code']),
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => true,
            'sort_order'  => $data['sort_order'] ?? 0,
        ]);

        return redirect()->route('expense-categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(ExpenseCategory $expenseCategory)
    {
        return view('expense-categories.edit', compact('expenseCategory'));
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|regex:/^[A-Z0-9_]+$/|unique:expense_categories,code,' . $expenseCategory->id,
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
            'is_active'   => 'boolean',
        ], [
            'code.regex'    => 'Kode hanya boleh huruf kapital, angka, dan underscore.',
            'code.unique'   => 'Kode sudah digunakan kategori lain.',
            'name.required' => 'Nama kategori wajib diisi.',
        ]);

        $expenseCategory->update([
            'code'        => strtoupper($data['code']),
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => $request->boolean('is_active'),
        ]);

        return redirect()->route('expense-categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        // Cek apakah sudah ada pengeluaran — tidak boleh dihapus
        if ($expenseCategory->expenses()->exists()) {
            return redirect()->route('expense-categories.index')
                ->with('error', 'Kategori tidak bisa dihapus karena sudah ada data pengeluaran. Nonaktifkan saja.');
        }

        $expenseCategory->delete();

        return redirect()->route('expense-categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }
}
