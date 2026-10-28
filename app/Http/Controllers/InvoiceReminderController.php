<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendInvoiceReminderRequest;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\InvoiceReminderService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman operasional reminder tagihan via WhatsApp (Owner + Admin).
 */
class InvoiceReminderController extends Controller
{
    public function __construct(
        private readonly InvoiceReminderService $reminderService,
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search'                  => $request->get('search'),
            'overdue_only'            => $request->boolean('overdue_only'),
            'never_reminded_this_month' => $request->boolean('never_reminded_this_month'),
        ];

        $rows = $this->reminderService->getUnpaidGroupedByStudent($filters);

        // Preview memakai seluruh tagihan belum lunas, sama seperti sendBatch().
        if ($rows->isNotEmpty()) {
            $invoicesByStudent = Invoice::query()->unpaid()
                ->whereIn('student_id', $rows->pluck('student.id'))
                ->with('enrollment.package.instrument')
                ->get()->groupBy('student_id');

            foreach ($rows as $row) {
                $row->preview_message = null;
                $row->preview_error = null;
                try {
                    $prepared = $this->reminderService->prepareReminder(
                        $row->student, $invoicesByStudent->get($row->student->id, collect()),
                    );
                    $row->phone_normalized = $prepared['phone'];
                    $row->preview_message = $prepared['message'];
                } catch (\InvalidArgumentException $e) {
                    $row->preview_error = $e->getMessage();
                }
            }
        }

        // ponytail: template tidak lagi dipilih manual — auto-fallback di service
        $whatsappReady = $this->whatsapp->isConfigured();

        return view('invoice-reminders.index', compact('rows', 'filters', 'whatsappReady'));
    }

    public function send(SendInvoiceReminderRequest $request): RedirectResponse
    {
        try {
            $summary = $this->reminderService->sendBatch(
                $request->validated('student_ids'),
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(
            action: AuditLog::ACTION_CREATE,
            entity: null,
            entityLabel: 'Batch Reminder WA Tagihan',
            newValues: [
                'student_count' => count($request->validated('student_ids')),
                'success'       => $summary['success'],
                'partial'       => $summary['partial'],
                'failed'        => $summary['failed'],
                'skipped'       => $summary['skipped'],
                'log_ids'       => $summary['log_ids'],
            ],
            notes: $summary['aborted']
                ? 'Dihentikan: ' . ($summary['abort_reason'] ?? 'auth error')
                : null,
        );

        $parts = [];
        if ($summary['success'] > 0) {
            $parts[] = "{$summary['success']} berhasil";
        }
        if ($summary['partial'] > 0) {
            $parts[] = "{$summary['partial']} sebagian (PDF)";
        }
        if ($summary['failed'] > 0) {
            $parts[] = "{$summary['failed']} gagal";
        }
        if ($summary['skipped'] > 0) {
            $parts[] = "{$summary['skipped']} dilewati";
        }

        $message = 'Pengiriman selesai: ' . (implode(', ', $parts) ?: 'tidak ada yang terkirim');

        if ($summary['aborted']) {
            return back()->with('error', $message . ' — ' . ($summary['abort_reason'] ?? 'Autentikasi provider WhatsApp ditolak.'));
        }

        if ($summary['failed'] > 0 && $summary['success'] === 0 && $summary['partial'] === 0) {
            $detail = ! empty($summary['errors'])
                ? ' — ' . implode(' | ', $summary['errors'])
                : '';

            return back()
                ->with('error', $message . $detail)
                ->with('reminder_errors', $summary['errors']);
        }

        if (! empty($summary['errors'])) {
            return back()
                ->with('success', $message)
                ->with('reminder_errors', $summary['errors']);
        }

        return back()->with('success', $message);
    }
}
