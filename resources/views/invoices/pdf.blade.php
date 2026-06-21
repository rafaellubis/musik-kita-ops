<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 18mm 15mm;
        }
        /* Reset eksplisit untuk DomPDF tanpa wildcard * selector */
        html, body, div, p, table, tr, th, td, h1, h2, h3, img, span {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #3A2015;
            line-height: 1.4;
        }
        .header {
            border-bottom: 3px solid #4A1F0A;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 0; border: none; }
        .studio-name { font-size: 14pt; font-weight: bold; color: #4A1F0A; }
        .studio-sub { font-size: 8.5pt; color: #7A3818; margin-top: 4px; }
        .inv-title { font-size: 16pt; font-weight: bold; color: #4A1F0A; text-align: right; }
        .inv-number { font-family: monospace; font-size: 9.5pt; color: #7A3818; text-align: right; margin-top: 2px; }
        
        /* STATUS BADGE */
        .status {
            display: inline-block;
            padding: 2px 8px;
            font-size: 8pt;
            font-weight: bold;
            margin-top: 6px;
            text-align: right;
            border-radius: 3px;
        }
        .status-UNPAID  { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .status-PARTIAL { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .status-PAID    { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-VOID    { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

        .info-table { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .info-table td { border: none; padding: 2px 0; font-size: 9.5pt; vertical-align: top; }
        .label { font-size: 7.5pt; text-transform: uppercase; color: #7A3818; font-weight: bold; }
        
        .section-title {
            font-size: 8.5pt;
            font-weight: bold;
            color: #4A1F0A;
            text-transform: uppercase;
            margin: 14px 0 8px;
            border-bottom: 1.5px solid rgba(93, 184, 144, 0.3);
            padding-bottom: 4px;
        }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th {
            background: rgba(93, 184, 144, 0.06);
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #7A3818;
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1.5px solid rgba(93, 184, 144, 0.3);
            font-weight: bold;
        }
        table.items td {
            padding: 6px 8px;
            border-bottom: 1px solid rgba(93, 184, 144, 0.08);
            font-size: 9pt;
            color: #3A2015;
        }
        .right { text-align: right; }
        
        .summary { width: 220px; margin-left: auto; margin-top: 14px; border-collapse: collapse; }
        .summary td { border: none; padding: 4px 8px; font-size: 9pt; }
        .summary .total td {
            font-weight: bold;
            font-size: 10pt;
            color: #4A1F0A;
            border-top: 2px solid #4A1F0A;
            padding-top: 8px;
            background: rgba(93, 184, 144, 0.05);
        }
        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid rgba(93, 184, 144, 0.15);
            font-size: 7.5pt;
            color: #7A3818;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="header">
    <table class="header-table">
        <tr>
            <td width="55%">
                <div class="studio-name">Musik KITA</div>
                <div class="studio-sub">Les Musik · Toko Alat Musik</div>
                <div class="studio-sub">Ruko Serpong Garden 1 Ruko 2 No. 19, Tangerang</div>
                <div class="studio-sub">WA: {{ \App\Services\FonnteService::STUDIO_WA_DISPLAY }}</div>
            </td>
            <td width="45%">
                <div class="inv-title">INVOICE</div>
                <div class="inv-number">{{ $invoice->invoice_number }}</div>
                <div class="status status-{{ $invoice->status }}">{{ $invoice->status }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="info-table">
    <tr>
        <td width="50%">
            <div class="label">Murid</div>
            <strong>{{ $invoice->student->full_name }}</strong><br>
            <span style="font-family:monospace;font-size:8pt;">{{ $invoice->student->student_code }}</span>
        </td>
        <td width="50%" class="right">
            <div class="label">Terbit</div>
            {{ $invoice->issued_at->format('d M Y') }}<br>
            <div class="label" style="margin-top:4px;">Jatuh Tempo</div>
            {{ $invoice->due_date->format('d M Y') }}<br>
            <div class="label" style="margin-top:4px;">Periode</div>
            {{ \Carbon\Carbon::create($invoice->year, $invoice->month, 1)->locale('id')->translatedFormat('F Y') }}
        </td>
    </tr>
</table>

@if($invoice->description)
    <p style="font-size:8pt;color:#8B6040;margin-bottom:8px;">{{ $invoice->description }}</p>
@endif

<div class="section-title">Rincian Tagihan</div>

<table class="items">
    <thead>
        <tr>
            <th width="50">Kode</th>
            <th>Deskripsi</th>
            <th class="right" width="100">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $item)
            <tr>
                <td style="font-family:monospace;color:#9B5E00;">{{ $item->item_code }}</td>
                <td>{{ $item->description }}</td>
                <td class="right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
            </tr>
            @if($item->discountItem)
                <tr>
                    <td style="font-family:monospace;color:#b45309;">DISKON</td>
                    <td>↳ {{ $item->discountItem->discount_reason }}</td>
                    <td class="right" style="color:#b45309;">–Rp {{ number_format(abs($item->discountItem->amount), 0, ',', '.') }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>

<table class="summary">
    <tr>
        <td>Total Tagihan</td>
        <td class="right">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td>Sudah Dibayar</td>
        <td class="right" style="color:#15803d;">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</td>
    </tr>
    <tr class="total">
        <td>SISA TAGIHAN</td>
        <td class="right">Rp {{ number_format($invoice->balance, 0, ',', '.') }}</td>
    </tr>
</table>

<div class="footer">
    Pembayaran: CASH di studio / TRANSFER / QRIS / DEBIT — hubungi admin studio.<br>
    Invoice resmi elektronik, tidak memerlukan stempel. Dicetak {{ now()->format('d M Y H:i') }}.
</div>

</body>
</html>
