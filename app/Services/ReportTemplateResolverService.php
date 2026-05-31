<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\ReportTemplate;

/**
 * Resolve template laporan progres otomatis dari enrollment/paket murid.
 */
class ReportTemplateResolverService
{
    /**
     * Tentukan jenis template dari paket enrollment.
     * DUO dipetakan ke REGULER (kurikulum Basic, seksi Berduo di template).
     */
    public function templateKindForPackage(Package $package): ?string
    {
        if ($package->isKidsClass()) {
            return ReportTemplate::KIND_KIDS;
        }

        if ($package->class_type === 'HOBBY') {
            return ReportTemplate::KIND_HOBBY;
        }

        if ($package->class_type === 'REGULER' || $package->isDuo()) {
            return ReportTemplate::KIND_REGULER;
        }

        return null;
    }

    /**
     * Cari template aktif yang cocok untuk enrollment.
     */
    public function resolveForEnrollment(Enrollment $enrollment): ?ReportTemplate
    {
        $enrollment->loadMissing('package');
        $kind = $this->templateKindForPackage($enrollment->package);

        if ($kind === null) {
            return null;
        }

        return ReportTemplate::query()
            ->where('instrument_id', $enrollment->package->instrument_id)
            ->where('template_kind', $kind)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Info preview template untuk UI guru (Alpine map).
     *
     * @return array{ok: bool, name?: string, error?: string}
     */
    public function previewForEnrollment(Enrollment $enrollment): array
    {
        $template = $this->resolveForEnrollment($enrollment);

        if ($template) {
            return ['ok' => true, 'name' => $template->name];
        }

        $code = $enrollment->package->code ?? '-';

        return [
            'ok'    => false,
            'error' => "Template laporan untuk paket {$code} belum tersedia. Hubungi Owner.",
        ];
    }
}
