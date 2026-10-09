<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminderLog;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsappMessageTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi reminder tagihan WA: query unpaid, compose pesan, kirim via Fonnte.
 */
class InvoiceReminderService
{
    public function __construct(
        private readonly FonnteService $fonnte,
        private readonly InvoicePdfService $pdf,
    ) {}

    /**
     * Daftar murid dengan invoice UNPAID/PARTIAL, dikelompokkan per murid.
     *
     * @return Collection<int, object{
     *   student: Student,
     *   invoices: Collection<int, Invoice>,
     *   total_balance: int,
     *   invoice_count: int,
     *   oldest_due_date: ?Carbon,
     *   last_reminder_at: ?Carbon,
     *   phone_valid: bool,
     *   phone_normalized: ?string,
     * }>
     */
    public function getUnpaidGroupedByStudent(array $filters = []): Collection
    {
        $invoiceQuery = Invoice::query()
            ->unpaid()
            ->with(['enrollment.package.instrument', 'student'])
            ->orderBy('due_date');

        if (! empty($filters['overdue_only'])) {
            $invoiceQuery->whereDate('due_date', '<', now()->startOfDay());
        }

        $invoices = $invoiceQuery->get();

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $invoices = $invoices->filter(function (Invoice $inv) use ($term) {
                $s = $inv->student;

                return str_contains(strtolower($s->full_name), strtolower($term))
                    || str_contains(strtolower($s->student_code), strtolower($term))
                    || str_contains(strtolower($s->parent_name ?? ''), strtolower($term));
            });
        }

        $grouped = $invoices->groupBy('student_id');

        $neverThisMonth = ! empty($filters['never_reminded_this_month']);

        $rows = $grouped->map(function (Collection $studentInvoices, int $studentId) {
            $student = $studentInvoices->first()->student;
            $phone = $this->fonnte->normalizePhone($student->parent_phone)
                ?? $this->fonnte->normalizePhone($student->phone);

            $lastLog = InvoiceReminderLog::query()
                ->where('student_id', $studentId)
                ->latest('sent_at')
                ->first();

            return (object) [
                'student'          => $student,
                'invoices'         => $studentInvoices->values(),
                'total_balance'    => (int) $studentInvoices->sum(fn (Invoice $i) => $i->balance),
                'invoice_count'    => $studentInvoices->count(),
                'oldest_due_date'  => $studentInvoices->min('due_date'),
                'last_reminder_at' => $lastLog?->sent_at,
                'phone_valid'      => $phone !== null,
                'phone_normalized' => $phone,
            ];
        })->values();

        if ($neverThisMonth) {
            $start = now()->startOfMonth();
            $rows = $rows->filter(function ($row) use ($start) {
                if (! $row->last_reminder_at) {
                    return true;
                }

                return $row->last_reminder_at->lt($start);
            })->values();
        }

