<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Generate PDF bytes per invoice (DomPDF) untuk link publik reminder WA.
 */
class InvoicePdfService
{
    public function __construct(
        private readonly ProgressReportPdfAssetService $pdfAssets,
    ) {}

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

        $logoPath = $this->pdfAssets->optimizedLogoPath();

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'logoPath' => $logoPath,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('default_media_type', 'print')
            ->setOption('enable_font_subsetting', true)
            ->output();
    }

    /**
     * Nama file aman untuk unduhan (slash → dash).
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
            $label = $invoice->description ?? 'Tagihan';
            if ($invoice->isInstallment() && $invoice->installment_number) {
                $label .= " (Termin {$invoice->installment_number}/3)";
            }
            return $label;
        }

        $instrument = $pkg->instrument?->name ?? '';
        $type = Str::title(str_replace('_', ' ', strtolower($pkg->class_type ?? '')));
        $label = trim("{$instrument} {$type}");

        if ($invoice->isInstallment() && $invoice->installment_number) {
            $label .= " (Termin {$invoice->installment_number}/3)";
        }

        return $label ?: $invoice->invoice_number;
    }
}
