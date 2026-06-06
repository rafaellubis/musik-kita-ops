<?php

namespace App\Http\Controllers;

use App\Models\SessionReportWaLog;
use App\Services\SessionReportWaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionReportWaLogController extends Controller
{
    public function __construct(
        private readonly SessionReportWaService $waService,
    ) {}

    public function index(Request $request): View
    {
        $query = SessionReportWaLog::query()
            ->with(['student', 'classSession.teacher', 'classSession.enrollment.package.instrument'])
            ->latest('sent_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->whereHas('student', fn ($q) => $q
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('student_code', 'like', "%{$search}%"));
        }

        if ($date = $request->get('date')) {
            $query->whereHas('classSession', fn ($q) => $q->whereDate('session_date', $date));
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('session-report-wa-logs.index', [
            'logs'      => $logs,
            'waService' => $this->waService,
            'filters'   => $request->only(['status', 'search', 'date']),
        ]);
    }

    public function resend(SessionReportWaLog $sessionReportWaLog): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['Owner', 'Admin']),
            403,
        );

        $session = $sessionReportWaLog->classSession()
            ->with(['student', 'teacher', 'substituteTeacher', 'enrollment.package.instrument', 'teacherNote'])
            ->first();

        if (! $session || ! $session->teacherNote) {
            return back()->with('error', 'Sesi atau catatan tidak ditemukan.');
        }

        $this->waService->sendForSession($session, null, force: true);

        return back()->with('success', 'Permintaan kirim ulang diproses.');
    }
}
