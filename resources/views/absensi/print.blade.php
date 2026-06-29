<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Harian - {{ $tanggalObj->locale('id')->translatedFormat('l, d F Y') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 16px;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 0;
            font-size: 13px;
            font-weight: bold;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        .text-center {
            text-align: center;
        }
        .col-no { width: 4%; }
        .col-time { width: 12%; }
        .col-room { width: 10%; }
        .col-student { width: 22%; }
        .col-teacher { width: 18%; }
        .col-status { width: 12%; }
        .col-sign { width: 11%; }

        .status-badge {
            font-weight: bold;
        }
        .status-scheduled {
            letter-spacing: 1px;
            color: #888;
        }

        .footer {
            width: 100%;
            margin-top: 50px;
            font-size: 12px;
        }
        .footer-table {
            width: 100%;
            border: none;
        }
        .footer-table td {
            border: none;
            padding: 0;
        }
        .sign-area {
            text-align: right;
            padding-right: 40px !important;
        }
        .sign-space {
            height: 70px;
        }

        /* Control Panel yang disembunyikan saat cetak */
        .no-print-panel {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-secondary {
            background-color: #fff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .btn-secondary:hover {
            background-color: #f1f5f9;
        }
        .btn-primary {
            background-color: #1e293b;
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #0f172a;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            th {
                background-color: #fff !important;
            }
        }
    </style>
</head>
<body>

    <!-- Panel Kontrol untuk Tampilan Layar (Disembunyikan saat Print) -->
    <div class="no-print-panel no-print">
        <div>
            <a href="{{ route('absensi.index', ['date' => $tanggal]) }}" class="btn btn-secondary">
                &larr; Kembali ke Absensi Harian
            </a>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary">
                Cetak Halaman (Print)
            </button>
        </div>
    </div>

    <!-- Kop Cetakan -->
    <div class="header">
        <h1>Musik KITA</h1>
        <p>Daftar Absensi Harian - {{ $tanggalObj->locale('id')->translatedFormat('l, d F Y') }}</p>
    </div>

    <!-- Tabel Data Sesi -->
    <table>
        <thead>
            <tr>
                <th class="col-no text-center">No</th>
                <th class="col-time text-center">Waktu</th>
                <th class="col-room">Ruang</th>
                <th class="col-student">Murid & Paket</th>
                <th class="col-teacher">Guru</th>
                <th class="col-status text-center">Status</th>
                <th class="col-sign text-center">Ttd Guru</th>
                <th class="col-sign text-center">Ttd Murid</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sessions as $index => $session)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">
                        {{ \Carbon\Carbon::parse($session->start_time)->format('H:i') }} - 
                        {{ \Carbon\Carbon::parse($session->end_time)->format('H:i') }}
                    </td>
                    <td>{{ $session->room->name ?? $session->room->code ?? '-' }}</td>
                    <td>
                        <strong>{{ $session->student->full_name }}</strong>
                        <br>
                        <span style="font-size: 10px; color: #555;">
                            {{ $session->enrollment->package->class_type ?? '' }} 
                            @if(isset($session->enrollment->package->instrument))
                                ({{ $session->enrollment->package->instrument->name }})
                            @endif
                            @if(isset($session->enrollment->package->grade))
                                - {{ $session->enrollment->package->grade }}
                            @endif
                        </span>
                    </td>
                    <td>
                        {{ $session->substituteTeacher->name ?? $session->teacher->name ?? '-' }}
                        @if($session->substitute_teacher_id)
                            <br><span style="font-size: 9px; color: #666; font-style: italic;">(Guru Pengganti)</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($session->status === \App\Models\ClassSession::STATUS_SCHEDULED)
                            <span class="status-scheduled">......</span>
                        @else
                            <span class="status-badge">{{ $session->status }}</span>
                        @endif
                    </td>
                    <td></td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px;">
                        Tidak ada sesi kelas terjadwal pada hari ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Tanda Tangan Penanggung Jawab -->
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td></td>
                <td class="sign-area" style="width: 35%;">
                    <p>Dibuat oleh,</p>
                    <div class="sign-space"></div>
                    <p><strong>( ________________________ )</strong></p>
                    <p style="margin-top: 4px;">Admin Musik KITA</p>
                </td>
            </tr>
        </table>
    </div>

    <script>
        // Picu cetak browser otomatis saat halaman selesai dimuat
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
