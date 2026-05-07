<?php

namespace App\Http\Controllers;

use App\Models\InvoiceComponent;
use Illuminate\Http\Request;

/**
 * CRUD katalog item tagihan manual (M05 — Owner only untuk write).
 *
 * Item di sini adalah "template" yang bisa dipilih Admin saat menambah
 * item manual ke invoice murid. Contoh: BUKU (Rp 100.000), KOSTUM (Rp 150.000).
 */
class InvoiceComponentController extends Controller
{
    public function index()
    {
        $components = InvoiceComponent::orderBy('sort_order')->orderBy('code')->get();
        return view('invoice-components.index', compact('components'));
    }

    public function create()
    {
        return view('invoice-components.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:30|unique:invoice_components,code|regex:/^[A-Z][A-Z0-9_]*$/',
            'name'          => 'required|string|max:100',
            'default_price' => 'required|integer|min:0|max:99999999',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'required|integer|min:0|max:999',
        ], [
            'code.required'          => 'Kode wajib diisi.',
            'code.unique'            => 'Kode sudah digunakan.',
            'code.regex'             => 'Kode hanya boleh huruf besar, angka, dan underscore. Contoh: BUKU, KOSTUM_KIDS.',
            'name.required'          => 'Nama tampilan wajib diisi.',
            'default_price.required' => 'Harga default wajib diisi.',
            'default_price.min'      => 'Harga tidak boleh negatif.',
            'sort_order.required'    => 'Urutan tampil wajib diisi.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        InvoiceComponent::create($validated);

        return redirect()->route('invoice-components.index')
            ->with('success', "Komponen tagihan '{$validated['code']}' berhasil ditambahkan.");
    }

    public function edit(InvoiceComponent $invoiceComponent)
    {
        return view('invoice-components.edit', compact('invoiceComponent'));
    }

    public function update(Request $request, InvoiceComponent $invoiceComponent)
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:30|unique:invoice_components,code,' . $invoiceComponent->id . '|regex:/^[A-Z][A-Z0-9_]*$/',
            'name'          => 'required|string|max:100',
            'default_price' => 'required|integer|min:0|max:99999999',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'required|integer|min:0|max:999',
        ], [
            'code.unique'       => 'Kode sudah digunakan oleh komponen lain.',
            'code.regex'        => 'Kode hanya boleh huruf besar, angka, dan underscore.',
            'default_price.min' => 'Harga tidak boleh negatif.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);

        $invoiceComponent->update($validated);

        return redirect()->route('invoice-components.index')
            ->with('success', "Komponen '{$invoiceComponent->code}' berhasil diperbarui.");
    }

    public function destroy(InvoiceComponent $invoiceComponent)
    {
        // Cegah hapus kalau sudah dipakai di invoice_items
        if ($invoiceComponent->invoiceItems()->exists()) {
            return back()->with('error',
                "Komponen '{$invoiceComponent->code}' tidak bisa dihapus karena sudah dipakai di invoice. Nonaktifkan saja.");
        }

        $code = $invoiceComponent->code;
        $invoiceComponent->delete();

        return redirect()->route('invoice-components.index')
            ->with('success', "Komponen '{$code}' berhasil dihapus.");
    }
}
