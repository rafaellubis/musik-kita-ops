<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemoveFineRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

/**
 * Tambah / hapus item tagihan manual di invoice (M05).
 *
 * Authorize (di-handle route middleware):
 *   - store   : role:Owner|Admin
 *   - destroy : role:Owner|Admin
 *
 * Rule bisnis:
 *   - Hanya invoice UNPAID/PARTIAL yang boleh diubah (PAID/VOID terkunci).
 *   - Hanya item manual (added_by != null) yang boleh dihapus.
 *   - Setiap perubahan item → recalc total_amount invoice.
 */
class InvoiceItemController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Tambah item manual ke invoice.
     */
    public function store(Request $request, Invoice $invoice)
    {
        // Invoice terkunci kalau sudah PAID atau VOID
        if (in_array($invoice->status, ['PAID', 'VOID'], true)) {
            return back()->with('error',
                'Invoice sudah ' . $invoice->status . ' — item tidak bisa ditambahkan.');
        }

        $data = $request->validate([
            'invoice_component_id' => 'required|exists:invoice_components,id',
            'description'          => 'required|string|max:255',
            'amount'               => 'required|integer|min:1|max:99999999',
        ], [
            'invoice_component_id.required' => 'Pilih komponen tagihan.',
            'invoice_component_id.exists'   => 'Komponen tagihan tidak valid.',
            'description.required'          => 'Deskripsi item wajib diisi.',
            'amount.required'               => 'Jumlah wajib diisi.',
            'amount.min'                    => 'Jumlah minimal Rp 1.',
        ]);

        // Ambil kode dari komponen yang dipilih
        $component = \App\Models\InvoiceComponent::find($data['invoice_component_id']);

        InvoiceItem::create([
            'invoice_id'           => $invoice->id,
            'invoice_component_id' => $component->id,
            'added_by'             => $request->user()->id,
            'item_code'            => $component->code,
            'description'          => $data['description'],
            'amount'               => $data['amount'],
        ]);

        // Recalc total invoice
        $newTotal = $invoice->items()->sum('amount');
        $invoice->update(['total_amount' => $newTotal]);
        $this->invoiceService->recalcStatus($invoice);

        return back()->with('success',
            "Item '{$component->code}' berhasil ditambahkan ke invoice.");
    }

    /**
     * Hapus item manual dari invoice.
     * Item sistem (added_by = null) tidak boleh dihapus.
     */
    public function destroy(InvoiceItem $invoiceItem)
    {
        $invoice = $invoiceItem->invoice;

        // Invoice terkunci
        if (in_array($invoice->status, ['PAID', 'VOID'], true)) {
            return back()->with('error',
                'Invoice sudah ' . $invoice->status . ' — item tidak bisa dihapus.');
        }

        // Hanya item manual yang boleh dihapus
        if (!$invoiceItem->isManual()) {
            return back()->with('error',
                'Item sistem (SPP, REG, DENDA, dll.) tidak bisa dihapus.');
        }

        $code = $invoiceItem->item_code;
        $invoiceItem->delete();

        // Recalc total invoice
        $newTotal = $invoice->items()->sum('amount');
        $invoice->update(['total_amount' => $newTotal]);
        $this->invoiceService->recalcStatus($invoice->fresh());

        return back()->with('success', "Item '{$code}' berhasil dihapus dari invoice.");
    }

    /**
     * Hapus item DENDA sistem + waiver permanen (cron tidak apply ulang).
     */
    public function removeFine(RemoveFineRequest $request, InvoiceItem $invoiceItem)
    {
        try {
            $this->invoiceService->waiveFine(
                $invoiceItem,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Denda berhasil dihapus dari invoice.');
    }
}
