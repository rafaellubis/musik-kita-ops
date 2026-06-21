<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        html, div, p, table, tr, th, td, h1, h2, h3, img, span {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #3A2015;
            line-height: 1.5;
        }

        .page {
            width: 100%;
        }

        .header {
            background-color: #ffffff;
            border-bottom: 3px solid #4A1F0A;
            padding: 20px 28px 18px;
        }

        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; border: none; padding: 0; }

        .brand-table {
            width: auto;
            border-collapse: collapse;
        }

        .brand-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .brand-tagline-cell {
            padding-top: 10px;
        }

        .studio-logo {
            height: 48px;
            max-width: 190px;
        }

        .studio-name {
            font-size: 18px;
            font-weight: bold;
            color: #4A1F0A;
        }

        .tagline-badge {
            display: inline-block;
            border: 1px solid #d4c4b0;
            color: #7A3818;
            font-size: 9px;
            padding: 2px 8px;
            text-transform: uppercase;
        }

        .contact-info {
            font-size: 10px;
            color: #7A3818;
            margin-top: 8px;
            line-height: 1.5;
        }

        .invoice-meta { text-align: right; }
        .inv-title {
            font-size: 24px;
            font-weight: bold;
            color: #4A1F0A;
            letter-spacing: 1px;
        }

        .inv-number {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 11px;
            color: #7A3818;
            margin-top: 4px;
            font-weight: bold;
        }

        .status-pill {
            display: inline-block;
            padding: 3px 10px;
            font-size: 9px;
            font-weight: bold;
            margin-top: 6px;
            text-transform: uppercase;
        }

        .status-UNPAID  { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .status-PARTIAL { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .status-PAID    { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-VOID    { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

        .body { padding: 24px 28px; }

        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { border: none; vertical-align: top; padding: 0; }

        .student-name {
            font-size: 14px;
            font-weight: bold;
            color: #4A1F0A;
        }

        .student-code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10px;
            color: #7A3818;
            margin-top: 3px;
            font-weight: bold;
        }

        .student-phone {
            font-size: 10px;
            color: #7A3818;
            margin-top: 2px;
        }

        .date-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #7A3818;
            font-weight: bold;
        }

        .date-row {
            font-size: 11px;
            color: #3A2015;
            line-height: 1.6;
        }

        .description-note {
            margin-top: 4px;
            font-size: 10px;
            color: #7A3818;
            font-style: italic;
        }

        .section-sep {
            height: 1.5px;
            background-color: #c8e6d6;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #4A1F0A;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        table.items { width: 100%; border-collapse: collapse; }

        table.items th {
            background-color: #f3faf6;
            font-size: 10px;
            text-transform: uppercase;
            color: #7A3818;
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1.5px solid #c8e6d6;
            font-weight: bold;
        }

        table.items td {
            padding: 8px 10px;
            border-bottom: 1px solid #e8f3ed;
            font-size: 11px;
            color: #3A2015;
        }

        table.items tr.item-discount td {
            color: #b45309;
            background-color: #fffbf5;
        }

        table.items .code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10px;
            color: #4A1F0A;
            font-weight: bold;
        }

        table.items tr.item-discount .code {
            padding-left: 16px;
            color: #b45309;
        }

        .right { text-align: right; }

        .summary-wrap { width: 100%; margin-top: 14px; }
        .summary-table {
            width: 220px;
            margin-left: auto;
            border-collapse: collapse;
        }

        .summary-table td {
            border: none;
            padding: 4px 10px;
            font-size: 11px;
            color: #3A2015;
        }

        .summary-paid td { color: #166534; font-weight: bold; }

        .summary-total td {
            font-weight: bold;
            font-size: 13px;
            color: #4A1F0A;
            border-top: 2px solid #4A1F0A;
            padding-top: 8px;
            background-color: #f3faf6;
        }

        .footer {
            padding: 14px 28px 16px;
            border-top: 1px solid #dceee4;
            background-color: #f8fcf9;
            font-size: 10px;
            color: #7A3818;
            line-height: 1.5;
        }

        .footer-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .footer-row td {
            border: none;
            padding: 0;
            font-size: 10px;
            vertical-align: top;
        }

        .footer-notes {
            border-top: 1px dashed #c8e6d6;
            padding-top: 6px;
            font-size: 9px;
            color: #C47A45;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="page">

    <div class="header">
        <table class="header-table">
            <tr>
                <td width="58%">
                    {{-- DomPDF: stack logo + tagline pakai baris tabel, bukan div/flex --}}
                    <table class="brand-table">
                        <tr>
                            <td>
                                @if(! empty($logoPath))
                                    <img class="studio-logo" src="{{ $logoPath }}" alt="Musik KITA">
                                @else
                                    <div class="studio-name">Musik KITA</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="brand-tagline-cell">
                                <span class="tagline-badge">Les Musik · Toko Alat Musik</span>
                            </td>
                        </tr>
                    </table>
                    <div class="contact-info">
                        Ruko Serpong Garden 1 Ruko 2 No. 19, Tangerang - Banten<br>
                        WA: {{ \App\Services\FonnteService::STUDIO_WA_DISPLAY }}
                    </div>
                </td>
                <td width="42%" class="invoice-meta">
                    <div class="inv-title">INVOICE</div>
                    <div class="inv-number">{{ $invoice->invoice_number }}</div>
                    <span class="status-pill status-{{ $invoice->status }}">{{ $invoice->status }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">

        <table class="info-table">
            <tr>
                <td width="50%">
                    <div class="student-name">{{ $invoice->student->full_name }}</div>
                    <div class="student-code">{{ $invoice->student->student_code }}</div>
                    @if($invoice->student->phone)
                        <div class="student-phone">{{ $invoice->student->phone }}</div>
                    @endif
                </td>
                <td width="50%" class="right">
                    <div class="date-row">
                        <span class="date-label">Terbit</span>&nbsp;{{ $invoice->issued_at->format('d M Y') }}
                    </div>
                    <div class="date-row">
                        <span class="date-label">Jatuh Tempo</span>&nbsp;{{ $invoice->due_date->format('d M Y') }}
                    </div>
                    <div class="date-row">
                        <span class="date-label">Periode</span>&nbsp;{{ \Carbon\Carbon::create($invoice->year, $invoice->month, 1)->locale('id')->translatedFormat('F Y') }}
                    </div>
                    @if($invoice->description)
                        <div class="description-note">{{ $invoice->description }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="section-sep"></div>
        <div class="section-title">Rincian Tagihan</div>

        <table class="items">
            <thead>
                <tr>
                    <th width="60">Kode</th>
                    <th>Deskripsi</th>
                    <th class="right" width="120">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @include('invoices.partials._item-rows', ['invoice' => $invoice])
            </tbody>
        </table>

        <div class="summary-wrap">
            <table class="summary-table">
                @include('invoices.partials._summary-rows', ['invoice' => $invoice])
            </table>
        </div>

    </div>

    <div class="footer">
        <table class="footer-row">
            <tr>
                <td>Pembayaran: <strong>CASH</strong> di studio / <strong>TRANSFER</strong> / <strong>QRIS</strong> / <strong>DEBIT</strong> — hubungi admin studio.</td>
                <td class="right">Dicetak {{ now()->format('d M Y H:i') }}</td>
            </tr>
        </table>
        <div class="footer-notes">
            Invoice resmi elektronik, tidak memerlukan stempel atau tanda tangan.<br>
            Invoice yang sudah terbit tidak bisa diubah atau didiskon.
        </div>
    </div>

</div>

</body>
</html>
