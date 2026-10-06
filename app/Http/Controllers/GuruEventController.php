<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\Teacher;
use App\Services\EventAttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GuruEventController extends Controller
{
    public function __construct(private EventAttendanceService $attendanceService) {}

    public function index(Request $request)
    {
        $teacher = $this->teacher($request);
        $events = $this->eventsFor($teacher)
            ->with(['teacherAssignments' => fn ($query) => $query
                ->where('teacher_id', $teacher->id)
                ->with(['participants' => fn ($participants) => $participants
                    ->where('status', EventParticipant::STATUS_ACTIVE)])])
            ->orderBy('event_date')
            ->get();

        $published = $events->where('status', Event::STATUS_PUBLISHED);
        $upcomingEvents = $published->filter(fn (Event $event) => $event->event_date->isFuture());
        $actionEvents = $published->filter(function (Event $event) {
            $assignment = $event->teacherAssignments->first();

            return $event->event_date->lte(today())
                && $assignment?->attendance_status === EventTeacherAssignment::ATTENDANCE_HADIR
                && $assignment->participants->contains(
                    'attendance_status', EventParticipant::ATTENDANCE_PENDING,
                );
        });
        $completedEvents = $events->where('status', Event::STATUS_COMPLETED)->sortByDesc('event_date');

        return view('guru.events.index', compact(
            'teacher', 'upcomingEvents', 'actionEvents', 'completedEvents',
        ));
    }

    public function show(Request $request, Event $event)
    {
        $teacher = $this->teacher($request);
        $event = $this->eventsFor($teacher)
            ->with(['teacherAssignments' => fn ($query) => $query
                ->where('teacher_id', $teacher->id)
                ->with([
                    'confirmedBy',
                    'participants' => fn ($participants) => $participants
                        ->where('status', EventParticipant::STATUS_ACTIVE)
                        ->with(['student', 'enrollment.package.instrument', 'attendanceUpdatedBy']),
                ])])
            ->findOrFail($event->id);

        $assignment = $event->teacherAssignments->firstOrFail();
        $participants = $assignment->participants;
        $estimatedHonor = (int) $participants->sum('snapshot_honor_amount');

        return view('guru.events.show', compact(
            'teacher', 'event', 'assignment', 'participants', 'estimatedHonor',
        ));
    }

    public function updateAttendance(Request $request, EventParticipant $participant)
    {
        $teacher = $this->teacher($request);
        $participant = EventParticipant::query()
            ->whereKey($participant->id)
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->whereHas('event', fn ($event) => $event->whereIn('status', [
                Event::STATUS_PUBLISHED,
                Event::STATUS_COMPLETED,
            ]))
            ->whereHas('activeTeacherAssignment', fn ($assignment) => $assignment
                ->where('teacher_id', $teacher->id))
            ->firstOrFail();

        $data = $request->validate([
            'attendance_status' => ['required', 'in:HADIR,TIDAK_HADIR'],
            'event_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'attendance_status.required' => 'Pilih kehadiran murid terlebih dahulu.',
            'attendance_status.in' => 'Status kehadiran murid tidak valid.',
            'event_notes.max' => 'Catatan event maksimal 1000 karakter.',
        ]);

        try {
            $this->attendanceService->recordParticipant(
                $participant,
                $data['attendance_status'],
                $data['event_notes'] ?? null,
                $request->user(),
            );
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            // Jalur AJAX mengembalikan pesan yang sama sebagai JSON supaya kartu bisa
            // menampilkannya inline tanpa kehilangan catatan yang sudah diketik.
            if ($request->wantsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()->route('guru.events.show', $participant->event_id)
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        if ($request->wantsJson()) {
            return $this->attendanceJsonResponse($participant, $teacher);
        }

        return redirect()->route('guru.events.show', $participant->event_id)
            ->with('success', 'Kehadiran murid berhasil diperbarui.');
    }

    /**
     * Payload JSON untuk simpan absensi tanpa reload halaman.
     * Kartu di-render ulang dari partial yang sama dengan halaman event,
     * jadi tidak ada markup kartu yang diduplikasi di sisi klien.
     */
    private function attendanceJsonResponse(EventParticipant $participant, Teacher $teacher): JsonResponse
    {
        $participant->refresh();
        $participant->load(['student', 'enrollment.package.instrument', 'attendanceUpdatedBy']);

        $event = $participant->event;
        $assignment = $event->teacherAssignments()
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $participants = $assignment->participants()
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->get();

        return response()->json([
            'success' => true,
            'incomplete' => $participants
                ->where('attendance_status', EventParticipant::ATTENDANCE_PENDING)
                ->count(),
            'card_html' => view('guru.events.participant-card', [
                'participant' => $participant,
                'event' => $event,
                'assignment' => $assignment,
            ])->render(),
        ]);
    }

    private function teacher(Request $request): Teacher
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403, 'Akun ini tidak terhubung ke data guru.');

        return $teacher;
    }

    private function eventsFor(Teacher $teacher): Builder
    {
        return Event::query()
            ->whereIn('status', [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED])
            ->whereHas('teacherAssignments', fn ($assignment) => $assignment
                ->where('teacher_id', $teacher->id)
                ->whereHas('participants', fn ($participants) => $participants
                    ->where('status', EventParticipant::STATUS_ACTIVE)));
    }
}
