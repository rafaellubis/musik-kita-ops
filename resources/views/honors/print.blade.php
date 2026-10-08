<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $honor->slip_number }}</title>
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

        /* Header */
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
        .bank-info {
            margin-top: 6px;
            font-size: 9.5px;
            color: #7A3818;
            font-style: italic;
            line-height: 1.5;
            opacity: 0.9;
        }
        .slip-title { text-align: right; }
        .slip-title .label { font-size: 10px; color: #7A3818; text-transform: uppercase; letter-spacing: 0.5px; }
        .slip-title .number { font-size: 14px; font-weight: 700; font-family: monospace; color: #4A1F0A; margin-top: 2px; }

        /* Info guru */
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

        /* Section heading */
        h2 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 13px;
            font-weight: 700;
            color: #4A1F0A;
            border-bottom: 1.5px solid rgba(93, 184, 144, 0.22);
            padding-bottom: 6px;
            margin-bottom: 12px;
            margin-top: 8px;
        }

        /* Tabel umum */
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
        .font-mono { font-family: monospace; font-size: 11px; }
        .font-bold { font-weight: bold; }

        /* Baris Kids Class */
        .row-kids { background: rgba(93, 184, 144, 0.03); }
        .row-kids td:nth-child(2) { font-size: 10px; color: #7A3818; }

        /* Footer tabel per-murid */
        .subtotal-row td {
            border-top: 2px solid rgba(93, 184, 144, 0.3);
            border-bottom: none;
            font-weight: 700;
            color: #4A1F0A;
            background: rgba(93, 184, 144, 0.03);
        }

        /* Baris total komponen honor */
        .total-row td {
            border-top: 2px solid #4A1F0A;
            border-bottom: none;
            font-weight: 700;
            color: #4A1F0A;
            font-size: 12px;
            background: rgba(93, 184, 144, 0.05);
        }

        /* Catatan kaki Kids Class */
        .kids-note { font-size: 10px; color: #7A3818; font-style: italic;
                     margin-top: 4px; margin-bottom: 20px; padding-left: 8px; }

        /* Rincian honor event */
        .event-group { break-inside: avoid; margin-bottom: 18px; }
        .event-heading {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 0 8px 6px;
            color: #4A1F0A;
            font-size: 11px;
            font-weight: 700;
        }
        .event-date { color: #7A3818; font-weight: 600; white-space: nowrap; }
        .event-group table { margin-bottom: 0; }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-paid  { background: rgba(52, 211, 153, 0.15); color: #065f46; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-calc  { background: rgba(59, 130, 246, 0.15); color: #1e40af; border: 1px solid rgba(59, 130, 246, 0.3); }
        .status-draft { background: rgba(107, 114, 128, 0.15); color: #374151; border: 1px solid rgba(107, 114, 128, 0.3); }

        /* Tanda tangan */
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 36px; }
        .sign-box { text-align: center; }
        .sign-box .role { font-size: 10px; color: #7A3818; text-transform: uppercase; letter-spacing: 0.5px; }
        .sign-box .line { border-bottom: 1.5px solid #4A1F0A; height: 55px; margin: 8px 24px; }
        .sign-box .name { font-size: 11px; font-weight: 600; color: #3A2015; }

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

    @if($eventBreakdown->isNotEmpty())
        <h2>Rincian Event</h2>
        @foreach($eventBreakdown as $entries)
            @php($event = $entries->first()->event)
            <section class="event-group">
                <div class="event-heading">
                    <span>{{ $event->name }}</span>
                    <span class="event-date">{{ $event->event_date->locale('id')->translatedFormat('d F Y') }}</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Nama Murid</th>
                            <th>Instrumen</th>
                            <th class="text-right">Jumlah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                            <tr>
                                <td>{{ $entry->participant->student->full_name }}</td>
                                <td>{{ $entry->participant->snapshot_instrument_name }}</td>
                                <td class="text-right font-mono">
                                    {{ number_format($entry->amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    @endif

    {{-- Komponen honor --}}
    <h2>Komponen Honor</h2>
    <table>
        <tbody>
            <tr>
                <td>Honor Pokok</td>
                <td class="text-left" style="color:#888;">
                    {{ $studentBreakdown->sum('session_count') }} sesi
                </td>
                <td class="text-right font-mono">
                    {{ number_format($honor->base_honor, 0, ',', '.') }}
                </td>
            </tr>
            @if($honor->event_honor > 0)
                <tr>
                    <td>Honor Event (Otomatis)</td>
                    <td>{{ $honor->event_honor_note }}</td>
                    <td class="text-right font-mono">
                        {{ number_format($honor->event_honor, 0, ',', '.') }}
                    </td>
                </tr>
            @endif
            <tr>
                <td>Honor Transport</td>
                <td style="color:#888;"></td>
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
