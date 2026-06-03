'use strict';

/**
 * generate_admin_manual_v2.cjs
 * Generator User Manual Admin Musik KITA — versi 2
 *
 * Perubahan dari v1:
 *   - Bab 5: Tambah Section 5.9 Status IZIN_PENDING
 *   - Bab 6: Tambah Section 6.7 Invoice Final Project Kids Class (KIDS_FP)
 *   - Semua bab lain tetap lengkap dari v1
 *
 * Jalankan: node generate_admin_manual_v2.cjs
 * Output  : C:\laragon\www\musik-kita-ops\User-Manual-Admin-Musik-KITA.docx
 */

const path = require('path');
const {
    Document,
    Packer,
    Paragraph,
    TextRun,
    HeadingLevel,
    AlignmentType,
    Table,
    TableRow,
    TableCell,
    WidthType,
    BorderStyle,
    ShadingType,
    PageBreak,
    Tab,
    LevelFormat,
    convertInchesToTwip,
    UnderlineType,
    NumberingConfig,
} = require('docx');
const fs = require('fs');

// ─────────────────────────────────────────────
// KONSTANTA WARNA
// ─────────────────────────────────────────────
const COLOR_HEADER_BG   = '2C5282';  // biru navy tua
const COLOR_HEADER_TEXT = 'FFFFFF';  // putih
const COLOR_ALT_ROW_BG  = 'EBF4FF';  // biru muda
const COLOR_NOTE_BG     = 'FFF8E1';  // kuning muda (catatan)
const COLOR_WARN_BG     = 'FFEBEE';  // merah muda (peringatan)
const COLOR_TITLE_BG    = 'E3F2FD';  // biru sangat muda (judul bab)

// ─────────────────────────────────────────────
// HELPER: PARAGRAF JUDUL BAB (h1)
// ─────────────────────────────────────────────
function h1(text) {
    return new Paragraph({
        heading: HeadingLevel.HEADING_1,
        spacing: { before: 320, after: 160 },
        children: [
            new TextRun({
                text,
                bold: true,
                size: 32,
                color: '1A365D',
            }),
        ],
    });
}

// ─────────────────────────────────────────────
// HELPER: PARAGRAF JUDUL SECTION (h2)
// ─────────────────────────────────────────────
function h2(text) {
    return new Paragraph({
        heading: HeadingLevel.HEADING_2,
        spacing: { before: 240, after: 120 },
        children: [
            new TextRun({
                text,
                bold: true,
                size: 26,
                color: '2C5282',
            }),
        ],
    });
}

// ─────────────────────────────────────────────
// HELPER: PARAGRAF JUDUL SUB-SECTION (h3)
// ─────────────────────────────────────────────
function h3(text) {
    return new Paragraph({
        heading: HeadingLevel.HEADING_3,
        spacing: { before: 200, after: 80 },
        children: [
            new TextRun({
                text,
                bold: true,
                size: 24,
                color: '2B6CB0',
            }),
        ],
    });
}

// ─────────────────────────────────────────────
// HELPER: PARAGRAF TEKS BIASA
// ─────────────────────────────────────────────
function para(text, opts = {}) {
    return new Paragraph({
        spacing: { before: 60, after: 80 },
        alignment: opts.center ? AlignmentType.CENTER : AlignmentType.LEFT,
        children: [
            new TextRun({
                text,
                size: 22,
                bold: opts.bold || false,
                italics: opts.italic || false,
                color: opts.color || '1A202C',
            }),
        ],
    });
}

// ─────────────────────────────────────────────
// HELPER: BULLET POINT (level 0 = •, level 1 = -)
// ─────────────────────────────────────────────
function bullet(text, level = 0, opts = {}) {
    const indent = level === 0
        ? { left: convertInchesToTwip(0.3), hanging: convertInchesToTwip(0.2) }
        : { left: convertInchesToTwip(0.6), hanging: convertInchesToTwip(0.2) };
    const prefix = level === 0 ? '• ' : '- ';
    return new Paragraph({
        spacing: { before: 40, after: 40 },
        indent,
        children: [
            new TextRun({
                text: prefix + text,
                size: 22,
                bold: opts.bold || false,
                italics: opts.italic || false,
                color: opts.color || '1A202C',
            }),
        ],
    });
}

// ─────────────────────────────────────────────
// HELPER: NUMBERED LIST ITEM
// ─────────────────────────────────────────────
function numbered(num, text, opts = {}) {
    return new Paragraph({
        spacing: { before: 40, after: 40 },
        indent: { left: convertInchesToTwip(0.4), hanging: convertInchesToTwip(0.25) },
        children: [
            new TextRun({
                text: `${num}. ${text}`,
                size: 22,
                bold: opts.bold || false,
                color: opts.color || '1A202C',
            }),
        ],
    });
}

// ─────────────────────────────────────────────
// HELPER: CATATAN (kotak kuning)
// ─────────────────────────────────────────────
function note(text) {
    return new Table({
        width: { size: 100, type: WidthType.PERCENTAGE },
        margins: { top: 80, bottom: 80, left: 100, right: 100 },
        rows: [
            new TableRow({
                children: [
                    new TableCell({
                        shading: { fill: 'FFF8E1', type: ShadingType.CLEAR, color: 'auto' },
                        margins: { top: 80, bottom: 80, left: 160, right: 160 },
                        borders: {
                            top:    { style: BorderStyle.SINGLE, size: 4, color: 'F9A825' },
                            bottom: { style: BorderStyle.SINGLE, size: 4, color: 'F9A825' },
                            left:   { style: BorderStyle.THICK,  size: 8, color: 'F9A825' },
                            right:  { style: BorderStyle.SINGLE, size: 4, color: 'F9A825' },
                        },
                        children: [
                            new Paragraph({
                                spacing: { before: 20, after: 20 },
                                children: [
                                    new TextRun({ text: '📌 Catatan: ', bold: true, size: 20, color: 'E65100' }),
                                    new TextRun({ text, size: 20, color: '3E2723' }),
                                ],
                            }),
                        ],
                    }),
                ],
            }),
        ],
    });
}

// ─────────────────────────────────────────────
// HELPER: PERINGATAN (kotak merah)
// ─────────────────────────────────────────────
function warning(text) {
    return new Table({
        width: { size: 100, type: WidthType.PERCENTAGE },
        margins: { top: 80, bottom: 80, left: 100, right: 100 },
        rows: [
            new TableRow({
                children: [
                    new TableCell({
                        shading: { fill: 'FFEBEE', type: ShadingType.CLEAR, color: 'auto' },
                        margins: { top: 80, bottom: 80, left: 160, right: 160 },
                        borders: {
                            top:    { style: BorderStyle.SINGLE, size: 4, color: 'C62828' },
                            bottom: { style: BorderStyle.SINGLE, size: 4, color: 'C62828' },
                            left:   { style: BorderStyle.THICK,  size: 8, color: 'C62828' },
                            right:  { style: BorderStyle.SINGLE, size: 4, color: 'C62828' },
                        },
                        children: [
                            new Paragraph({
                                spacing: { before: 20, after: 20 },
                                children: [
                                    new TextRun({ text: '⚠️ Perhatian: ', bold: true, size: 20, color: 'B71C1C' }),
                                    new TextRun({ text, size: 20, color: '3E2723' }),
                                ],
                            }),
                        ],
                    }),
                ],
            }),
        ],
    });
}

// ─────────────────────────────────────────────
// HELPER: HALAMAN BARU
// ─────────────────────────────────────────────
function pageBreak() {
    return new Paragraph({
        children: [new PageBreak()],
    });
}

// ─────────────────────────────────────────────
// HELPER: BARIS KOSONG
// ─────────────────────────────────────────────
function emptyLine() {
    return new Paragraph({ spacing: { before: 40, after: 40 }, children: [] });
}

// ─────────────────────────────────────────────
// HELPER: TABEL DATA
// makeTable(headers: string[], rows: string[][], opts)
// ─────────────────────────────────────────────
function makeTable(headers, rows, opts = {}) {
    // Baris header
    const headerRow = new TableRow({
        tableHeader: true,
        children: headers.map((h, i) => new TableCell({
            shading: { fill: COLOR_HEADER_BG, type: ShadingType.CLEAR, color: 'auto' },
            margins: { top: 80, bottom: 80, left: 120, right: 120 },
            width: opts.colWidths
                ? { size: opts.colWidths[i], type: WidthType.PERCENTAGE }
                : undefined,
            children: [
                new Paragraph({
                    alignment: AlignmentType.CENTER,
                    children: [
                        new TextRun({ text: h, bold: true, size: 20, color: COLOR_HEADER_TEXT }),
                    ],
                }),
            ],
        })),
    });

    // Baris data
    const dataRows = rows.map((row, rowIdx) =>
        new TableRow({
            children: row.map((cell, cellIdx) => new TableCell({
                shading: rowIdx % 2 === 1
                    ? { fill: COLOR_ALT_ROW_BG, type: ShadingType.CLEAR, color: 'auto' }
                    : { fill: 'FFFFFF', type: ShadingType.CLEAR, color: 'auto' },
                margins: { top: 60, bottom: 60, left: 120, right: 120 },
                width: opts.colWidths
                    ? { size: opts.colWidths[cellIdx], type: WidthType.PERCENTAGE }
                    : undefined,
                children: [
                    new Paragraph({
                        alignment: opts.centerCols && opts.centerCols.includes(cellIdx)
                            ? AlignmentType.CENTER
                            : AlignmentType.LEFT,
                        children: [
                            new TextRun({ text: String(cell), size: 20, color: '1A202C' }),
                        ],
                    }),
                ],
            })),
        })
    );

    return new Table({
        width: { size: 100, type: WidthType.PERCENTAGE },
        margins: { top: 120, bottom: 120 },
        rows: [headerRow, ...dataRows],
    });
}

