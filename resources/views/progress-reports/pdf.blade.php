<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Progres — {{ $progressReport->student->full_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 20px; }
        h2 { font-size: 12px; margin: 12px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .header-box { border: 1.5px solid #C8A870; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; }
        .header-title { font-size: 14px; font-weight: bold; text-align: center; color: #3D2610; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; }
        .header-sub { text-align: center; font-size: 10px; color: #8A6848; margin-bottom: 8px; }
        .divider { height: 1px; background: #E8D5A0; margin: 8px 0; }
        .meta-grid { display: table; width: 100%; }
        .meta-row { display: table-row; }
        .meta-label { display: table-cell; color: #8A6848; width: 100px; padding: 2px 0; }
        .meta-colon { display: table-cell; width: 12px; }
        .meta-value { display: table-cell; font-weight: bold; color: #2C1A07; }
        .meta-half { display: table; width: 50%; float: left; }
        .section-box { margin-bottom: 14px; }
        .section-title { font-size: 11px; font-weight: bold; color: #fff; background: #3D2610; padding: 5px 10px; }
        .section-summary { font-style: italic; color: #6B4A2A; font-size: 10px; padding: 5px 10px; background: #FBF3E0; border-left: 2px solid #C8A870; }
        .checklist { border: 1px solid #E8D5A0; }
        .check-item { padding: 5px 10px; border-bottom: 1px solid #F0E4C0; font-size: 11px; }
        .check-item:last-child { border-bottom: none; }
        .check-yes { color: #2a7a2a; font-weight: bold; }
        .check-no { color: #aaa; }
        .rep-chip { display: inline-block; font-size: 10px; padding: 2px 8px; border-radius: 10px; background: #F0E4C0; border: 1px solid #C8A870; margin: 2px; }
        .narrative { white-space: pre-wrap; line-height: 1.6; padding-left: 4px; }
        .session-date { font-size: 10px; font-weight: bold; color: #8A6848; font-family: monospace; margin-top: 6px; margin-bottom: 2px; }
        .session-text { padding-left: 8px; border-left: 2px solid #E8D5A0; }
        .footer { margin-top: 24px; text-align: right; }
        .ttd-box { display: inline-block; text-align: center; width: 150px; }
        .ttd-space { height: 48px; }
        .ttd-line { border-top: 1px solid #333; padding-top: 4px; font-size: 10px; font-weight: bold; }
        .ttd-role { font-size: 10px; color: #8A6848; }
        .clearfix::after { content: ''; display: table; clear: both; }
    </style>
</head>
<body>

<div class="header-box">
    <div class="header-title">Laporan Evaluasi Les Musik KITA</div>
    <div class="header-sub">Studio Musik KITA · Jakarta</div>
    <div class="divider"></div>
    <div class="clearfix">
        <div class="meta-half">
            <div class="meta-row"><span class="meta-label">Nama Siswa</span><span class="meta-colon">:</span><span class="meta-value">{{ $progressReport->student->full_name }}</span></div>
            <div class="meta-row"><span class="meta-label">Jurusan/Grade</span><span class="meta-colon">:</span><span class="meta-value">{{ $progressReport->enrollment->package->instrument->name }} / {{ $progressReport->enrollment->package->grade ?? $progressReport->enrollment->package->class_type }}</span></div>
        </div>
        <div class="meta-half">
            <div class="meta-row"><span class="meta-label">Periode</span><span class="meta-colon">:</span><span class="meta-value">{{ $progressReport->namaBulan() }}</span></div>
            <div class="meta-row"><span class="meta-label">Nama Pengajar</span><span class="meta-colon">:</span><span class="meta-value">{{ $progressReport->teacher->name }}</span></div>
        </div>
    </div>
</div>

@foreach($progressReport->template->sections as $section)
    @php $sectionRecord = $progressReport->sections->firstWhere('report_template_section_id', $section->id); @endphp
    <div class="section-box">
        <div class="section-title">{{ $section->sort_order }}. {{ $section->title }}</div>
        @if($sectionRecord?->summary)
            <div class="section-summary">{{ $sectionRecord->summary }}</div>
        @endif
        <div class="checklist">
            @foreach($section->items as $item)
                @php
                    $itemRecord = $progressReport->items->firstWhere('report_template_item_id', $item->id);
                    $checked = $itemRecord?->is_checked ?? false;
                @endphp
                <div class="check-item">
                    <span class="{{ $checked ? 'check-yes' : 'check-no' }}">{{ $checked ? '✓' : '○' }}</span>
                    &nbsp;{{ $item->label }}
                </div>
            @endforeach
        </div>
    </div>
@endforeach

@if($progressReport->repertoire)
    <h2>Repertoar yang Sudah Dipelajari</h2>
    @foreach($progressReport->repertoire as $lagu)
        <span class="rep-chip">{{ $lagu }}</span>
    @endforeach
@endif

@if($progressReport->highlight)
    <h2>Highlight Pencapaian</h2>
    <div class="narrative">{{ $progressReport->highlight }}</div>
@endif

@if($progressReport->sessionNotes->isNotEmpty())
    <h2>Catatan Per Sesi</h2>
    @foreach($progressReport->sessionNotes as $note)
        <div class="session-date">{{ \Carbon\Carbon::parse($note->session_date)->locale('id')->isoFormat('D MMMM Y') }}</div>
        <div class="session-text narrative">{{ $note->notes }}</div>
    @endforeach
@endif

@if($progressReport->summary_notes)
    <h2>Catatan</h2>
    <div class="narrative">{{ $progressReport->summary_notes }}</div>
@endif

@if($progressReport->target_notes)
    <h2>Target Bulan Depan</h2>
    <div class="narrative">{{ $progressReport->target_notes }}</div>
@endif

<div class="footer">
    <div class="ttd-box">
        <div>Pengajar,</div>
        <div class="ttd-space"></div>
        <div class="ttd-line">{{ $progressReport->teacher->name }}</div>
        <div class="ttd-role">Pengajar {{ $progressReport->enrollment->package->instrument->name }}</div>
    </div>
</div>

</body>
</html>
