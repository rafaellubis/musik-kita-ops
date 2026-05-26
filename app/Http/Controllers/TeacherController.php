<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Teacher;
use App\Models\Instrument;
use App\Services\TeacherService;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with('instruments')
            ->withCount(['enrollments as active_students' => fn ($q) => $q->where('status', 'ACTIVE')])
            ->orderBy('code')
            ->get();
        return view('teachers.index', compact('teachers'));
    }
 
    public function create()
    {
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();
        return view('teachers.create', compact('instruments'));
    }
 
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:teachers,code',
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:30',
            'bank_account_holder' => 'nullable|string|max:100',
            'joined_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'instruments' => 'array',
            'instruments.*' => 'exists:instruments,id',
            'primary_instrument' => 'nullable|exists:instruments,id',
        ]);
        $validated['is_active'] = $request->has('is_active');
        $teacher = Teacher::create(
            collect($validated)->except(['instruments', 'primary_instrument'])->toArray()
        );
        $this->syncInstruments($teacher, $request);
        return redirect()->route('teachers.index')->with('success', 'Guru berhasil ditambahkan.');
    }
 
    public function edit(string $id)
    {
        $teacher = Teacher::with('instruments')->findOrFail($id);
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();
        return view('teachers.edit', compact('teacher', 'instruments'));
    }
 
    public function update(Request $request, string $id)
    {
        $teacher = Teacher::findOrFail($id);
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:teachers,code,' . $id,
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:30',
            'bank_account_holder' => 'nullable|string|max:100',
            'joined_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'instruments' => 'array',
            'instruments.*' => 'exists:instruments,id',
            'primary_instrument' => 'nullable|exists:instruments,id',
        ]);

        // Tangani deactivation via TeacherService untuk cek cascade
        $newIsActive = $request->has('is_active');
        $wasActive   = $teacher->is_active;

        // Jika guru sedang dinonaktifkan, jalankan deactivation guard
        if ($wasActive && !$newIsActive) {
            try {
                $result = app(TeacherService::class)->deactivate($teacher);
                if ($result['warning']) {
                    session()->flash('warning', $result['warning']);
                }
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', $e->getMessage())->withInput();
            }
        }

        // Update field lain (is_active sudah ditangani TeacherService jika deactivation,
        // atau di sini jika re-aktivasi / tidak ada perubahan is_active)
        $updateData = collect($validated)->except(['instruments', 'primary_instrument'])->toArray();

        // Kalau deactivation sudah dijalankan TeacherService, jangan update is_active lagi
        if ($wasActive && !$newIsActive) {
            unset($updateData['is_active']);
        } else {
            $updateData['is_active'] = $newIsActive;
        }

        $teacher->update($updateData);
        $this->syncInstruments($teacher, $request);

        return redirect()->route('teachers.index')->with('success', 'Guru berhasil diperbarui.');
    }
 
    public function destroy(string $id)
    {
        Teacher::findOrFail($id)->delete();
        return redirect()->route('teachers.index')->with('success', 'Guru berhasil dihapus.');
    }
 
    private function syncInstruments(Teacher $teacher, Request $request): void
    {
        $instrumentIds = $request->input('instruments', []);
        $primaryId = $request->input('primary_instrument');
        $syncData = [];
        foreach ($instrumentIds as $id) {
            $syncData[$id] = ['is_primary' => (int)$id === (int)$primaryId];
        }
        $teacher->instruments()->sync($syncData);
    }
}
