<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\ProgressReport;
use App\Models\ProgressReportSessionNote;
use Illuminate\Support\Facades\DB;

class SessionNoteSyncService
{
    /**
     * Replace all session note snapshots for a progress report from eligible
     * HADIR / HADIR_TERLAMBAT class sessions in the report month.
     */
    public function sync(ProgressReport $report): void
    {
        DB::transaction(function () use ($report) {
            $sessions = ClassSession::query()
                ->where('enrollment_id', $report->enrollment_id)
                ->whereMonth('session_date', $report->month)
                ->whereYear('session_date', $report->year)
                ->whereIn('status', [
                    ClassSession::STATUS_HADIR,
                    ClassSession::STATUS_HADIR_TERLAMBAT,
                ])
                ->with('teacherNote')
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get();

            $report->sessionNotes()->delete();

            foreach ($sessions as $index => $session) {
                $teacherNote = $session->teacherNote;

                ProgressReportSessionNote::create([
                    'progress_report_id' => $report->id,
                    'class_session_id'   => $session->id,
                    'session_date'       => $session->session_date,
                    'session_sequence'   => $session->session_sequence,
                    'material_learned'   => $teacherNote?->material_learned,
                    'homework_notes'     => $teacherNote?->homework_notes,
                    'notes'              => $teacherNote?->notes ?? '',
                    'sort_order'         => $index,
                ]);
            }
        });
    }
}
