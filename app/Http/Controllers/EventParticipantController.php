<?php

namespace App\Http\Controllers;

use App\Http\Requests\Event\CancelEventParticipantRequest;
use App\Http\Requests\Event\StoreEventParticipantsRequest;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EventParticipantService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventParticipantController extends Controller
{
    public function __construct(private EventParticipantService $participantService) {}

    public function store(StoreEventParticipantsRequest $request, Event $event)
    {
        $student = Student::findOrFail($request->integer('student_id'));
        $participants = $this->participantService->addEnrollments(
            $event,
            $student,
            $request->input('enrollment_ids') ?? [],
            $request->user(),
            $request->validated('reason'),
        );

        return back()->with('success', "{$participants->count()} enrollment berhasil ditambahkan.");
    }

    public function updateTeacher(Request $request, EventParticipant $participant)
    {
        $data = $request->validate([
            'teacher_id' => [
                'required',
                Rule::exists('teachers', 'id')->where('is_active', true),
            ],
        ], [
            'teacher_id.required' => 'Guru pendamping wajib dipilih.',
            'teacher_id.exists' => 'Guru pendamping harus masih aktif.',
        ]);

        $teacher = Teacher::findOrFail($data['teacher_id']);
        $this->participantService->assignDraftTeacher($participant, $teacher, $request->user());

        return back()->with('success', 'Guru pendamping berhasil diperbarui.');
    }

    public function destroy(CancelEventParticipantRequest $request, EventParticipant $participant)
    {
        $participant = $this->participantService->cancelParticipant(
            $participant,
            $request->user(),
            $request->validated('cancellation_reason'),
        );

        return back()->with('success', $participant->exists
            ? 'Partisipasi berhasil dibatalkan.'
            : 'Peserta berhasil dihapus dari event.');
    }
}
