<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        /* Reset minimal */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 12pt;
            color: #222;
            background: #f3f4f6;
        }

        /* Container A4 */
        .page {
            background: #fff;
            margin: 20px auto;
            padding: 30px 40px;
            max-width: 210mm;
            min-height: 297mm;
        }

        h1 { font-size: 20pt; margin-bottom: 4px; }
        h2 { font-size: 14pt; margin-bottom: 8px; }
        h3 { font-size: 11pt; margin-bottom: 4px; color: #555; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .studio-name {
            font-size: 18pt;
            font-weight: bold;
            color: #1e40af;
        }
        .invoice-meta {
            text-align: right;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        .info-block { font-size: 11pt; }
        .info-block .label { color: #777; font-size: 9pt; text-transform: uppercase; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f1f5f9;
            font-size: 10pt;
            text-transform: uppercase;
            color: #475569;
        }
        td.text-right, th.text-right { text-align: right; }
        td.text-center, th.text-center { text-align: center; }

        .total-row {
            font-weight: bold;
            background: #f8fafc;
        }

        .summary {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
        }
        .summary table {
            width: 280px;
        }
        .summary td { padding: 4px 8px; border: none; }
        .summary .balance {
            font-size: 14pt;
            color: #dc2626;
            font-weight: bold;
            border-top: 2px solid #1e40af;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 10pt;
            font-weight: bold;
        }
        .status-UNPAID  { background: #fee2e2; color: #b91c1c; }
        .status-PARTIAL { background: #fef3c7; color: #b45309; }
        .status-PAID    { background: #dcfce7; color: #166534; }
        .status-VOID    { background: #f3f4f6; color: #6b7280; }

        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-size: 9pt;
            color: #666;
            display: flex;
            justify-content: space-between;
        }

        .toolbar {
            position: sticky;
            top: 0;
            background: #fff;
            padding: 10px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 0;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .toolbar button, .toolbar a {
            padding: 6px 14px;
            background: #1e40af;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 11pt;
        }
        .toolbar a.secondary { background: #6b7280; }

        /* Style khusus saat print */
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; padding: 15mm; box-shadow: none; }
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
            <div class="studio-name">MUSIK KITA</div>
            <div style="font-size: 10pt; color: #555;">
                Studio Musik & Sekolah Musik<br>
                — alamat dan kontak studio diisi di pengaturan —
            </div>
        </div>
        <div class="invoice-meta">
            <h1>INVOICE</h1>
            <div style="font-family: monospace; font-size: 11pt;">{{ $invoice->invoice_number }}</div>
            <div style="margin-top: 8px;">
                <span class="status-pill status-{{ $invoice->status }}">{{ $invoice->status }}</span>
            </div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-block">
            <div class="label">Tagihan Untuk</div>
            <strong>{{ $invoice->student->full_name }}</strong>
            <div style="font-family: monospace; font-size: 10pt; color: #777;">
                {{ $invoice->student->student_code }}
            </div>
            @if($invoice->student->phone)
                <div style="font-size: 10pt;">{{ $invoice->student->phone }}</div>
            @endif
            @if($invoice->student->parent_name)
                <div style="font-size: 10pt; color: #777;">
                    Orang tua: {{ $invoice->student->parent_name }}
                </div>
            @endif
        </div>
        <div class="info-block" style="text-align: right;">
            <div><span class="label">Tanggal Terbit:</span> {{ $invoice->issued_at->format('d M Y') }}</div>
            <div><span class="label">Jatuh Tempo:</span> {{ $invoice->due_date->format('d M Y') }}</div>
            <div><span class="label">Periode:</span>
                {{ \Carbon\Carbon::create($invoice->year, $invoice->month, 1)->format('F Y') }}
            </div>
            @if($invoice->description)
                <div style="margin-top: 4px; font-size: 10pt;">{{ $invoice->description }}</div>
            @endif
        </div>
    </div>

    <h3>Rincian Tagihan</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Kode</th>
                <th>Deskripsi</th>
                <th class="text-right" style="width: 130px;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td style="font-family: monospace;">{{ $item->item_code }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr>
                <td>Total Tagihan</td>
                <td class="text-right">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Sudah Dibayar</td>
                <td class="text-right" style="color: #16a34a;">
                    Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}
                </td>
            </tr>
            <tr class="balance">
                <td>SALDO</td>
                <td class="text-right">Rp {{ number_format($invoice->balance, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    @if($invoice->validPayments->isNotEmpty())
        <h3 style="margin-top: 24px;">Riwayat Pembayaran</h3>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Kuitansi</th>
                    <th>Metode</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->validPayments as $p)
                    <tr>
                        <td>{{ $p->payment_date->format('d M Y') }}</td>
                        <td style="font-family: monospace;">{{ $p->receipt_number }}</td>
                        <td>{{ $p->method }}</td>
                        <td class="text-right">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <div>
            Pembayaran:
            <strong>CASH</strong> di studio /
            <strong>TRANSFER</strong> ke rekening yang tertera (hubungi admin).
        </div>
        <div>Dicetak {{ now()->format('d M Y H:i') }}</div>
    </div>

</div>

</body>
</html>
