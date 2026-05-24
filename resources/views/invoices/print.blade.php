<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 11pt;
            color: #222;
            background: #f3f4f6;
        }

        .page {
            background: #fff;
            margin: 20px auto;
            max-width: 210mm;
            min-height: 148mm;
            overflow: hidden;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #FBF5EC;
            border-bottom: 3px solid #D4A853;
            padding: 14px 28px 12px;
            gap: 12px;
        }
        .studio-logo {
            height: 44px;
            max-width: 160px;
            object-fit: contain;
            object-position: left;
            display: block;
        }
        .tagline-badge {
            display: inline-block;
            border: 1px solid #D4A853;
            color: #9B5E00;
            font-size: 7pt;
            padding: 1px 8px;
            border-radius: 3px;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }
        .contact-info {
            font-size: 7pt;
            color: #8B6040;
            margin-top: 5px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .contact-row {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .contact-icon { width: 9px; height: 9px; flex-shrink: 0; }
        .wa-icon     { width: 11px; height: 11px; flex-shrink: 0; }

        .invoice-meta { text-align: right; flex-shrink: 0; }
        .invoice-meta h1 {
            font-size: 16pt;
            font-weight: bold;
            color: #7A3B00;
            letter-spacing: 1.5px;
        }
        .inv-number {
            font-family: monospace;
            font-size: 8.5pt;
            color: #666;
            margin-top: 2px;
        }

        /* STATUS PILL — tetap semantik */
        .status-pill {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
            margin-top: 5px;
        }
        .status-UNPAID  { background: #fee2e2; color: #b91c1c; }
        .status-PARTIAL { background: #fef3c7; color: #b45309; }
        .status-PAID    { background: #dcfce7; color: #166534; }
        .status-VOID    { background: #f3f4f6; color: #6b7280; }

        /* BODY */
        .body { padding: 12px 28px; }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 16px;
        }
        .student-name {
            font-size: 10pt;
            font-weight: bold;
            color: #2C1A07;
        }
        .student-code {
            font-family: monospace;
            font-size: 7.5pt;
            color: #8B6040;
            margin-top: 2px;
        }
        .student-phone {
            font-size: 7.5pt;
            color: #6B4020;
            margin-top: 1px;
        }
        .dates-info { text-align: right; flex-shrink: 0; }
        .date-row { font-size: 8pt; color: #2C1A07; line-height: 1.7; }
        .date-label { font-size: 7pt; text-transform: uppercase; color: #9B5E00; }

        .section-sep {
            height: 1px;
            background: linear-gradient(to right, #E8C87A, transparent);
            margin-bottom: 8px;
        }
        .section-title {
            font-size: 7pt;
            font-weight: bold;
            color: #9B5E00;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }

        /* TABEL */
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #FBF0DC;
            font-size: 7pt;
            text-transform: uppercase;
            color: #9B5E00;
            padding: 5px 7px;
            text-align: left;
            border-bottom: 1.5px solid #E8C87A;
        }
        td {
            padding: 5px 7px;
            border-bottom: 1px solid #F5EBD0;
            font-size: 8.5pt;
            color: #2C1A07;
        }
        tr:last-child td { border-bottom: none; }
        td.right, th.right { text-align: right; }
        td.code { font-family: monospace; font-size: 8pt; color: #9B5E00; }

        /* SUMMARY */
        .summary-wrap { display: flex; justify-content: flex-end; margin-top: 10px; }
        .summary-table { width: 200px; border-collapse: collapse; }
        .summary-table td { padding: 3px 7px; font-size: 8.5pt; color: #2C1A07; border: none; }
        .summary-table td.right { text-align: right; }
        .summary-paid td { color: #15803d; }
        .summary-total td {
            font-weight: bold;
            font-size: 10pt;
            border-top: 2px solid #D4A853;
            padding-top: 5px;
        }

        /* FOOTER */
        .footer {
            padding: 8px 28px 10px;
            border-top: 1px solid #F0E0C0;
            background: #FBF5EC;
            font-size: 7pt;
            color: #9B5E00;
            margin-top: 16px;
        }
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .footer-notes {
            border-top: 1px dashed #E8C87A;
            padding-top: 5px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 6.5pt;
            color: #B08040;
        }

        /* TOOLBAR */
        .toolbar {
            position: sticky;
            top: 0;
            background: #fff;
            padding: 10px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
            justify-content: center;
            z-index: 10;
        }
        .toolbar button, .toolbar a {
            padding: 6px 14px;
            background: #D4A853;
            color: #1A1000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 11pt;
            font-weight: bold;
        }
        .toolbar a.secondary {
            background: #6b7280;
            color: #fff;
            font-weight: normal;
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">🖨 Cetak / Save PDF</button>
    <a href="{{ route('invoices.show', $invoice->id) }}" class="secondary">← Kembali</a>
</div>

<div class="page">

    <div class="header">
        <div>
            <img class="studio-logo"
                 src="{{ asset('images/logo-musikkita-light-mode.PNG') }}"
                 alt="Musik KITA">
            <div class="tagline-badge">Les Musik &nbsp;·&nbsp; Toko Alat Musik</div>
            <div class="contact-info">
                <div class="contact-row">
                    <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="#9B5E00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                        <circle cx="12" cy="9" r="2.5"/>
                    </svg>
                    Ruko Serpong Garden 1 Ruko 2 No. 19, Tangerang - Banten
                </div>
                <div class="contact-row">
                    <svg class="wa-icon" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="16" cy="16" r="14" fill="#25D366"/>
                        <path fill="#fff" d="M22.5 19.4c-.3-.2-1.9-.9-2.2-1s-.5-.2-.7.2c-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.2-1.3-.5-2.5-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-1-2.3c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.2.2 2 3 4.8 4.2.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.6-.7 1.9-1.3.3-.6.3-1.2.2-1.3z"/>
                    </svg>
                    0816-92-05-92
                </div>
            </div>
        </div>
        <div class="invoice-meta">
            <h1>INVOICE</h1>
            <div class="inv-number">{{ $invoice->invoice_number }}</div>
            <span class="status-pill status-{{ $invoice->status }}">{{ $invoice->status }}</span>
        </div>
    </div>

    <div class="body">

        <div class="info-row">
            <div>
                <div class="student-name">{{ $invoice->student->full_name }}</div>
                <div class="student-code">{{ $invoice->student->student_code }}</div>
                @if($invoice->student->phone)
                    <div class="student-phone">{{ $invoice->student->phone }}</div>
                @endif
            </div>
            <div class="dates-info">
                <div class="date-row">
                    <span class="date-label">Terbit</span>&ensp;{{ $invoice->issued_at->format('d M Y') }}
                </div>
                <div class="date-row">
                    <span class="date-label">Jatuh Tempo</span>&ensp;{{ $invoice->due_date->format('d M Y') }}
                </div>
                <div class="date-row">
                    <span class="date-label">Periode</span>&ensp;{{ \Carbon\Carbon::create($invoice->year, $invoice->month, 1)->format('F Y') }}
                </div>
                @if($invoice->description)
                    <div class="date-row" style="margin-top: 3px; font-size: 7.5pt; color: #8B6040;">
                        {{ $invoice->description }}
                    </div>
                @endif
            </div>
        </div>

        <div class="section-sep"></div>
        <div class="section-title">Rincian Tagihan</div>

        <table>
            <thead>
                <tr>
                    <th style="width: 52px;">Kode</th>
                    <th>Deskripsi</th>
                    <th class="right" style="width: 100px;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td class="code">{{ $item->item_code }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                    </tr>
                    @if($item->discountItem)
                        <tr style="color:#b45309;font-size:9.5pt;">
                            <td class="code" style="padding-left:12pt;">DISKON</td>
                            <td>↳ {{ $item->discountItem->discount_reason }}
                                @if($item->discountItem->discount_type === 'PERCENT')
                                    ({{ $item->discountItem->discount_value }}%)
                                @endif
                            </td>
                            <td class="right">–Rp {{ number_format(abs($item->discountItem->amount), 0, ',', '.') }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="summary-wrap">
            <table class="summary-table">
                <tr>
                    <td>Total Tagihan</td>
                    <td class="right">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                </tr>
                <tr class="summary-paid">
                    <td>Sudah Dibayar</td>
                    <td class="right">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</td>
                </tr>
                <tr class="summary-total">
                    <td>TOTAL</td>
                    <td class="right">Rp {{ number_format($invoice->balance, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

    </div>

    <div class="footer">
        <div class="footer-row">
            <span>Pembayaran: <strong>CASH</strong> di studio / <strong>TRANSFER</strong> (hubungi admin)</span>
            <span>Dicetak {{ now()->format('d M Y H:i') }}</span>
        </div>
        <div class="footer-notes">
            <span>&#9432; Invoice ini adalah invoice resmi yang dikirim secara elektronik, tidak membutuhkan stempel atau tanda tangan.</span>
            <span>&#9432; Invoice yang sudah terbit tidak bisa diubah atau didiskon.</span>
        </div>
    </div>

</div>

</body>
</html>
