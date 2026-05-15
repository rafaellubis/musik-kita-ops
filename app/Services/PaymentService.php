<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pencatatan + void pembayaran (M05).
 *
 * Tanggung jawab:
 *   - Bikin row Payment dengan nomor kuitansi KW/YYYY/MM/NNNN (BR-5.17)
 *   - Trigger InvoiceService::recalcStatus() setelah create/void
 *   - Void payment (BR-5.18: hanya OWNER)
 *
 * Validasi authorize void dilakukan di Controller (route middleware
 * role:Owner). Service hanya validate state (payment belum di-void).
 */
class PaymentService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Bikin row Payment dan recalc invoice.
     *
     * @param array{
     *     amount: int,
     *     method: string,
     *     payment_date: string,
     *     proof_image?: string|null,
     *     notes?: string|null,
     * } $data
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        if ($invoice->status === Invoice::STATUS_VOID) {
            throw new InvalidArgumentException('Tidak bisa bayar invoice yang sudah VOID.');
        }

        if ($data['amount'] <= 0) {
            throw new InvalidArgumentException('Jumlah pembayaran harus lebih dari 0.');
        }

        // Semua invoice harus dibayar penuh dalam satu pembayaran.
        // Untuk KIDS_CLASS_BUNDLE cicilan, "partial" diwujudkan lewat 3 invoice terpisah —
        // bukan dengan membayar 1 invoice berkali-kali dengan nominal kurang.
        if ($data['amount'] < $invoice->balance) {
            throw new InvalidArgumentException(
                'Pembayaran harus dilunasi penuh. ' .
                'Saldo yang harus dibayar: Rp ' . number_format($invoice->balance, 0, ',', '.') . '.'
            );
        }

        if (!array_key_exists($data['method'], Payment::METHODS)) {
            throw new InvalidArgumentException(
                'Metode pembayaran tidak valid. Pilih: ' . implode(', ', array_keys(Payment::METHODS))
            );
        }

        return DB::transaction(function () use ($invoice, $data) {
            $now = now();
            $year = $now->year;
            $month = $now->month;

            $payment = Payment::create([
                'receipt_number' => $this->generateReceiptNumber($year, $month),
                'invoice_id'     => $invoice->id,
                'amount'         => $data['amount'],
                'method'         => $data['method'],
                'payment_date'   => $data['payment_date'],
                'proof_image'    => $data['proof_image'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'created_by'     => Auth::id(),
            ]);

            $this->invoiceService->recalcStatus($invoice);

            return $payment->fresh();
        });
    }

    /**
     * Void payment (set voided_at + voided_by + recalc invoice).
     * Owner-only check ada di route middleware.
     *
     * Idempotent: kalau payment sudah voided, throw exception.
     */
    public function voidPayment(Payment $payment, User $voidedBy, string $reason): Payment
    {
        if ($payment->is_voided) {
            throw new InvalidArgumentException(
                'Pembayaran sudah di-void sebelumnya pada ' . $payment->voided_at->format('d M Y H:i')
            );
        }

        return DB::transaction(function () use ($payment, $voidedBy, $reason) {
            $payment->update([
                'voided_at'     => now(),
                'voided_by'     => $voidedBy->id,
                'voided_reason' => $reason,
            ]);

            // Refresh invoice status setelah pembayaran tidak valid
            $this->invoiceService->recalcStatus($payment->invoice);

            return $payment->fresh();
        });
    }

    /**
     * Generate nomor kuitansi unik: KW/YYYY/MM/NNNN.
     */
    private function generateReceiptNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $latest = Payment::where('receipt_number', 'like', "KW/{$year}/{$monthStr}/%")
            ->orderBy('receipt_number', 'desc')
            ->value('receipt_number');

        $nextSeq = 1;
        if ($latest) {
            $parts = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('KW/%d/%s/%04d', $year, $monthStr, $nextSeq);
    }
}
