<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Progress Report — {{ $progressReport->student->full_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1F2937; margin: 0; padding: 1px 18px; }
        .top-header { width: 100%; border-collapse: collapse; margin-bottom: 2px; padding-bottom: 0; }
        .top-header td { vertical-align: top; border: none; padding: 0; }
        .studio-logo { height: 32px; max-width: 140px; display: block; margin-bottom: 2px; }
        .studio-contact { font-size: 8px; color: #166534; line-height: 1.15; }
        .report-number { font-size: 9px; color: #6B7280; text-align: right; white-space: nowrap; }
        .brand-strip { background: #1E3A5F; padding: 6px 12px; margin-bottom: 12px; margin-top: 0; }
        .doc-title { font-size: 12px; font-weight: bold; color: #fff; letter-spacing: .06em; text-transform: uppercase; }
        .doc-sub { font-size: 10px; font-weight: bold; color: #fff; margin-top: 2px; }
        .doc-period { font-size: 8.5px; color: rgba(255,255,255,.75); margin-top: 2px; }
        .meta-box { border: 1px solid #D1D5DB; padding: 10px 14px 12px; margin-bottom: 12px; }
        .meta-table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .meta-table td { padding: 2px 0; vertical-align: top; border: none; }
        .meta-label { color: #6B7280; width: 128px; }
        .meta-colon { width: 10px; color: #6B7280; }
        .meta-value { font-weight: bold; color: #1F2937; }
        .stars { color: #B45309; letter-spacing: 1px; font-size: 12px; }
        .section-title { font-size: 10.5px; font-weight: bold; color: #1E3A5F; margin: 10px 0 5px; padding-bottom: 3px; border-bottom: 1px solid #D1D5DB; }
        .week-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .week-label { width: 68px; font-size: 9.5px; color: #6B7280; vertical-align: top; padding: 5px 6px 5px 0; }
        .week-box { border: 1px solid #D1D5DB; background: #F9FAFB; padding: 5px 8px; font-size: 9.5px; color: #1F2937; min-height: 24px; line-height: 1.45; }
        .rating-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .rating-label { width: 118px; color: #6B7280; font-size: 10px; padding: 4px 6px 4px 0; vertical-align: middle; }
        .rating-stars { width: 88px; color: #B45309; font-size: 12px; letter-spacing: 1px; vertical-align: middle; white-space: nowrap; padding: 4px 8px 4px 0; }
        .rating-note-box { border: 1px solid #D1D5DB; background: #F9FAFB; padding: 5px 8px; font-size: 9px; color: #1F2937; min-height: 22px; line-height: 1.45; white-space: pre-wrap; vertical-align: middle; }
        .narrative-title { font-size: 10px; font-weight: bold; color: #1E3A5F; margin-bottom: 3px; }
        .narrative-box { border: 1px solid #D1D5DB; background: #F9FAFB; padding: 7px 9px; margin-bottom: 8px; min-height: 36px; font-size: 10px; white-space: pre-wrap; line-height: 1.55; color: #1F2937; }
        .kesimpulan-table { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 8px; table-layout: fixed; }
        .kesimpulan-cell { padding: 10px 6px; text-align: center; vertical-align: middle; font-size: 9px; color: #6B7280; background: #F9FAFB; width: 25%; line-height: 1.35; border: 2px solid #D1D5DB; }
        .kesimpulan-cell-active { padding: 10px 6px; text-align: center; vertical-align: middle; font-size: 9px; font-weight: bold; color: #1E3A5F; background: #EEF2FF; width: 25%; line-height: 1.35; border: 2px solid #1E3A5F; }
        .footer-box { margin-top: 14px; padding-top: 8px; border-top: 1px solid #D1D5DB; }
        .footer-instrument { font-size: 10.5px; font-weight: bold; color: #1F2937; margin-bottom: 5px; }
        .progress-label { font-size: 8px; font-weight: bold; color: #1E3A5F; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 4px; }
        .progress-pct { font-size: 10px; font-weight: bold; color: #1F2937; white-space: nowrap; padding-left: 10px; vertical-align: middle; }
        .ttd-wrap { margin-top: 24px; page-break-inside: avoid; }
        .ttd-table { width: 100%; border-collapse: collapse; }
        .ttd-table td { border: none; padding: 0; vertical-align: top; }
        .ttd-inner { width: 200px; text-align: center; }
        .ttd-date { font-size: 9.5px; color: #6B7280; margin-bottom: 3px; }
        .ttd-role-label { font-size: 9.5px; color: #1F2937; }
        .ttd-space { height: 44px; }
        .ttd-line { border-top: 1px solid #374151; padding-top: 3px; font-size: 9.5px; font-weight: bold; color: #1F2937; }
        .ttd-sub { font-size: 8.5px; color: #6B7280; margin-top: 2px; }
        .pdf-footnote { margin-top: 14px; font-size: 7.5px; color: #6B7280; text-align: center; border-top: 1px solid #D1D5DB; padding-top: 6px; }
    </style>
</head>
<body>

@php
    $pkg         = $progressReport->enrollment->package;
    $weekly      = $progressReport->weeklyMaterials();
    $avgRating   = $progressReport->averageSessionRating();
    $headerStars = $avgRating !== null
        ? \App\Models\ProgressReport::renderStars((int) round($avgRating))
        : '—';
    $pct         = $progressReport->progress_percent ?? 0;
    $barWidth    = 120;
    $fillPx      = (int) round($barWidth * min(max($pct, 0), 100) / 100);
    $emptyPx     = $barWidth - $fillPx;
    $ttdDate     = ($progressReport->submitted_at ?? now())
        ->locale('id')
        ->isoFormat('D MMMM Y');
    $kesimpulanLabels = \App\Models\ProgressReport::kesimpulanLabels();
@endphp

{{-- Studio header (kiri: logo + kontak, kanan: nomor laporan) --}}
<table class="top-header">
    <tr>
        <td width="70%">
            @if(!empty($logoPath))
                <img class="studio-logo" src="{{ $logoPath }}" alt="Musik KITA">
            @else
                <div style="font-size:11px;font-weight:bold;color:#166534;margin-bottom:4px;">MUSIK KITA</div>
            @endif
            <div class="studio-contact">
                {{ config('studio.address') }}<br>
                WA: {{ config('studio.whatsapp_display') }}
            </div>
        </td>
        <td width="30%">
            @if($progressReport->report_number)
                <div class="report-number">{{ $progressReport->report_number }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- Branded strip compact (tanpa logo) --}}
<div class="brand-strip">
    <div class="doc-title">Monthly Progress Report</div>
    <div class="doc-sub">Les Musik KITA</div>
</div>

<div class="meta-box">
    <table class="meta-table">
        <tr>
            <td class="meta-label">Nama</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $progressReport->student->full_name }}</td>
        </tr>
        <tr>
            <td class="meta-label">Instrumen</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $pkg->getReportInstrumentLabel() }}</td>
        </tr>
        <tr>
            <td class="meta-label">Guru Pengajar</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $progressReport->teacher->name }}</td>
        </tr>
        <tr>
            <td class="meta-label">Bulan</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $progressReport->namaBulan() }}</td>
        </tr>
        <tr>
            <td class="meta-label">Rating Anak hari Ini</td>
            <td class="meta-colon">:</td>
            <td><span class="stars">{{ $headerStars }}</span></td>
        </tr>
    </table>
</div>

<div class="section-title">Kehadiran dan Materi yang Dipelajari</div>
<table class="week-table">
    @foreach ([1 => 'Minggu 1', 2 => 'Minggu 2', 3 => 'Minggu 3', 4 => 'Minggu 4'] as $seq => $mingguLabel)
        <tr>
            <td class="week-label">{{ $mingguLabel }} :</td>
            <td class="week-box">{{ $weekly[$seq] ?? '—' }}</td>
        </tr>
    @endforeach
</table>

<div class="section-title">Perkembangan {{ $progressReport->student->full_name }} Selama Les di Bulan {{ $progressReport->namaBulan() }}</div>
<table class="rating-table">
    @foreach (\App\Models\ProgressReport::monthlyRatingFields() as $field)
        @php
            $ratingVal = $progressReport->{$field['rating']};
            $catatanVal = $progressReport->{$field['catatan']};
        @endphp
        <tr>
            <td class="rating-label">{{ $field['label'] }}</td>
            <td class="rating-stars">{{ $ratingVal ? \App\Models\ProgressReport::renderStars($ratingVal) : '—' }}</td>
            <td class="rating-note-box">{{ $catatanVal ?: '—' }}</td>
        </tr>
    @endforeach
</table>

<div class="narrative-title">Catatan Guru Terhadap Perkembangan Musikal {{ $progressReport->student->full_name }}</div>
<div class="narrative-box">{{ $progressReport->catatan_perkembangan_musikal ?? '—' }}</div>

<div class="narrative-title">Catatan Guru Terhadap Karakter {{ $progressReport->student->full_name }}</div>
<div class="narrative-box">{{ $progressReport->catatan_karakter ?? '—' }}</div>

<div class="section-title">Laporan Progress</div>
<table class="kesimpulan-table" width="100%" cellpadding="0" cellspacing="4">
    <tr>
        @foreach ($kesimpulanLabels as $key => $label)
            @php $isActive = $progressReport->kesimpulan_progress === $key; @endphp
            <td align="center" valign="middle" width="25%"
                class="{{ $isActive ? 'kesimpulan-cell-active' : 'kesimpulan-cell' }}"
                style="border:2px solid {{ $isActive ? '#1E3A5F' : '#D1D5DB' }}; background:{{ $isActive ? '#EEF2FF' : '#F9FAFB' }};">
                {{ $label }}
            </td>
        @endforeach
    </tr>
</table>

<div class="footer-box">
    <div class="footer-instrument">{{ $pkg->getReportInstrumentLabel() }}</div>
    <div class="progress-label">Progress</div>
    <table cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
        <tr>
            <td width="{{ $barWidth }}" style="vertical-align:middle;padding:0;">
                <table width="{{ $barWidth }}" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #D1D5DB;">
                    <tr>
                        @if($fillPx > 0)
                            <td width="{{ $fillPx }}" bgcolor="#1E3A5F" style="height:10px;padding:0;line-height:10px;font-size:1px;">&#160;</td>
                        @endif
                        @if($emptyPx > 0)
                            <td width="{{ $emptyPx }}" bgcolor="#E5E7EB" style="height:10px;padding:0;line-height:10px;font-size:1px;">&#160;</td>
                        @endif
                    </tr>
                </table>
            </td>
            <td class="progress-pct" align="left" valign="middle">{{ $pct }}%</td>
        </tr>
    </table>
</div>

<div class="ttd-wrap">
    <table class="ttd-table">
        <tr>
            <td></td>
            <td style="width:200px;">
                <div class="ttd-inner">
                    <div class="ttd-date">Jakarta, {{ $ttdDate }}</div>
                    <div class="ttd-role-label">Guru Pengajar</div>
                    <div class="ttd-space"></div>
                    <div class="ttd-line">{{ $progressReport->teacher->name }}</div>
                    <div class="ttd-sub">Pengajar {{ $pkg->instrument->name }}</div>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="pdf-footnote">
    Laporan ini merupakan evaluasi perkembangan belajar bulanan.
    Untuk pertanyaan lebih lanjut, silakan hubungi admin studio Musik KITA.
</div>

</body>
</html>
