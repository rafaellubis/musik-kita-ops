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
 * Orkestrasi reminder tagihan WA: query unpaid, compose pesan, kirim via Wablas.
 */
class InvoiceReminderService
{
    public function __construct(
        private readonly WablasService $wablas,
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
            $phone = $this->wablas->normalizePhone($student->parent_phone);

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
        $lines = $invoices->map(function (Invoice $inv) {
            $kelas = $this->pdf->captionFor($inv);
            $sisa = 'Rp ' . number_format($inv->balance, 0, ',', '.');

            return "• {$inv->invoice_number} ({$kelas}) — sisa {$sisa}";
        })->implode("\n");

        $tempo = $invoices->min('due_date');
        $tempoStr = $tempo
            ? Carbon::parse($tempo)->format('d M Y')
            : '-';

        $replacements = [
            '{nama_murid}'     => $student->full_name,
            '{kode_murid}'     => $student->student_code,
            '{nama_ortu}'      => $student->parent_name ?? 'Bapak/Ibu',
            '{total_tagihan}'  => 'Rp ' . number_format($total, 0, ',', '.'),
            '{jumlah_invoice}' => (string) $invoices->count(),
            '{daftar_invoice}' => $lines,
            '{tempo_terdekat}' => $tempoStr,
            '{studio_wa}'      => WablasService::STUDIO_WA_DISPLAY,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->body,
        );
    }

    /**
     * Kirim reminder ke satu murid: 1 teks + N PDF (jeda 500ms antar PDF).
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    public function sendReminder(
        Student $student,
        Collection $invoices,
        User $sender,
        ?WhatsappMessageTemplate $template = null,
    ): InvoiceReminderLog {
        $template ??= WhatsappMessageTemplate::defaultInvoiceReminder();

        if (! $template) {
            throw new \InvalidArgumentException('Template reminder aktif tidak ditemukan.');
        }

        $phone = $this->wablas->normalizePhone($student->parent_phone);
        if (! $phone) {
            throw new \InvalidArgumentException("Murid {$student->full_name} tidak punya nomor HP ortu yang valid.");
        }

        $message = $this->composeMessage($student, $invoices, $template);
        $wablasIds = [];
        $pdfNames = [];
        $errors = [];

        $textResult = $this->wablas->sendText($phone, $message);
        if ($textResult['message_id']) {
            $wablasIds[] = $textResult['message_id'];
        }

        $documentsTotal = $invoices->count();
        $documentsSent = 0;

        if (! $textResult['ok']) {
            $err = ($textResult['auth_invalid'] ?? false)
                ? 'Token Wablas tidak valid.'
                : ($textResult['error'] ?? 'Gagal kirim pesan teks.');

            return $this->persistLog(
                student: $student,
                sender: $sender,
                phone: $phone,
                message: $message,
                invoiceIds: $invoices->pluck('id')->all(),
                pdfNames: [],
                wablasIds: $wablasIds,
                documentsSent: 0,
                documentsTotal: $documentsTotal,
                status: InvoiceReminderLog::STATUS_FAILED,
                error: $err,
            );
        }

        foreach ($invoices->values() as $index => $invoice) {
            if ($index > 0) {
                usleep(500_000);
            }

            try {
                $bytes = $this->pdf->renderPdf($invoice);
            } catch (\Throwable $e) {
                $errors[] = "{$invoice->invoice_number}: gagal render PDF — {$e->getMessage()}";
                continue;
            }

            $filename = $this->pdf->filenameFor($invoice);
            $caption = $this->pdf->captionFor($invoice);

            $docResult = $this->wablas->sendDocumentFromLocal($phone, $bytes, $filename, $caption);

            if (! empty($docResult['skipped_size'])) {
                $errors[] = "{$invoice->invoice_number}: PDF > 2 MB, dilewati.";
                continue;
            }

            if ($docResult['ok']) {
                $documentsSent++;
                $pdfNames[] = $filename;
                if ($docResult['message_id']) {
                    $wablasIds[] = $docResult['message_id'];
                }
            } else {
                $errors[] = "{$invoice->invoice_number}: " . ($docResult['error'] ?? 'gagal kirim PDF');
            }
        }

        $status = InvoiceReminderLog::STATUS_SUCCESS;
        if ($documentsSent < $documentsTotal) {
            $status = InvoiceReminderLog::STATUS_PARTIAL;
        }

        return $this->persistLog(
            student: $student,
            sender: $sender,
            phone: $phone,
            message: $message,
            invoiceIds: $invoices->pluck('id')->all(),
            pdfNames: $pdfNames,
            wablasIds: $wablasIds,
            documentsSent: $documentsSent,
            documentsTotal: $documentsTotal,
            status: $status,
            error: $errors ? implode('; ', $errors) : null,
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
    public function sendBatch(array $studentIds, User $sender, ?int $templateId = null): array
    {
        set_time_limit(120);

        if (! $this->wablas->isConfigured()) {
            throw new \InvalidArgumentException('Kredensial Wablas belum dikonfigurasi di .env');
        }

        $template = $templateId
            ? WhatsappMessageTemplate::query()->where('id', $templateId)->where('is_active', true)->first()
            : WhatsappMessageTemplate::defaultInvoiceReminder();

        if (! $template) {
            throw new \InvalidArgumentException('Template pesan tidak ditemukan atau nonaktif.');
        }

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
            if (! $student || ! $this->wablas->isValidPhone($student->parent_phone)) {
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

            // Cek auth dengan dry-run teks kosong tidak ideal — kirim dan tangkap auth_invalid
            try {
                $log = $this->sendReminder($student, $invoices, $sender, $template);
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
                $summary['errors'][$student->student_code] = $this->humanizeWablasError($log->error_message);
            }

            if ($log->status === InvoiceReminderLog::STATUS_FAILED
                && str_contains(strtolower($log->error_message ?? ''), 'token wablas')) {
                $summary['aborted'] = true;
                $summary['abort_reason'] = $log->error_message;
                break;
            }
        }

        return $summary;
    }

    /** Terjemahkan pesan error Wablas ke Bahasa Indonesia yang mudah dipahami Admin. */
    private function humanizeWablasError(string $raw): string
    {
        $key = strtolower(trim($raw));

        return match (true) {
            str_contains($key, 'device blocked') => 'Perangkat WhatsApp di Wablas terblokir. Buka dashboard Wablas → cek status device → scan ulang QR / hubungi support Wablas.',
            str_contains($key, 'token') && str_contains($key, 'invalid') => 'Token Wablas tidak valid. Periksa WABLAS_TOKEN dan WABLAS_SECRET_KEY di .env.',
            str_contains($key, 'quota') => 'Kuota pesan Wablas habis. Top up atau tunggu reset kuota.',
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