// ─────────────────────────────────────────────
// HELPER: COVER PAGE
// ─────────────────────────────────────────────
function buildCoverPage() {
    return [
        emptyLine(),
        emptyLine(),
        emptyLine(),
        new Paragraph({
            alignment: AlignmentType.CENTER,
            spacing: { before: 0, after: 160 },
            children: [
                new TextRun({
                    text: '🎵 MUSIK KITA',
                    bold: true,
                    size: 52,
                    color: '1A365D',
                }),
            ],
        }),
        new Paragraph({
            alignment: AlignmentType.CENTER,
            spacing: { before: 0, after: 80 },
            children: [
                new TextRun({
                    text: 'Sistem Administrasi & Keuangan Studio',
                    size: 28,
                    color: '2C5282',
                    italics: true,
                }),
            ],
        }),
        emptyLine(),
        new Paragraph({
            alignment: AlignmentType.CENTER,
            spacing: { before: 80, after: 80 },
            children: [
                new TextRun({
                    text: 'USER MANUAL — ADMIN',
                    bold: true,
                    size: 40,
                    color: '1A365D',
                }),
            ],
        }),
        new Paragraph({
            alignment: AlignmentType.CENTER,
            spacing: { before: 40, after: 40 },
            children: [
                new TextRun({
                    text: 'Versi 2.0',
                    size: 28,
                    color: '4A5568',
                    bold: true,
                }),
            ],
        }),
        emptyLine(),
        emptyLine(),
        new Paragraph({
            alignment: AlignmentType.CENTER,
            spacing: { before: 40, after: 40 },
            children: [
                new TextRun({ text: 'Versi 2.0  |  Mei 2026', size: 22, color: '718096' }),
            ],
        }),
        new Paragraph({
            alignment: AlignmentType.CENTER,
            spacing: { before: 20, after: 20 },
            children: [
                new TextRun({ text: 'Dokumen Internal — Tidak untuk Disebarluaskan', size: 20, color: '718096', italics: true }),
            ],
        }),
        pageBreak(),
    ];
}

// ─────────────────────────────────────────────
// HELPER: DAFTAR ISI (manual, statis)
// ─────────────────────────────────────────────
function buildDaftarIsi() {
    const items = [
        ['Bab 1', 'Pendahuluan'],
        ['Bab 2', 'Dashboard'],
        ['Bab 3', 'Manajemen Murid'],
        ['Bab 4', 'Penjadwalan dan Sesi'],
        ['Bab 5', 'Absensi Harian'],
        ['  5.1', 'Membuka Halaman Absensi'],
        ['  5.2', 'Filter dan Counter Sesi'],
        ['  5.3', '9 Status Absensi'],
        ['  5.4', 'Syarat Izin Reschedule'],
        ['  5.5', 'Workflow Reschedule'],
        ['  5.6', 'Open Slot Board'],
        ['  5.7', 'Guru Pengganti'],
        ['  5.8', 'Kids Class — Absensi Grup'],
        ['  5.9', 'Status IZIN_PENDING  ★ BARU v2'],
        ['Bab 6', 'Keuangan Murid'],
        ['  6.1', 'Daftar Invoice Murid'],
        ['  6.2', 'Generate Invoice SPP'],
        ['  6.3', 'Apply Denda Keterlambatan'],
        ['  6.4', 'Detail Invoice'],
        ['  6.5', 'Catat Pembayaran'],
        ['  6.6', 'Cetak Kuitansi'],
        ['  6.7', 'Invoice Final Project Kids Class (KIDS_FP)  ★ BARU v2'],
        ['  6.8', 'Diskon Item Invoice'],
        ['Bab 7', 'Pengeluaran dan Kas'],
        ['Bab 8', 'Slip Honor Guru'],
        ['Bab 9', 'Event Studio'],
        ['Bab 10', 'Import Data Murid'],
        ['Bab 11', 'Master Data'],
        ['Bab 12', 'Laporan'],
        ['Lampiran', 'Referensi Cepat'],
    ];

    const paras = [
        h1('Daftar Isi'),
        emptyLine(),
    ];

    items.forEach(([num, label]) => {
        const isMain = !num.startsWith(' ');
        paras.push(new Paragraph({
            spacing: { before: isMain ? 80 : 30, after: isMain ? 60 : 20 },
            indent: isMain ? {} : { left: convertInchesToTwip(0.4) },
            children: [
                new TextRun({
                    text: `${num.trim()}   ${label}`,
                    size: isMain ? 22 : 20,
                    bold: isMain,
                    color: isMain ? '1A365D' : '4A5568',
                }),
            ],
        }));
    });

    paras.push(pageBreak());
    return paras;
}

