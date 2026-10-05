<?php

namespace App\Http\Controllers;

use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EventFinanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** CRUD identitas event Mini Concert saat masih DRAFT. */
class EventController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2024', 'max:2030'],
            'status' => ['nullable', 'in:DRAFT,PUBLISHED,COMPLETED,CANCELLED'],
            'type' => ['nullable', 'in:MINI_CONCERT,UJIAN_GRADE'],
        ]);

        $events = Event::query()
            ->addSelect([
                'active_participants_count' => EventParticipant::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('event_id', 'events.id')
                    ->where('status', EventParticipant::STATUS_ACTIVE),
                'unique_students_count' => EventParticipant::query()
                    ->selectRaw('COUNT(DISTINCT student_id)')
                    ->whereColumn('event_id', 'events.id')
                    ->where('status', EventParticipant::STATUS_ACTIVE),
                'teachers_count' => EventParticipant::query()
                    ->selectRaw('COUNT(DISTINCT active_teacher_assignment_id)')
                    ->whereColumn('event_id', 'events.id')
                    ->where('status', EventParticipant::STATUS_ACTIVE),
                'ready_teachers_count' => EventParticipant::query()
                    ->selectRaw('COUNT(DISTINCT active_teacher_assignment_id)')
                    ->whereColumn('event_id', 'events.id')
                    ->where('status', EventParticipant::STATUS_ACTIVE)
                    ->whereHas('activeTeacherAssignment', fn ($assignment) => $assignment
                        ->where('attendance_status', EventTeacherAssignment::ATTENDANCE_HADIR)),
                'recorded_attendance_count' => EventParticipant::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('event_id', 'events.id')
                    ->where('status', EventParticipant::STATUS_ACTIVE)
                    ->where('attendance_status', '!=', EventParticipant::ATTENDANCE_PENDING),
                'active_invoices_count' => Invoice::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('event_id', 'events.id')
                    ->where('status', '!=', Invoice::STATUS_VOID),
                'paid_invoices_count' => Invoice::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('event_id', 'events.id')
                    ->where('status', Invoice::STATUS_PAID),
            ])
            ->when(isset($filters['year']), fn ($query) => $query->whereYear('event_date', $filters['year']))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderByDesc('event_date')
            ->paginate(20)
            ->withQueryString();
        $years = Event::query()->orderByDesc('event_date')->pluck('event_date')
            ->map(fn ($date) => Carbon::parse($date)->year)->unique()->values();

        return view('events.index', compact('events', 'years', 'filters'));
    }

    public function create()
    {
        return view('events.create');
    }

    public function store(StoreEventRequest $request)
    {
        $data = $request->validated();
        $event = DB::transaction(function () use ($data, $request) {
            $event = Event::create([
                ...$data,
                'event_number' => $this->generateEventNumber(Carbon::parse($data['event_date'])->year),
                'status' => Event::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            AuditLog::record(
                action: AuditLog::ACTION_CREATE,
                entity: $event,
                entityLabel: $event->event_number,
                newValues: $event->only(['name', 'type', 'event_date', 'payment_due_date', 'status']),
            );

            return $event;
        });

        return redirect()->route('events.show', $event)
            ->with('success', "Event «{$event->name}» berhasil dibuat.");
    }

    public function show(Event $event, EventFinanceService $financeService)
    {
        $event->load([
            'participants.student',
            'participants.enrollment.package.instrument',
            'participants.activeTeacherAssignment.teacher',
            'participants.activeTeacherAssignment.confirmedBy',
            'invoices' => fn ($query) => $query->with('student:id,full_name,student_code')->orderBy('invoice_number'),
        ]);
        $financeSummary = $financeService->summary($event);
        $availableStudents = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->active())
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_code', 'status']);
        $activeTeachers = Teacher::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('events.show', compact('event', 'availableStudents', 'activeTeachers', 'financeSummary'));
    }

    public function edit(Event $event)
    {
        abort_unless($event->isDraft(), 422, 'Hanya event DRAFT yang dapat diubah.');

        return view('events.edit', compact('event'));
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $event = DB::transaction(function () use ($event, $request) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($event->isDraft(), 422, 'Hanya event DRAFT yang dapat diubah.');

            $oldValues = $event->only(['name', 'type', 'event_date', 'payment_due_date', 'notes']);
            $event->update($request->validated());

            AuditLog::record(
                action: AuditLog::ACTION_UPDATE,
                entity: $event,
                entityLabel: $event->event_number,
                oldValues: $oldValues,
                newValues: $event->only(array_keys($oldValues)),
            );

            return $event;
        });

        return redirect()->route('events.show', $event)
            ->with('success', 'Data event berhasil diperbarui.');
    }

    public function destroy(Event $event)
    {
        DB::transaction(function () use ($event) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($event->isDraft(), 422, 'Hanya event DRAFT yang dapat dihapus.');

            AuditLog::record(
                action: AuditLog::ACTION_DELETE,
                entity: $event,
                entityLabel: $event->event_number,
                oldValues: $event->only(['name', 'type', 'event_date', 'payment_due_date', 'status']),
            );
            $event->delete();
        });

        return redirect()->route('events.index')->with('success', 'Event DRAFT berhasil dihapus.');
    }

    private function generateEventNumber(int $year): string
    {
        $latest = Event::query()
            ->where('event_number', 'like', "EVT/{$year}/%")
            ->orderByDesc('event_number')
            ->value('event_number');
        $nextSequence = $latest ? ((int) substr(strrchr($latest, '/'), 1)) + 1 : 1;

        return sprintf('EVT/%d/%04d', $year, $nextSequence);
    }
}
