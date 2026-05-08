<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventHonorSlip;
use App\Models\Teacher;
use Illuminate\Http\Request;

/**
 * Slip honor guru untuk event (M08).
 *
 * Semua aksi (buat, edit, tandai bayar) hanya Owner.
 * Print bisa semua role ter-autentikasi.
 */
class EventHonorSlipController extends Controller
{
    /**
     * Buat slip honor untuk satu guru di event ini.
     * base_honor default Rp 250.000 (H_UJIAN flat).
     */
    public function store(Request $request, Event $event)
    {
        $data = $request->validate([
            'teacher_id'  => 'required|exists:teachers,id',
            'role'        => 'nullable|string|max:100',
            'base_honor'  => 'required|integer|min:0|max:9999999',
        ], [
            'teacher_id.required' => 'Pilih guru terlebih dahulu.',
            'base_honor.required' => 'Honor pokok wajib diisi.',
        ]);

        // Cek duplikat guru di event ini
        $exists = EventHonorSlip::where('event_id', $event->id)
            ->where('teacher_id', $data['teacher_id'])
            ->exists();
        if ($exists) {
            return back()->with('error', 'Slip honor untuk guru ini sudah ada.');
        }

        $teacher = Teacher::find($data['teacher_id']);

        $slip = EventHonorSlip::create([
            'slip_number'    => $this->generateSlipNumber($event),
            'event_id'       => $event->id,
            'teacher_id'     => $data['teacher_id'],
            'role'           => $data['role'] ?? null,
            'base_honor'     => $data['base_honor'],
            'transport_honor'=> 0,
            'other_honor'    => 0,
            'total_honor'    => $data['base_honor'],
            'status'         => EventHonorSlip::STATUS_DRAFT,
            'created_by'     => auth()->id(),
        ]);

        return redirect()->route('event-honor-slips.edit', $slip)
            ->with('success', "Slip honor untuk «{$teacher->name}» berhasil dibuat. Lengkapi komponen manual.");
    }

    public function edit(EventHonorSlip $eventHonorSlip)
    {
        if ($eventHonorSlip->isLocked()) {
            return redirect()->route('events.show', $eventHonorSlip->event_id)
                ->with('error', 'Slip yang sudah dibayarkan tidak bisa diedit.');
        }

        $eventHonorSlip->load('event', 'teacher');
        return view('event-honor-slips.edit', ['slip' => $eventHonorSlip]);
    }

    public function update(Request $request, EventHonorSlip $eventHonorSlip)
    {
        if ($eventHonorSlip->isLocked()) {
            return back()->with('error', 'Slip yang sudah dibayarkan tidak bisa diedit.');
        }

        $data = $request->validate([
            'role'             => 'nullable|string|max:100',
            'base_honor'       => 'required|integer|min:0|max:9999999',
            'transport_honor'  => 'required|integer|min:0|max:9999999',
            'other_honor'      => 'required|integer|min:0|max:9999999',
            'other_honor_note' => 'nullable|string|max:255',
        ], [
            'base_honor.required'      => 'Honor pokok wajib diisi.',
            'transport_honor.required' => 'Honor transport wajib diisi (isi 0 jika tidak ada).',
            'other_honor.required'     => 'Honor lain-lain wajib diisi (isi 0 jika tidak ada).',
        ]);

        // Keterangan wajib jika other_honor > 0
        if ((int) $data['other_honor'] > 0 && empty(trim($data['other_honor_note'] ?? ''))) {
            return back()->withErrors(['other_honor_note' => 'Keterangan lain-lain wajib diisi jika ada honor lain-lain.'])
                         ->withInput();
        }

        $eventHonorSlip->fill([
            'role'             => $data['role'] ?? null,
            'base_honor'       => $data['base_honor'],
            'transport_honor'  => $data['transport_honor'],
            'other_honor'      => $data['other_honor'],
            'other_honor_note' => $data['other_honor_note'] ?? null,
        ]);
        $eventHonorSlip->recalcTotal();

        return redirect()->route('events.show', $eventHonorSlip->event_id)
            ->with('success', 'Slip honor berhasil diperbarui.');
    }

    /**
     * Tandai slip sudah dibayarkan. Setelah ini slip terkunci dari edit.
     */
    public function markPaid(EventHonorSlip $eventHonorSlip)
    {
        if ($eventHonorSlip->isLocked()) {
            return back()->with('error', 'Slip ini sudah ditandai dibayarkan.');
        }

        $eventHonorSlip->update([
            'status'  => EventHonorSlip::STATUS_PAID,
            'paid_at' => now(),
            'paid_by' => auth()->id(),
        ]);

        return redirect()->route('events.show', $eventHonorSlip->event_id)
            ->with('success', "Slip honor «{$eventHonorSlip->teacher->name}» ditandai sudah dibayarkan.");
    }

    /**
     * Halaman cetak slip honor event.
     * Layout standalone (tanpa header/nav).
     */
    public function print(EventHonorSlip $eventHonorSlip)
    {
        $eventHonorSlip->load('event', 'teacher', 'paidBy');
        return view('event-honor-slips.print', ['slip' => $eventHonorSlip]);
    }

    /**
     * Hapus slip yang masih DRAFT (belum dibayar).
     */
    public function destroy(EventHonorSlip $eventHonorSlip)
    {
        if ($eventHonorSlip->isLocked()) {
            return back()->with('error', 'Slip yang sudah dibayarkan tidak bisa dihapus.');
        }

        $teacherName = $eventHonorSlip->teacher->name;
        $eventId     = $eventHonorSlip->event_id;
        $eventHonorSlip->delete();

        return redirect()->route('events.show', $eventId)
            ->with('success', "Slip honor «{$teacherName}» berhasil dihapus.");
    }

    // ============= HELPERS =============

    private function generateSlipNumber(Event $event): string
    {
        $year  = $event->event_date->year;
        $latest = EventHonorSlip::where('slip_number', 'like', "EVT-SLIP/{$year}/%")
            ->orderBy('slip_number', 'desc')
            ->value('slip_number');

        $nextSeq = 1;
        if ($latest) {
            $parts   = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('EVT-SLIP/%d/%04d', $year, $nextSeq);
    }
}