// ════════════════════════════════════════════════════════════════════
// BAB 1: PENDAHULUAN
// ════════════════════════════════════════════════════════════════════
function buildBab1() {
    return [
        h1('Bab 1 — Pendahuluan'),
        h2('1.1  Tentang Sistem Musik KITA'),
        para('Sistem Administrasi Musik KITA adalah aplikasi web internal yang mengelola seluruh operasional studio: pendaftaran murid, penjadwalan, absensi, keuangan murid, honor guru, dan laporan manajemen. Sistem berjalan secara lokal di jaringan LAN studio dan tidak memerlukan koneksi internet.'),
        emptyLine(),

        h2('1.2  Hak Akses Role Admin'),
        para('Sebagai Admin, Anda memiliki akses penuh ke kegiatan operasional harian, namun beberapa fungsi sensitif hanya dapat dilakukan oleh Owner:'),
        emptyLine(),
        makeTable(
            ['Fitur', 'Admin', 'Owner'],
            [
                ['Daftar & kelola murid', '✓', '✓'],
                ['Input absensi harian', '✓', '✓'],
                ['Generate invoice SPP', '✓', '✓'],
                ['Catat pembayaran', '✓', '✓'],
                ['Tambah item invoice manual', '✓', '✓'],
                ['Beri diskon invoice', '✓', '✓'],
                ['Generate Invoice Final Project Kids', '✓', '✓'],
                ['Void pembayaran', '✗', '✓ Only'],
                ['Hitung & edit honor guru', '✗', '✓ Only'],
                ['Tandai honor dibayar', '✗', '✓ Only'],
                ['Ubah harga paket / pricelist', '✗', '✓ Only'],
                ['Kelola user (tambah/hapus admin)', '✗', '✓ Only'],
                ['Lihat audit log raw', '✗', '✓ Only'],
                ['Buat / edit event (Mini Concert)', '✗', '✓'],
                ['Tambah peserta event', '✓', '✓'],
                ['Assign guru pendamping event', '✓', '✓'],
                ['Master data (guru, ruang, libur)', '✓', '✓'],
                ['Import data murid Excel', '✓', '✓'],
                ['Laporan murid', '✓', '✓'],
                ['Laporan keuangan P&L', '✗', '✓ Only'],
            ],
            { colWidths: [55, 22, 23], centerCols: [1, 2] }
        ),
        emptyLine(),

        h2('1.3  Login ke Sistem'),
        para('Sistem diakses melalui browser di jaringan LAN studio. Gunakan alamat IP lokal studio (contoh: http://192.168.1.10) atau localhost jika dari komputer server.'),
        emptyLine(),
        numbered(1, 'Buka browser (Chrome / Firefox / Edge).'),
        numbered(2, 'Masukkan alamat IP studio yang diberikan oleh Owner.'),
        numbered(3, 'Di halaman login, masukkan Username atau Email dan Password akun Admin Anda.'),
        numbered(4, 'Klik tombol "Masuk". Anda akan diarahkan ke Dashboard.'),
        emptyLine(),
        warning('Jangan berbagi password akun Admin dengan siapapun. Setiap aksi tercatat di Audit Log beserta identitas pengguna yang melakukan.'),
        emptyLine(),
        note('Jika lupa password, minta Owner untuk mereset melalui menu User Management.'),
        emptyLine(),

        h2('1.4  Navigasi Utama'),
        para('Sidebar kiri berisi seluruh menu sistem. Klik ikon atau label menu untuk membuka halaman. Topbar (baris atas) menampilkan nama pengguna yang sedang login dan tombol logout.'),
        emptyLine(),
        makeTable(
            ['Menu Sidebar', 'Fungsi'],
            [
                ['Dashboard', 'Ringkasan KPI, sesi hari ini, tagihan jatuh tempo'],
                ['Murid', 'Daftar, tambah, dan kelola murid'],
                ['Penjadwalan', 'Jadwal mingguan dan sesi bulanan'],
                ['Absensi', 'Input kehadiran sesi harian'],
                ['Open Slot Board', 'Pantau sesi IZIN_PENDING tanpa pengganti'],
                ['Keuangan', 'Invoice, pembayaran, kuitansi'],
                ['Pengeluaran', 'Catat pengeluaran kas'],
                ['Honor Guru', 'Lihat dan cetak slip honor (read-only Admin)'],
                ['Event', 'Mini Concert dan Ujian'],
                ['Import', 'Import data murid dari Excel'],
                ['Master Data', 'Guru, instrumen, ruangan, hari libur'],
                ['Laporan', 'Laporan murid dan keuangan'],
            ],
            { colWidths: [35, 65] }
        ),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 2: DASHBOARD
// ════════════════════════════════════════════════════════════════════
function buildBab2() {
    return [
        h1('Bab 2 — Dashboard'),
        h2('2.1  Ringkasan KPI'),
        para('Dashboard adalah halaman pertama yang muncul setelah login. Terdapat 4 kartu KPI di bagian atas:'),
        emptyLine(),
        makeTable(
            ['KPI', 'Keterangan'],
            [
                ['Murid Aktif', 'Jumlah murid dengan status Aktif saat ini'],
                ['Sesi Hari Ini', 'Total sesi terjadwal untuk tanggal hari ini'],
                ['Tagihan Belum Lunas', 'Jumlah invoice UNPAID + PARTIAL'],
                ['Guru Aktif', 'Jumlah guru dengan is_active = true'],
            ],
            { colWidths: [35, 65] }
        ),
        emptyLine(),

        h2('2.2  Tabel Sesi Hari Ini'),
        para('Di bawah KPI terdapat tabel yang menampilkan seluruh sesi yang dijadwalkan hari ini. Kolom yang ditampilkan: Jam, Murid, Guru, Ruang, Paket, Status Absensi. Dari tabel ini Admin dapat langsung klik baris untuk menuju halaman absensi sesi tersebut.'),
        emptyLine(),

        h2('2.3  Tagihan Jatuh Tempo Terlama'),
        para('Bagian bawah dashboard menampilkan daftar invoice dengan tanggal jatuh tempo paling lama (aging receivable). Ini membantu Admin memprioritaskan penagihan. Klik nomor invoice untuk membuka detail.'),
        emptyLine(),
        note('Dashboard menampilkan data real-time. Refresh halaman (F5) untuk mendapatkan data terbaru.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 3: MANAJEMEN MURID
// ════════════════════════════════════════════════════════════════════
function buildBab3() {
    return [
        h1('Bab 3 — Manajemen Murid'),
        h2('3.1  Daftar Murid'),
        para('Akses melalui menu "Murid" di sidebar. Halaman daftar menampilkan seluruh murid beserta kode murid, nama, status, paket utama, guru, dan tanggal aktif.'),
        emptyLine(),
        para('Filter tersedia: cari berdasarkan nama/kode, filter status (Aktif, Trial, Cuti, dll), filter instrumen.'),
        emptyLine(),

        h2('3.2  Tambah Murid Baru'),
        numbered(1, 'Klik tombol "Tambah Murid" di pojok kanan atas daftar murid.'),
        numbered(2, 'Isi formulir data murid: Nama Lengkap, Nama Panggilan, Jenis Kelamin, Tanggal Lahir, Nomor HP, Email (opsional).'),
        numbered(3, 'Isi data orang tua/wali: Nama, No. HP, Hubungan (Ayah/Ibu/Wali).'),
        numbered(4, 'Klik "Simpan". Murid tersimpan dengan status Calon dan kode murid otomatis (M-YYYY-NNNN).'),
        emptyLine(),
        note('Email murid bersifat opsional namun disarankan diisi untuk keperluan notifikasi di masa depan.'),
        emptyLine(),

        h2('3.3  Detail Murid'),
        para('Klik nama murid di daftar untuk membuka halaman detail. Halaman detail memiliki beberapa tab:'),
        emptyLine(),
        makeTable(
            ['Tab', 'Konten'],
            [
                ['Profil', 'Data personal murid, orang tua, dan riwayat perubahan status'],
                ['Kelas', 'Daftar semua enrollment (kelas) murid, aktif maupun selesai'],
                ['Jadwal', 'Jadwal mingguan tetap per enrollment'],
                ['Sesi', 'Riwayat semua sesi konkret murid'],
                ['Tagihan', 'Daftar invoice dan tombol generate tagihan baru'],
                ['Pembayaran', 'Riwayat pembayaran dan kuitansi'],
            ],
            { colWidths: [25, 75] }
        ),
        emptyLine(),

        h2('3.4  Lifecycle Status Murid'),
        para('Setiap murid memiliki satu status yang berubah mengikuti alur berikut:'),
        emptyLine(),
        makeTable(
            ['Dari', 'Ke', 'Cara / Syarat'],
            [
                ['Calon', 'Trial', 'Admin jadwalkan sesi trial'],
                ['Calon', 'Aktif', 'Skip trial — wajib isi alasan (walk-in, migrasi, reaktivasi, lulus Kids)'],
                ['Trial', 'Aktif', 'Konversi setelah trial sukses, bayar registrasi Rp 250.000 + SPP bulan 1'],
                ['Trial', 'Mundur', 'Murid tidak melanjutkan setelah trial'],
                ['Aktif', 'Cuti', 'Pengajuan cuti + bayar biaya cuti Rp 100.000'],
                ['Aktif', 'Mundur', 'Manual oleh Admin/Owner, atau auto jika tunggakan >1 bulan'],
                ['Aktif', 'Selesai', 'Kids Class lulus program 6 bulan'],
                ['Cuti', 'Aktif', 'Otomatis saat periode cuti berakhir, atau manual'],
                ['Cuti', 'Mundur', 'Tidak bayar cuti atau melebihi batas 2 bulan'],
                ['Selesai', 'Aktif', 'Re-enroll privat, tanpa bayar registrasi ulang'],
                ['Mundur', 'Aktif', 'Re-aktivasi, wajib bayar registrasi Rp 250.000'],
            ],
            { colWidths: [18, 18, 64] }
        ),
        emptyLine(),

        h2('3.5  Multi-Enrollment (Lebih dari Satu Kelas)'),
        para('Murid dapat mengikuti lebih dari satu kelas sekaligus (contoh: Piano + Gitar). Setiap kelas adalah satu enrollment terpisah.'),
        emptyLine(),
        bullet('Primary Enrollment: enrollment utama yang digunakan untuk generate invoice SPP otomatis.'),
        bullet('Enrollment non-primary: ditagih manual jika diperlukan.'),
        bullet('Untuk tambah kelas baru: buka tab "Kelas" di detail murid, klik "Tambah Kelas".'),
        bullet('Untuk set primary: Owner/Admin klik "Set Utama" di enrollment yang diinginkan.'),
        bullet('Untuk hentikan kelas non-primary: klik "Hentikan" — status enrollment berubah ke COMPLETED.'),
        emptyLine(),
        note('Jika semua enrollment murid berstatus COMPLETED atau INACTIVE, sistem secara otomatis mengubah status murid menjadi Mundur.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 4: PENJADWALAN DAN SESI
// ════════════════════════════════════════════════════════════════════
function buildBab4() {
    return [
        h1('Bab 4 — Penjadwalan dan Sesi'),
        h2('4.1  Jadwal Mingguan Tetap'),
        para('Setiap enrollment memiliki jadwal mingguan tetap yang mendefinisikan hari, jam mulai, jam selesai, guru, dan ruang untuk sesi reguler. Jadwal ini menjadi dasar generator sesi bulanan.'),
        emptyLine(),
        para('Cara menambah jadwal untuk enrollment:'),
        numbered(1, 'Buka detail murid → tab "Kelas".'),
        numbered(2, 'Klik nama enrollment yang ingin ditambahkan jadwal.'),
        numbered(3, 'Di halaman detail enrollment, klik "Tambah Jadwal".'),
        numbered(4, 'Pilih Hari, Jam Mulai, Jam Selesai, Guru, Ruang.'),
        numbered(5, 'Klik "Simpan". Sistem cek konflik jadwal guru dan ruang secara otomatis.'),
        emptyLine(),
        warning('Satu guru tidak boleh memiliki dua jadwal yang berlangsung bersamaan. Satu ruang tidak boleh digunakan oleh dua sesi di waktu yang sama.'),
        emptyLine(),

        h2('4.2  Generator Sesi Bulanan'),
        para('Sistem secara otomatis men-generate sesi konkret untuk bulan berikutnya setiap tanggal 25. Generator membuat sesi berdasarkan jadwal mingguan aktif masing-masing enrollment.'),
        emptyLine(),
        para('Aturan generator:'),
        bullet('Minimum 3 sesi, maksimum 4 sesi per murid per bulan.'),
        bullet('Minggu ke-5 tidak dihitung dalam kuota 4 sesi (kecuali sebagai sesi pengganti hari libur).'),
        bullet('Sesi pada hari libur nasional otomatis diberi status LIBUR.'),
        bullet('Jika holiday memiliki replacement_date, generator membuat sesi pengganti di tanggal tersebut.'),
        bullet('Enrollment dengan status ON_LEAVE tidak di-generate sesinya.'),
        emptyLine(),
        note('Admin dapat men-trigger generator manual dari menu Penjadwalan → Generate Sesi jika diperlukan sebelum tanggal 25.'),
        emptyLine(),

        h2('4.3  Melihat Daftar Sesi'),
        para('Menu Penjadwalan → Sesi menampilkan daftar semua sesi konkret. Filter tersedia berdasarkan bulan/tahun, guru, murid, dan status sesi.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 5: ABSENSI HARIAN
// ════════════════════════════════════════════════════════════════════
function buildBab5() {
    return [
        h1('Bab 5 — Absensi Harian'),
        h2('5.1  Membuka Halaman Absensi'),
        para('Klik menu "Absensi" di sidebar. Halaman absensi menampilkan daftar seluruh sesi yang dijadwalkan. Secara default halaman menampilkan sesi hari ini.'),
        emptyLine(),

        h2('5.2  Filter dan Counter Sesi'),
        para('Di bagian atas halaman terdapat filter tanggal dan filter guru. Di samping filter terdapat counter yang menunjukkan ringkasan status sesi di tanggal yang dipilih:'),
        emptyLine(),
        makeTable(
            ['Counter', 'Keterangan'],
            [
                ['Terjadwal', 'Sesi belum diinput absensinya (status SCHEDULED)'],
                ['Hadir', 'Sesi dengan status HADIR atau HADIR_TERLAMBAT'],
                ['Izin', 'Sesi dengan status IZIN_RESCHEDULE atau IZIN_VIDEO'],
                ['Hangus / Libur', 'Sesi HANGUS, LIBUR, atau CANCELLED'],
            ],
            { colWidths: [30, 70] }
        ),
        emptyLine(),

        h2('5.3  9 Status Absensi'),
        para('Untuk setiap sesi, Admin memilih salah satu dari 9 status berikut:'),
        emptyLine(),
        makeTable(
            ['Kode Status', 'Nama Tampilan', 'Keterangan', 'Honor Guru'],
            [
                ['SCHEDULED', 'Terjadwal', 'Belum diinput (status awal)', '—'],
                ['HADIR', 'Hadir', 'Murid hadir tepat waktu', 'H_REG (penuh)'],
                ['HADIR_TERLAMBAT', 'Hadir Terlambat', 'Murid hadir, tapi terlambat', 'H_REG (penuh)'],
                ['IZIN_RESCHEDULE', 'Izin Reschedule', 'Murid izin, berhak ganti jadwal', 'H_IZIN → Rp 0 (dibayar via sesi pengganti)'],
                ['IZIN_VIDEO', 'Izin Video', 'Izin ke-2+, ganti video', 'H_VIDEO (penuh)'],
                ['HANGUS', 'Hangus', 'No-show tanpa info / info < 5 jam', 'H_HANGUS (penuh)'],
                ['LIBUR', 'Libur', 'Hari libur nasional/studio', 'H_LIBUR (penuh, kecuali Internal → Rp 0)'],
                ['DIGANTI', 'Diganti Guru', 'Guru pengganti mengajar', 'H_PENG → ke guru pengganti'],
                ['CANCELLED', 'Dibatalkan', 'Sesi dibatalkan (bukan absensi)', '—'],
            ],
            { colWidths: [18, 18, 40, 24] }
        ),
        emptyLine(),

        h2('5.4  Syarat Izin Reschedule'),
        para('Sesi hanya bisa ditandai IZIN_RESCHEDULE jika memenuhi SEMUA syarat berikut:'),
        emptyLine(),
        bullet('Murid atau orang tua memberikan info minimal 5 jam sebelum sesi.'),
        bullet('Ini adalah izin PERTAMA murid tersebut di bulan berjalan.'),
        emptyLine(),
        para('Jika salah satu syarat tidak terpenuhi:'),
        bullet('Info < 5 jam atau tanpa info → status HANGUS (honor guru tetap dibayar penuh).'),
        bullet('Izin ke-2 atau lebih di bulan yang sama → status IZIN_VIDEO (video pengganti, honor penuh).'),
        emptyLine(),
        warning('Status HANGUS bukan berarti murid tidak bayar. Honor guru tetap dihitung penuh (H_HANGUS). Sesi dianggap terlaksana dari sisi keuangan.'),
        emptyLine(),

        h2('5.5  Workflow Reschedule'),
        para('Setelah sesi ditandai IZIN_RESCHEDULE, Admin perlu menjadwalkan sesi pengganti:'),
        emptyLine(),
        numbered(1, 'Di baris sesi yang sudah IZIN_RESCHEDULE, klik tombol "Jadwalkan Pengganti".'),
        numbered(2, 'Mini-modal terbuka. Isi Tanggal Pengganti, Jam, dan Ruang (opsional).'),
        numbered(3, 'Sistem cek konflik: guru tidak boleh memiliki sesi lain di waktu yang sama, ruang tidak boleh bentrok.'),
        numbered(4, 'Jika tidak ada konflik, klik "Simpan". Sesi pengganti dibuat dengan status SCHEDULED.'),
        numbered(5, 'Status sesi asli berubah dari IZIN_RESCHEDULE ke DIGANTI.'),
        emptyLine(),
        note('Sesi pengganti bisa dijadwalkan di bulan berjalan atau "dirapel" ke bulan berikutnya jika tidak ada slot tersedia di bulan ini.'),
        emptyLine(),

        h2('5.6  Open Slot Board'),
        para('Open Slot Board adalah halaman khusus yang menampilkan sesi-sesi IZIN_PENDING (sesi yang sudah di-set IZIN_RESCHEDULE tetapi belum memiliki sesi pengganti).'),
        emptyLine(),
        para('Akses: Sidebar → "Open Slot Board", atau dari banner notifikasi di halaman Absensi.'),
        emptyLine(),
        para('Di Open Slot Board, Admin memiliki dua pilihan aksi per baris:'),
        emptyLine(),
        makeTable(
            ['Aksi', 'Fungsi'],
            [
                ['Isi Slot', 'Gunakan slot waktu yang sama untuk murid lain yang butuh sesi pengganti. Sesi IZIN_PENDING murid asli tetap pending.'],
                ['Jadwalkan Pengganti', 'Buat sesi pengganti baru di tanggal/jam/ruang yang dipilih untuk murid asli.'],
            ],
            { colWidths: [25, 75] }
        ),
        emptyLine(),

        h2('5.7  Guru Pengganti'),
        para('Jika guru utama berhalangan, Admin dapat menugaskan guru pengganti untuk sesi tertentu:'),
        emptyLine(),
        numbered(1, 'Di baris sesi di halaman Absensi, klik tombol "Set Guru Pengganti".'),
        numbered(2, 'Pilih guru pengganti dari dropdown.'),
        numbered(3, 'Klik "Simpan". Status sesi berubah ke DIGANTI.'),
        numbered(4, 'Honor sesi tersebut otomatis dihitung ke guru pengganti (kode H_PENG), bukan guru utama.'),
        emptyLine(),
        warning('Setelah guru pengganti diset, honor sesi sudah tidak bisa dikembalikan ke guru utama secara otomatis. Hubungi Owner jika ada koreksi.'),
        emptyLine(),

        h2('5.8  Kids Class — Absensi Grup'),
        para('Sesi Kids Class diikuti oleh beberapa murid dalam satu grup. Halaman absensi Kids Class menampilkan daftar murid yang terdaftar di kelas tersebut. Admin menginput status kehadiran masing-masing murid secara individual.'),
        emptyLine(),
        note('Honor guru Kids Class dihitung berdasarkan jumlah murid yang terdaftar di kelas (Rp 42.500 per murid), bukan berdasarkan kehadiran aktual.'),
        emptyLine(),

        // ────────────────────────────────────────────────
        // SECTION BARU v2: 5.9 IZIN_PENDING
        // ────────────────────────────────────────────────
        h2('5.9  Status IZIN_PENDING  ★ Baru di v2'),
        para('IZIN_PENDING adalah status TRANSISI internal yang terjadi secara otomatis ketika Admin menandai sesi sebagai IZIN_RESCHEDULE, tetapi sesi pengganti belum dijadwalkan.'),
        emptyLine(),

        h3('Mengapa Ada IZIN_PENDING?'),
        para('Sistem memisahkan dua langkah: (1) Admin mencatat bahwa murid izin dan berhak reschedule, dan (2) Admin menjadwalkan sesi pengganti. Di antara kedua langkah itu, sesi berada dalam status "menggantung" — itulah IZIN_PENDING.'),
        emptyLine(),

        h3('Cara Sesi Masuk Status IZIN_PENDING'),
        numbered(1, 'Admin memilih status "IZIN_RESCHEDULE" untuk sesi di halaman Absensi.'),
        numbered(2, 'Sistem otomatis menandai sesi sebagai IZIN_PENDING sampai sesi pengganti dibuat.'),
        numbered(3, 'Setelah sesi pengganti berhasil dibuat, status sesi asli berubah ke DIGANTI dan IZIN_PENDING hilang.'),
        emptyLine(),

        h3('Di Mana IZIN_PENDING Muncul?'),
        makeTable(
            ['Lokasi', 'Bentuk Tampilan'],
            [
                ['Header halaman Absensi', 'Badge kuning dengan jumlah sesi IZIN_PENDING yang belum tertangani'],
                ['Open Slot Board', 'Setiap baris adalah satu sesi IZIN_PENDING'],
                ['Kolom "Pending" di Open Slot Board', 'Badge merah jika >= 7 hari, badge kuning jika < 7 hari'],
                ['Portal Guru', 'Guru melihat daftar sesi IZIN_PENDING miliknya'],
            ],
            { colWidths: [35, 65] }
        ),
        emptyLine(),

        h3('Honor Guru untuk Sesi IZIN_PENDING'),
        para('Honor guru untuk sesi yang berstatus IZIN_PENDING adalah Rp 0 (kode honor: H_IZIN). Honor baru dibayarkan ketika sesi pengganti dilaksanakan dan murid hadir (kode honor: H_REG pada sesi pengganti).'),
        emptyLine(),
        warning('Jangan bingungkan H_IZIN (Rp 0) dengan H_HANGUS (honor penuh). IZIN_RESCHEDULE → murid berhak ganti sesi → guru belum dibayar. HANGUS → murid no-show → guru tetap dibayar penuh.'),
        emptyLine(),

        h3('Peran Guru dalam IZIN_PENDING'),
        para('Guru aktif yang login ke portal mereka dapat:'),
        bullet('Melihat daftar sesi IZIN_PENDING miliknya.'),
        bullet('Mengusulkan (suggest) tanggal pengganti yang cocok.'),
        bullet('Saran dari guru muncul di Open Slot Board dengan badge amber 💬 di kolom "Saran Guru".'),
        emptyLine(),
        note('Admin yang memutuskan dan mengeksekusi penjadwalan pengganti. Saran guru hanyalah usulan, bukan konfirmasi jadwal.'),
        emptyLine(),

        h3('Cara Menyelesaikan IZIN_PENDING di Open Slot Board'),
        numbered(1, 'Buka Open Slot Board dari sidebar atau dari banner notifikasi di halaman Absensi.'),
        numbered(2, 'Temukan baris sesi IZIN_PENDING yang ingin diselesaikan.'),
        numbered(3, 'Jika ada saran guru (💬), saran sudah diisi otomatis di form.'),
        numbered(4, 'Klik "Jadwalkan Pengganti" → isi tanggal, jam, ruang (opsional) → klik "Jadwalkan".'),
        numbered(5, 'Sesi pengganti terbuat, baris IZIN_PENDING hilang dari daftar.'),
        emptyLine(),
        para('Atau gunakan "Isi Slot" jika slot waktu yang sama ingin diisi oleh murid lain yang butuh sesi pengganti. Sesi IZIN_PENDING murid asli tetap aktif hingga dijadwalkan terpisah.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 6: KEUANGAN MURID
// ════════════════════════════════════════════════════════════════════
function buildBab6() {
    return [
        h1('Bab 6 — Keuangan Murid'),
        h2('6.1  Daftar Invoice Murid'),
        para('Akses melalui menu "Keuangan" di sidebar, atau dari tab "Tagihan" di halaman detail murid. Daftar menampilkan semua invoice beserta nomor invoice, bulan/tahun, jenis tagihan, total, status, dan tanggal jatuh tempo.'),
        emptyLine(),
        makeTable(
            ['Status Invoice', 'Keterangan'],
            [
                ['UNPAID', 'Belum ada pembayaran sama sekali'],
                ['PARTIAL', 'Sudah ada pembayaran sebagian (khusus invoice cicilan Kids Class Bundle)'],
                ['PAID', 'Lunas — seluruh tagihan + denda sudah dibayar'],
            ],
            { colWidths: [25, 75] }
        ),
        emptyLine(),

        h2('6.2  Generate Invoice SPP'),
        para('Invoice SPP bulanan di-generate otomatis oleh sistem setiap tanggal 1. Admin juga dapat men-trigger generate manual:'),
        emptyLine(),
        numbered(1, 'Buka detail murid → tab "Tagihan".'),
        numbered(2, 'Klik tombol "Generate SPP Bulan Ini" jika invoice bulan berjalan belum ada.'),
        numbered(3, 'Sistem membuat invoice dengan item SPP senilai harga paket primary enrollment murid.'),
        numbered(4, 'Invoice baru muncul di daftar dengan status UNPAID.'),
        emptyLine(),
        note('Invoice SPP hanya dibuat untuk primary enrollment. Jika murid memiliki kelas kedua (non-primary), tagihan ditambahkan manual.'),
        emptyLine(),

        h2('6.3  Apply Denda Keterlambatan'),
        para('Denda berjalan otomatis mulai tanggal 11 (Rp 5.000 per hari). Sistem men-generate item denda secara otomatis melalui cron job harian. Admin juga dapat men-trigger apply denda manual dari halaman detail invoice jika diperlukan.'),
        emptyLine(),
        makeTable(
            ['Tanggal Bayar', 'Denda'],
            [
                ['1 – 10', 'Rp 0 (tidak ada denda)'],
                ['Tanggal 11', 'Rp 5.000 (1 hari)'],
                ['Tanggal 12', 'Rp 10.000 (2 hari)'],
                ['Tanggal 20', 'Rp 50.000 (10 hari)'],
                ['Tanggal 31', 'Rp 105.000 (21 hari)'],
            ],
            { colWidths: [40, 60], centerCols: [0, 1] }
        ),
        emptyLine(),

        h2('6.4  Detail Invoice'),
        para('Klik nomor invoice untuk membuka halaman detail. Halaman detail menampilkan:'),
        emptyLine(),
        bullet('Informasi header: nomor invoice, murid, enrollment, bulan/tahun, tanggal jatuh tempo, status.'),
        bullet('Tabel item tagihan: kode item, deskripsi, dan jumlah.'),
        bullet('Total tagihan dan sisa yang harus dibayar.'),
        bullet('Riwayat pembayaran yang sudah masuk.'),
        bullet('Tombol aksi: Catat Pembayaran, Tambah Item, Tambah Diskon.'),
        emptyLine(),

        h2('6.5  Catat Pembayaran'),
        numbered(1, 'Di halaman detail invoice, klik tombol "Catat Pembayaran".'),
        numbered(2, 'Isi form: Jumlah Bayar, Metode Pembayaran, Tanggal Bayar, Catatan (opsional).'),
        numbered(3, 'Upload foto bukti pembayaran (opsional tapi disarankan untuk transfer/QRIS).'),
        numbered(4, 'Klik "Simpan". Sistem memperbarui status invoice dan mencatat kuitansi otomatis.'),
        emptyLine(),
        makeTable(
            ['Metode Bayar', 'Keterangan'],
            [
                ['CASH', 'Tunai langsung di studio'],
                ['TRANSFER', 'Transfer bank'],
                ['QRIS', 'Pembayaran via QR Code'],
                ['DEBIT', 'Kartu debit (mesin EDC)'],
            ],
            { colWidths: [25, 75] }
        ),
        emptyLine(),
        warning('Void pembayaran hanya dapat dilakukan oleh Owner. Jika terjadi input salah, hubungi Owner untuk melakukan void.'),
        emptyLine(),

        h2('6.6  Cetak Kuitansi'),
        para('Setelah pembayaran dicatat, nomor kuitansi otomatis dibuat (format: KW/YYYY/MM/NNNN). Untuk mencetak kuitansi:'),
        emptyLine(),
        numbered(1, 'Buka halaman detail invoice.'),
        numbered(2, 'Di bagian Riwayat Pembayaran, klik "Cetak Kuitansi" di baris pembayaran yang diinginkan.'),
        numbered(3, 'Halaman print terbuka. Gunakan Ctrl+P untuk mencetak atau simpan sebagai PDF.'),
        emptyLine(),

        // ────────────────────────────────────────────────
        // SECTION BARU v2: 6.7 Invoice KIDS_FP
        // ────────────────────────────────────────────────
        h2('6.7  Invoice Final Project Kids Class (KIDS_FP)  ★ Baru di v2'),
        para('Invoice Final Project adalah tagihan khusus senilai Rp 140.000 yang dikenakan kepada murid Kids Class di akhir program 6 bulan. Invoice ini berbeda dari SPP bulanan dan harus di-generate secara manual oleh Admin.'),
        emptyLine(),

        h3('Kapan Tombol Generate Muncul?'),
        para('Tombol "Generate Invoice Final Project" muncul secara otomatis di tab Tagihan halaman detail murid apabila SEMUA kondisi berikut terpenuhi:'),
        emptyLine(),
        bullet('Murid memiliki primary enrollment aktif dengan paket bertipe KIDS_CLASS.'),
        bullet('Murid sudah berada di bulan ke-6 program Kids Class.'),
        bullet('Belum ada invoice sebelumnya dengan item kode KIDS_FP untuk murid ini.'),
        emptyLine(),
        note('Paket KIDS_CLASS_BUNDLE tidak memerlukan invoice KIDS_FP terpisah karena biaya Final Project sudah termasuk dalam bundel Rp 2.180.000.'),
        emptyLine(),

        h3('Cara Generate Invoice Final Project'),
        numbered(1, 'Buka halaman Detail Murid yang bersangkutan.'),
        numbered(2, 'Klik tab "Tagihan" di bagian atas halaman detail.'),
        numbered(3, 'Di bagian atas daftar tagihan, muncul panel berwarna biru dengan ikon 🎓 bertuliskan "Final Project belum ditagih".'),
        numbered(4, 'Klik tombol "Generate Invoice Final Project" di dalam panel tersebut.'),
        numbered(5, 'Modal konfirmasi terbuka menampilkan: Nama murid, Item: Final Project Kids Class, Total: Rp 140.000.'),
        numbered(6, 'Klik "Buat Invoice" untuk mengkonfirmasi.'),
        numbered(7, 'Sistem membuat invoice baru dan langsung mengarahkan halaman ke detail invoice yang baru dibuat.'),
        emptyLine(),

        h3('Hal yang Perlu Diperhatikan'),
        makeTable(
            ['Kondisi', 'Perilaku Sistem'],
            [
                ['Invoice KIDS_FP sudah pernah dibuat', 'Sistem menolak dengan pesan error — tidak bisa generate dua kali'],
                ['Status invoice awal', 'UNPAID — perlu dibayar oleh murid'],
                ['Item code', 'KIDS_FP (digunakan untuk pelacakan dan laporan)'],
                ['Paket KIDS_CLASS_BUNDLE', 'Tidak ada tombol — Final Project sudah embedded di bundle'],
                ['Murid di bawah bulan ke-6', 'Tombol tidak muncul — belum waktunya'],
            ],
            { colWidths: [40, 60] }
        ),
        emptyLine(),
        warning('Jangan generate invoice KIDS_FP lebih dari satu kali untuk murid yang sama. Sistem akan menolak, namun tetap berhati-hati agar tidak membingungkan murid dengan tagihan duplikat.'),
        emptyLine(),

        h2('6.8  Diskon Item Invoice'),
        para('Owner dan Admin dapat menambahkan diskon ke item tertentu dalam invoice yang berstatus UNPAID atau PARTIAL.'),
        emptyLine(),
        numbered(1, 'Buka detail invoice.'),
        numbered(2, 'Klik tombol "Tambah Diskon" di baris item yang ingin didiskon.'),
        numbered(3, 'Pilih tipe diskon: NOMINAL (Rp flat) atau PERCENT (% dari nilai item).'),
        numbered(4, 'Isi nilai diskon dan alasan diskon (WAJIB diisi).'),
        numbered(5, 'Klik "Simpan". Item diskon muncul di bawah item yang didiskon.'),
        emptyLine(),
        warning('Menghapus item diskon hanya dapat dilakukan oleh Owner. Invoice yang sudah berstatus PAID tidak dapat ditambahkan diskon.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 7: PENGELUARAN DAN KAS
// ════════════════════════════════════════════════════════════════════
function buildBab7() {
    return [
        h1('Bab 7 — Pengeluaran dan Kas'),
        h2('7.1  Daftar Pengeluaran'),
        para('Akses melalui menu "Pengeluaran" di sidebar. Halaman daftar menampilkan seluruh catatan pengeluaran dengan filter berdasarkan bulan, tahun, dan kategori.'),
        emptyLine(),

        h2('7.2  Catat Pengeluaran Baru'),
        numbered(1, 'Klik tombol "Tambah Pengeluaran" di pojok kanan atas.'),
        numbered(2, 'Isi form: Tanggal, Kategori, Deskripsi, dan Jumlah.'),
        numbered(3, 'Upload bukti (struk/foto) jika ada — opsional.'),
        numbered(4, 'Klik "Simpan". Pengeluaran tercatat dan masuk ke laporan P&L otomatis sesuai kategori.'),
        emptyLine(),
        makeTable(
            ['Kategori Pengeluaran', 'Contoh'],
            [
                ['Sewa', 'Biaya sewa gedung studio per bulan'],
                ['Listrik', 'Tagihan PLN studio'],
                ['Gaji Staff', 'Gaji admin, cleaning service'],
                ['Peralatan', 'Pembelian alat musik, aksesori, perbaikan alat'],
                ['ATK & Operasional', 'Buku catatan murid, alat tulis, bahan habis pakai'],
                ['Lain-lain', 'Pengeluaran di luar kategori di atas'],
            ],
            { colWidths: [35, 65] }
        ),
        emptyLine(),

        h2('7.3  Edit Pengeluaran'),
        para('Klik tombol "Edit" di baris pengeluaran yang ingin diubah. Form yang sama akan terbuka dengan data yang sudah terisi. Ubah field yang diperlukan dan klik "Simpan".'),
        emptyLine(),
        note('Penghapusan catatan pengeluaran hanya dapat dilakukan oleh Owner. Admin hanya bisa menambah dan mengedit.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 8: SLIP HONOR GURU
// ════════════════════════════════════════════════════════════════════
function buildBab8() {
    return [
        h1('Bab 8 — Slip Honor Guru'),
        h2('8.1  Hak Akses Admin untuk Honor'),
        para('Admin memiliki akses READ-ONLY untuk modul honor guru. Admin dapat melihat detail perhitungan honor dan mencetak slip, tetapi TIDAK dapat mengubah nilai honor atau menandai slip sebagai dibayar.'),
        emptyLine(),
        makeTable(
            ['Aksi', 'Admin', 'Owner'],
            [
                ['Lihat daftar slip honor', '✓', '✓'],
                ['Lihat detail breakdown honor', '✓', '✓'],
                ['Cetak slip honor PDF', '✓', '✓'],
                ['Hitung honor otomatis (H-2)', '✗', '✓ Only'],
                ['Edit komponen honor (transport, dll)', '✗', '✓ Only'],
                ['Tandai slip sebagai Dibayar', '✗', '✓ Only'],
            ],
            { colWidths: [50, 20, 30], centerCols: [1, 2] }
        ),
        emptyLine(),

        h2('8.2  Daftar Slip Honor'),
        para('Akses melalui menu "Honor Guru" di sidebar. Halaman daftar menampilkan slip honor semua guru untuk bulan yang dipilih. Status slip: DRAFT → CALCULATED → PAID.'),
        emptyLine(),

        h2('8.3  Detail Slip Honor'),
        para('Klik nama guru atau nomor slip untuk membuka detail. Halaman detail menampilkan:'),
        emptyLine(),
        bullet('Header informasi guru: nama, instrumen, info bank (untuk transfer honor).'),
        bullet('Tabel breakdown honor per sesi: tanggal, murid, kode honor, jumlah.'),
        bullet('Komponen tambahan: honor event (jika ada), honor transport, honor lain-lain.'),
        bullet('Total honor bulan tersebut.'),
        emptyLine(),

        h2('8.4  Kode Honor yang Mungkin Muncul di Breakdown'),
        makeTable(
            ['Kode', 'Skenario', 'Jumlah'],
            [
                ['H_REG', 'Sesi reguler terlaksana (hadir / terlambat)', 'harga_paket × 50% / 4'],
                ['H_TRIAL', 'Sesi trial murid hadir', 'Sama dengan H_REG'],
                ['TRIAL_NS', 'Sesi trial murid no-show', 'Rp 0'],
                ['H_VIDEO', 'Sesi izin video pengganti', 'Sama dengan H_REG'],
                ['H_LIBUR', 'Sesi hari libur nasional (is_honor_paid=true)', 'Sama dengan H_REG'],
                ['H_HANGUS', 'Sesi murid no-show / hangus', 'Sama dengan H_REG'],
                ['H_PENG', 'Sesi diajar guru pengganti', 'Ke guru pengganti'],
                ['H_KIDS', 'Sesi Kids Class', 'murid_terdaftar × Rp 42.500'],
                ['H_UJIAN', 'Guru pengawas ujian grade', 'Rp 250.000 flat/ujian'],
                ['H_IZIN', 'Sesi IZIN_RESCHEDULE (sesi asli)', 'Rp 0'],
            ],
            { colWidths: [15, 50, 35] }
        ),
        emptyLine(),

        h2('8.5  Cetak Slip Honor'),
        numbered(1, 'Buka detail slip honor guru yang ingin dicetak.'),
        numbered(2, 'Klik tombol "Cetak Slip" di pojok kanan atas.'),
        numbered(3, 'Halaman cetak terbuka menampilkan slip dalam format A4 siap cetak.'),
        numbered(4, 'Gunakan Ctrl+P → pilih printer atau "Save as PDF".'),
        emptyLine(),
        note('Slip honor yang sudah berstatus PAID (ditandai oleh Owner) tidak dapat diubah lagi. Ini menjamin integritas data penggajian.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 9: EVENT STUDIO
// ════════════════════════════════════════════════════════════════════
function buildBab9() {
    return [
        h1('Bab 9 — Event Studio'),
        h2('9.1  Daftar Event'),
        para('Akses melalui menu "Event" di sidebar. Daftar menampilkan semua event (Mini Concert dan Ujian) beserta tanggal, tipe, dan status (DRAFT / ACTIVE / COMPLETED).'),
        emptyLine(),
        note('Admin tidak bisa membuat atau mengedit event baru. Pembuatan event adalah hak Owner. Admin hanya bisa mengelola peserta dan data di dalam event yang sudah dibuat.'),
        emptyLine(),

        h2('9.2  Tambah Peserta Event'),
        numbered(1, 'Buka detail event yang sudah dibuat Owner.'),
        numbered(2, 'Klik tombol "Tambah Peserta".'),
        numbered(3, 'Cari murid dengan nama atau kode murid.'),
        numbered(4, 'Pilih tipe partisipasi: "Ujian + Tampil" (Rp 395.000) atau "Tampil Saja" (Rp 295.000).'),
        numbered(5, 'Klik "Tambah". Murid masuk ke daftar peserta dan invoice event otomatis dibuat.'),
        emptyLine(),

        h2('9.3  Assign Guru Pendamping (Konser KITA)'),
        para('Untuk event Konser KITA, setiap peserta dapat memiliki satu guru pendamping yang mendampingi selama penampilan:'),
        emptyLine(),
        numbered(1, 'Di halaman detail event, buka baris peserta yang ingin di-assign guru.'),
        numbered(2, 'Klik dropdown "Guru Pendamping" di kolom yang sesuai.'),
        numbered(3, 'Pilih guru dari dropdown (berisi semua guru aktif).'),
        numbered(4, 'Klik "Simpan". Guru pendamping tercatat untuk peserta tersebut.'),
        emptyLine(),
        para('Pengaturan guru pendamping:'),
        bullet('Hanya bisa diubah selama event masih berstatus DRAFT.'),
        bullet('NULL (kosong) berarti murid tidak memiliki guru pendamping atau guru tidak bisa hadir.'),
        bullet('Jika guru dihapus dari sistem, kolom ini otomatis menjadi NULL (tidak error).'),
        emptyLine(),

        h2('9.4  Input Hasil Ujian'),
        para('Setelah ujian selesai dan event di-set COMPLETED oleh Owner:'),
        emptyLine(),
        numbered(1, 'Buka detail event.'),
        numbered(2, 'Di baris peserta yang mengikuti ujian, klik "Input Hasil".'),
        numbered(3, 'Isi hasil ujian: LULUS atau TIDAK LULUS, dan catatan ujian jika perlu.'),
        numbered(4, 'Jika LULUS, sistem otomatis menaikkan grade murid ke grade berikutnya.'),
        numbered(5, 'Klik "Simpan".'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 10: IMPORT DATA MURID
// ════════════════════════════════════════════════════════════════════
function buildBab10() {
    return [
        h1('Bab 10 — Import Data Murid'),
        h2('10.1  Kapan Digunakan?'),
        para('Fitur Import digunakan untuk memasukkan data murid dalam jumlah banyak sekaligus dari file Excel (.xlsx / .csv). Biasanya digunakan saat migrasi data dari sistem lama.'),
        emptyLine(),

        h2('10.2  Upload File'),
        numbered(1, 'Akses menu "Import" di sidebar.'),
        numbered(2, 'Download template Excel dari tombol "Unduh Template".'),
        numbered(3, 'Isi template dengan data murid. Ikuti format kolom yang sudah ditentukan.'),
        numbered(4, 'Kembali ke halaman Import, klik "Pilih File" dan pilih file yang sudah diisi.'),
        numbered(5, 'Klik "Upload & Validasi".'),
        emptyLine(),

        h2('10.3  Validasi dan Preview'),
        para('Setelah upload, sistem melakukan validasi data. Halaman preview menampilkan:'),
        emptyLine(),
        bullet('Baris yang valid: siap diimport, ditampilkan dengan latar hijau.'),
        bullet('Baris yang error: ada masalah data (duplikat, format salah), ditampilkan dengan latar merah dan keterangan error.'),
        emptyLine(),
        note('Perbaiki error di file Excel dan upload ulang. Sistem tidak mengimport baris yang memiliki error.'),
        emptyLine(),

        h2('10.4  Konfirmasi Import'),
        numbered(1, 'Setelah review halaman preview dan memastikan data sudah benar, klik "Konfirmasi Import".'),
        numbered(2, 'Sistem memproses seluruh baris valid.'),
        numbered(3, 'Laporan hasil import muncul: jumlah berhasil diimport dan jumlah yang gagal (jika ada).'),
        emptyLine(),
        warning('Proses import tidak bisa diurungkan. Pastikan data sudah benar sebelum konfirmasi. Jika terjadi kesalahan massal, hubungi Owner untuk rollback via database.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 11: MASTER DATA
// ════════════════════════════════════════════════════════════════════
function buildBab11() {
    return [
        h1('Bab 11 — Master Data'),
        h2('11.1  Hak Akses Admin untuk Master Data'),
        para('Admin memiliki akses baca DAN tulis untuk sebagian besar master data. Pengecualian: harga paket dan PayrollConfig hanya bisa diubah Owner.'),
        emptyLine(),
        makeTable(
            ['Master Data', 'Admin', 'Owner'],
            [
                ['Kelola Guru (tambah, edit, nonaktifkan)', '✓', '✓'],
                ['Kelola Instrumen', '✓', '✓'],
                ['Kelola Ruangan', '✓', '✓'],
                ['Kelola Hari Libur', '✓', '✓'],
                ['Kelola Paket (nama, tipe, durasi)', '✗', '✓ Only'],
                ['Ubah Harga Paket', '✗', '✓ Only'],
                ['PayrollConfig (rumus honor)', '✗', '✓ Only'],
                ['Kelola Komponen Invoice', '✗', '✓ Only'],
            ],
            { colWidths: [55, 20, 25], centerCols: [1, 2] }
        ),
        emptyLine(),

        h2('11.2  Kelola Guru'),
        para('Akses melalui Master Data → Guru. Admin dapat:'),
        emptyLine(),
        bullet('Tambah guru baru: nama, kode guru, email, no. HP, instrumen yang dikuasai, tanggal bergabung.'),
        bullet('Edit data guru: ubah informasi kontak atau tambah instrumen.'),
        bullet('Nonaktifkan guru: set is_active = false. Guru tidak akan muncul di dropdown pilihan jadwal baru.'),
        emptyLine(),
        warning('Guru dengan riwayat sesi historis TIDAK BISA dihapus dari sistem. Gunakan "Nonaktifkan" untuk guru yang tidak lagi mengajar.'),
        emptyLine(),

        h2('11.3  Kelola Hari Libur'),
        para('Hari libur penting untuk generator sesi bulanan. Owner dan Admin dapat mengelola daftar hari libur:'),
        emptyLine(),
        makeTable(
            ['Field', 'Keterangan'],
            [
                ['Tanggal', 'Tanggal hari libur (format YYYY-MM-DD)'],
                ['Nama', 'Nama hari libur (contoh: Hari Raya Idul Fitri)'],
                ['Tipe', 'Nasional / Cuti Bersama / Internal (Internal = event studio seperti Konser KITA)'],
                ['Tanggal Pengganti', 'Opsional — jika diisi, generator membuat sesi pengganti di tanggal ini (harus bulan yang sama). TIDAK boleh diisi untuk tipe Internal.'],
                ['Honor Dibayar', 'Otomatis false untuk tipe Internal (honor guru Rp 0 untuk sesi libur ini)'],
            ],
            { colWidths: [25, 75] }
        ),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// BAB 12: LAPORAN
// ════════════════════════════════════════════════════════════════════
function buildBab12() {
    return [
        h1('Bab 12 — Laporan'),
        h2('12.1  Laporan Keuangan P&L'),
        para('Laporan Profit & Loss tersedia di menu Laporan → Keuangan. Laporan ini hanya dapat diakses oleh Owner.'),
        emptyLine(),
        note('Admin tidak memiliki akses ke laporan keuangan P&L. Jika Owner ingin berbagi ringkasan keuangan dengan Admin, Owner dapat mencetak atau export terlebih dahulu.'),
        emptyLine(),

        h2('12.2  Laporan Murid'),
        para('Laporan murid dapat diakses oleh semua role (Owner, Admin, Auditor). Akses melalui menu Laporan → Murid.'),
        emptyLine(),
        para('Laporan yang tersedia:'),
        emptyLine(),
        makeTable(
            ['Laporan', 'Konten'],
            [
                ['Rekap Status Murid', 'Jumlah murid per status (Aktif, Trial, Cuti, Mundur, Selesai)'],
                ['Retensi Murid', 'Grafik murid baru vs mundur per bulan'],
                ['Murid per Instrumen', 'Distribusi murid berdasarkan instrumen yang dipelajari'],
                ['Absensi Bulanan', 'Rekap status sesi per bulan (hadir, izin, hangus, libur)'],
                ['Murid Tunggakan', 'Daftar murid dengan invoice UNPAID > 30 hari'],
            ],
            { colWidths: [35, 65] }
        ),
        emptyLine(),

        h2('12.3  Export Laporan'),
        para('Setiap laporan dapat di-export dalam format PDF atau Excel:'),
        numbered(1, 'Buka laporan yang diinginkan.'),
        numbered(2, 'Atur filter (bulan, tahun, kategori) sesuai kebutuhan.'),
        numbered(3, 'Klik "Export PDF" atau "Export Excel" di pojok kanan atas.'),
        numbered(4, 'File download otomatis.'),
        emptyLine(),
        pageBreak(),
    ];
}

// ════════════════════════════════════════════════════════════════════
// LAMPIRAN: REFERENSI CEPAT
// ════════════════════════════════════════════════════════════════════
function buildLampiran() {
    return [
        h1('Lampiran — Referensi Cepat'),

        h2('A.  Status Murid — 6 Status Valid'),
        makeTable(
            ['Status', 'Keterangan'],
            [
                ['Calon', 'Sudah daftar, belum trial'],
                ['Trial', 'Sedang atau sudah trial, belum aktif'],
                ['Aktif', 'Murid berjalan, enrollment aktif, SPP ditagih'],
                ['Cuti', 'Sedang cuti berbayar, sesi di-pause'],
                ['Selesai', 'Lulus Kids Class 6 bulan'],
                ['Mengundurkan Diri', 'Keluar (manual Admin/Owner atau auto sistem)'],
            ],
            { colWidths: [30, 70] }
        ),
        emptyLine(),

        h2('B.  Status Sesi — 9 Status Valid'),
        makeTable(
            ['Status', 'Keterangan Singkat'],
            [
                ['SCHEDULED', 'Belum diinput absensi (status awal)'],
                ['HADIR', 'Murid hadir tepat waktu'],
                ['HADIR_TERLAMBAT', 'Murid hadir tapi terlambat'],
                ['IZIN_RESCHEDULE', 'Murid izin, berhak dapat sesi pengganti (izin 1 di bulan ini + >= 5 jam)'],
                ['IZIN_VIDEO', 'Murid izin, ganti dengan video (izin ke-2+ atau < 5 jam)'],
                ['HANGUS', 'Murid no-show, tidak ada info / info < 5 jam'],
                ['LIBUR', 'Hari libur nasional atau studio'],
                ['DIGANTI', 'Diajar oleh guru pengganti'],
                ['CANCELLED', 'Sesi dibatalkan total'],
            ],
            { colWidths: [28, 72] }
        ),
        emptyLine(),

        h2('C.  Format Nomor Dokumen'),
        makeTable(
            ['Dokumen', 'Format', 'Contoh'],
            [
                ['Kode Murid', 'M-YYYY-NNNN', 'M-2026-0001'],
                ['Invoice', 'INV/YYYY/MM/NNNN', 'INV/2026/05/0042'],
                ['Kuitansi', 'KW/YYYY/MM/NNNN', 'KW/2026/05/0007'],
                ['Slip Honor', 'SLIP/YYYY/MM/NNNN', 'SLIP/2026/05/0018'],
            ],
            { colWidths: [30, 35, 35], centerCols: [1, 2] }
        ),
        emptyLine(),

        h2('D.  Metode Pembayaran yang Valid'),
        makeTable(
            ['Kode', 'Nama', 'Keterangan'],
            [
                ['CASH', 'Tunai', 'Bayar langsung di studio'],
                ['TRANSFER', 'Transfer Bank', 'Transfer rekening studio'],
                ['QRIS', 'QRIS', 'Scan QR Code via dompet digital'],
                ['DEBIT', 'Kartu Debit', 'Gesek kartu via mesin EDC'],
            ],
            { colWidths: [15, 25, 60] }
        ),
        emptyLine(),

        h2('E.  Komponen Tagihan (Item Codes)'),
        makeTable(
            ['Kode', 'Nama', 'Nominal'],
            [
                ['REG', 'Registrasi murid baru', 'Rp 250.000'],
                ['SPP', 'SPP Bulanan', 'Sesuai harga paket'],
                ['KIDS_FP', 'Final Project Kids Class', 'Rp 140.000/murid'],
                ['CUTI', 'Biaya Cuti', 'Rp 100.000/pengajuan'],
                ['UJI', 'Ujian + Mini Concert', 'Rp 395.000'],
                ['MC', 'Mini Concert Saja', 'Rp 295.000'],
                ['DENDA', 'Denda Keterlambatan', 'Rp 5.000/hari (mulai hari ke-11)'],
                ['DISKON', 'Diskon Manual', 'NOMINAL (Rp flat) atau PERCENT (%)'],
            ],
            { colWidths: [15, 35, 50] }
        ),
        emptyLine(),

        h2('F.  Aturan Penting yang Sering Terlupakan'),
        makeTable(
            ['Aturan', 'Detail'],
            [
                ['Trial selalu 30 menit', 'Durasi trial 30 menit untuk SEMUA tipe paket, bukan ikut durasi paket yang diminati'],
                ['Honor trial no-show = Rp 0', 'Jika murid trial tidak datang, honor guru Rp 0 (kode TRIAL_NS)'],
                ['Void payment = Owner only', 'Admin tidak bisa void pembayaran yang sudah dicatat'],
                ['Kids Class usia 4 s/d < 5 tahun', 'Batas usia Kids Class adalah hari ulang tahun ke-4 sampai sebelum ulang tahun ke-5'],
                ['Honor cut-off H-2', 'Perhitungan honor dijalankan H-2 sebelum akhir bulan, bukan tanggal akhir'],
                ['Max 4 sesi per bulan', 'Murid tidak dapat memiliki lebih dari 4 sesi reguler per bulan (minggu ke-5 tidak dihitung)'],
                ['Diskon wajib ada alasan', 'Field "Alasan Diskon" WAJIB diisi saat menambahkan item diskon ke invoice'],
                ['Internal holiday = honor Rp 0', 'Hari libur tipe Internal (Konser KITA) → honor guru Rp 0, tidak ada sesi pengganti'],
            ],
            { colWidths: [35, 65] }
        ),
        emptyLine(),

        h2('G.  Kontak Bantuan Teknis'),
        para('Jika menemukan masalah pada sistem yang tidak bisa diselesaikan sendiri, hubungi Owner studio. Jangan coba mengubah data langsung di database tanpa sepengetahuan Owner.'),
        emptyLine(),
        para('Versi User Manual ini: v2.0 | Mei 2026', { italic: true, color: '718096' }),
        para('Dokumen ini bersifat internal dan rahasia. Tidak untuk disebarluaskan.', { italic: true, color: '718096' }),
    ];
}

// ════════════════════════════════════════════════════════════════════
// ASSEMBLY: GABUNGKAN SEMUA SECTION
// ════════════════════════════════════════════════════════════════════
async function generateDocument() {
    const allContent = [
        ...buildCoverPage(),
        ...buildDaftarIsi(),
        ...buildBab1(),
        ...buildBab2(),
        ...buildBab3(),
        ...buildBab4(),
        ...buildBab5(),
        ...buildBab6(),
        ...buildBab7(),
        ...buildBab8(),
        ...buildBab9(),
        ...buildBab10(),
        ...buildBab11(),
        ...buildBab12(),
        ...buildLampiran(),
    ];

    const doc = new Document({
        creator: 'Musik KITA — Sistem Administrasi',
        title: 'User Manual Admin Musik KITA v2',
        description: 'Panduan penggunaan sistem administrasi Musik KITA untuk role Admin',
        styles: {
            default: {
                document: {
                    run: {
                        font: 'Calibri',
                        size: 22,
                        color: '1A202C',
                    },
                },
            },
        },
        sections: [
            {
                properties: {
                    page: {
                        margin: {
                            top:    convertInchesToTwip(1.0),
                            bottom: convertInchesToTwip(1.0),
                            left:   convertInchesToTwip(1.2),
                            right:  convertInchesToTwip(1.0),
                        },
                    },
                },
                children: allContent,
            },
        ],
    });

    const outputPath = path.join(__dirname, 'User-Manual-Admin-Musik-KITA.docx');
    const buffer = await Packer.toBuffer(doc);
    fs.writeFileSync(outputPath, buffer);

    console.log('SUCCESS: User Manual Admin Musik KITA v2 berhasil dibuat!');
    console.log('Output  :', outputPath);
    console.log('Ukuran  :', (buffer.length / 1024).toFixed(1) + ' KB');
}

// Jalankan
generateDocument().catch((err) => {
    console.error('ERROR:', err.message);
    process.exit(1);
});
