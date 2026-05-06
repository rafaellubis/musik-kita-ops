<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\Instrument;

class PackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $packages = Package::with('instrument')->orderBy('sort_order')->get();
        return view('packages.index', compact('packages'));
    }
 
    public function create()
    {
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();
        return view('packages.create', compact('instruments'));
    }
 
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:packages,code',
            'instrument_id' => 'required|exists:instruments,id',
            'class_type' => 'required|in:REGULER,HOBBY,KIDS_CLASS,KIDS_CLASS_BUNDLE',
            'grade' => 'nullable|string|max:10',
            'duration_min' => 'required|integer|min:15|max:120',
            'price_per_month' => 'required|integer|min:0',
            'sort_order' => 'integer|min:0',
        ]);
        $validated['is_active'] = $request->has('is_active');
        Package::create($validated);
        return redirect()->route('packages.index')->with('success', 'Paket berhasil ditambahkan.');
    }
 
    public function edit(string $id)
    {
        $package = Package::findOrFail($id);
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();
        return view('packages.edit', compact('package', 'instruments'));
    }
 
    public function update(Request $request, string $id)
    {
        $package = Package::findOrFail($id);
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:packages,code,' . $id,
            'instrument_id' => 'required|exists:instruments,id',
            'class_type' => 'required|in:REGULER,HOBBY,KIDS_CLASS,KIDS_CLASS_BUNDLE',
            'grade' => 'nullable|string|max:10',
            'duration_min' => 'required|integer|min:15|max:120',
            'price_per_month' => 'required|integer|min:0',
            'sort_order' => 'integer|min:0',
        ]);
        $validated['is_active'] = $request->has('is_active');
        $package->update($validated);
        return redirect()->route('packages.index')->with('success', 'Paket berhasil diperbarui.');
    }
 
    public function destroy(string $id)
    {
        Package::findOrFail($id)->delete();
        return redirect()->route('packages.index')->with('success', 'Paket berhasil dihapus.');
    }
}