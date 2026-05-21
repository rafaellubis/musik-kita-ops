<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountRequest;
use App\Models\InvoiceItem;
use App\Services\DiscountService;
use Illuminate\Http\RedirectResponse;

/**
 * Endpoint beri/hapus diskon per item invoice (M05).
 *
 * {invoiceItem} selalu mengacu ke item INDUK yang akan/sudah mendapat diskon.
 * Middleware role:Owner|Admin sudah dipasang di routes/web.php.
 */
class DiscountController extends Controller
{
    public function __construct(private DiscountService $discountService) {}

    /**
     * Simpan atau update diskon untuk item invoice.
     * Idempotent: jika diskon sudah ada, nilainya diupdate.
     */
    public function store(InvoiceItem $invoiceItem, StoreDiscountRequest $request): RedirectResponse
    {
        try {
            $this->discountService->applyDiscount(
                item: $invoiceItem,
                type: $request->discount_type,
                value: (int) $request->discount_value,
                reason: $request->discount_reason,
                by: auth()->user(),
            );

            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('success', 'Diskon berhasil diterapkan.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Hapus diskon dari item invoice.
     * {invoiceItem} adalah item INDUK — controller ambil discountItem-nya.
     */
    public function destroy(InvoiceItem $invoiceItem): RedirectResponse
    {
        $discountItem = $invoiceItem->discountItem()->first();

        if (!$discountItem) {
            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('error', 'Diskon tidak ditemukan untuk item ini.');
        }

        try {
            $this->discountService->removeDiscount(
                discountItem: $discountItem,
                by: auth()->user(),
            );

            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('success', 'Diskon berhasil dihapus.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('invoices.show', $invoiceItem->invoice_id)
                ->with('error', $e->getMessage());
        }
    }
}
