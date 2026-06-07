<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualSessionRequest;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\ManualSessionService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class ManualSessionController extends Controller
{
    public function __construct(private readonly ManualSessionService $manualSessionService) {}

    public function store(
        StoreManualSessionRequest $request,
        Student $student,
        Enrollment $enrollment,
    ): RedirectResponse {
        abort_if($enrollment->student_id !== $student->id, 404);
        abort_if($enrollment->status !== 'ACTIVE', 422, 'Enrollment tidak aktif.');

        try {
            $session = $this->manualSessionService->create(
                enrollment: $enrollment,
                sessionDate: $request->validated('session_date'),
                startTime: $request->validated('start_time'),
                roomId: $request->validated('room_id'),
                attributionYear: (int) $request->validated('attribution_year'),
                attributionMonth: (int) $request->validated('attribution_month'),
                sessionSequence: $request->validated('session_sequence'),
            );
        } catch (InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['manual_session' => $e->getMessage()]);
        }

        AuditLog::record(
            action: AuditLog::ACTION_CREATE,
            entity: $session,
            entityLabel: "Sesi manual – {$student->full_name} seq {$session->session_sequence}",
            newValues: $session->only([
                'session_date', 'session_sequence', 'attribution_year', 'attribution_month',
            ]),
        );

        return redirect()
            ->route('students.show', $student)
            ->withFragment('tab-kelas')
            ->with('success', "Sesi manual (slot {$session->session_sequence}) berhasil dibuat.");
    }
}
