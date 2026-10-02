<?php

namespace App\Http\Controllers;

use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** CRUD identitas event Mini Concert saat masih DRAFT. */
class EventController extends Controller
{
    public function index()
    {
        $events = Event::withCount('participants')
            ->orderByDesc('event_date')
            ->paginate(20);

        return view('events.index', compact('events'));
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

    public function show(Event $event)
    {
        $event->load([
            'participants.student',
            'participants.enrollment.package.instrument',
            'participants.activeTeacherAssignment.teacher',
        ]);
        $availableStudents = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->active())
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_code', 'status']);
        $activeTeachers = Teacher::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('events.show', compact('event', 'availableStudents', 'activeTeachers'));
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
