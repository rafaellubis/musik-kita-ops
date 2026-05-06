<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Instrument;
use App\Models\Package;
use Illuminate\Http\Request;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Teacher;
use App\Models\Room;


class StudentController extends Controller
{
    public function index(Request $request)
    {
        // Build query bertahap berdasarkan filter
        $query = Student::query()
            ->with(['package.instrument', 'assignedTeacher', 'assignedRoom']);

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter instrumen (via relasi packages)
        if ($request->filled('instrument_id')) {
            $query->whereHas('package', function ($q) use ($request) {
                $q->where('instrument_id', $request->instrument_id);
            });
        }

        // Filter paket
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // Search by name atau code
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%')
                  ->orWhere('student_code', 'like', '%' . $request->search . '%')
                  ->orWhere('nickname', 'like', '%' . $request->search . '%');
            });
        }

        // Default: urut by code descending
        $students = $query->orderBy('student_code', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Stats per status (untuk header)
        $stats = Student::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Data dropdown filter
        $instruments = Instrument::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $packages = Package::where('is_active', true)
            ->with('instrument')
            ->orderBy('sort_order')
            ->get();

        return view('students.index', compact(
            'students', 'stats', 'instruments', 'packages'
        ));
    }

    public function show(string $id)
    {
        $student = Student::with([
            'package.instrument',
            'assignedTeacher',
            'assignedRoom',
        ])->findOrFail($id);

        return view('students.show', compact('student'));
    }

    // Method create, store, edit, update, destroy diisi di Sesi 4-5
	public function create()
	{
    $packages = Package::where('is_active', true)
        ->with('instrument')
        ->orderBy('sort_order')
        ->get();

    $teachers = Teacher::where('is_active', true)
        ->orderBy('name')
        ->get();

    $rooms = Room::where('is_active', true)
        ->orderBy('code')
        ->get();

    return view('students.create', compact('packages', 'teachers', 'rooms'));
	}

	public function store(StoreStudentRequest $request)
{
    $validated = $request->validated();

    // Auto-generate student_code
    $validated['student_code'] = Student::generateCode();

    // Set active_since kalau status Aktif
    if ($validated['status'] === 'Aktif') {
        $validated['active_since'] = now()->toDateString();
    }

    $student = Student::create($validated);

    return redirect()->route('students.show', $student->id)
        ->with('success', "Murid {$student->full_name} ({$student->student_code}) berhasil ditambahkan.");
	}
	
	public function edit(string $id)
	{
    $student = Student::findOrFail($id);

    $packages = Package::where('is_active', true)
        ->with('instrument')
        ->orderBy('sort_order')
        ->get();

    $teachers = Teacher::where('is_active', true)
        ->orderBy('name')
        ->get();

    $rooms = Room::where('is_active', true)
        ->orderBy('code')
        ->get();

    return view('students.edit', compact('student', 'packages', 'teachers', 'rooms'));
	}

	public function update(UpdateStudentRequest $request, string $id)
	{
    $student = Student::findOrFail($id);
    $validated = $request->validated();

    // Status TIDAK di-include dalam $validated
    // karena tidak ada di rules UpdateStudentRequest
    $student->update($validated);

    return redirect()->route('students.show', $student->id)
        ->with('success', "Data murid {$student->full_name} berhasil diperbarui.");
	}

	public function destroy(string $id)
	{
    // Redirect ke detail — hard delete dinonaktifkan
    // Status terminal (Mengundurkan Diri) akan dihandle via lifecycle action Sesi 8
    return redirect()->route('students.show', $id)
        ->with('error', 'Untuk mengakhiri status murid, gunakan tombol aksi di halaman detail.');
	}
}