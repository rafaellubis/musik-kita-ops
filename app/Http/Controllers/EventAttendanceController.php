<?php

namespace App\Http\Controllers;

use App\Http\Requests\Event\ConfirmTeacherAttendanceRequest;
use App\Http\Requests\Event\ReassignEventParticipantRequest;
use App\Http\Requests\Event\UpdateParticipantAttendanceRequest;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\Teacher;
use App\Services\EventAttendanceService;

class EventAttendanceController extends Controller
{
    public function __construct(private EventAttendanceService $attendanceService) {}

    public function confirmTeacher(ConfirmTeacherAttendanceRequest $request, EventTeacherAssignment $assignment)
    {
        $this->attendanceService->confirmTeacher(
            $assignment, $request->validated('attendance_status'), $request->user(), $request->validated('reason'),
        );

        return back()->with('success', 'Kehadiran guru berhasil diperbarui.');
    }

    public function recordParticipant(UpdateParticipantAttendanceRequest $request, EventParticipant $participant)
    {
        $this->attendanceService->recordParticipant(
            $participant, $request->validated('attendance_status'), $request->validated('event_notes'), $request->user(),
        );

        return back()->with('success', 'Kehadiran murid berhasil diperbarui.');
    }

    public function resetParticipant(UpdateParticipantAttendanceRequest $request, EventParticipant $participant)
    {
        $this->attendanceService->resetParticipant($participant, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Kehadiran murid dikembalikan ke PENDING.');
    }

    public function reassignParticipant(ReassignEventParticipantRequest $request, EventParticipant $participant)
    {
        $this->attendanceService->reassignParticipant(
            $participant, Teacher::findOrFail($request->integer('teacher_id')), $request->user(), $request->validated('reason'),
        );

        return back()->with('success', 'Guru pendamping berhasil diganti.');
    }
}
