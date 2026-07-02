<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Keuangan {{ $monthName }} — Musik KITA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,100..900;1,100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <style>
        /* ======================================================
         * RESET & BASE
         * ====================================================== */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 11px;
            color: #3A2015;
            background: #F5F9F7;
            line-height: 1.5;
        }

        /* ======================================================
         * WRAPPER — kartu putih di browser, transparan saat cetak
         * ====================================================== */
        .page {
            max-width: 720px;
            margin: 20px auto 40px;
            padding: 32px 36px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(74, 31, 10, 0.08);
            border: 1px solid rgba(93, 184, 144, 0.15);
        }

        /* ======================================================
         * HEADER DOKUMEN (tampil di setiap halaman cetak)
         * ====================================================== */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2.5px solid #4A1F0A;
            padding-bottom: 14px;
            margin-bottom: 6px;
        }
        .doc-header img {
            height: 44px;
            max-width: 180px;
            object-fit: contain;
            object-position: left;
            display: block;
        }
        .doc-header-right { text-align: right; }
        .doc-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 15px;
            font-weight: 700;
            color: #4A1F0A;
        }
        .doc-subtitle { font-size: 10px; color: #7A3818; margin-top: 3px; }
        .doc-printed  { font-size: 9px; color: #aaa; margin-top: 2px; }

        /* ======================================================
         * SECTION HEADING
         * ====================================================== */
        .section-heading {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 12px;
            font-weight: 700;
            color: #4A1F0A;
            border-bottom: 1.5px solid rgba(93, 184, 144, 0.25);
            padding-bottom: 5px;
            margin: 20px 0 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-heading:first-of-type { margin-top: 16px; }

        /* ======================================================
         * TABEL UMUM
         * ====================================================== */
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 4px; }
        th {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7A3818;
            border-bottom: 1.5px solid rgba(93, 184, 144, 0.3);
            padding: 5px 8px;
            font-weight: 600;
            text-align: left;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid rgba(93, 184, 144, 0.07);
            color: #3A2015;
            vertical-align: top;
        }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .font-mono   { font-family: monospace; font-size: 11px; }
        .font-bold   { font-weight: 700; }
        .text-muted  { color: #7A3818; }
        .text-dim    { color: #aaa; font-size: 10px; }

        /* Baris total / subtotal */
        .row-total td {
            border-top: 2px solid #4A1F0A;
            border-bottom: none;
            font-weight: 700;
            color: #4A1F0A;
            font-size: 11.5px;
            background: rgba(93, 184, 144, 0.04);
            padding-top: 8px;
        }
        .row-subtotal td {
            border-top: 1.5px solid rgba(93, 184, 144, 0.3);
            border-bottom: none;
            font-weight: 600;
            color: #4A1F0A;
            background: rgba(93, 184, 144, 0.03);
        }

        /* ======================================================
         * P&L SUMMARY ROWS
         * ====================================================== */
        .pl-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 5px 0;
            border-bottom: 1px solid rgba(93, 184, 144, 0.07);
            font-size: 11px;
        }
        .pl-row .label  { color: #3A2015; }
        .pl-row .amount { font-family: monospace; font-size: 11px; }
        .pl-row.indent .label  { padding-left: 16px; color: #7A3818; font-size: 10.5px; }
        .pl-row.indent .amount { font-size: 10.5px; color: #3A2015; }
        .pl-row.subtotal {
            border-top: 1.5px solid rgba(93,184,144,0.3);
            border-bottom: none;
            font-weight: 600;
            margin-top: 2px;
        }
        .pl-row.grand-total {
            border-top: 4px double #4A1F0A;
            border-bottom: 4px double #4A1F0A;
            font-weight: 700;
            font-size: 13px;
            margin-top: 4px;
            padding: 8px 0;
        }
        .pl-row.grand-total .label { color: #4A1F0A; }

        .color-laba  { color: #166534; }
        .color-rugi  { color: #b91c1c; }
        .color-beban { color: #b91c1c; }

        /* ======================================================
         * METODE PENERIMAAN
         * ====================================================== */
        .method-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 10px 0 4px;
        }
        .method-box {
            border: 1px solid rgba(93, 184, 144, 0.2);
            border-radius: 4px;
            padding: 8px 10px;
            background: rgba(93, 184, 144, 0.04);
            text-align: center;
        }
        .method-box .method-label  { font-size: 9px; text-transform: uppercase; color: #7A3818; letter-spacing: 0.5px; }
        .method-box .method-amount { font-family: monospace; font-size: 11px; font-weight: 600; color: #3A2015; margin-top: 2px; }

        /* ======================================================
         * BADGE STATUS
         * ====================================================== */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .badge-paid  { background: rgba(52,211,153,0.12); color: #065f46; border: 1px solid rgba(16,185,129,0.25); }
        .badge-draft { background: rgba(107,114,128,0.12); color: #374151; border: 1px solid rgba(107,114,128,0.25); }
        .badge-calc  { background: rgba(59,130,246,0.12); color: #1e40af; border: 1px solid rgba(59,130,246,0.25); }

        /* ======================================================
         * CATATAN KAKI
         * ====================================================== */
        .footnote {
            font-size: 9.5px;
            color: #7A3818;
            font-style: italic;
            margin-top: 8px;
            padding-left: 8px;
            border-left: 2px solid rgba(93, 184, 144, 0.3);
        }

        /* ======================================================
         * TOOLBAR (browser only — hilang saat cetak)
         * ====================================================== */
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
            font-family: 'Hanken Grotesk', sans-serif;
            transition: background 0.2s;
        }
        .toolbar button:hover { background: #7A3818; }
        .toolbar a.back { background: #6b7280; color: #fff; font-weight: 500; }
        .toolbar a.back:hover { background: #4b5563; }

        /* ======================================================
         * PAGE BREAK
         * ====================================================== */
        .page-break { page-break-before: always; }

        /* ======================================================
         * PRINT OVERRIDES
         * ====================================================== */
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none !important; }
            .page {
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
                border-radius: 0;
                background: transparent;
            }
        }
    </style>
</head>
<body>

{{-- ============================================================
     TOOLBAR — browser only
     ============================================================ --}}
<div class="toolbar">
    <button type="button" onclick="window.print()">🖨 Cetak / Simpan PDF</button>
    <a href="{{ route('reports.finance', ['year' => $year, 'month' => $month]) }}" class="back">← Kembali</a>
</div>

<div class="page">

    {{-- ===========================================================
         HALAMAN 1 — LAPORAN LABA RUGI
         =========================================================== --}}

    <div class="doc-header">
        <div>
            <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}" alt="Musik KITA">
        </div>
        <div class="doc-header-right">
            <div class="doc-title">Laporan Keuangan Bulanan</div>
            <div class="doc-subtitle">Periode: {{ $monthName }}</div>
            <div class="doc-printed">Dicetak: {{ now()->format('d M Y H:i') }}</div>
        </div>
    </div>

    {{-- PENDAPATAN --}}
    <div class="section-heading">A. Pendapatan</div>

    @php
        $itemCodeLabel = [
            'SPP'     => 'SPP Bulanan',
            'REG'     => 'Registrasi Murid Baru',
            'CUTI'    => 'Biaya Cuti',
            'UJI'     => 'Ujian + Mini Concert',
            'MC'      => 'Mini Concert',
            'DENDA'   => 'Denda Keterlambatan',
            'KIDS_FP' => 'Final Project Kids Class',
            'DISKON'  => 'Diskon',
        ];
    @endphp

    @forelse($revenueByType as $rt)
    <div class="pl-row indent">
        <span class="label">
            {{ $itemCodeLabel[$rt->item_code] ?? $rt->item_code }}
            <span class="text-dim">({{ $rt->invoice_count }} invoice)</span>
        </span>
        <span class="amount">Rp {{ number_format($rt->total, 0, ',', '.') }}</span>
    </div>
    @empty
    <div class="pl-row indent">
        <span class="label text-muted">Tidak ada pendapatan bulan ini</span>
        <span class="amount">Rp 0</span>
    </div>
    @endforelse

    <div class="pl-row subtotal">
        <span class="label">Total Pendapatan</span>
        <span class="amount color-laba">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</span>
    </div>

    {{-- Breakdown metode penerimaan --}}
    <div class="method-grid">
        @foreach(['CASH' => 'Cash', 'TRANSFER' => 'Transfer', 'QRIS' => 'QRIS', 'DEBIT' => 'Debit'] as $key => $label)
        <div class="method-box">
            <div class="method-label">{{ $label }}</div>
            <div class="method-amount">Rp {{ number_format($revenueByMethod[$key] ?? 0, 0, ',', '.') }}</div>
        </div>
        @endforeach
    </div>

    {{-- BEBAN --}}
    <div class="section-heading">B. Beban</div>

    <div class="pl-row indent">
        <span class="label">Honor Guru (dibayar bulan ini)</span>
        <span class="amount color-beban">(Rp {{ number_format($honorPaid, 0, ',', '.') }})</span>
    </div>
    @if($honorPaid < $totalHonor)
    <div class="pl-row indent" style="padding-top:0; border-bottom:none;">
        <span class="label text-dim" style="padding-left:32px;">
            Total akrual: Rp {{ number_format($totalHonor, 0, ',', '.') }} — selisih belum dibayar
        </span>
        <span></span>
    </div>
    @endif

    <div class="pl-row indent">
        <span class="label">Gaji Staff Non-Guru (dibayar bulan ini)</span>
        <span class="amount color-beban">(Rp {{ number_format($staffPayrollPaid, 0, ',', '.') }})</span>
    </div>
    @if($staffPayrollPaid < $totalStaffPayroll)
    <div class="pl-row indent" style="padding-top:0; border-bottom:none;">
        <span class="label text-dim" style="padding-left:32px;">
            Total akrual: Rp {{ number_format($totalStaffPayroll, 0, ',', '.') }}
        </span>
        <span></span>
    </div>
    @endif

    <div class="pl-row indent">
        <span class="label">Pengeluaran Operasional (transfer)</span>
        <span class="amount color-beban">(Rp {{ number_format($totalPengeluaranOperasional, 0, ',', '.') }})</span>
    </div>
    <div class="pl-row indent">
        <span class="label">Isi Saldo Petty Cash</span>
        <span class="amount color-beban">(Rp {{ number_format($totalPettyCashTopup, 0, ',', '.') }})</span>
    </div>

    @php $totalBeban = $honorPaid + $staffPayrollPaid + $totalPengeluaranOperasional + $totalPettyCashTopup; @endphp
    <div class="pl-row subtotal">
        <span class="label">Total Beban</span>
        <span class="amount color-beban">(Rp {{ number_format($totalBeban, 0, ',', '.') }})</span>
    </div>

    {{-- LABA / RUGI BERSIH --}}
    <div class="pl-row grand-total">
        <span class="label">Laba / Rugi Bersih</span>
        <span class="amount {{ $labaBersih >= 0 ? 'color-laba' : 'color-rugi' }}">
            Rp {{ number_format($labaBersih, 0, ',', '.') }}
        </span>
    </div>

    <div class="footnote">
        Catatan: Honor guru dan gaji staff hanya dihitung sebagai beban jika sudah berstatus PAID.
        Isi petty cash = top-up bulan ini; pengeluaran petty sudah termasuk di dalam saldo petty cash.
    </div>


    {{-- ===========================================================
         HALAMAN 2 — RINCIAN HONOR GURU
         =========================================================== --}}
    <div class="page-break"></div>

    <div class="doc-header">
        <div>
            <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}" alt="Musik KITA">
        </div>
        <div class="doc-header-right">
            <div class="doc-title">Rincian Honor Guru</div>
            <div class="doc-subtitle">Periode: {{ $monthName }}</div>
        </div>
    </div>

    @if($honorSlips->isEmpty())
        <div style="text-align:center; padding:32px 0; color:#7A3818; font-style:italic;">
            Belum ada slip honor guru untuk bulan ini.
        </div>
    @else
        <table style="margin-top:16px;">
            <thead>
                <tr>
                    <th style="width:24px;">No</th>
                    <th>Nama Guru</th>
                    <th class="text-right">Honor Pokok</th>
                    <th class="text-right">Transport</th>
                    <th class="text-right">Lain-lain</th>
                    <th class="text-right">Total</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($honorSlips as $i => $slip)
                <tr>
                    <td class="text-dim">{{ $i + 1 }}</td>
                    <td class="font-bold">{{ $slip->teacher->name }}</td>
                    <td class="text-right font-mono">{{ number_format($slip->base_honor, 0, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($slip->transport_honor, 0, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($slip->other_honor ?? 0, 0, ',', '.') }}</td>
                    <td class="text-right font-mono font-bold">{{ number_format($slip->total_honor, 0, ',', '.') }}</td>
                    <td class="text-center">
                        @if($slip->status === 'PAID')
                            <span class="badge badge-paid">Dibayar</span>
                        @elseif($slip->status === 'CALCULATED')
                            <span class="badge badge-calc">Terhitung</span>
                        @else
                            <span class="badge badge-draft">Draft</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <td colspan="2" class="font-bold">TOTAL HONOR GURU</td>
                    <td class="text-right font-mono">{{ number_format($honorSlips->sum('base_honor'), 0, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($honorSlips->sum('transport_honor'), 0, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($honorSlips->sum('other_honor'), 0, ',', '.') }}</td>
                    <td class="text-right font-mono">Rp {{ number_format($totalHonor, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
                @if($honorPaid < $totalHonor)
                <tr>
                    <td colspan="7" style="color:#b45309; font-size:9.5px; padding-top:6px; border-bottom:none;">
                        ⚠ Sudah dibayar (PAID): Rp {{ number_format($honorPaid, 0, ',', '.') }} —
                        belum dibayar: Rp {{ number_format($totalHonor - $honorPaid, 0, ',', '.') }}
                    </td>
                </tr>
                @endif
            </tfoot>
        </table>
        <div class="footnote">
            Hanya honor berstatus PAID yang masuk perhitungan P&L.
            Honor event sudah termasuk dalam komponen slip masing-masing guru.
        </div>
    @endif


    {{-- ===========================================================
         HALAMAN 3 — GAJI STAFF & PENGELUARAN OPERASIONAL
         =========================================================== --}}
    <div class="page-break"></div>

    <div class="doc-header">
        <div>
            <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}" alt="Musik KITA">
        </div>
        <div class="doc-header-right">
            <div class="doc-title">Gaji Staff & Pengeluaran Operasional</div>
            <div class="doc-subtitle">Periode: {{ $monthName }}</div>
        </div>
    </div>

    {{-- Gaji Staff --}}
    <div class="section-heading">Gaji Staff Non-Guru</div>

    @if($staffPayrollSlips->isEmpty())
        <div style="color:#7A3818; font-style:italic; font-size:10.5px; margin-bottom:16px;">
            Tidak ada slip gaji staff bulan ini.
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:24px;">No</th>
                    <th>Nama</th>
                    <th>Posisi</th>
                    <th class="text-right">Gaji Pokok</th>
                    <th class="text-right">Tunjangan</th>
                    <th class="text-right">Potongan</th>
                    <th class="text-right">Bersih</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staffPayrollSlips as $i => $slip)
                <tr>
                    <td class="text-dim">{{ $i + 1 }}</td>
                    <td class="font-bold">{{ $slip->employee->full_name }}</td>
                    <td class="text-muted" style="font-size:10px;">{{ $slip->employee->position }}</td>
                    <td class="text-right font-mono">{{ number_format($slip->base_salary, 0, ',', '.') }}</td>
                    <td class="text-right font-mono" style="color:#166534;">{{ number_format($slip->total_allowances, 0, ',', '.') }}</td>
                    <td class="text-right font-mono" style="color:#b91c1c;">({{ number_format($slip->total_deductions, 0, ',', '.') }})</td>
                    <td class="text-right font-mono font-bold">{{ number_format($slip->net_salary, 0, ',', '.') }}</td>
                    <td class="text-center">
                        @if($slip->status === 'PAID')
                            <span class="badge badge-paid">Dibayar</span>
                        @elseif($slip->status === 'CALCULATED')
                            <span class="badge badge-calc">Terhitung</span>
                        @else
                            <span class="badge badge-draft">Draft</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <td colspan="6" class="font-bold">TOTAL GAJI STAFF</td>
                    <td class="text-right font-mono">Rp {{ number_format($totalStaffPayroll, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div class="footnote" style="margin-bottom:20px;">
            Sudah dibayar: Rp {{ number_format($staffPayrollPaid, 0, ',', '.') }}.
            Hanya status PAID yang masuk P&L.
        </div>
    @endif

    {{-- Pengeluaran Operasional --}}
    <div class="section-heading">Pengeluaran Operasional (via Transfer)</div>

    @if($expenseByCategory->isEmpty())
        <div style="color:#7A3818; font-style:italic; font-size:10.5px;">
            Tidak ada pengeluaran operasional bulan ini.
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:24px;">No</th>
                    <th>Kategori</th>
                    <th class="text-center" style="width:70px;">Transaksi</th>
                    <th class="text-right">Total</th>
                    <th class="text-right" style="width:50px;">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenseByCategory as $i => $cat)
                <tr>
                    <td class="text-dim">{{ $i + 1 }}</td>
                    <td>
                        <span class="font-bold">{{ $cat->cat_name }}</span>
                        <span class="text-dim" style="margin-left:4px;">{{ $cat->cat_code }}</span>
                    </td>
                    <td class="text-center text-muted">{{ $cat->cnt }}</td>
                    <td class="text-right font-mono">Rp {{ number_format($cat->total, 0, ',', '.') }}</td>
                    <td class="text-right text-dim">
                        {{ $totalPengeluaranOperasional > 0
                            ? number_format($cat->total / $totalPengeluaranOperasional * 100, 1)
                            : 0 }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <td colspan="3" class="font-bold">TOTAL PENGELUARAN OPERASIONAL</td>
                    <td class="text-right font-mono">Rp {{ number_format($totalPengeluaranOperasional, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div class="footnote">
            Tidak termasuk pengeluaran via petty cash dan gaji staff (dihitung terpisah).
        </div>
    @endif


    {{-- ===========================================================
         HALAMAN 4 — PETTY CASH
         =========================================================== --}}
    <div class="page-break"></div>

    <div class="doc-header">
        <div>
            <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}" alt="Musik KITA">
        </div>
        <div class="doc-header-right">
            <div class="doc-title">Petty Cash</div>
            <div class="doc-subtitle">Periode: {{ $monthName }}</div>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="section-heading">Ringkasan Petty Cash</div>

    <div class="pl-row indent">
        <span class="label">Top-up bulan ini</span>
        <span class="amount color-laba">Rp {{ number_format($totalPettyCashTopup, 0, ',', '.') }}</span>
    </div>
    <div class="pl-row indent">
        <span class="label">Pengeluaran bulan ini</span>
        <span class="amount color-beban">(Rp {{ number_format($totalPettyCashExpense, 0, ',', '.') }})</span>
    </div>
    <div class="pl-row subtotal">
        <span class="label">Saldo Akhir Petty Cash</span>
        <span class="amount {{ $saldoPettyCashAkhir >= 0 ? 'color-laba' : 'color-rugi' }}">
            Rp {{ number_format($saldoPettyCashAkhir, 0, ',', '.') }}
        </span>
    </div>

    {{-- Rincian transaksi --}}
    <div class="section-heading" style="margin-top:20px;">Rincian Pengeluaran Petty Cash</div>

    @if($pettyCashExpenses->isEmpty())
        <div style="color:#7A3818; font-style:italic; font-size:10.5px;">
            Tidak ada pengeluaran petty cash bulan ini.
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:70px;">Tanggal</th>
                    <th style="width:110px;">Kategori</th>
                    <th>Keterangan</th>
                    <th class="text-right" style="width:110px;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pettyCashExpenses as $pce)
                <tr>
                    <td class="text-muted" style="font-size:10px; white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($pce->expense_date)->format('d/m/Y') }}
                    </td>
                    <td style="font-size:10px;">{{ $pce->category->name ?? '—' }}</td>
                    <td>{{ $pce->description }}</td>
                    <td class="text-right font-mono">Rp {{ number_format($pce->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <td colspan="3" class="font-bold">TOTAL PENGELUARAN PETTY CASH</td>
                    <td class="text-right font-mono">Rp {{ number_format($totalPettyCashExpense, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
        <div class="footnote">
            Pengeluaran petty cash bersumber dari saldo kas kecil studio.
            Top-up saldo sudah dihitung sebagai beban di P&L.
        </div>
    @endif

</div>{{-- .page --}}

<script>
    // Auto-print saat halaman selesai dimuat
    window.onload = function () { window.print(); };
</script>
</body>
</html>
