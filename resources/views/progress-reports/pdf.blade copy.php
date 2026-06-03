<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Progres — {{ $progressReport->student->full_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2C1A07; margin: 0; padding: 20px; }
        h2 { font-size: 12px; margin: 14px 0 6px; color: #3D2610; border-bottom: 1px solid #E8D5A0; padding-bottom: 4px; }
        .header-box { border: 1.5px solid #C8A870; border-radius: 4px; padding: 12px 16px; margin-bottom: 14px; }
        .header-title { font-size: 14px; font-weight: bold; text-align: center; color: #3D2610; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
        .header-sub { text-align: center; font-size: 10px; color: #8A6848; margin-bottom: 8px; }
        .header-note { text-align: center; font-size: 9px; color: #6B4A2A; font-style: italic; margin-bottom: 8px; }
        .divider { height: 1px; background: #E8D5A0; margin: 8px 0; }
        .meta-grid { width: 100%; border-collapse: collapse; }
        .meta-grid td { padding: 3px 0; vertical-align: top; }
        .meta-label { color: #8A6848; width: 110px; }
        .meta-colon { width: 12px; color: #8A6848; }
        .meta-value { font-weight: bold; color: #2C1A07; }
        .legend-box { font-size: 9px; color: #6B4A2A; background: #FBF3E0; border: 1px solid #E8D5A0; padding: 6px 10px; margin-bottom: 14px; line-height: 1.5; }
        .section-box { margin-bottom: 12px; page-break-inside: avoid; }
        .section-head { background: #3D2610; color: #fff; padding: 5px 10px; font-size: 11px; font-weight: bold; }
        .section-count { float: right; font-weight: normal; font-size: 10px; opacity: 0.9; }
        .section-summary { font-style: italic; color: #6B4A2A; font-size: 10px; padding: 6px 10px; background: #FBF3E0; border-left: 3px solid #C8A870; line-height: 1.5; }
        .checklist { border: 1px solid #E8D5A0; }
        .check-item { padding: 5px 10px; border-bottom: 1px solid #F0E4C0; font-size: 10.5px; line-height: 1.4; }
        .check-item:last-child { border-bottom: none; }
        .check-yes { color: #2a7a2a; font-weight: bold; }
        .check-no { color: #aaa; }
        .check-label-muted { color: #8A6848; }
        .rep-chip { display: inline-block; font-size: 10px; padding: 3px 10px; border-radius: 10px; background: #F0E4C0; border: 1px solid #C8A870; margin: 2px 4px 2px 0; }
        .narrative-block { margin-bottom: 10px; }
        .narrative-title { font-size: 11px; font-weight: bold; color: #3D2610; margin-bottom: 4px; }
        .narrative { white-space: pre-wrap; line-height: 1.6; color: #2C1A07; }
        .session-date { font-size: 10px; font-weight: bold; color: #8A6848; margin-top: 8px; margin-bottom: 2px; }
        .session-text { padding-left: 8px; border-left: 2px solid #E8D5A0; margin-bottom: 4px; }
        .two-col { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .two-col td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        .two-col td:last-child { padding: 0 0 0 8px; }
        .message-box { background: #FBF3E0; border: 1px solid #E8D5A0; padding: 8px 10px; border-radius: 3px; min-height: 60px; }
        .footer { margin-top: 28px; text-align: right; page-break-inside: avoid; }
        .ttd-box { display: inline-block; text-align: center; width: 180px; }
        .ttd-city { font-size: 10px; color: #6B4A2A; margin-bottom: 4px; }
        .ttd-space { height: 50px; }
        .ttd-line { border-top: 1px solid #333; padding-top: 4px; font-size: 10px; font-weight: bold; }
        .ttd-role { font-size: 9px; color: #8A6848; margin-top: 2px; }
        .pdf-footnote { margin-top: 16px; font-size: 8px; color: #8A6848; text-align: center; border-top: 1px solid #E8D5A0; padding-top: 8px; }
    </style>
</head>
<body>
@php
    $pkg = $progressReport->enrollment->package;
    $isKids = $pkg->isKidsClass();
    $isDuo  = $pkg->isDuo();
    // Label program — mudah dipahami orang tua
    if ($isKids) {
        $programLabel = 'Kids Class · Eksplorasi minat & bakat (usia 4–5 tahun)';
    } elseif ($isDuo) {
        $programLabel = $pkg->instrument->name . ' · Reguler Basic · Belajar Berdua (1 ruangan)';
    } elseif ($pkg->class_type === 'REGULER') {
        $gradeLabel = $pkg->grade === 'BASIC' ? 'Basic' : ($pkg->grade ?? '-');
        $programLabel = $pkg->instrument->name . ' · Reguler · Level ' . $gradeLabel;
    } elseif ($pkg->class_type === 'HOBBY') {
        $programLabel = $pkg->instrument->name . ' · Hobby · ' . $pkg->duration_min . ' menit';
    } else {
        $programLabel = $pkg->instrument->name . ' · ' . $pkg->code;
    }
    $repertoireTitle = $isKids
        ? 'Aktivitas & Lagu yang Dijelajahi'
        : 'Repertoar yang Dipelajari';
    $ttdDate = ($progressReport->submitted_at ?? now())
        ->locale('id')
        ->isoFormat('D MMMM Y');
@endphp
{{-- Header --}}
<div class="header-box">
    <div class="header-title">Laporan Progres Les Musik KITA</div>
    <div class="header-sub">Studio Musik KITA · Laporan Bulanan</div>
    <div class="header-note">Dikirim kepada orang tua / wali murid</div>
    <div class="divider"></div>
    <table class="meta-grid">
        <tr>
            <td class="meta-label">Nama Murid</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $progressReport->student->full_name }}</td>
            <td class="meta-label">Periode</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $progressReport->namaBulan() }}</td>
        </tr>
        <tr>
            <td class="meta-label">Program Les</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $programLabel }}</td>
            <td class="meta-label">Pengajar</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $progressReport->teacher->name }}</td>
        </tr>
        @if($progressReport->student->nickname)
        <tr>
            <td class="meta-label">Panggilan</td>
            <td class="meta-colon">:</td>
            <td class="meta-value">{{ $progressReport->student->nickname }}</td>
            <td colspan="3"></td>
        </tr>
        @endif
    </table>
</div>
{{-- Legenda checklist --}}
<div class="legend-box">
    <strong>Petunjuk membaca checklist:</strong>
    ✓ = sudah ditargetkan & menunjukkan kemajuan layak bulan ini &nbsp;|&nbsp;
    ○ = belum fokus bulan ini / masih dalam proses / belum diajarkan.
    Narasi di setiap bagian menjelaskan konteks untuk orang tua.
</div>
{{-- Checklist per seksi --}}
@foreach($progressReport->template->sections as $section)
    @php
        $sectionRecord = $progressReport->sections->firstWhere('report_template_section_id', $section->id);
        $checkedCount = 0;
        $totalItems = $section->items->count();
    @endphp
    <div class="section-box">
        <div class="section-head">
            {{ $section->sort_order }}. {{ $section->title }}
            @if($totalItems > 0)
                <span class="section-count">{{ $checkedCount = $section->items->filter(fn($item) => ($progressReport->items->firstWhere('report_template_item_id', $item->id)?->is_checked ?? false))->count() }}/{{ $totalItems }} tercapai</span>
            @endif
        </div>
        @if($sectionRecord?->summary)
            <div class="section-summary">{{ $sectionRecord->summary }}</div>
        @endif
        @if($totalItems > 0)
            <div class="checklist">
                @foreach($section->items as $item)
                    @php
                        $itemRecord = $progressReport->items->firstWhere('report_template_item_id', $item->id);
                        $checked = $itemRecord?->is_checked ?? false;
                    @endphp
                    <div class="check-item">
                        <span class="{{ $checked ? 'check-yes' : 'check-no' }}">{{ $checked ? '✓' : '○' }}</span>
                        @if($checked)
                            {{ $item->label }}
                        @else
                            <span class="check-label-muted">{{ $item->label }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endforeach
{{-- Repertoar / aktivitas Kids --}}
@if(!empty($progressReport->repertoire))
    <h2>{{ $repertoireTitle }}</h2>
    @foreach($progressReport->repertoire as $lagu)
        <span class="rep-chip">{{ $lagu }}</span>
    @endforeach
@endif
{{-- Highlight --}}
@if($progressReport->highlight)
    <div class="narrative-block">
        <div class="narrative-title">Pencapaian Utama Bulan Ini</div>
        <div class="narrative">{{ $progressReport->highlight }}</div>
    </div>
@endif
{{-- Catatan per sesi --}}
@if($progressReport->sessionNotes->isNotEmpty())
    <h2>Ringkasan Per Pertemuan</h2>
    @foreach($progressReport->sessionNotes as $note)
        <div class="session-date">{{ \Carbon\Carbon::parse($note->session_date)->locale('id')->isoFormat('D MMMM Y') }}</div>
        <div class="session-text narrative">{{ $note->notes }}</div>
    @endforeach
@endif
{{-- Pesan guru + target (dua kolom) --}}
@if($progressReport->summary_notes || $progressReport->target_notes)
    <table class="two-col">
        <tr>
            @if($progressReport->summary_notes)
                <td>
                    <div class="narrative-title">Pesan Guru untuk Murid & Orang Tua</div>
                    <div class="message-box narrative">{{ $progressReport->summary_notes }}</div>
                </td>
            @endif
            @if($progressReport->target_notes)
                <td>
                    <div class="narrative-title">Fokus Latihan Bulan Depan</div>
                    <div class="message-box narrative">{{ $progressReport->target_notes }}</div>
                </td>
            @endif
        </tr>
    </table>
@endif
{{-- Tanda tangan --}}
<div class="footer">
    <div class="ttd-box">
        <div class="ttd-city">Jakarta, {{ $ttdDate }}</div>
        <div>Hormat kami,</div>
        <div class="ttd-space"></div>
        <div class="ttd-line">{{ $progressReport->teacher->name }}</div>
        <div class="ttd-role">Pengajar {{ $progressReport->enrollment->package->instrument->name }}</div>
    </div>
</div>
<div class="pdf-footnote">
    Laporan ini merupakan evaluasi perkembangan belajar bulanan.
    Untuk pertanyaan lebih lanjut, silakan hubungi admin studio Musik KITA.
</div>
</body>
</html>