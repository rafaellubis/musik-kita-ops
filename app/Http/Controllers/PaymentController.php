<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Catat pembayaran + void (M05).
 *
 * Authorize:
 *   - store : role:Owner|Admin (route middleware)
 *   - void  : role:Owner only (BR-5.18)
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $service,
    ) {}

    /**
     * Catat pembayaran baru untuk invoice.
     */
    public function store(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount'       => 'required|integer|min:1|max:99999999',
            'method'       => ['required', Rule::in(array_keys(Payment::METHODS))],
            'payment_date' => 'required|date|before_or_equal:today',
            'proof_image'  => 'nullable|image|max:2048', // 2MB
            'notes'        => 'nullable|string|max:500',
        ], [
            'amount.required'       => 'Jumlah pembayaran wajib diisi.',
            'amount.min'            => 'Jumlah pembayaran minimal Rp 1.',
            'method.required'       => 'Metode pembayaran wajib dipilih.',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'payment_date.before_or_equal' => 'Tanggal pembayaran tidak boleh di masa depan.',
            'proof_image.image'     => 'File bukti harus berupa gambar (JPG/PNG).',
            'proof_image.max'       => 'Ukuran bukti pembayaran maksimal 2MB.',
        ]);

        // Upload file bukti kalau ada
        $proofPath = null;
        if ($request->hasFile('proof_image')) {
            $proofPath = $request->file('proof_image')->store('payments', 'public');
        }

        try {
            $payment = $this->service->recordPayment($invoice, [
                'amount'       => $data['amount'],
                'method'       => $data['method'],
                'payment_date' => $data['payment_date'],
                'proof_image'  => $proofPath,
                'notes'        => $data['notes'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice->id)
            ->with('success', sprintf(
                'Pembayaran %s dicatat. Kuitansi: %s.',
                'Rp ' . number_format($data['amount'], 0, ',', '.'),
                $payment->receipt_number,
            ));
    }

    /**
     * Void pembayaran. Hanya Owner — middleware role:Owner di route.
     */
    /**
     * Halaman kuitansi A4 untuk dicetak.
     */
    public function receipt(Payment $payment)
    {
        $payment->load(['invoice.student', 'invoice.items', 'createdBy']);

        return view('payments.receipt', compact('payment'));
    }

    public function void(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Alasan void wajib diisi untuk audit trail.',
        ]);

        try {
            $this->service->voidPayment($payment, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(
            action: AuditLog::ACTION_VOID,
            entity: $payment,
            entityLabel: $payment->receipt_number,
            notes: 'Alasan: ' . $data['reason'],
        );

        return back()->with('success', sprintf(
            'Pembayaran %s berhasil di-void. Status invoice di-recalc.',
            $payment->receipt_number,
        ));
    }
}
