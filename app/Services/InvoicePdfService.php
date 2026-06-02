<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Generate PDF bytes per invoice (DomPDF) untuk lampiran WhatsApp.
 */
class InvoicePdfService
{
    /**
     * Render satu invoice ke raw PDF bytes.
     */
    public function renderPdf(Invoice $invoice): string
    {
        $invoice->load([
            'student',
            'enrollment.package.instrument',
            'items' => fn ($q) => $q->whereNull('parent_item_id')->with('discountItem'),
        ]);

        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * Nama file aman untuk Wablas (slash → dash).
     * Contoh: INV/2026/06/0001 → INV-2026-06-0001.pdf
     */
    public function filenameFor(Invoice $invoice): string
    {
        $base = str_replace('/', '-', $invoice->invoice_number);

        return $base . '.pdf';
    }

    /**
     * Caption singkat untuk lampiran PDF.
     */
    public function captionFor(Invoice $invoice): string
    {
        $kelas = $this->classLabel($invoice);

        return "Tagihan {$invoice->invoice_number} — {$kelas}";
    }

    private function classLabel(Invoice $invoice): string
    {
        $pkg = $invoice->enrollment?->package;
        if (! $pkg) {
            return $invoice->description ?? 'Tagihan';
        }

        $instrument = $pkg->instrument?->name ?? '';
        $type = Str::title(str_replace('_', ' ', strtolower($pkg->class_type ?? '')));

        return trim("{$instrument} {$type}") ?: $invoice->invoice_number;
    }
}
