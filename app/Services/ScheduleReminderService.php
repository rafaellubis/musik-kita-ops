<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\ScheduleReminderLog;
use App\Models\Student;
use App\Models\WhatsappMessageTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi pengingat jadwal WA: query sesi SCHEDULED, compose pesan, kirim via Fonnte.
 */
class ScheduleReminderService
{
    public function __construct(
        private readonly FonnteService $fonnte,
    ) {}

    /** Apakah fitur aktif dan siap dijalankan? */
    public function isEnabled(): bool
    {
        return (bool) config('schedule_reminder.enabled');
    }

    /** Mode pengingat dari config. */
    public function mode(): string
    {
        return (string) config('schedule_reminder.mode', ScheduleReminderLog::MODE_DAY_BEFORE);
    }

    /**
     * Apakah command cron boleh jalan pada waktu $now?
     * Mode hours_before selalu true (cron tiap 15 menit); lainnya cek send_time.
     */
    public function shouldRunAt(Carbon $now): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($this->mode() === ScheduleReminderLog::MODE_HOURS_BEFORE) {
            return true;
        }

        $sendTime = (string) config('schedule_reminder.send_time', '18:00');
        [$hour, $minute] = array_pad(explode(':', $sendTime), 2, '0');

        return $now->hour === (int) $hour && $now->minute === (int) $minute;
    }

    /** Tanggal sesi yang menjadi target pengingat. */
    public function targetDate(Carbon $now): Carbon
    {
        return match ($this->mode()) {
            ScheduleReminderLog::MODE_DAY_BEFORE => $now->copy()->addDay()->startOfDay(),
            default => $now->copy()->startOfDay(),
        };
    }

    /**
     * Kirim pengingat sesuai config. Return ringkasan eksekusi.
     *
     * @return array{
     *   skipped: bool,
     *   reason: ?string,
     *   sent: int,
     *   failed: int,
     *   skipped_students: int,
     *   errors: array<string, string>,
     * }
     */
    public function sendDueReminders(Carbon $now, bool $dryRun = false): array
    {
        $summary = [
            'skipped'          => false,
            'reason'           => null,
            'sent'             => 0,
            'failed'           => 0,
            'skipped_students' => 0,
            'errors'           => [],
        ];

        if (! $this->isEnabled()) {
            $summary['skipped'] = true;
            $summary['reason'] = 'Pengingat jadwal nonaktif (SCHEDULE_REMINDER_ENABLED=false).';

            return $summary;
        }

        if (! $this->shouldRunAt($now) && ! $dryRun) {
            $summary['skipped'] = true;
            $summary['reason'] = 'Belum waktunya kirim (mode=' . $this->mode() . ').';

            return $summary;
        }

        if (! $this->fonnte->isConfigured() && ! $dryRun) {
            $summary['skipped'] = true;
            $summary['reason'] = 'Kredensial Fonnte belum dikonfigurasi.';

            return $summary;
        }

        $template = WhatsappMessageTemplate::defaultScheduleReminder();
        if (! $template) {
            $summary['skipped'] = true;
            $summary['reason'] = 'Template SCHEDULE_REMINDER aktif tidak ditemukan.';

            return $summary;
        }

        $groups = $this->groupSessionsForReminder($now);
        if ($groups->isEmpty()) {
            $summary['reason'] = 'Tidak ada sesi SCHEDULED yang perlu diingatkan.';

            return $summary;
        }

        foreach ($groups as $group) {
            /** @var Student $student */
            $student = $group['student'];
            /** @var Collection<int, ClassSession> $sessions */
            $sessions = $group['sessions'];
            $targetDate = $group['target_date'];

            $recipientPhone = $this->resolveReminderPhone($student);
            if ($recipientPhone === null) {
                $summary['skipped_students']++;
                continue;
            }

            if ($this->alreadyReminded($student->id, $targetDate, $sessions)) {
                $summary['skipped_students']++;
                continue;
            }

            $message = $this->composeMessage($student, $sessions, $targetDate, $template);

            if ($dryRun) {
                $summary['sent']++;
                continue;
            }

            $phone = $this->fonnte->normalizePhone($recipientPhone);
            $result = $this->fonnte->sendText($recipientPhone, $message);

            $log = $this->persistLog(
                student: $student,
                targetDate: $targetDate,
                sessions: $sessions,
                phone: (string) $phone,
                message: $message,
                providerMessageIds: $result['message_ids'],
                status: $result['ok'] ? ScheduleReminderLog::STATUS_SUCCESS : ScheduleReminderLog::STATUS_FAILED,
                error: $result['error'],
            );

            if ($log->status === ScheduleReminderLog::STATUS_SUCCESS) {
                $summary['sent']++;
            } else {
                $summary['failed']++;
                $summary['errors'][$student->student_code] = $this->humanizeError($log->error_message ?? 'Gagal kirim.');
            }
        }

        return $summary;
    }

    /**
     * @return Collection<int, array{student: Student, sessions: Collection<int, ClassSession>, target_date: Carbon}>
     */
    public function groupSessionsForReminder(Carbon $now): Collection
    {
        $mode = $this->mode();

        if ($mode === ScheduleReminderLog::MODE_HOURS_BEFORE) {
            return $this->groupHoursBeforeSessions($now);
        }

        $targetDate = $this->targetDate($now);
        $sessions = $this->baseSessionQuery()
            ->whereDate('session_date', $targetDate->toDateString())
            ->get();

        return $this->groupByStudent($sessions, $targetDate);
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     * @return Collection<int, array{student: Student, sessions: Collection<int, ClassSession>, target_date: Carbon}>
     */
    private function groupByStudent(Collection $sessions, Carbon $targetDate): Collection
    {
        return $sessions
            ->groupBy('student_id')
            ->map(function (Collection $studentSessions) use ($targetDate) {
                $student = $studentSessions->first()->student;

                return [
                    'student'      => $student,
                    'sessions'     => $studentSessions->sortBy('start_time')->values(),
                    'target_date'  => $targetDate->copy()->startOfDay(),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{student: Student, sessions: Collection<int, ClassSession>, target_date: Carbon}>
     */
    private function groupHoursBeforeSessions(Carbon $now): Collection
    {
        $hoursBefore = (int) config('schedule_reminder.hours_before', 2);
        $window = (int) config('schedule_reminder.hours_before_window_minutes', 7);
        $targetMinutes = $hoursBefore * 60;

        $sessions = $this->baseSessionQuery()
            ->whereDate('session_date', $now->toDateString())
            ->get()
            ->filter(function (ClassSession $session) use ($now, $targetMinutes, $window) {
                $start = Carbon::parse("{$session->session_date} {$session->start_time}");
                $minutesUntilStart = $now->diffInMinutes($start, false);

                return $minutesUntilStart >= ($targetMinutes - $window)
                    && $minutesUntilStart <= ($targetMinutes + $window);
            });

        return $sessions
            ->groupBy('student_id')
            ->map(function (Collection $studentSessions) {
                $student = $studentSessions->first()->student;
                $sessionDate = Carbon::parse($studentSessions->first()->session_date)->startOfDay();

                return [
                    'student'      => $student,
                    'sessions'     => $studentSessions->sortBy('start_time')->values(),
                    'target_date'  => $sessionDate,
                ];
            })
            ->values();
    }

    private function baseSessionQuery()
    {
        return ClassSession::query()
            ->where('status', ClassSession::STATUS_SCHEDULED)
            ->whereHas('student', fn ($q) => $q->where('status', 'Aktif'))
            ->whereHas('enrollment', fn ($q) => $q->where('status', Enrollment::STATUS_ACTIVE))
            ->with([
                'student',
                'teacher',
                'room',
                'enrollment.package.instrument',
            ]);
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     */
    private function alreadyReminded(int $studentId, Carbon $targetDate, Collection $sessions): bool
    {
        $mode = $this->mode();
        $sessionIds = $sessions->pluck('id')->all();

        if ($mode === ScheduleReminderLog::MODE_HOURS_BEFORE) {
            foreach ($sessionIds as $sessionId) {
                $exists = ScheduleReminderLog::query()
                    ->where('reminder_mode', $mode)
                    ->where('status', ScheduleReminderLog::STATUS_SUCCESS)
                    ->whereJsonContains('class_session_ids', $sessionId)
                    ->exists();

                if ($exists) {
                    return true;
                }
            }

            return false;
        }

        return ScheduleReminderLog::query()
            ->where('student_id', $studentId)
            ->whereDate('target_date', $targetDate->toDateString())
            ->where('reminder_mode', $mode)
            ->where('status', ScheduleReminderLog::STATUS_SUCCESS)
            ->exists();
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     */
    public function composeMessage(
        Student $student,
        Collection $sessions,
        Carbon $targetDate,
        WhatsappMessageTemplate $template,
    ): string {
        $scheduleLines = $sessions->map(fn (ClassSession $session) => $this->formatSessionLine($session))->implode("\n");

        $replacements = [
            '{nama_murid}'     => $student->full_name,
            '{kode_murid}'     => $student->student_code,
            '{nama_ortu}'      => $student->parent_name ?? 'Bapak/Ibu',
            '{tanggal}'        => $targetDate->locale('id')->translatedFormat('d M Y'),
            '{daftar_jadwal}'  => $scheduleLines,
            '{studio_wa}'      => FonnteService::STUDIO_WA_DISPLAY,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->body,
        );
    }

    private function formatSessionLine(ClassSession $session): string
    {
        $start = substr((string) $session->start_time, 0, 5);
        $end = substr((string) $session->end_time, 0, 5);
        $instrument = $session->enrollment?->package?->instrument?->name ?? 'Les Musik';
        $teacher = $session->teacher?->name ?? '-';
        $room = $session->room?->name ?? '-';

        return "• {$start}–{$end} — {$instrument} ({$teacher}) — {$room}";
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     * @param  array<int, string>  $providerMessageIds
     */
    private function persistLog(
        Student $student,
        Carbon $targetDate,
        Collection $sessions,
        string $phone,
        string $message,
        array $providerMessageIds,
        string $status,
        ?string $error,
    ): ScheduleReminderLog {
        return DB::transaction(function () use (
            $student, $targetDate, $sessions, $phone, $message, $providerMessageIds, $status, $error,
        ) {
            return ScheduleReminderLog::create([
                'student_id'             => $student->id,
                'target_date'            => $targetDate->toDateString(),
                'reminder_mode'          => $this->mode(),
                'class_session_ids'      => $sessions->pluck('id')->values()->all(),
                'provider'               => 'fonnte',
                'phone'                  => $phone,
                'message_body'           => $message,
                'provider_message_ids'   => $providerMessageIds,
                'status'                 => $status,
                'error_message'          => $error,
                'sent_by'                => null,
                'sent_at'                => now(),
            ]);
        });
    }

    /** Prioritas: parent_phone → phone murid. */
    private function resolveReminderPhone(Student $student): ?string
    {
        if ($this->fonnte->isValidPhone($student->parent_phone)) {
            return $student->parent_phone;
        }

        if ($this->fonnte->isValidPhone($student->phone)) {
            return $student->phone;
        }

        return null;
    }

    private function humanizeError(string $raw): string
    {
        $key = strtolower(trim($raw));

        return match (true) {
            str_contains($key, 'token') && str_contains($key, 'invalid') => 'Token Fonnte tidak valid. Periksa FONNTE_TOKEN di .env.',
            str_contains($key, 'insufficient quota') => 'Kuota pesan Fonnte habis.',
            str_contains($key, 'target invalid') => 'Nomor WhatsApp tujuan tidak valid.',
            str_contains($key, 'device') => 'Device Fonnte belum terhubung. Scan QR di dashboard Fonnte.',
            default => $raw,
        };
    }
}
