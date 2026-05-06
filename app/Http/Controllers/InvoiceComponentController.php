<?php

namespace App\Http\Controllers;

use App\Models\InvoiceComponent;
use Illuminate\Http\Request;

class InvoiceComponentController extends Controller
{
    public function index()
    {
        $components = InvoiceComponent::orderBy('sort_order')->get();
        return view('invoice-components.index', compact('components'));
    }

    public function create()
    {
        return view('invoice-components.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:invoice_components,code|regex:/^[A-Z_]+$/',
            'name' => 'required|string|max:100',
            'type' => 'required|in:REGULER,TRIAL,KIDS_FINAL,CUTI,UJIAN,MINI_CONCERT,DENDA',
            'amount_or_formula' => 'required|string|max:100',
            'description' => 'nullable|string',
            'sort_order' => 'required|integer|min:0',
        ]);
        $validated['is_active'] = $request->has('is_active');
        InvoiceComponent::create($validated);
        return redirect()->route('invoice-components.index')->with('success', 'Komponen tagihan ditambahkan.');
    }

    public function edit(string $id)
    {
        $invoiceComponent = InvoiceComponent::findOrFail($id);
        return view('invoice-components.edit', compact('invoiceComponent'));
    }

    public function update(Request $request, string $id)
    {
        $invoiceComponent = InvoiceComponent::findOrFail($id);
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:invoice_components,code,' . $id . '|regex:/^[A-Z_]+$/',
            'name' => 'required|string|max:100',
            'type' => 'required|in:REGULER,TRIAL,KIDS_FINAL,CUTI,UJIAN,MINI_CONCERT,DENDA',
            'amount_or_formula' => 'required|string|max:100',
            'description' => 'nullable|string',
            'sort_order' => 'required|integer|min:0',
        ]);
        $validated['is_active'] = $request->has('is_active');
        $invoiceComponent->update($validated);
        return redirect()->route('invoice-components.index')->with('success', 'Komponen tagihan diperbarui.');
    }

    public function destroy(string $id)
    {
        InvoiceComponent::findOrFail($id)->delete();
        return redirect()->route('invoice-components.index')->with('success', 'Komponen tagihan dihapus.');
    }
}