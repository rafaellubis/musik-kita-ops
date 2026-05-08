<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $honor->slip_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }

        .page { max-width: 680px; margin: 0 auto; padding: 32px 28px; }

        .header { display: flex; justify-content: space-between; align-items: flex-start;
                  border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        .studio-name { font-size: 20px; font-weight: bold; }
        .studio-sub  { font-size: 11px; color: #555; margin-top: 2px; }
        .slip-title  { text-align: right; }
        .slip-title .label { font-size: 11px; color: #666; }
        .slip-title .number { font-size: 14px; font-weight: bold; font-family: monospace; }

        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px;
                     background: #f9f9f9; padding: 12px; border-radius: 4px; margin-bottom: 16px; }
        .meta-item .label { font-size: 10px; color: #666; }
        .meta-item .value { font-size: 13px; font-weight: 500; }

        h2 { font-size: 12px; font-weight: bold; border-bottom: 1px solid #ddd;
             padding-bottom: 4px; margin-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 12px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase;
             color: #666; border-bottom: 1px solid #ccc; padding: 4px 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: bold; }
        .total-row td { border-top: 2px solid #aaa; border-bottom: none; font-weight: bold; font-size: 13px; }

        .components-table td:last-child { text-align: right; }

        .total-box { background: #1e3a5f; color: #fff; border-radius: 6px;
                     padding: 14px 18px; margin-bottom: 20px; }
        .total-box .label { font-size: 10px; color: #bcd; }
        .total-box .amount { font-size: 22px; font-weight: bold; margin-top: 2px; }

        .bank-box { border: 1px solid #ccc; border-radius: 4px; padding: 10px 14px;
                    margin-bottom: 20px; }
        .bank-box .label { font-size: 10px; color: #777; }
        .bank-box .value { font-size: 14px; font-weight: bold; font-family: monospace; margin-top: 3px; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr;
                      gap: 20px; margin-top: 28px; }
        .sign-box { text-align: center; }
        .sign-box .role { font-size: 10px; color: #666; }
        .sign-box .line { border-bottom: 1px solid #111; height: 50px; margin: 6px 10px; }
        .sign-box .name { font-size: 11px; }

        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px;
                        font-size: 10px; font-weight: bold; }
        .status-paid  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .status-calc  { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .status-draft { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Tombol cetak (tidak tercetak) --}}
    <div class="no-print" style="text-align:right; margin-bottom:16px;">
        <button onclick="window.print()" style="padding:6px 16px; background:#1e3a5f; color:#fff; border:none; border-radius:4px; cursor:pointer;">
            🖨 Cetak / Simpan PDF
        </button>
        <a href="{{ route('honors.show', $honor) }}"
           style="margin-left:10px; font-size:12px; color:#555; text-decoration:none;">
            ← Kembali
        </a>
    </div>

    {{-- Header --}}
    <div class="header">
        <div>
            <div class="studio-name">Musik KITA</div>
            <div class="studio-sub">Slip Honor Guru</div>
        </div>
        <div class="slip-title">
            <div class="label">No. Slip</div>
            <div class="number">{{ $honor->slip_number }}</div>
            <div style="margin-top:4px;">
                @if($honor->status === 'PAID')
                    <span class="status-badge status-paid">DIBAYARKAN</span>
                @elseif($honor->status === 'CALCULATED')
                    <span class="status-badge status-calc">TERHITUNG</span>
                @else
                    <span class="status-badge status-draft">DRAFT</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Info guru --}}
    <div class="meta-grid">
        <div class="meta-item">
            <div class="label">Nama Guru</div>
            <div class="value">{{ $honor->teacher->name }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Periode</div>
            <div class="value">{{ $monthName }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Instrumen</div>
            <div class="value">
                {{ $honor->teacher->instruments->pluck('name')->implode(', ') ?: '—' }}
            </div>
        </div>
        <div class="meta-item">
            <div class="label">Tanggal Cetak</div>
            <div class="value">{{ now()->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Komponen honor --}}
    <h2>Rincian Komponen Honor</h2>
    <table class="components-table">
        <thead>
            <tr>
                <th>Komponen</th>
                <th>Keterangan</th>
                <th style="text-align:right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Honor Pokok</td>
                <td>
                    Otomatis dari {{ array_sum(array_column($breakdown->toArray(), 'count')) }} sesi absensi
                </td>
                <td class="text-right">{{ number_format($honor->base_honor, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Honor Transport</td>
                <td>Input manual</td>
                <td class="text-right">{{ number_format($honor->transport_honor, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Honor Lain-lain</td>
                <td>{{ $honor->other_honor_note ?: 'Input manual' }}</td>
                <td class="text-right">{{ number_format($honor->other_honor, 0, ',', '.') }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2">TOTAL HONOR</td>
                <td class="text-right">{{ number_format($honor->total_honor, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Total besar --}}
    <div class="total-box">
        <div class="label">Total Honor yang Diterima</div>
        <div class="amount">Rp {{ number_format($honor->total_honor, 0, ',', '.') }}</div>
    </div>

    {{-- Info bank transfer --}}
    @if($honor->teacher->bank_name || $honor->teacher->bank_account)
        <div class="bank-box">
            <div class="label">Transfer ke</div>
            <div class="value">
                {{ $honor->teacher->bank_name }} — {{ $honor->teacher->bank_account }}
            </div>
        </div>
    @endif

    {{-- Rincian per kategori honor --}}
    @if($breakdown->isNotEmpty())
        <h2>Rincian Honor Pokok per Kategori</h2>
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Keterangan</th>
                    <th style="text-align:right">Sesi</th>
                    <th style="text-align:right">Subtotal (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $honorLabels = [
                        'H_REG'    => 'Sesi Reguler (Hadir)',
                        'H_TRIAL'  => 'Trial (Murid Hadir)',
                        'TRIAL_NS' => 'Trial No-show (Honor Nol)',
                        'H_VIDEO'  => 'Izin Video Pengganti',
                        'H_LIBUR'  => 'Libur Nasional',
                        'H_HANGUS' => 'Hangus / No-show Murid',
                        'H_PENG'   => 'Guru Pengganti',
                        'H_KIDS'   => 'Kids Class',
                        'H_UJIAN'  => 'Pengawas Ujian',
                    ];
                @endphp
                @foreach($breakdown as $code => $row)
                    <tr>
                        <td class="font-mono">{{ $code }}</td>
                        <td>{{ $honorLabels[$code] ?? $code }}</td>
                        <td class="text-right">{{ $row['count'] }}</td>
                        <td class="text-right">{{ number_format($row['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Tanda tangan --}}
    <div class="signatures">
        <div class="sign-box">
            <div class="role">Penerima Honor</div>
            <div class="line"></div>
            <div class="name">{{ $honor->teacher->name }}</div>
        </div>
        <div class="sign-box">
            <div class="role">Pimpinan Studio</div>
            <div class="line"></div>
            <div class="name">Musik KITA</div>
        </div>
    </div>

    @if($honor->status === 'PAID' && $honor->paid_at)
        <p style="font-size:10px; color:#888; text-align:right; margin-top:12px;">
            Dibayarkan: {{ $honor->paid_at->format('d M Y') }}
            @if($honor->paidBy) — {{ $honor->paidBy->name }} @endif
        </p>
    @endif

</div>
</body>
</html>
