<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\Student;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manajemen event studio: Mini Concert & Ujian (M08).
 *
 * Akses:
 *   - Buat / edit event          : Owner only
 *   - Tambah / hapus peserta     : Owner + Admin
 *   - Input hasil ujian          : Owner only
 *   - Tandai completed           : Owner only
 *   - Read (index, show)         : Owner + Admin + Auditor
 */
class EventController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index()
    {
        $events = Event::withCount('participants')
            ->orderBy('event_date', 'desc')
            ->paginate(20);

        return view('events.index', compact('events'));
    }

    public function create()
    {
        return view('events.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:MINI_CONCERT,UJIAN,MINI_CONCERT_UJIAN',
            'event_date' => 'required|date',
            'notes'      => 'nullable|string|max:1000',
        ], [
            'name.required'       => 'Nama event wajib diisi.',
            'type.required'       => 'Tipe event wajib dipilih.',
            'event_date.required' => 'Tanggal event wajib diisi.',
        ]);

        $date = Carbon::parse($data['event_date']);

        $event = Event::create([
            'event_number' => $this->generateEventNumber($date->year),
            'name'         => $data['name'],
            'type'         => $data['type'],
            'event_date'   => $data['event_date'],
            'notes'        => $data['notes'] ?? null,
            'status'       => Event::STATUS_DRAFT,
            'created_by'   => auth()->id(),
        ]);

        return redirect()->route('events.show', $event)
            ->with('success', "Event «{$event->name}» berhasil dibuat. Tambahkan peserta di bawah.");
    }

    public function show(Event $event)
    {
        $event->load(['participants.student', 'participants.enrollment.package', 'participants.accompanyingTeacher']);

        // Daftar murid aktif yang belum terdaftar sebagai peserta event ini
        $registeredStudentIds = $event->participants->pluck('student_id')->toArray();
        $availableStudents = Student::whereIn('status', ['Aktif', 'Trial'])
            ->whereNotIn('id', $registeredStudentIds)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_code', 'status']);

        // Untuk dropdown guru pendamping di Konser KITA
        $activeTeachers = \App\Models\Teacher::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('events.show', compact('event', 'availableStudents', 'activeTeachers'));
    }

    public function edit(Event $event)
    {
        return view('events.edit', compact('event'));
    }

    public function update(Request $request, Event $event)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:MINI_CONCERT,UJIAN,MINI_CONCERT_UJIAN',
            'event_date' => 'required|date',
            'notes'      => 'nullable|string|max:1000',
        ], [
            'name.required'       => 'Nama event wajib diisi.',
            'event_date.required' => 'Tanggal event wajib diisi.',
        ]);

        if ($event->isCompleted()) {
            return back()->with('error', 'Event yang sudah selesai tidak bisa diubah.');
        }

        $event->update($data);
        return redirect()->route('events.show', $event)
            ->with('success', 'Data event berhasil diperbarui.');
    }

    // ============= PESERTA =============

    /**
     * Tambah murid sebagai peserta event.
     * Otomatis membuat invoice item di invoice bulan event.
     */
    public function addParticipant(Request $request, Event $event)
    {
        $data = $request->validate([
            'student_id'         => 'required|exists:students,id',
            'participation_type' => 'required|in:UJIAN_TAMPIL,TAMPIL_SAJA',
        ], [
            'student_id.required'         => 'Pilih murid terlebih dahulu.',
            'participation_type.required' => 'Pilih tipe partisipasi.',
        ]);

        // Cek duplikat
        $exists = EventParticipant::where('event_id', $event->id)
            ->where('student_id', $data['student_id'])
            ->exists();
        if ($exists) {
            return back()->with('error', 'Murid ini sudah terdaftar sebagai peserta.');
        }

        $student = Student::with(['enrollments' => fn ($q) => $q->where('status', 'ACTIVE')])->find($data['student_id']);
        $enrollment = $student->enrollments->first();

        $fee = $data['participation_type'] === EventParticipant::TYPE_UJIAN_TAMPIL
            ? EventParticipant::FEE_UJIAN_TAMPIL
            : EventParticipant::FEE_TAMPIL_SAJA;

        $invoiceCode = EventParticipant::INVOICE_CODE[$data['participation_type']];
        $eventDate   = Carbon::parse($event->event_date);

        DB::transaction(function () use ($event, $student, $enrollment, $data, $fee, $invoiceCode, $eventDate) {
            // Cari invoice bulan event yang belum lunas
            $invoice = Invoice::where('student_id', $student->id)
                ->where('year', $eventDate->year)
                ->where('month', $eventDate->month)
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
                ->first();

            // Kalau tidak ada, buat invoice baru khusus event fee
            if (!$invoice) {
                $invoice = $this->invoiceService->createOneOff(
                    student: $student,
                    items: [[
                        'code'        => $invoiceCode,
                        'description' => $this->buildFeeDescription($data['participation_type'], $event),
                        'amount'      => $fee,
                        'metadata'    => ['event_id' => $event->id],
                    ]],
                    description: "Biaya Event: {$event->name}",
                    issuedAt: $eventDate,
                );

                $invoiceItem = $invoice->items->first();
            } else {
                // Tambahkan item ke invoice yang sudah ada
                $invoiceItem = InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'item_code'   => $invoiceCode,
                    'description' => $this->buildFeeDescription($data['participation_type'], $event),
                    'amount'      => $fee,
                    'metadata'    => ['event_id' => $event->id],
                    'added_by'    => auth()->id(),
                ]);

                // Recalc total invoice
                $newTotal = $invoice->items()->sum('amount');
                $invoice->update(['total_amount' => $newTotal]);
                $this->invoiceService->recalcStatus($invoice);
            }

            EventParticipant::create([
                'event_id'           => $event->id,
                'student_id'         => $student->id,
                'enrollment_id'      => $enrollment?->id,
                'participation_type' => $data['participation_type'],
                'fee_amount'         => $fee,
                'invoice_id'         => $invoice->id,
                'invoice_item_id'    => $invoiceItem->id,
            ]);
        });

        return back()->with('success', "Murid «{$student->full_name}» berhasil ditambahkan sebagai peserta.");
    }

    /**
     * Hapus peserta dari event.
     * Juga hapus invoice item yang dibuat, dan recalc invoice.
     */
    public function removeParticipant(EventParticipant $participant)
    {
        if ($participant->event->isCompleted()) {
            return back()->with('error', 'Peserta tidak bisa dihapus dari event yang sudah selesai.');
        }

        $studentName = $participant->student->full_name;

        DB::transaction(function () use ($participant) {
            // Hapus invoice item & recalc invoice
            if ($participant->invoiceItem) {
                $invoice = $participant->invoice;
                $participant->invoiceItem->delete();

                if ($invoice) {
                    $newTotal = $invoice->items()->sum('amount');
                    if ($newTotal > 0) {
                        $invoice->update(['total_amount' => $newTotal]);
                        $this->invoiceService->recalcStatus($invoice);
                    } else {
                        // Invoice kosong — hapus saja jika belum ada pembayaran
                        $hasPaid = $invoice->validPayments()->exists();
                        if (!$hasPaid) {
                            $invoice->delete();
                        }
                    }
                }
            }
            $participant->delete();
        });

        return back()->with('success', "Peserta «{$studentName}» berhasil dihapus dari event.");
    }

    /**
     * Update guru pendamping untuk satu peserta event.
     * Hanya bisa diubah selama event masih DRAFT.
     * Digunakan untuk Konser KITA — mencatat guru yang mendampingi murid saat konser.
     */
    public function updateParticipantTeacher(Request $request, EventParticipant $participant)
    {
        if ($participant->event->isCompleted()) {
            return back()->with('error', 'Tidak bisa ubah guru pendamping — event sudah selesai.');
        }

        $request->validate([
            'accompanying_teacher_id' => 'nullable|exists:teachers,id',
        ]);

        $participant->update([
            'accompanying_teacher_id' => $request->input('accompanying_teacher_id') ?: null,
        ]);

        return back()->with('success', 'Guru pendamping berhasil diperbarui.');
    }

    // ============= HASIL UJIAN =============

    /**
     * Simpan hasil ujian untuk semua peserta UJIAN_TAMPIL.
     * Grade naik otomatis jika LULUS (hanya untuk paket REGULER).
     */
    public function saveExamResults(Request $request, Event $event)
    {
        if (!$event->hasExam()) {
            return back()->with('error', 'Tipe event ini tidak punya komponen ujian.');
        }

        // Validasi array results[participant_id] = LULUS|TIDAK_LULUS
        $data = $request->validate([
            'results'            => 'required|array',
            'results.*'          => 'nullable|in:LULUS,TIDAK_LULUS',
            'notes'              => 'nullable|array',
            'notes.*'            => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($event, $data) {
            $ujianParticipants = $event->participants()
                ->where('participation_type', EventParticipant::TYPE_UJIAN_TAMPIL)
                ->with(['student', 'enrollment.package'])
                ->get();

            foreach ($ujianParticipants as $participant) {
                $result = $data['results'][$participant->id] ?? null;
                $note   = $data['notes'][$participant->id] ?? null;

                if ($result === null) continue;

                $gradeAfter = null;

                // Upgrade grade otomatis jika LULUS & paket REGULER
                if ($result === EventParticipant::RESULT_LULUS && $participant->enrollment) {
                    $currentPkg = $participant->enrollment->package;
                    if ($currentPkg && $currentPkg->class_type === 'REGULER') {
                        $nextPkg = Package::where('instrument_id', $currentPkg->instrument_id)
                            ->where('class_type', 'REGULER')
                            ->where('duration_min', $currentPkg->duration_min)
                            ->where('sort_order', '>', $currentPkg->sort_order)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->first();

                        if ($nextPkg) {
                            $gradeAfter = $nextPkg->grade;
                            // Update package di enrollment
                            $participant->enrollment->update(['package_id' => $nextPkg->id]);
                        }
                    }
                }

                $participant->update([
                    'exam_result'  => $result,
                    'grade_before' => $participant->grade_before ?? $participant->enrollment?->package?->grade,
                    'grade_after'  => $gradeAfter,
                    'exam_notes'   => $note,
                ]);
            }
        });

        return back()->with('success', 'Hasil ujian berhasil disimpan.');
    }

    // ============= STATUS EVENT =============

    /**
     * Tandai event sebagai COMPLETED.
     * Setelah ini, peserta tidak bisa ditambah/dihapus.
     */
    public function complete(Event $event)
    {
        if ($event->isCompleted()) {
            return back()->with('error', 'Event sudah selesai.');
        }

        $event->update(['status' => Event::STATUS_COMPLETED]);
        return redirect()->route('events.show', $event)
            ->with('success', 'Event ditandai selesai. Silakan generate slip honor guru.');
    }

    // ============= HELPERS =============

    private function buildFeeDescription(string $type, Event $event): string
    {
        $label = $type === EventParticipant::TYPE_UJIAN_TAMPIL ? 'Ujian + Mini Concert' : 'Mini Concert';
        return "{$label} — {$event->name} ({$event->event_date->format('d M Y')})";
    }

    private function generateEventNumber(int $year): string
    {
        $latest = Event::where('event_number', 'like', "EVT/{$year}/%")
            ->orderBy('event_number', 'desc')
            ->value('event_number');

        $nextSeq = 1;
        if ($latest) {
            $parts   = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('EVT/%d/%04d', $year, $nextSeq);
    }
}
