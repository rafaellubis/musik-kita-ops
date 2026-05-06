<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);
        $holidays = Holiday::whereYear('date', $year)
            ->orderBy('date')
            ->get();

        $availableYears = Holiday::selectRaw('YEAR(date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return view('holidays.index', compact('holidays', 'availableYears', 'year'));
    }

    public function create()
    {
        return view('holidays.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|unique:holidays,date',
            'name' => 'required|string|max:100',
            'type' => 'required|in:Nasional,Cuti Bersama,Internal',
            'notes' => 'nullable|string',
        ]);
        $validated['is_active'] = $request->has('is_active');
        Holiday::create($validated);
        return redirect()->route('holidays.index')->with('success', 'Hari libur ditambahkan.');
    }

    public function edit(string $id)
    {
        $holiday = Holiday::findOrFail($id);
        return view('holidays.edit', compact('holiday'));
    }

    public function update(Request $request, string $id)
    {
        $holiday = Holiday::findOrFail($id);
        $validated = $request->validate([
            'date' => 'required|date|unique:holidays,date,' . $id,
            'name' => 'required|string|max:100',
            'type' => 'required|in:Nasional,Cuti Bersama,Internal',
            'notes' => 'nullable|string',
        ]);
        $validated['is_active'] = $request->has('is_active');
        $holiday->update($validated);
        return redirect()->route('holidays.index')->with('success', 'Hari libur diperbarui.');
    }

    public function destroy(string $id)
    {
        Holiday::findOrFail($id)->delete();
        return redirect()->route('holidays.index')->with('success', 'Hari libur dihapus.');
    }
}
