<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendInvoiceReminderRequest;
use App\Models\AuditLog;
use App\Models\WhatsappMessageTemplate;
use App\Services\InvoiceReminderService;
use App\Services\WablasService;
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
        private readonly WablasService $wablas,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search'                  => $request->get('search'),
            'overdue_only'            => $request->boolean('overdue_only'),
            'never_reminded_this_month' => $request->boolean('never_reminded_this_month'),
        ];

        $rows = $this->reminderService->getUnpaidGroupedByStudent($filters);
        $templates = WhatsappMessageTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $wablasReady = $this->wablas->isConfigured();

        return view('invoice-reminders.index', compact('rows', 'templates', 'filters', 'wablasReady'));
    }

    public function send(SendInvoiceReminderRequest $request): RedirectResponse
    {
        try {
            $summary = $this->reminderService->sendBatch(
                $request->validated('student_ids'),
                $request->user(),
                $request->validated('template_id'),
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
            return back()->with('error', $message . ' — ' . ($summary['abort_reason'] ?? 'Token Wablas tidak valid.'));
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
