<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\SessionReportWaLog;
use App\Models\SessionTeacherNote;
use App\Models\Student;
use App\Models\WhatsappMessageTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SessionReportWaService
{
    public function __construct(
        private readonly FonnteService $fonnte,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('session_report_wa.enabled');
    }

    public function debounceMinutes(): int
    {
        return max(1, (int) config('session_report_wa.debounce_minutes', 10));
    }

    /** @return 'DISABLED'|'PENDING'|'SENT'|'FAILED'|'SKIPPED' */
    public function deliveryState(ClassSession $session): string
    {
        if (! $this->isEnabled()) {
            return 'DISABLED';
        }

        $note = $session->teacherNote;
        if (! $note || ! $this->noteHasSendableContent($note)) {
            return 'DISABLED';
        }

        $latest = $this->latestSuccessLog($session->id);
        if ($latest && $note->updated_at && $latest->sent_at->gte($note->updated_at)) {
            return 'SENT';
        }

        $latestAny = SessionReportWaLog::query()
            ->where('class_session_id', $session->id)
            ->latest('sent_at')
            ->first();

        if ($latestAny?->status === SessionReportWaLog::STATUS_FAILED) {
            return 'FAILED';
        }

        if ($latestAny?->status === SessionReportWaLog::STATUS_SKIPPED) {
            return 'SKIPPED';
        }

        return 'PENDING';
    }

    public function maskPhone(?string $phone): string
    {
        $normalized = $this->fonnte->normalizePhone($phone);
        if ($normalized === null || strlen($normalized) < 8) {
            return '—';
        }

        return substr($normalized, 0, 4) . '***' . substr($normalized, -4);
    }

    public function composeMessage(ClassSession $session, bool $isUpdate = false): string
    {
        $template = WhatsappMessageTemplate::defaultSessionReport();
        if (! $template) {
            throw new \RuntimeException('Template SESSION_REPORT aktif tidak ditemukan.');
        }

        $student = $session->student;
        $note = $session->teacherNote;
        $teacherName = $session->status === ClassSession::STATUS_DIGANTI
            ? ($session->substituteTeacher?->name ?? $session->teacher?->name ?? '-')
            : ($session->teacher?->name ?? '-');

        $sessionDate = Carbon::parse($session->session_date)->locale('id')->translatedFormat('d F Y');
        $instrument = $session->enrollment?->package?->instrument?->name ?? 'Les Musik';

        $catatan = trim((string) ($note?->notes ?? ''));
        $blokCatatan = $catatan !== ''
            ? "*Catatan guru:*\n{$catatan}"
            : '';

        $replacements = [
            '{nama_ortu}'      => $student?->parent_name ?? 'Bapak/Ibu',
            '{nama_murid}'     => $student?->full_name ?? '-',
            '{tanggal_sesi}'   => $sessionDate,
            '{instrumen}'      => $instrument,
            '{nama_guru}'      => $teacherName,
            '{materi}'         => filled(trim((string) ($note?->material_learned ?? '')))
                ? trim((string) $note->material_learned)
                : 'Belum dicatat',
            '{tugas}'          => filled(trim((string) ($note?->homework_notes ?? '')))
                ? trim((string) $note->homework_notes)
                : 'Tidak ada tugas khusus — cukup latihan ringan sesuai materi hari ini',
            '{blok_catatan}'   => $blokCatatan,
            '{pesan_semangat}' => $this->encouragementLine($student, $note?->session_rating),
            '{studio_wa}'      => FonnteService::STUDIO_WA_DISPLAY,
        ];

        $body = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->body,
        );

        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        if ($isUpdate) {
            $prefix = trim((string) config('session_report_wa.update_prefix', '[Update]'));
            if ($prefix !== '') {
                $body = "{$prefix}\n\n{$body}";
            }
        }

        return trim($body);
    }

    public function sendForSession(ClassSession $session, ?Carbon $noteUpdatedAt = null, bool $force = false): ?SessionReportWaLog
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $session->loadMissing([
            'student',
            'teacher',
            'substituteTeacher',
            'enrollment.package.instrument',
            'teacherNote',
        ]);

        $note = $session->teacherNote;
        if (! $note || ! $this->noteHasSendableContent($note)) {
            return null;
        }

        if ($noteUpdatedAt !== null && $note->updated_at->gt($noteUpdatedAt)) {
            return null;
        }

        $latestSuccess = $this->latestSuccessLog($session->id);
        if (! $force && $latestSuccess && $note->updated_at && $latestSuccess->sent_at->gte($note->updated_at)) {
            return null;
        }

        $student = $session->student;
        if (! $student) {
            return null;
        }

        $recipientPhone = $this->resolveRecipientPhone($student);
        if ($recipientPhone === null) {
            return $this->persistLog(
                session: $session,
                student: $student,
                phone: '',
                message: '',
                providerMessageIds: [],
                status: SessionReportWaLog::STATUS_SKIPPED,
                isUpdate: $latestSuccess !== null,
                error: 'Nomor WhatsApp tujuan tidak tersedia.',
            );
        }

        if (! $this->fonnte->isConfigured()) {
            return $this->persistLog(
                session: $session,
                student: $student,
                phone: (string) $this->fonnte->normalizePhone($recipientPhone),
                message: '',
                providerMessageIds: [],
                status: SessionReportWaLog::STATUS_FAILED,
                isUpdate: $latestSuccess !== null,
                error: 'Kredensial Fonnte belum dikonfigurasi.',
            );
        }

        $isUpdate = $latestSuccess !== null;
        $message = $this->composeMessage($session, $isUpdate);
        $result = $this->fonnte->sendText($recipientPhone, $message);

        return $this->persistLog(
            session: $session,
            student: $student,
            phone: (string) $this->fonnte->normalizePhone($recipientPhone),
            message: $message,
            providerMessageIds: $result['message_ids'],
            status: $result['ok'] ? SessionReportWaLog::STATUS_SUCCESS : SessionReportWaLog::STATUS_FAILED,
            isUpdate: $isUpdate,
            error: $result['error'],
        );
    }

    private function encouragementLine(?Student $student, ?int $rating): string
    {
        $name = $student?->full_name ?? 'Ananda';

        return match (true) {
            $rating === 5 => "Hari ini {$name} tampil sangat antusias dan fokus — perkembangannya terlihat jelas!",
            $rating === 4 => "{$name} menunjukkan kemajuan yang baik hari ini. Pertahankan semangatnya!",
            $rating === 3 => "{$name} sudah berusaha dengan baik. Sedikit latihan rutin di rumah akan membuat hasilnya makin terasa.",
            default       => "Setiap sesi adalah langkah berharga. Mari terus mendampingi {$name} dengan sabar dan konsisten.",
        };
    }

    private function noteHasSendableContent(SessionTeacherNote $note): bool
    {
        return filled(trim((string) ($note->material_learned ?? '')))
            || filled(trim((string) ($note->homework_notes ?? '')))
            || filled(trim((string) ($note->notes ?? '')))
            || filled($note->session_rating);
    }

    private function resolveRecipientPhone(Student $student): ?string
    {
        if ($this->fonnte->isValidPhone($student->parent_phone)) {
            return $student->parent_phone;
        }

        if ($this->fonnte->isValidPhone($student->phone)) {
            return $student->phone;
        }

        return null;
    }

    private function latestSuccessLog(int $classSessionId): ?SessionReportWaLog
    {
        return SessionReportWaLog::query()
            ->where('class_session_id', $classSessionId)
            ->where('status', SessionReportWaLog::STATUS_SUCCESS)
            ->latest('sent_at')
            ->first();
    }

    /** @param array<int, string> $providerMessageIds */
    private function persistLog(
        ClassSession $session,
        Student $student,
        string $phone,
        string $message,
        array $providerMessageIds,
        string $status,
        bool $isUpdate,
        ?string $error,
    ): SessionReportWaLog {
        return DB::transaction(function () use (
            $session, $student, $phone, $message, $providerMessageIds, $status, $isUpdate, $error,
        ) {
            return SessionReportWaLog::create([
                'class_session_id'       => $session->id,
                'student_id'             => $student->id,
                'phone'                  => $phone,
                'message_body'           => $message,
                'provider'               => 'fonnte',
                'provider_message_ids'   => $providerMessageIds,
                'status'                 => $status,
                'is_update'              => $isUpdate,
                'error_message'          => $error,
                'sent_at'                => now(),
            ]);
        });
    }
}
