<?php

namespace App\Http\Controllers;

use App\Models\PayrollConfig;
use Illuminate\Http\Request;

class PayrollConfigController extends Controller
{
    public function index()
    {
        $configs = PayrollConfig::orderBy('formula_type')->orderBy('id')->get();
        return view('payroll-configs.index', compact('configs'));
    }

    public function create()
    {
        return view('payroll-configs.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'scenario_code' => 'required|string|max:30|unique:payroll_configs,scenario_code',
            'scenario_name' => 'required|string|max:100',
            'formula_type' => 'required|in:PERCENTAGE,PER_STUDENT,FIXED,CONSTANT',
            'value_or_formula' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);
        $validated['is_active'] = $request->has('is_active');
        PayrollConfig::create($validated);
        return redirect()->route('payroll-configs.index')->with('success', 'Konfigurasi ditambahkan.');
    }

    public function edit(string $id)
    {
        $payrollConfig = PayrollConfig::findOrFail($id);
        return view('payroll-configs.edit', compact('payrollConfig'));
    }

    public function update(Request $request, string $id)
    {
        $payrollConfig = PayrollConfig::findOrFail($id);
        $validated = $request->validate([
            'scenario_code' => 'required|string|max:30|unique:payroll_configs,scenario_code,' . $id,
            'scenario_name' => 'required|string|max:100',
            'formula_type' => 'required|in:PERCENTAGE,PER_STUDENT,FIXED,CONSTANT',
            'value_or_formula' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);
        $validated['is_active'] = $request->has('is_active');
        $payrollConfig->update($validated);
        return redirect()->route('payroll-configs.index')->with('success', 'Konfigurasi diperbarui.');
    }

    public function destroy(string $id)
    {
        PayrollConfig::findOrFail($id)->delete();
        return redirect()->route('payroll-configs.index')->with('success', 'Konfigurasi dihapus.');
    }
}