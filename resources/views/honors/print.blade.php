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

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start;
                  border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        .studio-name { font-size: 20px; font-weight: bold; }
        .studio-sub  { font-size: 11px; color: #555; margin-top: 2px; }
        .bank-info   { margin-top: 5px; font-size: 9.5px; color: #555; font-style: italic; line-height: 1.6; }
        .slip-title  { text-align: right; }
        .slip-title .label  { font-size: 11px; color: #666; }
        .slip-title .number { font-size: 14px; font-weight: bold; font-family: monospace; }

        /* Info guru */
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px;
                     background: #f9f9f9; padding: 12px; border-radius: 4px; margin-bottom: 16px; }
        .meta-item .label { font-size: 10px; color: #666; }
        .meta-item .value { font-size: 13px; font-weight: 500; }

        /* Section heading */
        h2 { font-size: 12px; font-weight: bold; border-bottom: 1px solid #ddd;
             padding-bottom: 4px; margin-bottom: 8px; }

        /* Tabel umum */
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase;
             color: #666; border-bottom: 1px solid #ccc; padding: 4px 6px; font-weight: normal; }
        td { padding: 5px 6px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .font-mono  { font-family: monospace; }
        .font-bold  { font-weight: bold; }

        /* Baris Kids Class */
        .row-kids { background: #fffbf0; }
        .row-kids td:nth-child(2) { font-size: 10px; color: #888; }

        /* Footer tabel per-murid */
        .subtotal-row td { border-top: 2px solid #aaa; border-bottom: none; font-weight: bold; }

        /* Baris total komponen honor */
        .total-row td { border-top: 2px solid #111; border-bottom: none; font-weight: bold; }

        /* Catatan kaki Kids Class */
        .kids-note { font-size: 10px; color: #888; font-style: italic;
                     margin-top: -10px; margin-bottom: 16px; padding-left: 6px; }

        /* Status badge */
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px;
                        font-size: 10px; font-weight: bold; }
        .status-paid  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .status-calc  { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .status-draft { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

        /* Tanda tangan */
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 28px; }
        .sign-box { text-align: center; }
        .sign-box .role { font-size: 10px; color: #666; }
        .sign-box .line { border-bottom: 1px solid #111; height: 50px; margin: 6px 10px; }
        .sign-box .name { font-size: 11px; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Tombol cetak --}}
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
            <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}"
                 alt="Musik KITA"
                 style="height:48px; max-width:190px; object-fit:contain; object-position:left; display:block;">
            <div class="studio-sub">Slip Honor Guru</div>
            @if($honor->teacher->bank_name || $honor->teacher->bank_account)
                <div class="bank-info">
                    {{ $honor->teacher->bank_name }}
                    @if($honor->teacher->bank_account)
                        &nbsp;·&nbsp; {{ $honor->teacher->bank_account }}
                    @endif
                    @if($honor->teacher->bank_account_holder)
                        <br>a.n. {{ $honor->teacher->bank_account_holder }}
                    @endif
                </div>
            @endif
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

    {{-- Rincian sesi per murid --}}
    @if($studentBreakdown->isNotEmpty())
        <h2>Rincian Sesi per Murid</h2>
        <table>
            <thead>
                <tr>
                    <th>Nama Murid</th>
                    <th>Instrumen</th>
                    <th class="text-right">Sesi</th>
                    <th class="text-right">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($studentBreakdown as $row)
                    <tr class="{{ $row['is_kids'] ? 'row-kids' : '' }}">
                        <td>{{ $row['student_name'] }}</td>
                        <td>{{ $row['instrument'] }}</td>
                        <td class="text-right">{{ $row['session_count'] }}</td>
                        <td class="text-right font-mono">
                            {{ number_format($row['total_amount'], 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="subtotal-row">
                    <td colspan="2" class="font-bold">Subtotal Honor Pokok</td>
                    <td class="text-right font-bold">
                        {{ $studentBreakdown->sum('session_count') }} sesi
                    </td>
                    <td class="text-right font-mono font-bold">
                        {{ number_format($studentBreakdown->sum('total_amount'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>

        @if($hasKids)
            <p class="kids-note">
                * Kids Class: honor per murid = jumlah sesi × Rp 42.500
                (dihitung dari murid terdaftar, bukan kehadiran)
            </p>
        @endif
    @endif

    {{-- Komponen honor --}}
    <h2>Komponen Honor</h2>
    <table>
        <tbody>
            <tr>
                <td>Honor Pokok</td>
                <td class="text-right" style="color:#888;">
                    {{ $studentBreakdown->sum('session_count') }} sesi
                </td>
                <td class="text-right font-mono">
                    {{ number_format($honor->base_honor, 0, ',', '.') }}
                </td>
            </tr>
            @if($honor->event_honor > 0)
                <tr>
                    <td>Honor Event</td>
                    <td style="color:#888;">{{ $honor->event_honor_note ?: 'Input manual' }}</td>
                    <td class="text-right font-mono">
                        {{ number_format($honor->event_honor, 0, ',', '.') }}
                    </td>
                </tr>
            @endif
            <tr>
                <td>Honor Transport</td>
                <td style="color:#888;">Input manual</td>
                <td class="text-right font-mono">
                    {{ number_format($honor->transport_honor, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td>Honor Lain-lain</td>
                <td style="color:#888;">{{ $honor->other_honor_note ?: '—' }}</td>
                <td class="text-right font-mono">
                    {{ number_format($honor->other_honor, 0, ',', '.') }}
                </td>
            </tr>
            <tr class="total-row">
                <td colspan="2" class="font-bold">TOTAL HONOR YANG DITERIMA</td>
                <td class="text-right font-mono font-bold">
                    Rp {{ number_format($honor->total_honor, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

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
            <div class="name">Charly Nurjaya, S.MG</div>
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
