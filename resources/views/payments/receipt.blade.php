<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $payment->receipt_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 12pt;
            color: #222;
            background: #f3f4f6;
        }

        .page {
            background: #fff;
            margin: 20px auto;
            padding: 30px 40px;
            max-width: 210mm;
            min-height: 148mm; /* setengah A4 cukup untuk kuitansi */
            position: relative;
        }

        h1 { font-size: 22pt; margin-bottom: 4px; }
        h2 { font-size: 14pt; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #166534;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .studio-name {
            font-size: 18pt;
            font-weight: bold;
            color: #166534;
        }
        .receipt-title {
            text-align: right;
        }
        .receipt-title h1 {
            color: #166534;
            letter-spacing: 2px;
        }

        .body-grid {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 8px 16px;
            margin-bottom: 24px;
            font-size: 12pt;
        }
        .label { color: #555; }
        .value { font-weight: 500; }

        .amount-box {
            background: #f0fdf4;
            border: 2px solid #166534;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 24px 0;
            text-align: center;
        }
        .amount-box .label { font-size: 10pt; color: #555; text-transform: uppercase; }
        .amount-box .amount {
            font-size: 24pt;
            font-weight: bold;
            color: #166534;
            margin-top: 4px;
        }
        .amount-box .terbilang {
            margin-top: 6px;
            font-style: italic;
            font-size: 11pt;
            color: #444;
        }

        .signature {
            display: flex;
            justify-content: flex-end;
            margin-top: 40px;
        }
        .signature-block {
            text-align: center;
            min-width: 220px;
        }
        .signature-block .city { margin-bottom: 60px; font-size: 11pt; }
        .signature-block .name {
            border-top: 1px solid #555;
            padding-top: 4px;
            font-weight: 500;
        }

        .void-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-20deg);
            font-size: 80pt;
            font-weight: bold;
            color: rgba(220, 38, 38, 0.3);
            border: 12px solid rgba(220, 38, 38, 0.3);
            padding: 10px 40px;
            border-radius: 12px;
            pointer-events: none;
        }

        .toolbar {
            position: sticky;
            top: 0;
            background: #fff;
            padding: 10px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .toolbar button, .toolbar a {
            padding: 6px 14px;
            background: #166534;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 11pt;
        }
        .toolbar a.secondary { background: #6b7280; }

        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }

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
    <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="secondary">← Kembali</a>
</div>

<div class="page">
    @if($payment->is_voided)
        <div class="void-stamp">VOID</div>
    @endif

    <div class="header">
        <div>
            <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}"
                 alt="Musik KITA"
                 style="height:52px; max-width:200px; object-fit:contain; object-position:left; display:block;">
            <div style="font-size: 10pt; color: #555; margin-top:4px;">
                Studio Musik & Sekolah Musik<br>
                — alamat dan kontak studio —
            </div>
        </div>
        <div class="receipt-title">
            <h1>KUITANSI</h1>
            <div style="font-family: monospace; font-size: 11pt;">{{ $payment->receipt_number }}</div>
        </div>
    </div>

    <div class="body-grid">
        <div class="label">Telah terima dari:</div>
        <div class="value">
            <strong>{{ $payment->invoice->student->full_name }}</strong>
            <span style="font-family: monospace; font-size: 10pt; color: #777; margin-left: 8px;">
                {{ $payment->invoice->student->student_code }}
            </span>
        </div>

        <div class="label">Untuk pembayaran:</div>
        <div class="value">
            {{ $payment->invoice->description ?? 'Tagihan murid' }}
            <div style="font-size: 10pt; color: #777; margin-top: 2px;">
                Invoice: {{ $payment->invoice->invoice_number }}
                @if($payment->invoice->items->isNotEmpty())
                    ({{ $payment->invoice->items->pluck('item_code')->implode(', ') }})
                @endif
            </div>
        </div>

        <div class="label">Metode:</div>
        <div class="value">{{ $payment->method }}</div>

        <div class="label">Tanggal Bayar:</div>
        <div class="value">{{ $payment->payment_date->format('l, d F Y') }}</div>

        @if($payment->notes)
            <div class="label">Catatan:</div>
            <div class="value">{{ $payment->notes }}</div>
        @endif
    </div>

    <div class="amount-box">
        <div class="label">Jumlah Pembayaran</div>
        <div class="amount">Rp {{ number_format($payment->amount, 0, ',', '.') }}</div>
    </div>

    @if($payment->is_voided)
        <div style="background: #fee2e2; border: 1px solid #b91c1c; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
            <strong style="color: #b91c1c;">PEMBAYARAN INI TELAH DI-VOID</strong>
            <div style="font-size: 10pt; margin-top: 4px;">
                Di-void pada {{ $payment->voided_at->format('d M Y H:i') }}
                oleh {{ $payment->voidedBy->name ?? '?' }}.
                @if($payment->voided_reason)
                    Alasan: {{ $payment->voided_reason }}
                @endif
            </div>
        </div>
    @endif

    <div class="signature">
        <div class="signature-block">
            <div class="city">Jakarta, {{ $payment->payment_date->format('d F Y') }}</div>
            <div class="name">
                {{ $payment->createdBy->name ?? 'Admin Studio' }}<br>
                <span style="font-size: 9pt; color: #777;">Penerima Pembayaran</span>
            </div>
        </div>
    </div>

    <div class="footer">
        Kuitansi ini sah dan diterbitkan elektronik.
        Dicetak {{ now()->format('d M Y H:i') }}.
    </div>
</div>

</body>
</html>
