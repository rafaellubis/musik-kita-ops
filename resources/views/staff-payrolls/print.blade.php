<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $staffPayroll->slip_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }
        .page { max-width: 680px; margin: 0 auto; padding: 32px 28px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        .studio-name { font-size: 20px; font-weight: bold; }
        .studio-sub { font-size: 11px; color: #555; margin-top: 2px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; background: #f9f9f9; padding: 12px; border-radius: 4px; margin-bottom: 16px; }
        .meta-item .label { font-size: 10px; color: #666; }
        .meta-item .value { font-size: 13px; font-weight: 500; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #666; border-bottom: 1px solid #ccc; padding: 4px 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .total-row td { border-top: 2px solid #111; font-weight: bold; }
        .deduction { color: #b91c1c; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="page">
    <div class="no-print" style="text-align:right; margin-bottom:16px;">
        <button onclick="window.print()" style="padding:8px 16px; cursor:pointer;">Cetak / Simpan PDF</button>
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
