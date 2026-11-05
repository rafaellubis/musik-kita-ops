<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminderLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceReminderPanelService
{
    public function __construct(
        private readonly InvoiceReminderService $reminders,
        private readonly WhatsAppService $whatsapp,
        private readonly InvoicePdfService $pdf,
    ) {}

    public function preview(Invoice $source, User $user, ?array $invoiceIds = null, ?int $resendLogId = null): array
    {
        $source->refresh()->load('student');
        $excluded = [];
        if ($resendLogId !== null) {
            $log = InvoiceReminderLog::findOrFail($resendLogId);
            abort_unless(in_array($source->id, $log->invoice_ids, true), 422, 'Riwayat tidak terkait invoice ini.');
            $invoiceIds = [$source->id];
            foreach ($log->invoice_ids as $id) {
                if ($id === $source->id) continue;
                $candidate = Invoice::find($id);
                if ($candidate && $candidate->student_id === $source->student_id && $this->eligible($candidate)) {
                    $invoiceIds[] = $id;
                } else {
                    $excluded[] = ['id' => $id, 'reason' => 'Invoice sudah lunas, dibatalkan, atau tidak tersedia.'];
                }
            }
        }
        $explicit = $invoiceIds !== null && $resendLogId === null;
        $ids = array_map('intval', $invoiceIds ?? [$source->id]);
        $selected = $this->invoices($ids);
        if (!in_array($source->id, $ids, true) || count(array_unique($ids)) !== count($ids)
            || $selected->count() !== count($ids)
            || $selected->contains(fn ($i) => $i->student_id !== $source->student_id)
            || ($explicit && $selected->contains(fn ($i) => !$this->eligible($i)))) {
            throw ValidationException::withMessages(['invoice_ids' => 'Pilih invoice belum lunas milik murid yang sama dan sertakan invoice sumber.']);
        }

        $blockers = [];
        if ($selected->contains(fn ($i) => !$this->eligible($i))) $blockers[] = 'Invoice sudah lunas, dibatalkan, atau tidak memiliki sisa tagihan.';
        if (!$this->whatsapp->isConfigured()) $blockers[] = 'Konfigurasi provider WhatsApp belum lengkap.';
        $student = $source->student;
        $parentPhone = $this->whatsapp->normalizePhone($student?->parent_phone);
        $phone = $parentPhone ?? $this->whatsapp->normalizePhone($student?->phone);
        $recipient = $phone ? ['name' => $parentPhone ? ($student->parent_name ?: 'Orang tua') : $student->full_name,
            'phone' => $phone, 'kind' => $parentPhone ? 'parent' : 'student'] : null;
        $message = '';
        if ($student) {
            try {
                $message = $this->reminders->prepareReminder($student, $selected)['message'];
                if (mb_strlen($message) > 5000) $blockers[] = 'Pesan melebihi 5.000 karakter. Periksa template atau kurangi invoice terpilih.';
                if (trim($message) === '') $blockers[] = 'Template menghasilkan pesan kosong.';
            } catch (\InvalidArgumentException $e) {
                $blockers[] = $e->getMessage();
            }
        } else {
            $blockers[] = 'Data murid tidak tersedia.';
        }

        $token = null;
        $expires = null;
        if (!$blockers) {
            $token = (string) Str::uuid();
            $expires = now()->addMinutes(30);
            Cache::put($this->key($token), ['user_id' => $user->id, 'source_invoice_id' => $source->id,
                'invoice_ids' => $selected->modelKeys(), 'recipient' => $recipient,
                'snapshot' => $this->snapshot($selected), 'expires_at' => $expires->timestamp, 'state' => 'ready'], $expires);
        }
        $other = Invoice::unpaid()->where('student_id', $source->student_id)->whereNotIn('id', $ids)
            ->whereColumn('total_amount', '>', 'paid_amount')->with('enrollment.package.instrument')->orderBy('due_date')->orderBy('id')->get();

        return ['source_invoice_id' => $source->id,
            'student' => ['id' => $student?->id, 'full_name' => $student?->full_name ?? 'Murid tidak tersedia',
                'edit_url' => $student ? route('students.edit', $student) : null],
            'recipient' => $recipient, 'selected_invoices' => $selected->map(fn ($i) => $this->summary($i))->all(),
            'other_invoices' => $other->map(fn ($i) => $this->summary($i))->all(), 'excluded_invoices' => $excluded,
            'total_balance' => $selected->sum('balance'), 'message_body' => $message, 'can_send' => !$blockers,
            'blockers' => $blockers, 'preview_token' => $token, 'expires_at' => $expires?->toIso8601String(),
            'repeat_warnings' => $this->repeatWarnings($ids)];
    }

    private function key(string $token): string
    {
        return 'invoice-reminder-panel:'.$token;
    }

    private function eligible(Invoice $invoice): bool
    {
        return in_array($invoice->status, ['UNPAID', 'PARTIAL'], true) && $invoice->balance > 0;
    }

    private function invoices(array $ids): Collection
    {
        return Invoice::whereIn('id', $ids)->with(['student', 'enrollment.package.instrument', 'items', 'validPayments'])
            ->orderBy('due_date')->orderBy('id')->get();
    }

    private function summary(Invoice $invoice): array
    {
        return ['id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'period' => Carbon::create($invoice->year, $invoice->month, 1)->locale('id')->translatedFormat('F Y'),
            'caption' => $this->pdf->captionFor($invoice), 'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status, 'balance' => $invoice->balance];
    }

    private function snapshot(Collection $invoices): string
    {
        // Hash isi relasi juga: perubahan rincian/PDF tidak selalu mengubah updated_at invoice.
        $data = $invoices->map(fn ($i) => [
            'invoice' => $i->getAttributes(), 'student' => $i->student?->getAttributes(),
            'enrollment' => $i->enrollment?->getAttributes(), 'package' => $i->enrollment?->package?->getAttributes(),
            'instrument' => $i->enrollment?->package?->instrument?->getAttributes(),
            'items' => $i->items->sortBy('id')->map(fn ($item) => $item->getAttributes())->values()->all(),
            'payments' => $i->validPayments->sortBy('id')->map(fn ($payment) => $payment->getAttributes())->values()->all(),
        ])->all();
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function repeatWarnings(array $ids): array
    {
        $logs = InvoiceReminderLog::where('status', 'SUCCESS')->where('sent_at', '>=', now()->startOfDay())
            ->where('sent_at', '<', now()->addDay()->startOfDay())->where(function ($q) use ($ids) {
                foreach ($ids as $id) $q->orWhereJsonContains('invoice_ids', (int) $id);
            })->with('sender:id,name')->orderByDesc('sent_at')->orderByDesc('id')->get();
        $warnings = [];
        foreach ($logs as $log) {
            foreach (array_intersect($ids, $log->invoice_ids) as $id) {
                $warnings[$id] ??= ['invoice_id' => (int) $id, 'sent_at' => $log->sent_at->toIso8601String(),
                    'sender_name' => $log->sender?->name ?? 'Pengguna #'.$log->sent_by];
            }
        }
        return array_values($warnings);
    }
}
