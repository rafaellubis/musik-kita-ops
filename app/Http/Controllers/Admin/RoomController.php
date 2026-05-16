<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::orderBy('code')->get();
        return view('rooms.index', compact('rooms'));
    }

    public function create()
    {
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();
        return view('rooms.create', compact('instruments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                    => 'required|string|max:10|unique:rooms,code|regex:/^[A-Z0-9]+$/',
            'name'                    => 'required|string|max:50',
            'capacity'                => 'required|integer|min:1|max:20',
            'supported_instruments'   => 'nullable|array',
            'supported_instruments.*' => 'string|exists:instruments,name',
            'notes'                   => 'nullable|string',
        ], [
            'code.regex'    => 'Kode hanya boleh huruf besar dan angka.',
            'code.unique'   => 'Kode ruangan sudah dipakai.',
            'name.required' => 'Nama ruangan wajib diisi.',
            'capacity.min'  => 'Kapasitas minimal 1.',
        ]);

        $validated['supported_instruments'] = $request->input('supported_instruments', []);
        $validated['is_active'] = $request->boolean('is_active', true);

        Room::create($validated);

        return redirect()->route('rooms.index')->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Room $room)
    {
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();
        return view('rooms.edit', compact('room', 'instruments'));
    }

    public function update(Request $request, Room $room)
    {
        $validated = $request->validate([
            'code'                    => 'required|string|max:10|unique:rooms,code,' . $room->id . '|regex:/^[A-Z0-9]+$/',
            'name'                    => 'required|string|max:50',
            'capacity'                => 'required|integer|min:1|max:20',
            'supported_instruments'   => 'nullable|array',
            'supported_instruments.*' => 'string|exists:instruments,name',
            'notes'                   => 'nullable|string',
        ], [
            'code.regex'  => 'Kode hanya boleh huruf besar dan angka.',
            'code.unique' => 'Kode ruangan sudah dipakai.',
        ]);

        $instrumenBaru = $request->input('supported_instruments', []);
        $instrumenDihapus = array_diff($room->supported_instruments ?? [], $instrumenBaru);

        // Cek jadwal aktif yang terdampak perubahan fasilitas
        $warning = null;
        if (!empty($instrumenDihapus)) {
            $terdampak = Schedule::active()
                ->where('room_id', $room->id)
                ->whereHas('enrollment.package.instrument', function ($q) use ($instrumenDihapus) {
                    $q->whereIn('name', array_values($instrumenDihapus));
                })
                ->with('enrollment.student')
                ->get();

            if ($terdampak->isNotEmpty()) {
                $namaMurid = $terdampak
                    ->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                    ->unique()
                    ->implode(', ');
                $warning = "Perhatian: {$terdampak->count()} jadwal aktif terdampak perubahan fasilitas ini: {$namaMurid}. Perbarui jadwal mereka secara manual.";
            }
        }

        $validated['supported_instruments'] = $instrumenBaru;
        $validated['is_active'] = $request->boolean('is_active');

        $room->update($validated);

        return redirect()
            ->route('rooms.index')
            ->with('success', 'Ruangan berhasil diperbarui.')
            ->with('warning', $warning);
    }

    public function destroy(Room $room)
    {
        // Tolak hapus jika masih ada schedule aktif
        if ($room->schedules()->active()->exists()) {
            return back()->with('error',
                "Ruangan [{$room->code}] masih dipakai oleh jadwal aktif. Nonaktifkan ruangan atau pindahkan jadwal dulu.");
        }

        $room->delete();
        return redirect()->route('rooms.index')->with('success', 'Ruangan berhasil dihapus.');
    }
}
