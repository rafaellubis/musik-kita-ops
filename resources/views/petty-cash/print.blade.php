<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Petty Cash — {{ $monthName }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }
        .page { max-width: 900px; margin: 0 auto; padding: 32px 28px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        .header img { height: 48px; max-width: 190px; object-fit: contain; object-position: left; }
        .header-title { text-align: right; }
        .header-title h1 { font-size: 16px; font-weight: bold; }
        .header-title .period { font-size: 12px; color: #555; margin-top: 2px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
        .summary-item { background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #eee; }
        .summary-item .label { font-size: 10px; color: #666; text-transform: uppercase; }
        .summary-item .value { font-size: 14px; font-weight: bold; margin-top: 4px; }
        .summary-item .value.positive { color: #15803d; }
        .summary-item .value.negative { color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 16px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #666; border-bottom: 1px solid #ccc; padding: 5px 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; font-size: 10px; }
        .footer { margin-top: 24px; font-size: 10px; color: #666; text-align: right; border-top: 1px solid #ddd; padding-top: 8px; }
        @media print {
            .no-print { display: none !important; }
            nav, header { display: none !important; }
        }
        @media (max-width: 640px) {
            .summary-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="no-print" style="text-align:right; margin-bottom:16px;">
        <button onclick="window.print()" style="padding:8px 16px; cursor:pointer;">Cetak / Simpan PDF</button>
        <a href="{{ route('petty-cash.index', ['year' => $year, 'month' => $month]) }}"
           style="margin-left:12px; color:#4338ca;">← Kembali</a>
    </div>

    <div class="header">
        <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}" alt="Musik KITA">
        <div class="header-title">
            <h1>Laporan Petty Cash</h1>
            <div class="period">{{ $monthName }}</div>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-item">
            <div class="label">Saldo Awal</div>
            <div class="value">Rp {{ number_format($summary['opening_balance'], 0, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Total Isi Saldo</div>
            <div class="value positive">+ Rp {{ number_format($summary['total_topup'], 0, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Total Pengeluaran</div>
            <div class="value negative">− Rp {{ number_format($summary['total_expense'], 0, ',', '.') }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Saldo Akhir</div>
            <div class="value">Rp {{ number_format($summary['closing_balance'], 0, ',', '.') }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>No. Referensi</th>
                <th>Tipe</th>
                <th>Keterangan</th>
                <th>Kategori</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
                <th class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mutations as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</td>
                    <td class="font-mono">{{ $row->number }}</td>
                    <td>{{ $row->type === 'topup' ? 'Isi Saldo' : 'Pengeluaran' }}</td>
                    <td>{{ $row->description }}</td>
                    <td>
                        @if($row->type === 'expense' && isset($row->model->category))
                            {{ $row->model->category->name }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        @if($row->debit > 0)
                            Rp {{ number_format($row->debit, 0, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        @if($row->credit > 0)
                            Rp {{ number_format($row->credit, 0, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right font-mono">Rp {{ number_format($row->running_balance, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:#666; padding:16px;">
                        Belum ada mutasi petty cash pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dicetak: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
