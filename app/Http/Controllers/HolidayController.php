<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
        $data = $request->validate([
            'date'             => 'required|date|unique:holidays,date',
            'name'             => 'required|string|max:100',
            'type'             => 'required|in:Nasional,Cuti Bersama,Internal',
            'notes'            => 'nullable|string|max:500',
            'is_active'        => 'nullable|boolean',
            'replacement_date' => [
                'nullable',
                'date',
                'unique:holidays,replacement_date',
                // Tanggal pengganti harus dalam bulan yang sama dengan date
                function ($attribute, $value, $fail) use ($request) {
                    if (!$value || !$request->date) return;
                    if (date('Y-m', strtotime($value)) !== date('Y-m', strtotime($request->date))) {
                        $fail('Tanggal pengganti harus dalam bulan yang sama dengan tanggal libur.');
                    }
                    if ($value === $request->date) {
                        $fail('Tanggal pengganti tidak boleh sama dengan tanggal libur.');
                    }
                },
                // Event studio (Internal) tidak boleh punya replacement_date
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $request->type === 'Internal') {
                        $fail('Event studio (Internal) tidak bisa punya tanggal pengganti. Gunakan fitur Reschedule.');
                    }
                },
            ],
            'is_honor_paid'    => 'nullable|boolean',
        ], [
            'date.unique'             => 'Tanggal libur ini sudah ada di sistem.',
            'replacement_date.unique' => 'Tanggal pengganti ini sudah dipakai oleh hari libur lain.',
        ]);

        $holiday = Holiday::create([
            'date'             => $data['date'],
            'name'             => $data['name'],
            'type'             => $data['type'],
            'notes'            => $data['notes'] ?? null,
            'is_active'        => $request->boolean('is_active', true),
            'replacement_date' => $data['replacement_date'] ?? null,
            // Event studio (Internal) selalu is_honor_paid=false; tipe lain ikut checkbox
            'is_honor_paid'    => $data['type'] === 'Internal'
                ? false
                : $request->boolean('is_honor_paid', true),
        ]);

        // Catat audit log
        AuditLog::record(
            action: AuditLog::ACTION_CREATE,
            entity: $holiday,
            entityLabel: $holiday->name . ' (' . $holiday->date . ')',
        );

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
        $data = $request->validate([
            'date'             => 'required|date|unique:holidays,date,' . $holiday->id,
            'name'             => 'required|string|max:100',
            'type'             => 'required|in:Nasional,Cuti Bersama,Internal',
            'notes'            => 'nullable|string|max:500',
            'is_active'        => 'nullable|boolean',
            'replacement_date' => [
                'nullable',
                'date',
                'unique:holidays,replacement_date,' . $holiday->id,
                // Tanggal pengganti harus dalam bulan yang sama dengan date
                function ($attribute, $value, $fail) use ($request) {
                    if (!$value || !$request->date) return;
                    if (date('Y-m', strtotime($value)) !== date('Y-m', strtotime($request->date))) {
                        $fail('Tanggal pengganti harus dalam bulan yang sama dengan tanggal libur.');
                    }
                    if ($value === $request->date) {
                        $fail('Tanggal pengganti tidak boleh sama dengan tanggal libur.');
                    }
                },
                // Event studio (Internal) tidak boleh punya replacement_date
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $request->type === 'Internal') {
                        $fail('Event studio (Internal) tidak bisa punya tanggal pengganti. Gunakan fitur Reschedule.');
                    }
                },
            ],
            'is_honor_paid'    => 'nullable|boolean',
        ], [
            'date.unique'             => 'Tanggal libur ini sudah ada di sistem.',
            'replacement_date.unique' => 'Tanggal pengganti ini sudah dipakai oleh hari libur lain.',
        ]);

        $holiday->update([
            'date'             => $data['date'],
            'name'             => $data['name'],
            'type'             => $data['type'],
            'notes'            => $data['notes'] ?? null,
            'is_active'        => $request->boolean('is_active', true),
            'replacement_date' => $data['replacement_date'] ?? null,
            // Event studio (Internal) selalu is_honor_paid=false; tipe lain ikut checkbox
            'is_honor_paid'    => $data['type'] === 'Internal'
                ? false
                : $request->boolean('is_honor_paid', true),
        ]);

        // Catat audit log
        AuditLog::record(
            action: AuditLog::ACTION_UPDATE,
            entity: $holiday,
            entityLabel: $holiday->name . ' (' . $holiday->date . ')',
        );

        return redirect()->route('holidays.index')->with('success', 'Hari libur diperbarui.');
    }

    public function destroy(string $id)
    {
        Holiday::findOrFail($id)->delete();
        return redirect()->route('holidays.index')->with('success', 'Hari libur dihapus.');
    }
}
