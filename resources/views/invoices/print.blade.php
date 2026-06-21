<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,100..900;1,100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Hanken Grotesk', 'DM Sans', sans-serif;
            font-size: 11px;
            color: #3A2015;
            background: #F5F9F7;
            line-height: 1.5;
        }

        .page {
            background: #fff;
            margin: 20px auto;
            max-width: 210mm;
            min-height: 148mm;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(74, 31, 10, 0.08);
            border: 1px solid rgba(93, 184, 144, 0.15);
            overflow: hidden;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #ffffff;
            border-bottom: 3px solid #4A1F0A;
            padding: 20px 28px 18px;
            gap: 12px;
        }
        .brand-stack {
            display: block;
        }
        .studio-logo {
            height: 48px;
            max-width: 190px;
            object-fit: contain;
            object-position: left;
            display: block;
        }
        .tagline-badge {
            display: block;
            border: 1px solid rgba(74, 31, 10, 0.18);
            color: #7A3818;
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 3px;
            margin-top: 6px;
            letter-spacing: 0.5px;
            font-weight: 500;
            text-transform: uppercase;
            width: fit-content;
        }
        .contact-info {
            font-size: 10px;
            color: #7A3818;
            margin-top: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .contact-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .contact-icon { width: 10px; height: 10px; flex-shrink: 0; stroke: #7A3818; }
        .wa-icon     { width: 11px; height: 11px; flex-shrink: 0; }

        .invoice-meta { text-align: right; flex-shrink: 0; }
        .invoice-meta h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 24px;
            font-weight: 700;
            color: #4A1F0A;
            letter-spacing: 1px;
        }
        .inv-number {
            font-family: monospace;
            font-size: 11px;
            color: #7A3818;
            margin-top: 4px;
            font-weight: 600;
        }

        /* STATUS PILL */
        .status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-UNPAID  { background: rgba(239, 68, 68, 0.15); color: #b91c1c; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-PARTIAL { background: rgba(245, 158, 11, 0.15); color: #b45309; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-PAID    { background: rgba(52, 211, 153, 0.15); color: #166534; border: 1px solid rgba(52, 211, 153, 0.3); }
        .status-VOID    { background: rgba(107, 114, 128, 0.15); color: #374151; border: 1px solid rgba(107, 114, 128, 0.3); }

        /* BODY */
        .body { padding: 24px 28px; }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 16px;
        }
        .student-name {
            font-size: 14px;
            font-weight: 700;
            color: #4A1F0A;
        }
        .student-code {
            font-family: monospace;
            font-size: 10px;
            color: #7A3818;
            margin-top: 3px;
            font-weight: 600;
        }
        .student-phone {
            font-size: 10px;
            color: #7A3818;
            margin-top: 2px;
        }
        .dates-info { text-align: right; flex-shrink: 0; }
        .date-row { font-size: 11px; color: #3A2015; line-height: 1.6; }
        .date-label { font-size: 9px; text-transform: uppercase; color: #7A3818; font-weight: 600; letter-spacing: 0.5px; }

        .section-sep {
            height: 1.5px;
            background: linear-gradient(to right, rgba(93, 184, 144, 0.3), transparent);
            margin-bottom: 12px;
        }
        .section-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 12px;
            font-weight: 700;
            color: #4A1F0A;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        /* TABEL */
        table { width: 100%; border-collapse: collapse; }
        th {
            background: rgba(93, 184, 144, 0.06);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7A3818;
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1.5px solid rgba(93, 184, 144, 0.3);
            font-weight: 600;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(93, 184, 144, 0.08);
            font-size: 11px;
            color: #3A2015;
        }
        tr:last-child td { border-bottom: none; }
        td.right, th.right { text-align: right; }
        td.code { font-family: monospace; font-size: 10px; color: #4A1F0A; font-weight: 600; }
        tr.item-discount td { color: #b45309; font-size: 11px; font-weight: 500; background: rgba(245, 158, 11, 0.03); }
        tr.item-discount .code { padding-left: 16px; color: #b45309; }

        /* SUMMARY */
        .summary-wrap { display: flex; justify-content: flex-end; margin-top: 14px; }
        .summary-table { width: 220px; border-collapse: collapse; }
        .summary-table td { padding: 4px 10px; font-size: 11px; color: #3A2015; border: none; }
        .summary-table td.right { text-align: right; }
        .summary-paid td { color: #166534; font-weight: 500; }
        .summary-total td {
            font-weight: 700;
            font-size: 13px;
            color: #4A1F0A;
            border-top: 2px solid #4A1F0A;
            padding-top: 8px;
            background: rgba(93, 184, 144, 0.05);
        }

        /* FOOTER */
        .footer {
            padding: 14px 28px 16px;
            border-top: 1px solid rgba(93, 184, 144, 0.15);
            background: rgba(93, 184, 144, 0.03);
            font-size: 10px;
            color: #7A3818;
            margin-top: 20px;
        }
        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .footer-notes {
            border-top: 1px dashed rgba(93, 184, 144, 0.22);
            padding-top: 6px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            font-size: 9px;
            color: #C47A45;
        }

        /* TOOLBAR */
        .toolbar {
            position: sticky;
            top: 0;
            background: #fff;
            padding: 10px 20px;
            box-shadow: 0 2px 10px rgba(74,31,10,0.06);
            display: flex;
            gap: 12px;
            justify-content: center;
            z-index: 10;
            border-bottom: 1px solid rgba(93,184,144,0.15);
        }
        .toolbar button, .toolbar a {
            padding: 8px 18px;
            background: #4A1F0A;
            color: #5DB890;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition: all 0.2s ease;
        }
        .toolbar button:hover, .toolbar a:hover {
            background: #7A3818;
        }
        .toolbar a.secondary {
            background: #6b7280;
            color: #fff;
            font-weight: 500;
        }
        .toolbar a.secondary:hover {
            background: #4b5563;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; color: #3A2015; }
            .toolbar { display: none; }
            .page {
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
                background: transparent;
            }
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
            <div class="brand-stack">
                <img class="studio-logo"
                     src="{{ asset('images/logo-musikkita-light-mode.PNG') }}"
                     alt="Musik KITA">
                <div class="tagline-badge">Les Musik · Toko Alat Musik</div>
            </div>
            <div class="contact-info">
                <div class="contact-row">
                    <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                    <div class="date-row" style="margin-top: 4px; font-size: 10px; color: #7A3818; font-style: italic;">
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
                    <th style="width: 60px;">Kode</th>
                    <th>Deskripsi</th>
                    <th class="right" style="width: 120px;">Jumlah</th>
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