        return $rows->sortBy(fn ($row) => $row->student->full_name)->values();
    }

    /**
     * Ganti placeholder template dengan data murid & invoice.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    public function composeMessage(Student $student, Collection $invoices, WhatsappMessageTemplate $template): string
    {
        $total = $invoices->sum(fn (Invoice $i) => $i->balance);
        
        // ponytail: fallback support for legacy {daftar_invoice} template variable
        $lines = $invoices->map(function (Invoice $inv) {
            $kelas = $this->pdf->captionFor($inv);
            $sisa = 'Rp ' . number_format($inv->balance, 0, ',', '.');
            $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'invoices.public-pdf',
                now()->addDays(30), // kadaluarsa 30 hari sejak reminder dikirim
                ['invoice' => $inv->id],
            );

            return "• {$inv->invoice_number} ({$kelas}) — sisa {$sisa}\n  Link PDF: {$signedUrl}";
        })->implode("\n");

        $count = $invoices->count();

        // 1. {invoice_number}
        $invoiceNumberVal = $count === 1 
            ? $invoices->first()->invoice_number 
            : $invoices->map(fn (Invoice $inv) => "- {$inv->invoice_number}")->implode("\n");

        // 2. {pdf_link}
        $getSignedUrl = function (Invoice $inv) {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'invoices.public-pdf',
                now()->addDays(30),
                ['invoice' => $inv->id],
            );
        };
        $pdfLinkVal = $count === 1
            ? $getSignedUrl($invoices->first())
            : $invoices->map(fn (Invoice $inv) => "- {$inv->invoice_number}: " . $getSignedUrl($inv))->implode("\n");

        // 3. {paket_murid}
        $getClassLabel = function (Invoice $inv) {
            $pkg = $inv->enrollment?->package;
            if (! $pkg) {
                return $inv->description ?? 'Tagihan';
            }
            $instrument = $pkg->instrument?->name ?? '';
            $type = \Illuminate\Support\Str::title(str_replace('_', ' ', strtolower($pkg->class_type ?? '')));
            $grade = ($pkg->class_type === 'REGULER' && $pkg->grade) ? " {$pkg->grade}" : '';
            return trim("{$instrument} {$type}{$grade}");
        };
        $paketMuridVal = $count === 1
            ? $getClassLabel($invoices->first())
            : $invoices->map(fn (Invoice $inv) => "- " . $getClassLabel($inv))->implode("\n");

        // 4. {Bulan_tahun}
        $indoMonths = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $getBulanTahun = function (Invoice $inv) use ($indoMonths) {
            $m = $inv->month;
            $y = $inv->year;
            $mStr = $indoMonths[$m] ?? Carbon::parse($inv->due_date)->format('F');
            return "{$mStr} {$y}";
        };
        $bulanTahunVal = $count === 1
            ? $getBulanTahun($invoices->first())
            : $invoices->map(fn (Invoice $inv) => "- " . $getBulanTahun($inv))->implode("\n");

        $tempo = $invoices->min('due_date');
        $tempoStr = $tempo
            ? Carbon::parse($tempo)->format('d M Y')
            : '-';

        $replacements = [
            '{nama_murid}'      => $student->full_name,
            '{kode_murid}'      => $student->student_code,
            '{nama_ortu}'       => $student->parent_name ?? 'Bapak/Ibu',
            '{total_tagihan}'   => 'Rp ' . number_format($total, 0, ',', '.'),
            '{jumlah_invoice}'  => (string) $count,
            '{daftar_invoice}'  => $lines,
            '{invoice_number}'  => $invoiceNumberVal,
            '{ivoice_number}'   => $invoiceNumberVal, // ponytail: catch typo from request
            '{pdf_link}'        => $pdfLinkVal,
            '{paket_murid}'     => $paketMuridVal,
            '{Bulan_tahun}'     => $bulanTahunVal,
            '{tempo_terdekat}'  => $tempoStr,
            '{studio_wa}'       => FonnteService::STUDIO_WA_DISPLAY,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->body,
        );
    }

    /**
     * Siapkan tujuan dan pesan yang sama untuk preview maupun pengiriman.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array{phone: string, message: string}
     */
    public function prepareReminder(Student $student, Collection $invoices, ?WhatsappMessageTemplate $template = null): array
    {
        $phone = $this->fonnte->normalizePhone($student->parent_phone);
        $isStudentFallback = false;

        if (! $phone) {
            $phone = $this->fonnte->normalizePhone($student->phone);
            $isStudentFallback = true;
        }

        if (! $phone) {
            throw new \InvalidArgumentException("Murid {$student->full_name} tidak punya nomor HP ortu atau murid yang valid.");
        }

        if (! $template) {
            $template = $isStudentFallback
                ? WhatsappMessageTemplate::query()->where('code', WhatsappMessageTemplate::CODE_INVOICE_REMINDER_STUDENT)->where('is_active', true)->first()
                : WhatsappMessageTemplate::defaultInvoiceReminder();
        }

        if (! $template) {
            throw new \InvalidArgumentException('Template reminder aktif tidak ditemukan.');
        }

        return ['phone' => $phone, 'message' => $this->composeMessage($student, $invoices, $template)];
    }

    /**
     * Kirim reminder ke satu murid: 1 teks berisi link PDF.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    public function sendReminder(
        Student $student,
        Collection $invoices,
        User $sender,
        ?WhatsappMessageTemplate $template = null,
    ): InvoiceReminderLog {
        ['phone' => $phone, 'message' => $message] = $this->prepareReminder($student, $invoices, $template);
        $fonnteIds = [];

        // ponytail: kirim text tunggal berisi link pdf tanpa mengirim file lampiran berat via WA
        $result = $this->fonnte->sendText($phone, $message);
        if (! empty($result['message_ids'])) {
            $fonnteIds = $result['message_ids'];
        }

        if ($result['ok']) {
            return $this->persistLog(
                student: $student,
                sender: $sender,
                phone: $phone,
                message: $message,
                invoiceIds: $invoices->pluck('id')->all(),
                pdfNames: [], // Kosong karena dikirim via URL
                wablasIds: $fonnteIds, // Simpan Fonnte ID ke kolom ini
                documentsSent: 0,
                documentsTotal: 0,
                status: InvoiceReminderLog::STATUS_SUCCESS,
                error: null,
            );
        }

        $err = ($result['auth_invalid'] ?? false)
            ? 'Token Fonnte tidak valid.'
            : ($result['error'] ?? 'Gagal kirim pengingat tagihan.');

        return $this->persistLog(
            student: $student,
            sender: $sender,
            phone: $phone,
            message: $message,
            invoiceIds: $invoices->pluck('id')->all(),
            pdfNames: [],
            wablasIds: $fonnteIds,
            documentsSent: 0,
            documentsTotal: 0,
            status: InvoiceReminderLog::STATUS_FAILED,
            error: $err,
        );
    }

    /**
     * Kirim batch ke banyak murid. Return ringkasan + flag abort auth.
     *
     * @param  array<int>  $studentIds
     * @return array{
     *   success: int,
     *   partial: int,
     *   failed: int,
     *   skipped: int,
     *   errors: array<string, string>,
     *   aborted: bool,
     *   abort_reason: ?string,
     *   log_ids: array<int>,
     * }
     */
    public function sendBatch(array $studentIds, User $sender): array
    {
        set_time_limit(120);

        if (! $this->fonnte->isConfigured()) {
            throw new \InvalidArgumentException('Kredensial Fonnte belum dikonfigurasi di .env');
        }

        // ponytail: template selalu null — auto-fallback di sendReminder() yang menentukan
        // INVOICE_REMINDER (no ortu) atau INVOICE_REMINDER_STUDENT (no murid)

        $summary = [
            'success'       => 0,
            'partial'       => 0,
            'failed'        => 0,
            'skipped'       => 0,
            'errors'        => [],
            'aborted'       => false,
            'abort_reason'  => null,
            'log_ids'       => [],
        ];

        $studentIds = array_slice(array_unique($studentIds), 0, 30);

        foreach ($studentIds as $studentId) {
            $student = Student::find($studentId);
            if (! $student) {
                $summary['skipped']++;
                continue;
            }

            $hasValidPhone = $this->fonnte->isValidPhone($student->parent_phone)
                || $this->fonnte->isValidPhone($student->phone);

            if (! $hasValidPhone) {
                $summary['skipped']++;
                continue;
            }

            $invoices = Invoice::query()
                ->unpaid()
                ->where('student_id', $studentId)
                ->with('enrollment.package.instrument')
                ->get();

            if ($invoices->isEmpty()) {
                $summary['skipped']++;
                continue;
            }

            try {
                $log = $this->sendReminder($student, $invoices, $sender, null); // auto-fallback template
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['errors'][$student->student_code] = $e->getMessage();
                continue;
            }

            $summary['log_ids'][] = $log->id;

            match ($log->status) {
                InvoiceReminderLog::STATUS_SUCCESS => $summary['success']++,
                InvoiceReminderLog::STATUS_PARTIAL => $summary['partial']++,
                default => $summary['failed']++,
            };

            if ($log->error_message) {
                $summary['errors'][$student->student_code] = $this->humanizeFonnteError($log->error_message);
            }

            if ($log->status === InvoiceReminderLog::STATUS_FAILED
                && str_contains(strtolower($log->error_message ?? ''), 'token fonnte')) {
                $summary['aborted'] = true;
                $summary['abort_reason'] = $log->error_message;
                break;
            }
        }

        return $summary;
    }

    /**
     * Kirim reminder terjadwal H-N sebelum tempo (teks saja, tanpa PDF).
     * Dipanggil cron invoice-reminders:send-scheduled pada jam yang dikonfigurasi.
     *
     * @return array{
     *   skipped_time: bool,
     *   skipped_disabled: bool,
     *   eligible: int,
     *   sent: int,
     *   failed: int,
     *   skipped: int,
     *   skipped_already_reminded: int,
     *   dry_run: bool,
     *   errors: array<string, string>,
     *   log_ids: array<int>,
     * }
     */
    public function sendScheduledReminders(Carbon $now, bool $dryRun = false): array
    {
        $summary = [
            'skipped_time'             => false,
            'skipped_disabled'         => false,
            'eligible'                 => 0,
            'sent'                     => 0,
            'failed'                   => 0,
            'skipped'                  => 0,
            'skipped_already_reminded' => 0,
            'dry_run'                  => $dryRun,
            'errors'                   => [],
            'log_ids'                  => [],
        ];

        if (! SchedulerSettings::invoiceReminderEnabled()) {
            $summary['skipped_disabled'] = true;

            return $summary;
        }

        if ($now->format('H:i') !== SchedulerSettings::invoiceReminderTime()) {
            $summary['skipped_time'] = true;

            return $summary;
        }

        if (! $this->fonnte->isConfigured()) {
            throw new \InvalidArgumentException('Kredensial Fonnte belum dikonfigurasi.');
        }

        $sender = User::role('Owner')->orderBy('id')->first();
        if (! $sender) {
            throw new \InvalidArgumentException('Tidak ada user Owner untuk mencatat pengiriman reminder.');
        }

        $templateId = SchedulerSettings::invoiceReminderTemplateId();
        $template = null;
        if ($templateId) {
            $template = WhatsappMessageTemplate::query()->where('id', $templateId)->where('is_active', true)->first();
            if (! $template) {
                throw new \InvalidArgumentException('Template reminder aktif tidak ditemukan.');
            }
        }

        $daysBefore = SchedulerSettings::invoiceReminderDaysBeforeDue();
        $targetDueDate = $now->copy()->startOfDay()->addDays($daysBefore)->toDateString();
        $monthStart = $now->copy()->startOfMonth();

        $invoices = Invoice::query()
            ->unpaid()
            ->whereDate('due_date', $targetDueDate)
            ->with(['student', 'enrollment.package.instrument'])
            ->get();

        foreach ($invoices->groupBy('student_id') as $studentId => $studentInvoices) {
            $student = $studentInvoices->first()->student;

            $alreadyReminded = InvoiceReminderLog::query()
                ->where('student_id', $studentId)
                ->where('sent_at', '>=', $monthStart)
                ->exists();

            if ($alreadyReminded) {
                $summary['skipped_already_reminded']++;
                continue;
            }

            $hasValidPhone = $this->fonnte->isValidPhone($student->parent_phone)
                || $this->fonnte->isValidPhone($student->phone);

            if (! $hasValidPhone) {
                $summary['skipped']++;
                continue;
            }

            $summary['eligible']++;

            if ($dryRun) {
                $summary['sent']++;
                continue;
            }

            try {
                $log = $this->sendTextOnlyReminder(
                    $student,
                    $studentInvoices->values(),
                    $sender,
                    $template,
                );
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['errors'][$student->student_code] = $e->getMessage();
                continue;
            }

            $summary['log_ids'][] = $log->id;

            if ($log->status === InvoiceReminderLog::STATUS_SUCCESS) {
                $summary['sent']++;
            } else {
                $summary['failed']++;
                if ($log->error_message) {
                    $summary['errors'][$student->student_code] = $this->humanizeFonnteError($log->error_message);
                }
            }

            if ($log->status === InvoiceReminderLog::STATUS_FAILED
                && str_contains(strtolower($log->error_message ?? ''), 'token fonnte')) {
                break;
            }
        }

        return $summary;
    }

    /**
     * Kirim reminder teks saja (tanpa lampiran PDF) — untuk scheduler otomatis.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    public function sendTextOnlyReminder(
        Student $student,
        Collection $invoices,
        User $sender,
        ?WhatsappMessageTemplate $template = null,
    ): InvoiceReminderLog {
        ['phone' => $phone, 'message' => $message] = $this->prepareReminder($student, $invoices, $template);
        $result = $this->fonnte->sendText($phone, $message);
        $fonnteIds = [];

        if (! empty($result['message_ids'])) {
            $fonnteIds = $result['message_ids'];
        }

        if (! $result['ok']) {
            $err = ($result['auth_invalid'] ?? false)
                ? 'Token Fonnte tidak valid.'
                : ($result['error'] ?? 'Gagal kirim pesan teks.');

            return $this->persistLog(
                student: $student,
                sender: $sender,
                phone: $phone,
                message: $message,
                invoiceIds: $invoices->pluck('id')->all(),
                pdfNames: [],
                wablasIds: $fonnteIds,
                documentsSent: 0,
                documentsTotal: 0,
                status: InvoiceReminderLog::STATUS_FAILED,
                error: $err,
            );
        }

        return $this->persistLog(
            student: $student,
            sender: $sender,
            phone: $phone,
            message: $message,
            invoiceIds: $invoices->pluck('id')->all(),
            pdfNames: [],
            wablasIds: $fonnteIds,
            documentsSent: 0,
            documentsTotal: 0,
            status: InvoiceReminderLog::STATUS_SUCCESS,
            error: null,
        );
    }

    /** Terjemahkan pesan error Fonnte ke Bahasa Indonesia yang mudah dipahami Admin. */
    private function humanizeFonnteError(string $raw): string
    {
        $key = strtolower(trim($raw));

        return match (true) {
            str_contains($key, 'device blocked') => 'Perangkat WhatsApp di Fonnte terblokir. Buka dashboard Fonnte → cek status device → scan ulang QR / hubungi support Fonnte.',
            str_contains($key, 'token') && str_contains($key, 'invalid') => 'Token Fonnte tidak valid. Periksa FONNTE_TOKEN di .env.',
            str_contains($key, 'quota') => 'Kuota pesan Fonnte habis. Top up atau tunggu reset kuota.',
            str_contains($key, 'phone') && str_contains($key, 'invalid') => 'Nomor WhatsApp tujuan tidak valid.',
            default => $raw,
        };
    }

    private function persistLog(
        Student $student,
        User $sender,
        string $phone,
        string $message,
        array $invoiceIds,
        array $pdfNames,
        array $wablasIds,
        int $documentsSent,
        int $documentsTotal,
        string $status,
        ?string $error,
    ): InvoiceReminderLog {
        return DB::transaction(function () use (
            $student, $sender, $phone, $message, $invoiceIds, $pdfNames,
            $wablasIds, $documentsSent, $documentsTotal, $status, $error,
        ) {
            return InvoiceReminderLog::create([
                'student_id'          => $student->id,
                'sent_by'             => $sender->id,
                'phone'               => $phone,
                'message_body'        => $message,
                'invoice_ids'         => $invoiceIds,
                'pdf_filenames'       => $pdfNames,
                'wablas_message_ids'  => $wablasIds,
                'documents_sent'      => $documentsSent,
                'documents_total'     => $documentsTotal,
                'status'              => $status,
                'error_message'       => $error,
                'sent_at'             => now(),
            ]);
        });
    }
}
