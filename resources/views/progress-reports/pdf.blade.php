<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Progres — {{ $progressReport->student->full_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2C1A07; margin: 0; padding: 20px; }
        h2 { font-size: 12px; margin: 14px 0 6px; color: #3D2610; border-bottom: 1px solid #E8D5A0; padding-bottom: 4px; }
        .header-box { border: 1.5px solid #C8A870; border-radius: 4px; padding: 12px 16px; margin-bottom: 14px; }
        .header-title { font-size: 14px; font-weight: bold; text-align: center; color: #3D2610; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px; }
        .divider { height: 1px; background: #E8D5A0; margin: 8px 0; }
        .meta-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .meta-table td { padding: 3px 0; vertical-align: top; }
        .meta-label { color: #8A6848; width: 130px; }
        .meta-colon { width: 12px; color: #8A6848; }
        .meta-value { font-weight: bold; color: #2C1A07; }
        .stars { color: #C8A870; letter-spacing: 2px; font-size: 13px; }
        .section-title { font-size: 11px; font-weight: bold; color: #3D2610; margin: 12px 0 6px; }
        .week-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .week-label { width: 70px; font-size: 10px; color: #8A6848; vertical-align: top; padding: 6px 8px 6px 0; }
        .week-box { border: 1px solid #E8D5A0; background: #FEFCF5; padding: 6px 10px; font-size: 10px; color: #2C1A07; min-height: 28px; line-height: 1.5; }
        .rating-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .rating-label { width: 130px; color: #8A6848; font-size: 10.5px; padding: 4px 0; }
        .narrative-box { border: 1px solid #E8D5A0; background: #FEFCF5; padding: 8px 10px; margin-bottom: 8px; min-height: 40px; font-size: 10.5px; white-space: pre-wrap; line-height: 1.6; color: #2C1A07; }
        .narrative-title { font-size: 10.5px; font-weight: bold; color: #3D2610; margin-bottom: 4px; }
        .kesimpulan-table { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 10px; }
        .kesimpulan-cell { border: 1px solid #E8D5A0; padding: 8px 4px; text-align: center; font-size: 9.5px; color: #8A6848; background: #FEFCF5; width: 25%; }
        .kesimpulan-cell-active { border: 2px solid #C8A870; background: #FBF3E0; font-weight: bold; color: #3D2610; }
        .footer-box { margin-top: 16px; padding-top: 10px; border-top: 1px solid #E8D5A0; }
        .footer-instrument { font-size: 11px; font-weight: bold; color: #3D2610; margin-bottom: 6px; }
        .ttd-box { text-align: right; margin-top: 28px; page-break-inside: avoid; }
        .ttd-space { height: 50px; }
        .ttd-line { border-top: 1px solid #333; display: inline-block; padding-top: 4px; font-size: 10px; font-weight: bold; min-width: 180px; text-align: center; }
        .ttd-role { font-size: 9px; color: #8A6848; margin-top: 2px; text-align: center; }
        .pdf-footnote { margin-top: 16px; font-size: 8px; color: #8A6848; text-align: center; border-top: 1px solid #E8D5A0; padding-top: 8px; }
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
    $emoji       = $progressReport->instrumentEmoji();
    $ttdDate     = ($progressReport->submitted_at ?? now())
        ->locale('id')
        ->isoFormat('D MMMM Y');
    $kesimpulanLabels = \App\Models\ProgressReport::kesimpulanLabels();
@endphp

{{-- ===== HEADER ===== --}}
<div class="header-box">
    <div class="header-title">Laporan Progres Les Musik KITA</div>
    <div class="divider"></div>
    <table class="meta-table">
        <tr>
            <td class="meta-label">Nama</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $progressReport->student->full_name }}</td>
        </tr>
        <tr>
            <td class="meta-label">Instrumen</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $pkg->instrument->name }}</td>
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

{{-- ===== KEHADIRAN & MATERI ===== --}}
<div class="section-title">Kehadiran dan Materi yang Dipelajari</div>
<table class="week-table">
    @foreach ([1 => 'Minggu 1', 2 => 'Minggu 2', 3 => 'Minggu 3', 4 => 'Minggu 4'] as $seq => $mingguLabel)
        <tr>
            <td class="week-label">{{ $mingguLabel }} :</td>
            <td class="week-box">{{ $weekly[$seq] ?? '—' }}</td>
        </tr>
    @endforeach
</table>

{{-- ===== PERKEMBANGAN ===== --}}
<div class="section-title">Perkembangan {{ $progressReport->student->full_name }} Selama Les di Bulan {{ $progressReport->namaBulan() }}</div>
<table class="rating-table">
    @foreach ([
        'Teknik Bermain' => $progressReport->rating_teknik,
        'Materi'         => $progressReport->rating_materi,
        'Reading'        => $progressReport->rating_reading,
        'Repertoar'      => $progressReport->rating_repertoar,
    ] as $ratingLabel => $ratingVal)
        <tr>
            <td class="rating-label">{{ $ratingLabel }}</td>
            <td><span class="stars">{{ $ratingVal ? \App\Models\ProgressReport::renderStars($ratingVal) : '—' }}</span></td>
        </tr>
    @endforeach
</table>

{{-- ===== CATATAN NARATIF ===== --}}
<div class="narrative-title">Catatan Guru Terhadap Perkembangan Musikal {{ $progressReport->student->full_name }}</div>
<div class="narrative-box">{{ $progressReport->catatan_perkembangan_musikal ?? '—' }}</div>

<div class="narrative-title">Catatan Guru Terhadap Karakter {{ $progressReport->student->full_name }}</div>
<div class="narrative-box">{{ $progressReport->catatan_karakter ?? '—' }}</div>

{{-- ===== KESIMPULAN PROGRESS ===== --}}
<div class="section-title">Kesimpulan Progress</div>
<table class="kesimpulan-table">
    <tr>
        @foreach ($kesimpulanLabels as $key => $label)
            <td class="{{ $progressReport->kesimpulan_progress === $key ? 'kesimpulan-cell-active' : 'kesimpulan-cell' }}">
                {{ $label }}
            </td>
        @endforeach
    </tr>
</table>

{{-- ===== FOOTER: INSTRUMENT + LEVEL + PROGRESS BAR ===== --}}
<div class="footer-box">
    <div class="footer-instrument">{{ $emoji }} {{ $pkg->instrument->name }} · {{ $pkg->getLevelLabel() }}</div>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:80%; vertical-align:middle;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #C8A870; overflow:hidden;">
                    <tr>
                        <td width="{{ $pct }}%" bgcolor="#C8A870" style="height:12px;"></td>
                        <td width="{{ 100 - $pct }}%" bgcolor="#F0E4C0" style="height:12px;"></td>
                    </tr>
                </table>
            </td>
            <td style="width:20%; text-align:right; padding-left:8px; font-size:10px; color:#3D2610; vertical-align:middle;">{{ $pct }}%</td>
        </tr>
    </table>
</div>

{{-- ===== TTD ===== --}}
<div class="ttd-box">
    <div style="font-size:10px; color:#6B4A2A; margin-bottom:4px;">Jakarta, {{ $ttdDate }}</div>
    <div style="font-size:10px;">Hormat kami,</div>
    <div class="ttd-space"></div>
    <div class="ttd-line">{{ $progressReport->teacher->name }}</div>
    <div class="ttd-role">Pengajar {{ $pkg->instrument->name }}</div>
</div>

<div class="pdf-footnote">
    Laporan ini merupakan evaluasi perkembangan belajar bulanan.
    Untuk pertanyaan lebih lanjut, silakan hubungi admin studio Musik KITA.
</div>

</body>
</html>
