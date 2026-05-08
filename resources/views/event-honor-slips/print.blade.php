<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Honor Event — {{ $slip->teacher->name }}</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; color: #111; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 16px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; }
        .header p { margin: 2px 0; color: #555; }
        .slip-number { text-align: right; font-size: 11px; color: #555; margin-bottom: 12px; }
        table.info { width: 100%; margin-bottom: 16px; }
        table.info td { padding: 3px 6px; }
        table.info td:first-child { width: 35%; color: #555; }
        table.info td:last-child { font-weight: 600; }
        table.komponen { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.komponen th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; font-size: 11px; text-transform: uppercase; }
        table.komponen td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        table.komponen td.num { text-align: right; font-family: monospace; }
        table.komponen tr.total td { font-weight: 700; background: #f9fafb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-draft { background: #fef3c7; color: #92400e; }
        .footer { margin-top: 40px; display: flex; justify-content: space-between; }
        .footer .sign { width: 180px; text-align: center; }
        .footer .sign .line { border-bottom: 1px solid #000; margin-bottom: 4px; padding-bottom: 50px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom:16px;">
    <button onclick="window.print()"
            style="padding:6px 16px;background:#4f46e5;color:#fff;border:none;border-radius:4px;cursor:pointer;">
        Cetak / Simpan PDF
    </button>
    <a href="{{ route('events.show', $slip->event) }}"
       style="margin-left:12px;font-size:12px;color:#6b7280;">← Kembali</a>
</div>

<div class="header">
    <h1>STUDIO MUSIK KITA</h1>
    <p>Slip Honor Guru — Event</p>
</div>

<div class="slip-number">{{ $slip->slip_number }}</div>

<table class="info">
    <tr>
        <td>Event</td>
        <td>{{ $slip->event->name }}</td>
    </tr>
    <tr>
        <td>Tanggal Event</td>
        <td>{{ $slip->event->event_date->format('d M Y') }}</td>
    </tr>
    <tr>
        <td>Nama Guru</td>
        <td>{{ $slip->teacher->name }}</td>
    </tr>
    <tr>
        <td>Peran</td>
        <td>{{ $slip->role ?: '—' }}</td>
    </tr>
    <tr>
        <td>Status</td>
        <td>
            <span class="badge {{ $slip->isLocked() ? 'badge-paid' : 'badge-draft' }}">
                {{ $slip->status_label }}
            </span>
            @if($slip->paid_at)
                <span style="font-size:10px;color:#6b7280;margin-left:6px;">
                    ({{ $slip->paid_at->format('d M Y H:i') }})
                </span>
            @endif
        </td>
    </tr>
</table>

<table class="komponen">
    <thead>
        <tr>
            <th>Komponen Honor</th>
            <th style="text-align:right;">Jumlah (Rp)</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Honor Pokok Event (H_UJIAN)</td>
            <td class="num">{{ number_format($slip->base_honor, 0, ',', '.') }}</td>
            <td>Rp 250.000 flat / event</td>
        </tr>
        <tr>
            <td>Honor Transport</td>
            <td class="num">{{ number_format($slip->transport_honor, 0, ',', '.') }}</td>
            <td>—</td>
        </tr>
        <tr>
            <td>Honor Lain-lain</td>
            <td class="num">{{ number_format($slip->other_honor, 0, ',', '.') }}</td>
            <td>{{ $slip->other_honor_note ?: '—' }}</td>
        </tr>
        <tr class="total">
            <td>TOTAL</td>
            <td class="num">{{ number_format($slip->total_honor, 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="sign">
        <div class="line"></div>
        <div>Guru</div>
        <div style="font-weight:600;margin-top:2px;">{{ $slip->teacher->name }}</div>
    </div>

    <div class="sign">
        <div class="line"></div>
        <div>Pemilik Studio</div>
        <div style="font-weight:600;margin-top:2px;">Musik KITA</div>
    </div>
</div>

<div style="margin-top:24px;font-size:10px;color:#9ca3af;text-align:center;">
    Dicetak: {{ now()->format('d M Y H:i') }}
    @if($slip->paidBy) · Dibayarkan oleh: {{ $slip->paidBy->name }} @endif
</div>

</body>
</html>
