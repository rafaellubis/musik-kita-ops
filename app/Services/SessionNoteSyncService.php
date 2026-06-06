<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\ProgressReport;
use App\Models\ProgressReportSessionNote;
use App\Models\SessionTeacherNote;
use Illuminate\Support\Facades\DB;

class SessionNoteSyncService
{
    /**
     * Replace all session note snapshots for a progress report from eligible
     * HADIR / HADIR_TERLAMBAT class sessions, plus confirmed DIGANTI sessions
     * that already have substitute teacher notes.
     */
    public function sync(ProgressReport $report): void
    {
        DB::transaction(function () use ($report) {
            $sessions = ClassSession::query()
                ->where('enrollment_id', $report->enrollment_id)
                ->whereMonth('session_date', $report->month)
                ->whereYear('session_date', $report->year)
                ->where(function ($query) {
                    $query->whereIn('status', [
                        ClassSession::STATUS_HADIR,
                        ClassSession::STATUS_HADIR_TERLAMBAT,
                    ])->orWhere(function ($sub) {
                        $sub->where('status', ClassSession::STATUS_DIGANTI)
                            ->whereNotNull('honor_code');
                    });
                })
                ->with(['teacherNote', 'substituteTeacher'])
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get()
                ->filter(function (ClassSession $session) {
                    if (in_array($session->status, [
                        ClassSession::STATUS_HADIR,
                        ClassSession::STATUS_HADIR_TERLAMBAT,
                    ], true)) {
                        return true;
                    }

                    return $session->status === ClassSession::STATUS_DIGANTI
                        && self::noteHasContent($session->teacherNote);
                })
                ->values();

            $report->sessionNotes()->delete();

            foreach ($sessions as $index => $session) {
                $teacherNote = $session->teacherNote;
                $isSubstituteSession = $session->status === ClassSession::STATUS_DIGANTI;

                ProgressReportSessionNote::create([
                    'progress_report_id'       => $report->id,
                    'class_session_id'         => $session->id,
                    'session_date'             => $session->session_date,
                    'session_sequence'         => $session->session_sequence,
                    'material_learned'         => $teacherNote?->material_learned,
                    'homework_notes'           => $teacherNote?->homework_notes,
                    'notes'                    => $teacherNote?->notes ?? '',
                    'session_rating'           => $teacherNote?->session_rating,
                    'substitute_teacher_name'  => $isSubstituteSession
                        ? $session->substituteTeacher?->name
                        : null,
                    'sort_order'               => $index,
                ]);
            }
        });
    }

    private static function noteHasContent(?SessionTeacherNote $note): bool
    {
        if (! $note) {
            return false;
        }

        return filled(trim((string) ($note->material_learned ?? '')))
            || filled(trim((string) ($note->homework_notes ?? '')))
            || filled(trim((string) ($note->notes ?? '')))
            || filled($note->session_rating);
    }
}
