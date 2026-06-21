<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $staffPayroll->slip_number }}</title>
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
            max-width: 720px;
            margin: 20px auto;
            padding: 40px 36px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(74, 31, 10, 0.08);
            border: 1px solid rgba(93, 184, 144, 0.15);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2.5px solid #4A1F0A;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .studio-name {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 22px;
            font-weight: 700;
            color: #4A1F0A;
        }
        .studio-sub {
            font-size: 11px;
            color: #7A3818;
            margin-top: 2px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 24px;
            background: rgba(93, 184, 144, 0.06);
            padding: 14px 18px;
            border-radius: 6px;
            border: 1px solid rgba(93, 184, 144, 0.12);
            margin-bottom: 24px;
        }
        .meta-item .label { font-size: 10px; color: #7A3818; text-transform: uppercase; letter-spacing: 0.5px; }
        .meta-item .value { font-size: 12px; font-weight: 600; color: #3A2015; margin-top: 2px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; }
        th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7A3818;
            border-bottom: 1.5px solid rgba(93, 184, 144, 0.3);
            padding: 6px 8px;
            font-weight: 600;
        }
        td { padding: 7px 8px; border-bottom: 1px solid rgba(93, 184, 144, 0.08); color: #3A2015; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .total-row td {
            border-top: 2px solid #4A1F0A;
            border-bottom: none;
            font-weight: 700;
            color: #4A1F0A;
            font-size: 12px;
            background: rgba(93, 184, 144, 0.05);
        }
        .deduction { color: #b91c1c; font-weight: 500; }
        
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; color: #3A2015; }
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
<div class="page">
    <div class="no-print" style="text-align:right; margin-bottom:16px;">
        <button onclick="window.print()" style="padding:6px 16px; background:#4A1F0A; color:#5DB890; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
            🖨 Cetak / Simpan PDF
        </button>
        <a href="{{ route('staff-payrolls.show', $staffPayroll) }}"
           style="margin-left:10px; font-size:12px; color:#7A3818; text-decoration:none; font-weight:500;">
            ← Kembali
        </a>
    </div>

    <div class="header">
        <div>
            <div class="studio-name">Musik KITA</div>
            <div class="studio-sub">Studio Musik — Slip Gaji Karyawan</div>
            @if($staffPayroll->employee->bank_account)
            <div style="margin-top:8px; font-size:10px; color:#555;">
                {{ $staffPayroll->employee->bank_name }} · {{ $staffPayroll->employee->bank_account }}<br>
                a.n. {{ $staffPayroll->employee->bank_account_holder }}
            </div>
            @endif
        </div>
        <div style="text-align:right;">
            <div style="font-size:11px; color:#666;">No. Slip</div>
            <div style="font-size:14px; font-weight:bold; font-family:monospace;">{{ $staffPayroll->slip_number }}</div>
            <div style="font-size:11px; margin-top:4px;">Periode: {{ $monthName }}</div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-item"><div class="label">Nama</div><div class="value">{{ $staffPayroll->employee->full_name }}</div></div>
        <div class="meta-item"><div class="label">Posisi</div><div class="value">{{ $staffPayroll->employee->position }}</div></div>
        <div class="meta-item"><div class="label">Kode Karyawan</div><div class="value">{{ $staffPayroll->employee->employee_code }}</div></div>
        <div class="meta-item"><div class="label">Status</div><div class="value">{{ $staffPayroll->status_label }}</div></div>
    </div>

    <table>
        <thead>
            <tr><th>Komponen</th><th>Keterangan</th><th class="text-right">Nominal</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Gaji Pokok</td>
                <td>—</td>
                <td class="text-right">Rp {{ number_format($staffPayroll->base_salary, 0, ',', '.') }}</td>
            </tr>
            @foreach($staffPayroll->items->whereIn('item_type', ['ALLOWANCE', 'OVERTIME']) as $item)
            <tr>
                <td>{{ $item->item_code_label }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            @foreach($staffPayroll->items->where('item_type', 'DEDUCTION') as $item)
            <tr>
                <td>{{ $item->item_code_label }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-right deduction">(Rp {{ number_format($item->amount, 0, ',', '.') }})</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Gaji Bersih (Take Home Pay)</td>
                <td class="text-right">Rp {{ number_format($staffPayroll->net_salary, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if($staffPayroll->status === 'PAID')
    <p style="font-size:11px; color:#555;">
        Dibayarkan pada {{ $staffPayroll->paid_at?->format('d F Y') }}
        @if($staffPayroll->paidBy) oleh {{ $staffPayroll->paidBy->name }} @endif
    </p>
    @endif

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:40px;">
        <div style="text-align:center; font-size:10px;">
            <div>Disetujui,</div>
            <div style="border-bottom:1px solid #111; height:50px; margin:8px 20px;"></div>
            <div>Owner Musik KITA</div>
        </div>
        <div style="text-align:center; font-size:10px;">
            <div>Diterima,</div>
            <div style="border-bottom:1px solid #111; height:50px; margin:8px 20px;"></div>
            <div>{{ $staffPayroll->employee->full_name }}</div>
        </div>
    </div>
</div>
</body>
</html>
