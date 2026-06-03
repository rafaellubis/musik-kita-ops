// generate_owner_manual.cjs
// Generator User Manual Owner — Musik KITA Operations System
// Menggunakan library docx v9.x (CommonJS)

"use strict";

const {
  Document, Packer, Paragraph, TextRun, HeadingLevel,
  Table, TableRow, TableCell, WidthType, BorderStyle,
  AlignmentType, ShadingType, VerticalAlign,
  convertInchesToTwip, LevelFormat, NumberFormat,
  UnderlineType
} = require("docx");

const fs = require("fs");
const path = require("path");

// ============================================================
// KONSTANTA WARNA
// ============================================================
const COLOR_H1       = "1A237E"; // indigo gelap
const COLOR_H2       = "283593";
const COLOR_H3       = "1565C0";
const COLOR_NOTE     = "E65100"; // oranye
const COLOR_WARNING  = "B71C1C"; // merah
const COLOR_OWNER    = "6A1B9A"; // ungu
const COLOR_HEADER   = "1A237E"; // header tabel
const COLOR_ALT_ROW  = "E8EAF6"; // baris alternating
const COLOR_BLACK    = "000000";
const COLOR_WHITE    = "FFFFFF";

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function h1(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 400, after: 200 },
    children: [new TextRun({ text, bold: true, color: COLOR_H1, size: 36 })],
  });
}

function h2(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 300, after: 150 },
    children: [new TextRun({ text, bold: true, color: COLOR_H2, size: 28 })],
  });
}

function h3(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_3,
    spacing: { before: 200, after: 100 },
    children: [new TextRun({ text, bold: true, color: COLOR_H3, size: 24 })],
  });
}

function para(text) {
  return new Paragraph({
    alignment: AlignmentType.JUSTIFIED,
    spacing: { before: 80, after: 80 },
    children: [new TextRun({ text, size: 22 })],
  });
}

function bullet(text, level = 0) {
  return new Paragraph({
    bullet: { level },
    spacing: { before: 60, after: 60 },
    indent: { left: convertInchesToTwip(0.25 + level * 0.25) },
    children: [new TextRun({ text, size: 22 })],
  });
}

function note(text) {
  return new Paragraph({
    spacing: { before: 80, after: 80 },
    children: [
      new TextRun({ text: "CATATAN: ", bold: true, italic: true, color: COLOR_NOTE, size: 22 }),
      new TextRun({ text, italic: true, color: COLOR_NOTE, size: 22 }),
    ],
  });
}

function warning(text) {
  return new Paragraph({
    spacing: { before: 80, after: 80 },
    children: [
      new TextRun({ text: "⚠ PERHATIAN: ", bold: true, color: COLOR_WARNING, size: 22 }),
      new TextRun({ text, bold: true, color: COLOR_WARNING, size: 22 }),
    ],
  });
}

function ownerOnly(text) {
  return new Paragraph({
    spacing: { before: 80, after: 80 },
    children: [
      new TextRun({ text: "👑 OWNER ONLY: ", bold: true, color: COLOR_OWNER, size: 22 }),
      new TextRun({ text, bold: true, color: COLOR_OWNER, size: 22 }),
    ],
  });
}

function pageBreak() {
  return new Paragraph({
    children: [new TextRun({ break: 1 })],
  });
}

function emptyLine() {
  return new Paragraph({ children: [new TextRun({ text: "" })] });
}

function makeTable(headers, rows) {
  const headerCells = headers.map((h) =>
    new TableCell({
      shading: { fill: COLOR_HEADER, type: ShadingType.CLEAR, color: COLOR_HEADER },
      verticalAlign: VerticalAlign.CENTER,
      margins: { top: 80, bottom: 80, left: 100, right: 100 },
      children: [
        new Paragraph({
          alignment: AlignmentType.CENTER,
          children: [new TextRun({ text: h, bold: true, color: COLOR_WHITE, size: 20 })],
        }),
      ],
    })
  );

  const dataRows = rows.map((row, rowIndex) => {
    const cells = row.map((cell) =>
      new TableCell({
        shading: rowIndex % 2 === 1
          ? { fill: COLOR_ALT_ROW, type: ShadingType.CLEAR, color: COLOR_ALT_ROW }
          : { fill: COLOR_WHITE, type: ShadingType.CLEAR, color: COLOR_WHITE },
        margins: { top: 60, bottom: 60, left: 100, right: 100 },
        children: [
          new Paragraph({
            children: [new TextRun({ text: String(cell), size: 20 })],
          }),
        ],
      })
    );
    return new TableRow({ children: cells });
  });

  return new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [new TableRow({ children: headerCells, tableHeader: true }), ...dataRows],
    margins: { top: 100, bottom: 100 },
  });
}

// ============================================================
// COVER PAGE
// ============================================================
function makeCover() {
  return [
    emptyLine(), emptyLine(), emptyLine(), emptyLine(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { before: 400, after: 200 },
      children: [new TextRun({ text: "MUSIK KITA", bold: true, size: 72, color: COLOR_H1 })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { before: 100, after: 100 },
      children: [new TextRun({ text: "Operations System", size: 40, color: COLOR_H2 })],
    }),
    emptyLine(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { before: 200, after: 200 },
      children: [new TextRun({ text: "USER MANUAL — OWNER", bold: true, size: 52, color: COLOR_OWNER })],
    }),
    emptyLine(), emptyLine(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      children: [new TextRun({ text: "Panduan Lengkap Penggunaan Sistem Administrasi Studio Musik", size: 28, italic: true, color: "555555" })],
    }),
    emptyLine(), emptyLine(), emptyLine(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      children: [new TextRun({ text: "Versi: 1.4  |  Mei 2026", size: 24, color: "777777" })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      children: [new TextRun({ text: "Platform: Laravel 11  |  Akses: LAN Lokal Studio", size: 24, color: "777777" })],
    }),
    pageBreak(),
  ];
}

// ============================================================
// DAFTAR ISI
// ============================================================
function makeTOC() {
  const items = [
    "Bab 1: Pendahuluan",
    "Bab 2: Dashboard Owner",
    "Bab 3: Manajemen Murid",
    "Bab 4: Penjadwalan dan Sesi",
    "Bab 5: Absensi Harian",
    "Bab 6: Keuangan Murid — Tagihan dan Pembayaran",
    "Bab 7: Pengeluaran dan Kas",
    "Bab 8: Slip Honor Guru",
    "Bab 9: Event Studio",
    "Bab 10: Import Data Murid",
    "Bab 11: Master Data",
    "Bab 12: User Management",
    "Bab 13: Audit Log",
    "Bab 14: Laporan",
    "Bab 15: Otomatisasi dan Cron Job",
    "Lampiran A: Matriks Hak Akses Lengkap",
    "Lampiran B: Status Murid",
    "Lampiran C: Status Sesi",
    "Lampiran D: Kode Honor Guru",
    "Lampiran E: Format Nomor Seri",
    "Lampiran F: Komponen Tagihan",
    "Lampiran G: Aturan Penting yang Perlu Diingat",
  ];
  return [
    h1("DAFTAR ISI"),
    emptyLine(),
    ...items.map((item) => bullet(item)),
    pageBreak(),
  ];
}

// ============================================================
// BAB 1: PENDAHULUAN
// ============================================================
function makeBab1() {
  return [
    h1("BAB 1: PENDAHULUAN"),
    emptyLine(),

    h2("1.1 Tentang Sistem Musik KITA"),
    para("Sistem Musik KITA Operations adalah aplikasi administrasi dan keuangan internal yang dirancang khusus untuk studio musik Musik KITA. Sistem ini menggantikan seluruh proses operasional yang sebelumnya dikelola menggunakan spreadsheet Excel, sehingga seluruh data murid, jadwal, absensi, tagihan, dan honor guru dapat dikelola dalam satu platform terpadu."),
    para("Sistem berjalan secara offline di jaringan LAN lokal studio (tidak memerlukan koneksi internet) dan dapat diakses melalui browser di perangkat yang terhubung ke WiFi studio. Tidak ada data yang dikirim ke server eksternal."),
    para("Data yang dikelola sistem meliputi: sekitar 300 murid aktif, 18 guru, 9 studio room, lebih dari 1.200 sesi privat per bulan, serta sesi Kids Class grup."),
    emptyLine(),

    h2("1.2 Perbandingan Hak Akses Pengguna"),
    para("Sistem menggunakan 4 role pengguna dengan hak akses yang berbeda-beda. Sebagai Owner, Anda memiliki akses penuh ke semua fitur tanpa batasan."),
    emptyLine(),
    makeTable(
      ["Fitur / Aksi", "Owner", "Admin", "Auditor", "Guru"],
      [
        ["Login ke sistem", "Ya", "Ya", "Ya", "Ya (portal khusus)"],
        ["Lihat semua data & laporan", "Ya", "Ya", "Ya (read-only)", "Terbatas (data sendiri)"],
        ["Daftar murid baru", "Ya", "Ya", "Tidak", "Tidak"],
        ["Input absensi harian", "Ya", "Ya", "Tidak", "Tidak"],
        ["Catat tagihan & pembayaran", "Ya", "Ya", "Tidak", "Tidak"],
        ["Ubah harga paket kelas", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Void pembayaran", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Hitung & edit honor guru", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Tandai honor dibayarkan", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Buat dan selesaikan event", "Ya", "Tidak (hanya tambah peserta)", "Tidak", "Tidak"],
        ["Kelola user (tambah/edit/hapus)", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Lihat Audit Log", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Laporan Keuangan (P&L)", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Laporan Murid", "Ya", "Ya", "Ya", "Tidak"],
        ["Kelola Payroll Config", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Kelola Katalog Item Tagihan", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Hapus master data", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Import data murid Excel", "Ya", "Ya", "Tidak", "Tidak"],
      ]
    ),
    emptyLine(),

    h2("1.3 Cara Login"),
    para("Sistem mendukung dua metode login: menggunakan username ATAU menggunakan alamat email. Keduanya valid dan dapat digunakan bergantian."),
    h3("Langkah Login:"),
    bullet("Buka browser (Chrome/Firefox/Edge) dan akses alamat IP lokal studio, contoh: http://192.168.1.x"),
    bullet("Di halaman login, masukkan username (contoh: owner) ATAU email lengkap (contoh: owner@musikkita.local)"),
    bullet("Masukkan password"),
    bullet("Klik tombol 'Masuk'"),
    bullet("Sistem akan memvalidasi kredensial dan mengarahkan Anda ke /dashboard"),
    emptyLine(),
    note("Akun default setelah seeder: owner@musikkita.local / password — WAJIB diganti sebelum sistem dipakai live di studio."),
    warning("Jangan share password akun Owner kepada siapapun. Owner memiliki akses ke semua data keuangan dan honor guru."),
    emptyLine(),

    h2("1.4 Navigasi Sidebar Owner"),
    para("Sebagai Owner, seluruh menu di sidebar tersedia tanpa pembatasan. Menu disusun berdasarkan area fungsional:"),
    bullet("Dashboard — Ringkasan operasional dan keuangan real-time"),
    bullet("Murid — Daftar, tambah, dan kelola seluruh murid"),
    bullet("Jadwal & Sesi — Kalender jadwal mingguan dan sesi konkret"),
    bullet("Absensi — Input absensi harian, open slot board"),
    bullet("Tagihan (Invoice) — SPP, denda, pembayaran, kuitansi"),
    bullet("Pengeluaran — Catat dan kelola pengeluaran studio"),
    bullet("Slip Honor — Kalkulasi dan pembayaran honor guru"),
    bullet("Event — Mini Concert dan Ujian Grade"),
    bullet("Import — Import data murid dari Excel"),
    bullet("Master Data — Guru, Instrumen, Ruangan, Paket, Hari Libur, Payroll Config, Katalog Tagihan"),
    bullet("User Management — Kelola akun pengguna sistem"),
    bullet("Audit Log — Riwayat semua aksi di sistem"),
    bullet("Laporan — Laporan keuangan P&L dan laporan murid"),
    emptyLine(),

    h2("1.5 Perbedaan Utama Owner vs Admin"),
    makeTable(
      ["Kemampuan", "Owner", "Admin"],
      [
        ["Void pembayaran yang sudah dicatat", "Ya", "Tidak"],
        ["Hitung dan edit honor guru", "Ya", "Tidak"],
        ["Tandai honor dibayarkan (lock slip)", "Ya", "Tidak"],
        ["Buat event baru dan selesaikan event", "Ya", "Tidak (tambah peserta saja)"],
        ["Kelola user sistem", "Ya", "Tidak"],
        ["Lihat Audit Log", "Ya", "Tidak"],
        ["Laporan keuangan P&L", "Ya", "Tidak"],
        ["Ubah harga paket kelas", "Ya", "Tidak"],
        ["Kelola Payroll Config / formula honor", "Ya", "Tidak"],
        ["Kelola Katalog Item Tagihan", "Ya", "Tidak"],
        ["Hapus master data", "Ya", "Tidak"],
        ["Hapus pengeluaran", "Ya", "Tidak"],
      ]
    ),
    pageBreak(),
  ];
}

// ============================================================
// BAB 2: DASHBOARD OWNER
// ============================================================
function makeBab2() {
  return [
    h1("BAB 2: DASHBOARD OWNER"),
    emptyLine(),
    para("Dashboard adalah halaman pertama yang muncul setelah login. Dashboard Owner menampilkan ringkasan lengkap kondisi operasional dan keuangan studio secara real-time."),
    emptyLine(),

    h2("2.1 KPI Utama (Kartu Statistik Atas)"),
    para("Empat kartu statistik utama di bagian atas dashboard menampilkan:"),
    makeTable(
      ["KPI", "Keterangan", "Rincian Tambahan"],
      [
        ["Pendapatan Bulan Ini", "Total pembayaran masuk di bulan berjalan", "Rincian: CASH + Transfer + QRIS + DEBIT"],
        ["Pengeluaran Bulan Ini", "Total pengeluaran studio di bulan berjalan", "Breakdown per kategori tersedia di Laporan"],
        ["Laba/Rugi Bulan Ini", "Pendapatan dikurangi pengeluaran dan honor", "Warna hijau jika laba, merah jika rugi"],
        ["Saldo Kas", "Saldo kas studio saat ini", "Memperhitungkan semua pengeluaran dan pemasukan"],
      ]
    ),
    emptyLine(),

    h2("2.2 Grafik P&L 6 Bulan Terakhir"),
    para("Area chart interaktif (menggunakan ApexCharts) menampilkan tren keuangan 6 bulan terakhir dengan tiga seri data:"),
    bullet("Pemasukan (Pendapatan) — warna hijau"),
    bullet("Honor Guru (Biaya honor bulanan) — warna oranye"),
    bullet("Pengeluaran Operasional — warna merah"),
    para("Hover mouse di atas titik grafik untuk melihat nilai detail per bulan. Grafik membantu Owner memantau tren dan mengidentifikasi bulan-bulan dengan performa di bawah rata-rata."),
    emptyLine(),

    h2("2.3 Donut Chart Distribusi Murid per Instrumen"),
    para("Diagram donut menampilkan distribusi murid aktif berdasarkan instrumen yang dipelajari. Hover untuk melihat jumlah murid per instrumen dan persentasenya. Berguna untuk memantau instrumen paling populer dan kapasitas pengajar."),
    emptyLine(),

    h2("2.4 Aging Piutang (Panel Tagihan Tertunggak)"),
    para("Panel ini menampilkan status piutang studio yang dikelompokkan berdasarkan usia keterlambatan:"),
    makeTable(
      ["Kategori", "Keterangan"],
      [
        ["Belum Jatuh Tempo", "Invoice yang masih dalam periode bayar (tgl 1-10)"],
        ["Telat 1-30 Hari", "Invoice melewati jatuh tempo tapi belum >30 hari"],
        ["Telat >30 Hari", "Invoice sangat terlambat — perlu tindakan segera"],
      ]
    ),
    para("Setiap kategori menampilkan total nominal (Rp) dan jumlah tagihan. Klik pada kategori untuk langsung masuk ke halaman Tagihan dengan filter yang sudah diterapkan."),
    emptyLine(),

    h2("2.5 Statistik Murid"),
    para("Panel statistik murid menampilkan jumlah murid per status:"),
    bullet("Aktif — murid berjalan normal dengan enrollment aktif"),
    bullet("Trial — sedang dalam atau sudah selesai sesi trial"),
    bullet("Cuti — murid dalam periode cuti berbayar"),
    bullet("Calon — sudah daftar, belum trial"),
    bullet("Total — seluruh murid di database"),
    para("Klik angka atau label untuk membuka halaman Daftar Murid dengan filter status yang sesuai."),
    emptyLine(),

    h2("2.6 Tabel 10 Invoice Terlama Belum Lunas"),
    para("Tabel ini menampilkan 10 invoice paling lama yang belum lunas, diurutkan dari yang paling tua. Kolom yang ditampilkan:"),
    bullet("Nama Murid"),
    bullet("Periode Invoice (bulan/tahun)"),
    bullet("Sisa Tagihan (total dikurangi yang sudah dibayar)"),
    bullet("Jatuh Tempo"),
    bullet("Status (UNPAID / PARTIAL)"),
    para("Klik nama murid untuk langsung masuk ke detail invoice murid tersebut."),
    emptyLine(),

    h2("2.7 Panel Slip Honor Belum Dibayarkan"),
    para("Daftar slip honor guru yang statusnya masih DRAFT atau CALCULATED (belum ditandai PAID). Tampil: nama guru, periode bulan/tahun, dan total honor. Klik untuk masuk ke halaman detail slip honor dan menyelesaikan pembayaran."),
    emptyLine(),

    h2("2.8 Link Cepat"),
    bullet("Laporan Keuangan → menuju /reports/finance"),
    bullet("Laporan Murid → menuju /reports/students"),
    bullet("Kelola Honor → menuju /honors"),
    pageBreak(),
  ];
}

// ============================================================
// BAB 3: MANAJEMEN MURID
// ============================================================
function makeBab3() {
  return [
    h1("BAB 3: MANAJEMEN MURID"),
    emptyLine(),
    para("Modul Manajemen Murid adalah inti operasional sistem. Owner memiliki akses identik dengan Admin, ditambah kemampuan melihat metadata teknis lengkap di tab Riwayat dan tidak ada pembatasan aksi lifecycle murid."),
    emptyLine(),

    h2("3.1 Daftar Murid"),
    para("Halaman /students menampilkan seluruh murid yang terdaftar di sistem. Fitur-fitur yang tersedia:"),
    h3("Filter dan Pencarian:"),
    bullet("Kotak pencarian teks bebas — mencari berdasarkan nama, kode murid (M-), atau nama panggilan"),
    bullet("Filter Status — dropdown: Semua, Calon, Trial, Aktif, Cuti, Selesai, Mengundurkan Diri"),
    bullet("Filter Instrumen — dropdown instrumen aktif"),
    bullet("Filter Guru — dropdown guru aktif"),
    h3("Tabel Daftar Murid:"),
    bullet("Kode Murid (format M-YYYY-NNNN, contoh M-2026-0001)"),
    bullet("Nama Lengkap dan Nama Panggilan"),
    bullet("Paket Aktif (dari primary enrollment)"),
    bullet("Guru (dari primary enrollment)"),
    bullet("Status (badge berwarna)"),
    bullet("Tombol aksi: Lihat Detail"),
    note("Kode murid otomatis dibuat saat pendaftaran. Format: M-[tahun]-[nomor urut 4 digit]."),
    emptyLine(),

    h2("3.2 Tambah Murid Baru"),
    para("Klik tombol 'Tambah Murid' untuk membuka form pendaftaran murid baru. Form dibagi 4 bagian:"),
    h3("Bagian 1 — Data Pribadi Murid:"),
    bullet("Nama Lengkap (wajib)"),
    bullet("Nama Panggilan"),
    bullet("Jenis Kelamin (L / P)"),
    bullet("Tanggal Lahir (wajib untuk validasi usia Kids Class)"),
    bullet("Nomor Telepon"),
    bullet("Email (opsional)"),
    bullet("Alamat"),
    bullet("Catatan tambahan"),
    h3("Bagian 2 — Data Orang Tua / Wali:"),
    bullet("Nama Orang Tua/Wali"),
    bullet("Nomor HP Orang Tua/Wali (wajib)"),
    bullet("Email Orang Tua/Wali"),
    bullet("Hubungan (Ayah / Ibu / Wali)"),
    h3("Bagian 3 — Paket Kelas (Enrollment):"),
    bullet("Pilih Instrumen"),
    bullet("Pilih Paket (otomatis filter berdasarkan instrumen)"),
    bullet("Pilih Guru (otomatis filter berdasarkan instrumen yang diajarkan)"),
    bullet("Pilih Ruangan"),
    bullet("Hari dan Jam Kelas"),
    h3("Bagian 4 — Status Awal:"),
    bullet("Status awal murid baru selalu 'Calon'"),
    bullet("Jika skip trial: centang 'Langsung Aktif', wajib pilih alasan (walk_in / migrasi / reaktivasi / lulus_kids)"),
    note("Murid baru secara default masuk status Calon. Admin/Owner kemudian menjadwalkan trial atau langsung mengaktifkan."),
    emptyLine(),

    h2("3.3 Detail Murid (5 Tab)"),
    para("Klik nama murid untuk masuk ke halaman detail. Terdapat 5 tab:"),
    h3("Tab 1 — Informasi:"),
    bullet("Data pribadi murid (nama, kontak, tanggal lahir, gender)"),
    bullet("Data orang tua/wali"),
    bullet("Status saat ini dengan badge berwarna"),
    bullet("Tanggal aktif, tanggal sesi terakhir"),
    bullet("Catatan tambahan"),
    bullet("Tombol Edit Data Murid"),
    h3("Tab 2 — Kelas (Enrollment):"),
    bullet("Daftar semua enrollment (aktif, cuti, selesai) murid ini"),
    bullet("Setiap enrollment menampilkan: paket, guru, ruangan, jadwal, status, tanggal mulai"),
    bullet("Badge 'UTAMA' pada primary enrollment"),
    bullet("Tombol: Jadikan Utama (jika bukan primary), Hentikan (jika aktif)"),
    bullet("Tombol 'Tambah Kelas' untuk multi-enrollment"),
    h3("Tab 3 — Jadwal & Sesi:"),
    bullet("Grid kalender atau tabel sesi per bulan"),
    bullet("Filter bulan/tahun"),
    bullet("Setiap sesi: tanggal, waktu, guru, ruang, status absensi, honor"),
    bullet("Generate sesi jika belum ada untuk bulan yang dipilih"),
    h3("Tab 4 — Tagihan:"),
    bullet("Daftar semua invoice murid (nomor, periode, total, status, sisa)"),
    bullet("Tombol: Lihat Detail Invoice, Tambah Invoice Manual"),
    bullet("Tombol 'Generate Final Project KIDS_FP' untuk murid Kids Class di bulan ke-6"),
    bullet("Progress bar cicilan untuk KIDS_CLASS_BUNDLE"),
    h3("Tab 5 — Riwayat:"),
    bullet("Semua perubahan status murid (lifecycle history)"),
    bullet("Tanggal, dari status, ke status, alasan, siapa yang mengubah"),
    bullet("Metadata teknis (Owner dapat melihat field JSON lengkap)"),
    emptyLine(),

    h2("3.4 Panel Lifecycle Murid"),
    para("Di halaman detail murid, tersedia panel aksi lifecycle di sisi kanan atau bagian atas. Tombol yang muncul bergantung pada status murid saat ini:"),
    makeTable(
      ["Status Saat Ini", "Tombol Tersedia", "Keterangan"],
      [
        ["Calon", "Jadwalkan Trial, Langsung Aktif", "Trial: buat sesi trial. Langsung Aktif: skip trial + wajib alasan"],
        ["Trial", "Konversi ke Aktif, Mundurkan", "Konversi: murid lanjut + generate invoice REG + SPP. Mundur: tidak lanjut"],
        ["Aktif", "Ajukan Cuti, Mundurkan", "Cuti: wajib bayar Rp 100.000. Mundur: akun dinonaktifkan"],
        ["Aktif (Kids)", "Selesaikan (lulus), Mundurkan", "Selesai: Kids Class lulus 6 bulan"],
        ["Cuti", "Aktifkan Kembali, Perpanjang Cuti, Mundurkan", "Aktifkan: enrollment kembali ACTIVE. Perpanjang: maks 1x (total 2 bulan)"],
        ["Selesai", "Re-aktivasi", "Re-enroll privat tanpa bayar registrasi ulang"],
        ["Mundur", "Re-aktivasi", "Kembali aktif, WAJIB bayar Rp 250.000 registrasi"],
      ]
    ),
    emptyLine(),

    h2("3.5 Multi-Enrollment: Murid dengan Lebih dari Satu Kelas"),
    para("Sistem mendukung satu murid memiliki lebih dari satu enrollment aktif bersamaan (contoh: Piano Reguler + Gitar Hobby). Aturan penting:"),
    bullet("Setiap murid memiliki tepat satu primary enrollment (ditandai badge UTAMA)"),
    bullet("Invoice SPP otomatis hanya dibuat untuk primary enrollment"),
    bullet("Enrollment non-primary ditagih secara manual jika diperlukan"),
    h3("Cara Tambah Kelas:"),
    bullet("Buka tab Kelas di detail murid"),
    bullet("Klik 'Tambah Kelas'"),
    bullet("Pilih paket, guru, ruangan, jadwal baru"),
    bullet("Klik Simpan — enrollment baru ditambahkan dengan status ACTIVE"),
    h3("Cara Jadikan Utama:"),
    bullet("Di tab Kelas, temukan enrollment yang ingin dijadikan primary"),
    bullet("Klik tombol 'Jadikan Utama'"),
    bullet("Primary enrollment lama berubah menjadi non-primary"),
    h3("Cara Hentikan Enrollment:"),
    bullet("Klik 'Hentikan' pada enrollment yang ingin dihentikan"),
    bullet("Enrollment berubah status ke COMPLETED"),
    bullet("Jika semua enrollment COMPLETED/INACTIVE, murid otomatis mundur"),
    pageBreak(),
  ];
}

// ============================================================
// BAB 4: PENJADWALAN DAN SESI
// ============================================================
function makeBab4() {
  return [
    h1("BAB 4: PENJADWALAN DAN SESI"),
    emptyLine(),

    h2("4.1 Kalender Jadwal Mingguan"),
    para("Halaman /sessions atau /schedules menampilkan kalender jadwal mingguan tetap studio. Kalender menampilkan semua sesi yang dijadwalkan dalam format grid per jam dan per hari."),
    h3("Fitur Kalender:"),
    bullet("Filter per guru, per ruangan, per instrumen"),
    bullet("Klik sel jadwal untuk melihat popup detail: murid, guru, ruangan, status"),
    bullet("Navigasi antar minggu (tombol Sebelumnya / Berikutnya)"),
    bullet("Indikator warna per status sesi (SCHEDULED = abu, HADIR = hijau, dll)"),
    emptyLine(),

    h2("4.2 Daftar Sesi"),
    para("Daftar sesi konkret (bukan jadwal mingguan) per tanggal. Fitur:"),
    bullet("Statistik per status: jumlah SCHEDULED, HADIR, IZIN, HANGUS, LIBUR, dll"),
    bullet("Filter: tanggal, bulan, guru, murid, status"),
    bullet("Tabel sesi: tanggal, murid, guru, ruang, waktu, status, honor_code, honor_amount"),
    bullet("Edit sesi: klik ikon edit untuk membuka modal edit"),
    bullet("Hapus sesi: hanya sesi SCHEDULED yang belum berlangsung"),
    emptyLine(),

    h2("4.3 Generate Sesi Bulanan"),
    para("Generator sesi membuat sesi konkret dari jadwal mingguan tetap untuk satu bulan penuh. Proses ini berjalan otomatis (cron tanggal 25) dan dapat juga dipicu manual."),
    h3("Aturan Generator:"),
    bullet("Maksimal 4 sesi per murid per enrollment per bulan"),
    bullet("Minggu ke-5 tidak dihitung sebagai sesi reguler (hanya untuk pengganti)"),
    bullet("Jika tanggal sesi bertepatan dengan hari libur nasional (tabel holidays), sesi dibuat dengan status LIBUR"),
    bullet("Jika hari libur memiliki replacement_date, generator membuat sesi pengganti di tanggal tersebut (di luar counter 4 sesi)"),
    bullet("Enrollment dengan status ON_LEAVE (cuti) tidak dibuatkan sesi"),
    h3("Cara Trigger Manual:"),
    bullet("Masuk ke halaman Sesi atau Jadwal"),
    bullet("Klik tombol 'Generate Sesi [Bulan]'"),
    bullet("Konfirmasi bulan yang akan di-generate"),
    bullet("Tunggu proses selesai — progress ditampilkan"),
    note("Proses idempotent: tidak membuat duplikat jika sesi sudah ada untuk periode tersebut."),
    warning("Generate sesi untuk bulan yang sedang berjalan dapat mempengaruhi absensi yang belum diinput. Disarankan generate untuk bulan berikutnya."),
    emptyLine(),

    h2("4.4 Edit Sesi Manual"),
    para("Setiap sesi dapat diedit melalui modal edit. Field yang dapat diubah:"),
    bullet("Jam mulai dan jam selesai"),
    bullet("Guru (termasuk guru pengganti — honor otomatis dialihkan)"),
    bullet("Ruangan"),
    bullet("Catatan"),
    bullet("Status (untuk koreksi manual)"),
    note("Perubahan guru di sesi akan mempengaruhi perhitungan honor. Pastikan guru pengganti diisi di field substitute_teacher, bukan mengganti field guru utama."),
    pageBreak(),
  ];
}

// ============================================================
// BAB 5: ABSENSI HARIAN
// ============================================================
function makeBab5() {
  return [
    h1("BAB 5: ABSENSI HARIAN"),
    emptyLine(),

    h2("5.1 Halaman Absensi"),
    para("Halaman /absensi adalah halaman operasional utama yang digunakan setiap hari. Menampilkan semua sesi untuk tanggal yang dipilih."),
    bullet("Date picker di bagian atas — pilih tanggal absensi"),
    bullet("Counter: jumlah sesi belum diisi vs sudah diisi absensi"),
    bullet("Tabel sesi: murid, guru, ruang, waktu, status, tombol aksi"),
    emptyLine(),

    h2("5.2 Filter Absensi"),
    bullet("Filter Guru — tampilkan sesi guru tertentu saja"),
    bullet("Filter Status — tampilkan sesi dengan status tertentu"),
    bullet("Cari Murid — pencarian nama murid"),
    emptyLine(),

    h2("5.3 Input Status Absensi (9 Status)"),
    para("Setiap sesi dapat diisi dengan salah satu dari 9 status berikut:"),
    makeTable(
      ["Kode Status", "Keterangan", "Honor Guru", "Catatan"],
      [
        ["HADIR", "Murid hadir tepat waktu", "H_REG (penuh)", ""],
        ["HADIR_TERLAMBAT", "Murid hadir tapi terlambat", "H_REG (penuh)", "Isi field menit keterlambatan"],
        ["IZIN_RESCHEDULE", "Murid izin, berhak reschedule", "H_IZIN (Rp 0 — dibayar via sesi pengganti)", "Hanya berlaku jika memenuhi syarat (lihat 5.4)"],
        ["IZIN_VIDEO", "Murid izin, pengganti video", "H_VIDEO (penuh)", "Izin ke-2 atau tidak memenuhi syarat reschedule"],
        ["HANGUS", "Murid tidak hadir tanpa info cukup", "H_HANGUS (penuh)", "Info <5 jam atau tanpa info sama sekali"],
        ["LIBUR", "Hari libur nasional", "H_LIBUR (penuh)", "Set otomatis oleh generator"],
        ["DIGANTI", "Guru diganti/absen, murid tetap belajar", "H_PENG (ke guru pengganti)", "Wajib isi substitute_teacher"],
        ["CANCELLED", "Sesi dibatalkan (tidak ada pihak yang hadir)", "Rp 0", "Digunakan jika studio tutup, dll"],
        ["SCHEDULED", "Belum diisi absensi", "—", "Status default saat sesi dibuat"],
      ]
    ),
    emptyLine(),

    h2("5.4 Syarat Izin Reschedule (BR-4.4)"),
    para("Murid berhak mendapat sesi pengganti (reschedule) HANYA jika memenuhi kedua syarat berikut:"),
    bullet("Syarat 1: Murid atau orang tua memberikan informasi minimal 5 jam sebelum jadwal sesi"),
    bullet("Syarat 2: Ini adalah izin PERTAMA murid di bulan tersebut"),
    emptyLine(),
    para("Jika salah satu syarat tidak terpenuhi:"),
    bullet("Izin ke-2 atau lebih di bulan yang sama → status IZIN_VIDEO (video pengganti)"),
    bullet("Info kurang dari 5 jam atau tanpa info → status HANGUS"),
    note("Keputusan status ada di tangan Admin/Owner yang menginput absensi. Sistem tidak otomatis mengubah status berdasarkan waktu."),
    emptyLine(),

    h2("5.5 Reschedule: Pilih Slot Pengganti"),
    para("Setelah status IZIN_RESCHEDULE diinput, mini-modal akan muncul untuk memilih slot pengganti:"),
    bullet("Pilih tanggal pengganti (bisa bulan berjalan atau bulan depan — BR-4.5)"),
    bullet("Pilih jam mulai dan jam selesai"),
    bullet("Pilih ruangan"),
    bullet("Sistem otomatis pre-fill guru yang sama"),
    bullet("Klik Simpan — sesi pengganti dibuat"),
    h3("Conflict Detection Otomatis:"),
    bullet("Sistem memeriksa apakah guru sudah memiliki sesi lain di waktu yang sama"),
    bullet("Sistem memeriksa apakah ruangan sudah dipakai di waktu yang sama"),
    bullet("Jika ada konflik, sistem menampilkan pesan error — Admin harus pilih slot lain"),
    emptyLine(),

    h2("5.6 Status IZIN_PENDING"),
    para("IZIN_PENDING adalah status transisi sementara. Status ini muncul ketika guru melaporkan murid izin via portal guru, namun Admin belum mengonfirmasi apakah izin ini berhak reschedule atau tidak."),
    bullet("Owner/Admin dapat melihat daftar sesi berstatus IZIN_PENDING di halaman absensi"),
    bullet("Konfirmasi: ubah ke IZIN_RESCHEDULE atau IZIN_VIDEO sesuai kondisi"),
    bullet("Portal guru menampilkan saran berdasarkan histori izin murid di bulan berjalan"),
    emptyLine(),

    h2("5.7 Open Slot Board"),
    para("Halaman Open Slot Board (/absensi/open-slots) menampilkan semua slot waktu yang tersedia untuk sesi pengganti. Berguna saat Admin mencari slot untuk murid yang perlu reschedule."),
    h3("Dua Aksi di Open Slot Board:"),
    bullet("Isi Slot: langsung jadwalkan sesi pengganti untuk murid di slot ini"),
    bullet("Jadwalkan Pengganti: pilih dari daftar murid yang menunggu sesi pengganti"),
    bullet("Saran guru auto-populate berdasarkan instrumen yang cocok dan guru yang tidak konflik di slot tersebut"),
    emptyLine(),

    h2("5.8 Guru Pengganti (DIGANTI)"),
    para("Jika guru utama berhalangan dan digantikan guru lain, gunakan status DIGANTI:"),
    bullet("Set status sesi ke DIGANTI"),
    bullet("Pilih guru pengganti di field 'Guru Pengganti' (substitute_teacher_id)"),
    bullet("Honor sesi ini otomatis dikalkulasi ke guru pengganti (H_PENG), bukan guru utama"),
    bullet("Guru utama tidak mendapat honor untuk sesi ini"),
    warning("Pastikan field Guru Pengganti diisi sebelum menyimpan status DIGANTI. Jika kosong, honor sesi tidak akan teralokasi ke siapapun."),
    pageBreak(),
  ];
}

// ============================================================
// BAB 6: KEUANGAN MURID
// ============================================================
function makeBab6() {
  return [
    h1("BAB 6: KEUANGAN MURID — TAGIHAN DAN PEMBAYARAN"),
    emptyLine(),

    h2("6.1 Daftar Invoice"),
    para("Halaman /invoices menampilkan semua tagihan seluruh murid. Panel statistik di bagian atas menampilkan:"),
    bullet("Total tagihan UNPAID (belum dibayar sama sekali)"),
    bullet("Total tagihan PARTIAL (sudah dibayar sebagian)"),
    bullet("Total tagihan PAID (lunas) bulan ini"),
    bullet("Total nilai tagihan beredar"),
    h3("Filter Daftar Invoice:"),
    bullet("Filter Status: UNPAID / PARTIAL / PAID / Semua"),
    bullet("Filter Bulan/Tahun"),
    bullet("Filter Murid (autocomplete nama)"),
    bullet("Filter Jenis: SPP / Registrasi / Kids FP / dll"),
    emptyLine(),

    h2("6.2 Generate SPP Bulanan"),
    para("Invoice SPP dibuat otomatis oleh sistem pada tanggal 1 setiap bulan (via cron). Owner juga dapat men-trigger secara manual jika diperlukan."),
    bullet("Sistem membuat invoice SPP untuk setiap murid berstatus Aktif dengan primary enrollment aktif"),
    bullet("Nomor invoice format: INV/YYYY/MM/NNNN (reset per bulan)"),
    bullet("Jatuh tempo: tanggal 10 bulan berjalan"),
    bullet("Proses idempotent: tidak membuat duplikat jika invoice sudah ada"),
    h3("Trigger Manual:"),
    bullet("Masuk ke halaman Invoice"),
    bullet("Klik 'Generate SPP [Bulan Ini]'"),
    bullet("Konfirmasi — proses dimulai"),
    note("Murid dengan status Cuti (enrollment ON_LEAVE) tetap membayar SPP. Sesi yang tidak dijalankan selama cuti tidak mengurangi tagihan."),
    emptyLine(),

    h2("6.3 Denda Keterlambatan"),
    para("Denda dikenakan secara otomatis (cron harian) mulai tanggal 11 untuk invoice yang belum lunas."),
    bullet("Besaran denda: Rp 5.000 per hari"),
    bullet("Mulai dihitung dari tanggal 11 (bukan tanggal 10)"),
    bullet("Denda terakumulasi setiap hari hingga invoice lunas"),
    bullet("Denda ditampilkan sebagai item DENDA di dalam invoice"),
    h3("Apply Denda Manual:"),
    bullet("Masuk ke halaman Invoice"),
    bullet("Klik 'Apply Denda Hari Ini'"),
    bullet("Sistem menambahkan item denda ke semua invoice UNPAID/PARTIAL yang sudah melewati tanggal 10"),
    emptyLine(),

    h2("6.4 Detail Invoice"),
    para("Klik nomor invoice untuk melihat detail. Halaman detail menampilkan:"),
    bullet("Ringkasan finansial: total tagihan, sudah dibayar, sisa"),
    bullet("Tabel item invoice (SPP, REG, DENDA, DISKON, dll)"),
    bullet("Riwayat pembayaran (tanggal, metode, jumlah, bukti, kasir)"),
    bullet("Tombol: Catat Pembayaran, Tambah Item Manual, Cetak Invoice"),
    emptyLine(),

    h2("6.5 Catat Pembayaran"),
    para("Untuk mencatat pembayaran dari murid:"),
    bullet("Buka detail invoice"),
    bullet("Klik 'Catat Pembayaran'"),
    bullet("Isi form: tanggal pembayaran, jumlah yang dibayar, metode"),
    bullet("Metode yang tersedia: CASH, TRANSFER, QRIS, DEBIT"),
    bullet("Upload bukti pembayaran (foto/screenshot) — opsional tapi disarankan untuk non-CASH"),
    bullet("Tambahkan catatan jika perlu"),
    bullet("Klik Simpan"),
    para("Setelah disimpan:"),
    bullet("Status invoice otomatis update: PARTIAL jika belum lunas, PAID jika sudah lunas"),
    bullet("Nomor kuitansi dibuat otomatis: KW/YYYY/MM/NNNN"),
    bullet("Kuitansi siap dicetak"),
    emptyLine(),

    h2("6.6 Cetak Invoice dan Kuitansi"),
    bullet("Cetak Invoice: klik 'Cetak Invoice' di halaman detail invoice — format A4 PDF"),
    bullet("Cetak Kuitansi: klik 'Cetak Kuitansi' setelah pembayaran dicatat — format A4 atau setengah halaman"),
    bullet("Kuitansi menampilkan: nama murid, nomor kuitansi, tanggal, metode, jumlah terbilang, tanda tangan"),
    emptyLine(),

    h2("6.7 Beri Diskon"),
    para("Owner dan Admin dapat menambahkan diskon ke item tertentu dalam invoice yang masih UNPAID atau PARTIAL."),
    h3("Cara Memberi Diskon:"),
    bullet("Buka detail invoice"),
    bullet("Klik ikon diskon atau 'Tambah Diskon' di baris item yang ingin didiskon"),
    bullet("Pilih tipe diskon: NOMINAL (Rp flat) atau PERCENT (% dari nilai item)"),
    bullet("Masukkan nilai diskon"),
    bullet("Wajib isi Alasan Diskon (field teks)"),
    bullet("Klik Simpan"),
    note("Diskon hanya bisa ditambahkan ke invoice UNPAID atau PARTIAL. Invoice yang sudah PAID tidak bisa diberi diskon."),
    ownerOnly("Hapus atau void item diskon hanya dapat dilakukan oleh Owner."),
    emptyLine(),

    h2("6.8 VOID Pembayaran — OWNER ONLY"),
    ownerOnly("Void pembayaran adalah aksi permanen yang hanya dapat dilakukan oleh Owner."),
    para("Void pembayaran digunakan jika ada kesalahan pencatatan pembayaran (misalnya: nominal salah, murid yang salah, metode pembayaran keliru)."),
    h3("Cara Void Pembayaran:"),
    bullet("Buka detail invoice"),
    bullet("Di bagian Riwayat Pembayaran, temukan baris pembayaran yang ingin di-void"),
    bullet("Klik tombol 'Void' (hanya muncul untuk Owner)"),
    bullet("Isi alasan void pada form yang muncul — wajib diisi"),
    bullet("Konfirmasi tindakan"),
    h3("Dampak Void Pembayaran:"),
    bullet("Status invoice kembali ke kondisi sebelum pembayaran tersebut (UNPAID atau PARTIAL)"),
    bullet("Kuitansi yang terkait menampilkan watermark VOID berwarna merah"),
    bullet("Entri audit log dicatat: siapa yang void, kapan, alasan"),
    warning("Void pembayaran tidak bisa dibatalkan. Jika perlu membayar ulang, catat pembayaran baru setelah void."),
    emptyLine(),

    h2("6.9 Invoice Final Project Kids Class (KIDS_FP)"),
    para("Untuk murid Kids Class yang memasuki bulan ke-6 program, tersedia tombol khusus untuk generate invoice Final Project senilai Rp 140.000 per murid."),
    h3("Cara Generate Invoice KIDS_FP:"),
    bullet("Buka halaman detail murid Kids Class"),
    bullet("Masuk ke tab Tagihan"),
    bullet("Klik tombol 'Generate Invoice Final Project'"),
    bullet("Sistem menampilkan modal konfirmasi dengan detail: nama murid, jumlah Rp 140.000, periode"),
    bullet("Klik Konfirmasi — invoice KIDS_FP dibuat"),
    note("Sistem mencegah pembuatan invoice KIDS_FP duplikat. Tombol tidak muncul jika invoice sudah pernah dibuat."),
    emptyLine(),

    h2("6.10 Kids Class Bundle Cicilan"),
    para("Murid Kids Class dapat memilih membayar secara cicilan 3 termin (total program 6 bulan Rp 2.180.000 dibagi ke 3 termin):"),
    bullet("Termin 1 (Bulan ke-1): Rp 726.667"),
    bullet("Termin 2 (Bulan ke-2): Rp 726.667"),
    bullet("Termin 3 (Bulan ke-4): Rp 726.666"),
    para("Ketiga invoice cicilan diikat oleh installment_group_id yang sama. Di halaman tagihan murid, tersedia progress bar yang menampilkan berapa termin sudah lunas."),
    note("Cicilan hanya tersedia untuk paket KIDS_CLASS_BUNDLE, bukan KIDS_CLASS reguler."),
    pageBreak(),
  ];
}

// ============================================================
// BAB 7: PENGELUARAN DAN KAS
// ============================================================
function makeBab7() {
  return [
    h1("BAB 7: PENGELUARAN DAN KAS"),
    emptyLine(),

    h2("7.1 Daftar Pengeluaran"),
    para("Halaman /expenses menampilkan semua pengeluaran studio yang telah dicatat. Panel ringkasan menampilkan:"),
    bullet("Total pengeluaran bulan ini"),
    bullet("Saldo kas saat ini"),
    bullet("Breakdown per kategori (Sewa, Listrik, Gaji Staff, Peralatan, dll)"),
    h3("Filter Daftar Pengeluaran:"),
    bullet("Filter tanggal / rentang tanggal"),
    bullet("Filter kategori"),
    bullet("Pencarian deskripsi"),
    emptyLine(),

    h2("7.2 Catat Pengeluaran"),
    para("Klik 'Tambah Pengeluaran' untuk mencatat pengeluaran baru. Field yang diisi:"),
    bullet("Tanggal pengeluaran (wajib)"),
    bullet("Kategori (dropdown: Sewa, Listrik, Gaji Staff, Peralatan, Operasional, Lain-lain)"),
    bullet("Deskripsi / keterangan (wajib)"),
    bullet("Jumlah / nominal Rp (wajib)"),
    bullet("Foto bukti / nota (opsional, maks 2MB, format JPG/PNG/PDF)"),
    bullet("Catatan tambahan"),
    note("Pengeluaran yang sudah dicatat akan langsung masuk ke kalkulasi P&L di laporan keuangan."),
    emptyLine(),

    h2("7.3 Edit Pengeluaran"),
    para("Klik ikon edit pada baris pengeluaran untuk mengubah data. Semua field dapat diubah selama pengeluaran belum terkait dengan slip honor."),
    emptyLine(),

    h2("7.4 Hapus Pengeluaran — OWNER ONLY"),
    ownerOnly("Hapus pengeluaran hanya dapat dilakukan oleh Owner."),
    bullet("Klik ikon hapus pada baris pengeluaran"),
    bullet("Konfirmasi penghapusan"),
    bullet("Pengeluaran dihapus permanen dari sistem"),
    warning("Pengeluaran yang sudah terkait dengan slip honor guru tidak dapat dihapus. Nonaktifkan atau edit datanya jika perlu koreksi."),
    pageBreak(),
  ];
}

// ============================================================
// BAB 8: SLIP HONOR GURU
// ============================================================
function makeBab8() {
  return [
    h1("BAB 8: SLIP HONOR GURU"),
    emptyLine(),
    para("Modul Slip Honor mengelola kalkulasi dan pembayaran honor semua guru. Seluruh aksi utama (hitung, edit, tandai dibayar) adalah hak eksklusif Owner."),
    emptyLine(),

    h2("8.1 Hak Akses Owner di Slip Honor"),
    bullet("Melihat daftar semua slip honor semua guru (Owner + Admin + Auditor)"),
    bullet("Menghitung honor bulanan (Owner only)"),
    bullet("Mengedit komponen honor manual: event, transport, lain-lain (Owner only)"),
    bullet("Mencetak slip honor (Owner + Admin)"),
    bullet("Menandai honor sebagai dibayarkan / PAID (Owner only)"),
    emptyLine(),

    h2("8.2 Daftar Slip Honor"),
    para("Halaman /honors menampilkan daftar slip honor dengan statistik lengkap:"),
    bullet("Total honor bulan ini (semua guru)"),
    bullet("Sudah dibayarkan (nominal dan jumlah guru)"),
    bullet("Belum dibayarkan (nominal dan jumlah guru)"),
    bullet("Guru yang belum dibuatkan slip honor bulan ini"),
    h3("Filter:"),
    bullet("Filter bulan/tahun"),
    bullet("Filter guru"),
    bullet("Filter status: DRAFT / CALCULATED / PAID"),
    emptyLine(),

    h2("8.3 Hitung Honor Bulanan"),
    ownerOnly("Tombol Hitung Honor hanya muncul untuk Owner."),
    para("Klik tombol 'Hitung Honor [Bulan]' untuk memulai kalkulasi honor semua guru untuk bulan yang dipilih."),
    h3("Proses Kalkulasi:"),
    bullet("Sistem membaca semua sesi yang memiliki honor_code untuk bulan tersebut"),
    bullet("Mengelompokkan honor per guru berdasarkan kode honor"),
    bullet("Membuat atau memperbarui slip honor (SLIP/YYYY/MM/NNNN) per guru"),
    bullet("Sesi setelah tanggal cut-off (H-2 sebelum akhir bulan) masuk ke bulan berikutnya"),
    bullet("Slip dengan status PAID tidak disentuh (terlindungi dari re-kalkulasi)"),
    note("Proses idempotent: menjalankan kalkulasi dua kali di bulan yang sama aman — hanya memperbarui slip yang belum PAID."),
    emptyLine(),

    h2("8.4 Detail Slip Honor"),
    para("Klik nomor slip untuk melihat detail. Halaman menampilkan:"),
    bullet("Ringkasan komponen honor: pokok (auto), event, transport, lain-lain, total"),
    bullet("Informasi bank guru di bagian atas (nama bank, nomor rekening, atas nama)"),
    bullet("Breakdown honor per kode honor (H_REG, H_KIDS, H_HANGUS, dll)"),
    bullet("Collapsible tabel detail sesi per murid: tanggal, murid, kode honor, nominal"),
    emptyLine(),

    h2("8.5 Edit Komponen Manual — OWNER ONLY"),
    ownerOnly("Form edit komponen manual hanya tersedia untuk Owner."),
    para("Setelah slip dihitung, Owner dapat menambahkan komponen honor manual:"),
    bullet("Honor Event + Keterangan event: diisi jika guru mendapat honor event terpisah dari absensi sesi"),
    bullet("Honor Transport: diisi secara manual (tidak ada formula otomatis)"),
    bullet("Honor Lain-lain + Keterangan wajib: field keterangan WAJIB diisi jika nominal lain-lain > 0"),
    bullet("Preview total honor real-time saat mengisi form"),
    bullet("Klik Simpan untuk memperbarui slip"),
    warning("Setelah slip ditandai PAID, komponen tidak dapat diubah lagi. Pastikan semua komponen benar sebelum menandai dibayar."),
    emptyLine(),

    h2("8.6 Cetak Slip Honor"),
    para("Klik 'Cetak Slip Honor' untuk membuka versi cetak. Format cetak menampilkan:"),
    bullet("Header: nama studio, periode slip, nomor slip"),
    bullet("Informasi bank guru di header (nama bank, nomor rekening, atas nama)"),
    bullet("Tabel komponen honor: pokok, event, transport, lain-lain"),
    bullet("Rincian honor per murid: tanggal sesi, murid, kode honor, nominal"),
    bullet("Total honor keseluruhan"),
    bullet("Kolom tanda tangan"),
    emptyLine(),

    h2("8.7 Tandai Dibayarkan — OWNER ONLY"),
    ownerOnly("Aksi Tandai Dibayarkan hanya bisa dilakukan oleh Owner."),
    para("Setelah honor guru dibayarkan secara tunai atau transfer:"),
    bullet("Buka detail slip honor"),
    bullet("Klik 'Tandai Dibayarkan'"),
    bullet("Isi tanggal pembayaran dan metode"),
    bullet("Konfirmasi"),
    bullet("Status slip berubah ke PAID — slip terkunci dari edit"),
    warning("Tindakan ini tidak dapat dibatalkan. Setelah PAID, slip honor tidak bisa diubah atau di-reset kembali ke DRAFT."),
    emptyLine(),

    h2("8.8 Kode Honor dan Formula"),
    makeTable(
      ["Kode", "Skenario", "Formula / Nominal"],
      [
        ["H_REG", "Sesi terlaksana (murid hadir atau terlambat)", "harga_paket × 50% ÷ 4"],
        ["H_TRIAL", "Sesi trial — murid HADIR", "Sama dengan H_REG sesuai paket calon"],
        ["TRIAL_NS", "Sesi trial — murid NO-SHOW", "Rp 0 (honor nol)"],
        ["H_VIDEO", "Izin video pengganti", "Sama dengan H_REG"],
        ["H_LIBUR", "Sesi libur nasional (tanpa pengganti)", "H_REG penuh (BR-4.10)"],
        ["H_HANGUS", "Murid no-show / tidak hadir tanpa izin cukup", "H_REG penuh"],
        ["H_PENG", "Guru pengganti mengajar", "H_REG ke guru pengganti"],
        ["H_KIDS", "Sesi Kids Class", "jumlah_murid × Rp 42.500"],
        ["H_UJIAN", "Pengawas ujian grade", "Rp 250.000 flat per ujian"],
        ["H_IZIN", "Sesi original saat murid izin reschedule", "Rp 0 (honor dibayar via sesi pengganti)"],
      ]
    ),
    emptyLine(),

    h2("8.9 Cut-off Honor"),
    para("Honor guru dikalkulasi berdasarkan cut-off tanggal H-2 sebelum akhir bulan. Contoh:"),
    bullet("Bulan dengan 31 hari: cut-off tanggal 29"),
    bullet("Bulan dengan 30 hari: cut-off tanggal 28"),
    bullet("Februari (28 hari): cut-off tanggal 26"),
    para("Sesi yang terjadi setelah tanggal cut-off akan dimasukkan ke perhitungan honor bulan berikutnya, bukan bulan berjalan."),
    note("Aturan cut-off ini berlaku untuk semua guru dan semua kode honor. Tidak ada pengecualian."),
    pageBreak(),
  ];
}

// ============================================================
// BAB 9: EVENT STUDIO
// ============================================================
function makeBab9() {
  return [
    h1("BAB 9: EVENT STUDIO"),
    emptyLine(),
    para("Modul Event mengelola Mini Concert dan Ujian Grade studio. Owner memiliki akses penuh termasuk membuat, menyelesaikan, dan input hasil ujian."),
    emptyLine(),

    h2("9.1 Daftar Event"),
    para("Halaman /events menampilkan semua event yang pernah dibuat. Kolom: nama event, tipe, tanggal, status (DRAFT / COMPLETED), jumlah peserta."),
    emptyLine(),

    h2("9.2 Buat Event Baru — OWNER ONLY"),
    ownerOnly("Membuat event baru hanya bisa dilakukan oleh Owner."),
    para("Klik 'Buat Event Baru' dan isi form:"),
    bullet("Nama Event (wajib)"),
    bullet("Tipe Event: Mini Concert | Mini Concert + Ujian | Ujian Grade"),
    bullet("Tanggal Pelaksanaan (wajib)"),
    bullet("Catatan tambahan"),
    bullet("Klik Simpan — event dibuat dengan status DRAFT"),
    emptyLine(),

    h2("9.3 Tambah Peserta"),
    para("Owner dan Admin dapat menambahkan peserta ke event yang masih DRAFT:"),
    bullet("Buka detail event"),
    bullet("Klik 'Tambah Peserta'"),
    bullet("Cari dan pilih murid"),
    bullet("Pilih tipe partisipasi:"),
    bullet("  — Ujian + Tampil: Rp 395.000 (invoice UJI dibuat otomatis)", 1),
    bullet("  — Tampil Saja: Rp 295.000 (invoice MC dibuat otomatis)", 1),
    bullet("Klik Simpan — murid ditambahkan sebagai peserta, invoice otomatis dibuat"),
    emptyLine(),

    h2("9.4 Assign Guru Pendamping"),
    para("Untuk event Konser KITA, setiap peserta dapat memiliki guru pendamping yang menemani di atas panggung."),
    bullet("Buka detail event (harus status DRAFT)"),
    bullet("Di tabel peserta, temukan kolom 'Guru Pendamping'"),
    bullet("Klik dropdown per peserta dan pilih guru"),
    bullet("NULL berarti tidak ada pendamping atau guru tidak bisa hadir"),
    bullet("Data ini digunakan saat event diselesaikan untuk inject honor H_PENDAMPING ke slip honor guru"),
    note("Guru pendamping hanya bisa diubah selama event masih DRAFT. Setelah event COMPLETED, data terkunci."),
    emptyLine(),

    h2("9.5 Hapus Peserta"),
    para("Owner dan Admin dapat menghapus peserta dari event yang masih DRAFT:"),
    bullet("Klik ikon hapus di baris peserta"),
    bullet("Konfirmasi penghapusan"),
    bullet("Invoice yang sudah dibuat untuk peserta tersebut harus di-void secara manual jika sudah ada pembayaran"),
    emptyLine(),

    h2("9.6 Selesaikan Event — OWNER ONLY"),
    ownerOnly("Menyelesaikan event hanya bisa dilakukan oleh Owner."),
    bullet("Buka detail event (status DRAFT)"),
    bullet("Klik 'Selesaikan Event'"),
    bullet("Konfirmasi tindakan"),
    bullet("EventHonorService berjalan: menginjeksi honor pendamping (H_PENDAMPING Rp 250.000) ke slip honor masing-masing guru pendamping"),
    bullet("Status event berubah ke COMPLETED — tidak bisa di-undo"),
    warning("Pastikan semua data peserta dan guru pendamping sudah benar sebelum menyelesaikan event. Tindakan ini tidak bisa dibatalkan."),
    emptyLine(),

    h2("9.7 Input Hasil Ujian"),
    para("Setelah event selesai, Owner dapat menginput hasil ujian per peserta:"),
    bullet("Buka detail event (status COMPLETED)"),
    bullet("Di tabel peserta, isi kolom Hasil Ujian: Lulus / Tidak Lulus"),
    bullet("Jika Lulus: grade murid otomatis naik satu level (contoh: Level 1 → Level 2)"),
    bullet("Catatan ujian dapat diisi per peserta"),
    bullet("Klik Simpan per baris"),
    note("Grade naik otomatis hanya berlaku untuk paket tipe REGULER yang memiliki grade (Basic, Level 1-4). Paket HOBBY tidak memiliki grade."),
    emptyLine(),

    h2("9.8 Link ke Slip Honor"),
    para("Honor pengawas ujian (H_UJIAN Rp 250.000 flat per sesi ujian) dimasukkan ke slip honor guru yang bertugas sebagai pengawas. Cek slip honor bulan yang sama dengan event untuk melihat komponen H_UJIAN."),
    pageBreak(),
  ];
}

// ============================================================
// BAB 10: IMPORT DATA MURID
// ============================================================
function makeBab10() {
  return [
    h1("BAB 10: IMPORT DATA MURID"),
    emptyLine(),

    h2("10.1 Kapan Digunakan"),
    para("Fitur Import digunakan untuk migrasi massal data murid dari sistem lama (Excel) ke sistem Musik KITA. Cocok digunakan saat:"),
    bullet("Pertama kali sistem dipakai — migrate 300+ murid sekaligus"),
    bullet("Ada data batch baru yang perlu dimasukkan"),
    bullet("Re-import data setelah koreksi di file Excel"),
    emptyLine(),

    h2("10.2 Download Template Excel"),
    bullet("Masuk ke halaman /import"),
    bullet("Klik 'Download Template Excel'"),
    bullet("File template berisi kolom-kolom yang diperlukan beserta contoh data dan keterangan format"),
    bullet("Isi template dengan data murid — ikuti format kolom dengan tepat"),
    note("Gunakan template yang disediakan sistem. Jangan modifikasi nama kolom atau urutan kolom."),
    emptyLine(),

    h2("10.3 Upload dan Validasi"),
    bullet("Di halaman /import, klik 'Upload File Excel'"),
    bullet("Pilih file Excel yang sudah diisi"),
    bullet("Sistem memvalidasi setiap baris dan menampilkan preview:"),
    bullet("  — Hijau: Baris baru (akan ditambahkan)", 1),
    bullet("  — Kuning: Baris overwrite (data sudah ada, akan diperbarui)", 1),
    bullet("  — Merah: Baris error (ada data yang tidak valid, tidak akan diimport)", 1),
    bullet("Tinjau preview sebelum konfirmasi"),
    emptyLine(),

    h2("10.4 Konfirmasi Import"),
    bullet("Setelah meninjau preview, klik 'Konfirmasi Import'"),
    bullet("Sistem memproses semua baris valid"),
    bullet("Hasil import ditampilkan: berhasil, dilewati, error"),
    warning("Import tidak bisa dibatalkan setelah dikonfirmasi. Data yang sudah masuk harus dihapus manual jika ada kesalahan massal."),
    emptyLine(),

    h2("10.5 Tips Import"),
    bullet("Baris dengan error dilewati — baris lain yang valid tetap diimport"),
    bullet("Perbaiki baris error di file Excel, lalu upload ulang — baris yang sudah berhasil tidak akan duplikat"),
    bullet("Pastikan guru dan paket yang direferensikan di Excel sudah ada di master data sebelum import"),
    bullet("Backup data sistem sebelum import massal besar"),
    pageBreak(),
  ];
}

// ============================================================
// BAB 11: MASTER DATA
// ============================================================
function makeBab11() {
  return [
    h1("BAB 11: MASTER DATA"),
    emptyLine(),
    para("Menu Master Data berisi konfigurasi dasar sistem. Beberapa sub-menu hanya bisa diakses Owner, beberapa bisa diakses Owner dan Admin."),
    emptyLine(),

    h2("11.1 Guru (Owner + Admin)"),
    para("Halaman /teachers menampilkan grid kartu semua guru aktif dan nonaktif."),
    h3("Tambah Guru Baru:"),
    bullet("Klik 'Tambah Guru'"),
    bullet("Isi form: nama (wajib), kode guru, email, nomor HP"),
    bullet("Informasi bank (untuk slip honor cetak): nama bank, nomor rekening, atas nama"),
    bullet("Tanggal bergabung"),
    bullet("Catatan"),
    h3("Edit Guru:"),
    bullet("Klik ikon edit di kartu guru"),
    bullet("Semua field dapat diubah termasuk informasi bank"),
    h3("Set Matriks Instrumen:"),
    bullet("Di halaman detail guru, ada tabel matriks instrumen"),
    bullet("Centang instrumen yang dapat diajarkan guru tersebut"),
    bullet("Matriks ini digunakan untuk filter guru saat tambah enrollment (hanya guru yang bisa mengajar instrumen tersebut yang muncul)"),
    h3("Deaktivasi Guru:"),
    bullet("Klik 'Nonaktifkan' di halaman detail guru"),
    bullet("Guru nonaktif tidak muncul di dropdown saat tambah enrollment atau absensi"),
    warning("Guru yang memiliki riwayat sesi historis TIDAK BISA dihapus permanen. Gunakan fitur Nonaktifkan."),
    emptyLine(),

    h2("11.2 Instrumen (Owner + Admin)"),
    para("Halaman /instruments — CRUD instrumen yang tersedia di studio."),
    bullet("Tambah instrumen baru: nama instrumen"),
    bullet("Edit: ubah nama instrumen"),
    bullet("Lihat relasi: instrumen dipakai oleh paket apa saja"),
    note("Instrumen Saxophone terdaftar di sistem tetapi semua paket Saxophone dinonaktifkan karena tidak ada guru aktif."),
    emptyLine(),

    h2("11.3 Ruangan (Owner + Admin)"),
    para("Halaman /rooms — CRUD ruangan studio."),
    bullet("Kode ruangan (R1-R9)"),
    bullet("Nama ruangan"),
    bullet("Kapasitas"),
    bullet("Supported Instruments (JSON array): instrumen yang bisa diajarkan di ruangan ini, contoh: [Piano, Gitar]"),
    note("Informasi supported_instruments digunakan untuk filter ruangan saat tambah enrollment. Hanya ruangan dengan instrumen yang cocok yang muncul."),
    emptyLine(),

    h2("11.4 Paket Kelas — OWNER ONLY"),
    ownerOnly("Kelola paket kelas hanya bisa dilakukan oleh Owner."),
    para("Halaman /packages — CRUD paket kelas yang tersedia."),
    h3("Field Paket:"),
    bullet("Code — kode unik paket (wajib, contoh: REG-PIANO-L1)"),
    bullet("Instrumen — pilih dari instrumen aktif"),
    bullet("Tipe Kelas: REGULER / HOBBY / DUO / KIDS_CLASS / KIDS_CLASS_BUNDLE"),
    bullet("Grade (hanya untuk REGULER): Basic / Level 1 / Level 2 / Level 3 / Level 4"),
    bullet("Durasi: dalam menit (30 atau 45)"),
    bullet("Harga per bulan (Rp)"),
    bullet("Aktif / Nonaktif"),
    bullet("Urutan tampil (sort_order)"),
    note("Honor per sesi tidak disimpan sebagai kolom. Honor dihitung otomatis via formula Payroll Config: harga_paket × 50% ÷ 4."),
    warning("Mengubah harga paket berlaku untuk invoice yang dibuat SETELAH perubahan. Invoice yang sudah ada tidak berubah. Ubah harga hanya di awal bulan."),
    emptyLine(),

    h2("11.5 Kalender Hari Libur (Owner + Admin)"),
    para("Halaman /holidays — kelola hari libur yang mempengaruhi generate sesi."),
    h3("Field Hari Libur:"),
    bullet("Tanggal (wajib, unik)"),
    bullet("Nama Hari Libur"),
    bullet("Tipe: Nasional | Cuti Bersama | Internal"),
    bullet("Tanggal Pengganti (replacement_date): jika diisi, generator membuat sesi pengganti di tanggal ini"),
    bullet("is_honor_paid: apakah guru dibayar honor untuk sesi LIBUR ini"),
    h3("Aturan Penting:"),
    bullet("replacement_date harus dalam bulan yang sama dengan tanggal libur"),
    bullet("Tipe Internal (Konser KITA, event studio): replacement_date wajib dikosongkan, is_honor_paid otomatis false"),
    bullet("Alpine.js di form holiday otomatis menyarankan minggu ke-5 sebagai tanggal pengganti"),
    bullet("replacement_date bersifat unique — dua hari libur tidak boleh punya tanggal pengganti yang sama"),
    emptyLine(),

    h2("11.6 Konfigurasi Honor / Payroll Config — OWNER ONLY"),
    ownerOnly("Kelola Payroll Config hanya bisa dilakukan oleh Owner."),
    para("Tabel konfigurasi formula perhitungan honor. Setiap kode honor memiliki formula yang tersimpan di sini."),
    h3("Field Payroll Config:"),
    bullet("Code: kode honor (H_REG, H_KIDS, H_UJIAN, dll)"),
    bullet("Tipe: PERCENTAGE / PER_STUDENT / FIXED / CONSTANT"),
    bullet("Value / Rumus: nilai atau formula (contoh: package_price * 0.5 / 4)"),
    bullet("Deskripsi"),
    note("Perubahan formula Payroll Config mempengaruhi semua kalkulasi honor berikutnya. Hati-hati mengubah formula yang sudah berjalan."),
    emptyLine(),

    h2("11.7 Katalog Item Tagihan / Invoice Components — OWNER ONLY"),
    ownerOnly("Kelola Katalog Item Tagihan hanya bisa dilakukan oleh Owner."),
    para("Daftar semua jenis item yang bisa ditambahkan ke invoice secara manual. Admin memilih dari katalog ini saat menambah item ke invoice."),
    h3("Field Invoice Component:"),
    bullet("Code: kode unik UPPERCASE (REG, SPP, KIDS_FP, dll)"),
    bullet("Nama tampilan"),
    bullet("Harga default (Rp) — bisa dioverride saat tambah ke invoice"),
    bullet("Tipe: REGULER / TRIAL / KIDS_FINAL / CUTI / UJIAN / MINI_CONCERT / DENDA"),
    bullet("Aktif / Nonaktif"),
    bullet("Urutan tampil"),
    warning("Item yang sudah dipakai di invoice tidak bisa dihapus. Nonaktifkan saja jika tidak ingin ditampilkan ke Admin."),
    pageBreak(),
  ];
}

// ============================================================
// BAB 12: USER MANAGEMENT
// ============================================================
function makeBab12() {
  return [
    h1("BAB 12: USER MANAGEMENT — OWNER ONLY"),
    emptyLine(),
    ownerOnly("Seluruh fitur User Management hanya bisa diakses oleh Owner."),
    emptyLine(),

    h2("12.1 Daftar User"),
    para("Halaman /users menampilkan semua pengguna sistem. Statistik di atas:"),
    bullet("Total pengguna"),
    bullet("Pengguna aktif"),
    bullet("Pengguna nonaktif"),
    h3("Tabel Daftar User:"),
    bullet("Avatar dan Nama Lengkap"),
    bullet("Username"),
    bullet("Email"),
    bullet("Role (Owner / Admin / Auditor / Guru)"),
    bullet("Status Akun (Aktif / Nonaktif)"),
    bullet("Guru Terhubung (untuk role Guru)"),
    bullet("Tombol aksi: Edit, Reset Password, Aktifkan/Nonaktifkan, Hapus"),
    h3("Filter:"),
    bullet("Pencarian nama, username, email"),
    bullet("Filter role"),
    bullet("Filter status"),
    emptyLine(),

    h2("12.2 Tambah User Baru"),
    bullet("Klik 'Tambah User'"),
    bullet("Nama Lengkap (wajib)"),
    bullet("Username: diisi manual ATAU kosongkan untuk auto-generate dari nama (slug format, contoh: juan-dela-cruz → juan-dela-cruz)"),
    bullet("Email (wajib)"),
    bullet("Role: Owner / Admin / Auditor / Guru"),
    bullet("Untuk role Guru: wajib pilih data Guru dari dropdown (tabel teachers)"),
    bullet("Password awal: minimal 8 karakter"),
    bullet("Klik Simpan — akun dibuat, user bisa langsung login"),
    note("Username harus unik di seluruh sistem. Sistem akan menampilkan error jika username sudah digunakan."),
    emptyLine(),

    h2("12.3 Edit User"),
    bullet("Klik ikon edit pada baris user"),
    bullet("Ubah: nama, username, email, role"),
    bullet("Jika role Guru: ubah link ke data teacher jika diperlukan"),
    bullet("Klik Simpan"),
    emptyLine(),

    h2("12.4 Reset Password User"),
    bullet("Klik 'Reset Password' pada baris user"),
    bullet("Isi password baru (minimal 8 karakter)"),
    bullet("Isi konfirmasi password"),
    bullet("Klik Simpan — password diperbarui"),
    note("Fitur ini digunakan jika user lupa password. User tidak perlu melakukan apapun, password langsung berubah di database."),
    emptyLine(),

    h2("12.5 Aktifkan/Nonaktifkan User"),
    bullet("Klik 'Nonaktifkan' untuk menonaktifkan akun user"),
    bullet("User nonaktif tidak bisa login — muncul pesan 'Akun Anda tidak aktif'"),
    bullet("Klik 'Aktifkan' untuk mengaktifkan kembali akun"),
    warning("Owner tidak bisa mengubah status akun dirinya sendiri. Jika perlu, minta pemilik sistem lain yang memiliki akses Owner."),
    emptyLine(),

    h2("12.6 Hapus User Permanen"),
    para("Penghapusan permanen user hanya bisa dilakukan jika memenuhi dua syarat:"),
    bullet("Syarat 1: Akun sudah dalam status Nonaktif"),
    bullet("Syarat 2: User tidak memiliki riwayat di audit log (tidak pernah melakukan aksi apapun)"),
    bullet("Klik ikon hapus — konfirmasi"),
    bullet("User dihapus permanen"),
    warning("Jika user sudah memiliki riwayat audit log (pernah login, input data, dll), penghapusan permanen tidak diizinkan. Nonaktifkan saja akun tersebut."),
    emptyLine(),

    h2("12.7 Buat Akun Guru via Artisan Command"),
    para("Untuk membuat akun pengguna secara batch untuk semua guru aktif, gunakan artisan command di terminal server:"),
    new Paragraph({
      spacing: { before: 80, after: 80 },
      children: [new TextRun({ text: "php artisan guru:create-accounts", font: "Courier New", size: 22, bold: true })],
    }),
    h3("Perilaku Command:"),
    bullet("Membaca semua guru aktif dari tabel teachers"),
    bullet("Membuat akun user dengan role Guru untuk setiap guru yang belum punya akun"),
    bullet("Format username: slug dari nama guru (contoh: THOMAS → thomas, T. HADI → t-hadi)"),
    bullet("Password awal: sama dengan username"),
    bullet("Output tabel di terminal: nama guru, username, status (baru/sudah ada)"),
    note("Setelah command selesai, informasikan username dan password awal ke masing-masing guru. Minta mereka ganti password saat pertama login."),
    pageBreak(),
  ];
}

// ============================================================
// BAB 13: AUDIT LOG
// ============================================================
function makeBab13() {
  return [
    h1("BAB 13: AUDIT LOG — OWNER ONLY"),
    emptyLine(),
    ownerOnly("Halaman Audit Log hanya bisa diakses oleh Owner."),
    emptyLine(),

    h2("13.1 Tentang Audit Log"),
    para("Audit Log adalah catatan otomatis dari semua aksi penting yang dilakukan di sistem. Setiap kali user melakukan aksi CREATE, UPDATE, DELETE, LOGIN, LOGOUT, PRINT, VOID, atau perubahan lifecycle murid — sistem secara otomatis mencatat siapa, apa, kapan, dan apa yang berubah."),
    para("Audit log bersifat append-only: hanya bisa ditambah, tidak bisa dihapus, tidak bisa diubah."),
    emptyLine(),

    h2("13.2 Membaca Audit Log"),
    para("Halaman /audit-logs menampilkan riwayat lengkap aksi di sistem."),
    h3("Filter Tersedia:"),
    bullet("Filter Aksi: CREATE / UPDATE / DELETE / LOGIN / LOGOUT / PRINT / VOID / LIFECYCLE"),
    bullet("Filter Entitas: Student / Invoice / Payment / Teacher / dll"),
    bullet("Filter User: siapa yang melakukan aksi"),
    bullet("Filter Rentang Tanggal"),
    bullet("Pencarian teks bebas"),
    h3("Kolom Tabel Audit Log:"),
    makeTable(
      ["Kolom", "Keterangan"],
      [
        ["Waktu", "Timestamp aksi (tanggal dan jam)"],
        ["User", "Nama user yang melakukan aksi"],
        ["Aksi", "Badge berwarna: CREATE (hijau), UPDATE (biru), DELETE (merah), VOID (oranye), dll"],
        ["Entitas", "Tipe data yang diubah (Student, Invoice, Payment, dll)"],
        ["Label", "Nama atau identitas rekaman yang diubah (nama murid, nomor invoice, dll)"],
        ["Nilai Lama", "Data sebelum diubah (JSON, dapat di-expand)"],
        ["Nilai Baru", "Data setelah diubah (JSON, dapat di-expand)"],
        ["Catatan", "Keterangan tambahan jika ada"],
      ]
    ),
    emptyLine(),

    h2("13.3 Aturan Penting"),
    bullet("Audit log TIDAK bisa dihapus melalui UI — ini adalah fitur keamanan"),
    bullet("Bahkan Owner tidak bisa menghapus entri audit log"),
    bullet("Jika perlu investigasi data historis, gunakan filter untuk mempersempit"),
    bullet("Audit log LOGIN/LOGOUT mencatat IP address dan user agent browser"),
    pageBreak(),
  ];
}

// ============================================================
// BAB 14: LAPORAN
// ============================================================
function makeBab14() {
  return [
    h1("BAB 14: LAPORAN"),
    emptyLine(),

    h2("14.1 Laporan Keuangan — OWNER ONLY"),
    ownerOnly("Laporan Keuangan hanya bisa diakses oleh Owner."),
    para("Halaman /reports/finance menampilkan laporan keuangan lengkap studio."),
    h3("Filter Periode:"),
    bullet("Pilih bulan dan tahun (default: bulan berjalan)"),
    bullet("Klik 'Tampilkan' untuk refresh data"),
    h3("Ringkasan P&L:"),
    makeTable(
      ["Item", "Warna", "Keterangan"],
      [
        ["Total Pendapatan", "Hijau", "Semua pembayaran invoice yang masuk di periode ini"],
        ["Total Honor Guru", "Merah", "Total honor yang telah dikalkulasi (slip PAID + CALCULATED)"],
        ["Total Pengeluaran", "Merah", "Semua pengeluaran yang dicatat di periode ini"],
        ["Laba Bersih", "Hijau/Merah", "Pendapatan - Honor - Pengeluaran"],
      ]
    ),
    h3("Breakdown Pendapatan per Metode:"),
    bullet("CASH — total dan jumlah transaksi"),
    bullet("TRANSFER — total dan jumlah transaksi"),
    bullet("QRIS — total dan jumlah transaksi"),
    bullet("DEBIT — total dan jumlah transaksi"),
    h3("Breakdown Pendapatan per Kode Item:"),
    bullet("SPP, REG, KIDS_FP, CUTI, UJI, MC, dll"),
    h3("Tabel Honor per Guru:"),
    bullet("Nama guru, total sesi, total honor pokok, komponen tambahan, total slip"),
    h3("Tabel Pengeluaran per Kategori:"),
    bullet("Kategori, jumlah transaksi, total nominal"),
    bullet("Tombol 'Cetak PDF' untuk download laporan"),
    emptyLine(),

    h2("14.2 Laporan Murid (Owner + Admin + Auditor)"),
    para("Halaman /reports/students dapat diakses oleh Owner, Admin, dan Auditor."),
    h3("Kartu Statistik:"),
    bullet("Murid Baru periode ini"),
    bullet("Murid Mundur periode ini"),
    bullet("Total Murid Aktif saat ini"),
    bullet("Total Murid di database"),
    h3("Distribusi Status:"),
    bullet("Donut chart distribusi murid per status (Aktif, Trial, Cuti, Calon, Selesai, Mundur)"),
    h3("Distribusi Instrumen:"),
    bullet("Bar chart atau tabel jumlah murid aktif per instrumen"),
    bullet("Link ke halaman Data Murid dengan filter instrumen yang dipilih"),
    pageBreak(),
  ];
}

// ============================================================
// BAB 15: OTOMATISASI DAN CRON JOB
// ============================================================
function makeBab15() {
  return [
    h1("BAB 15: OTOMATISASI DAN CRON JOB"),
    emptyLine(),
    para("Sistem Musik KITA memiliki beberapa proses otomatis yang berjalan secara terjadwal. Proses-proses ini dikelola oleh Laravel Scheduler dan WAJIB diaktifkan via Windows Task Scheduler agar berjalan."),
    emptyLine(),

    h2("15.1 Proses Otomatis Harian dan Bulanan"),
    makeTable(
      ["Waktu Trigger", "Nama Proses", "Yang Dikerjakan"],
      [
        ["Tgl 1 jam 06:00", "Generate SPP Bulanan", "Membuat invoice SPP untuk semua murid Aktif dengan primary enrollment aktif"],
        ["Tgl 11-31 harian jam 06:00", "Apply Denda Keterlambatan", "Menambahkan item DENDA Rp 5.000 ke semua invoice UNPAID/PARTIAL yang melewati tgl 10"],
        ["Tgl 25 jam 06:00", "Generate Sesi Bulan Berikutnya", "Membuat sesi konkret dari jadwal mingguan aktif untuk bulan berikutnya"],
        ["H-2 Akhir Bulan", "Cut-off Honor", "Sesi setelah tanggal ini masuk ke perhitungan bulan depan"],
        ["Harian jam 06:05", "Cek Tunggakan Murid", "Notifikasi ke Owner dan Admin jika ada murid dengan tunggakan lebih dari 1 bulan"],
      ]
    ),
    emptyLine(),

    h2("15.2 Setup Scheduler Windows (WAJIB)"),
    para("Karena sistem berjalan di Windows (Laragon), Laravel Scheduler harus dijalankan via Windows Task Scheduler. Langkah setup:"),
    bullet("Buka Windows Task Scheduler (cari di Start Menu)"),
    bullet("Klik 'Create Basic Task'"),
    bullet("Nama: Musik KITA Scheduler"),
    bullet("Trigger: Daily, setiap menit (set repeat every 1 minute)"),
    bullet("Action: Start a program"),
    new Paragraph({
      spacing: { before: 80, after: 80 },
      children: [new TextRun({ text: "Program: C:\\laragon\\bin\\php\\php8.3\\php.exe", font: "Courier New", size: 20 })],
    }),
    new Paragraph({
      spacing: { before: 40, after: 80 },
      children: [new TextRun({ text: "Arguments: artisan schedule:run", font: "Courier New", size: 20 })],
    }),
    new Paragraph({
      spacing: { before: 40, after: 80 },
      children: [new TextRun({ text: "Start in: C:\\laragon\\www\\musik-kita-ops", font: "Courier New", size: 20 })],
    }),
    warning("Jika Task Scheduler tidak aktif atau tidak terkonfigurasi dengan benar, SEMUA proses otomatis (generate SPP, denda, sesi) TIDAK akan berjalan. Pastikan ini dikonfigurasi sebelum sistem dipakai live."),
    emptyLine(),

    h2("15.3 Trigger Manual via Artisan"),
    para("Owner dapat menjalankan proses otomatis secara manual melalui terminal Laragon (klik kanan shortcut Laragon → Terminal):"),
    makeTable(
      ["Perintah", "Fungsi"],
      [
        ["php artisan sessions:generate-month", "Generate sesi untuk bulan berikutnya secara manual"],
        ["php artisan invoices:generate-spp", "Generate SPP untuk bulan berjalan secara manual"],
        ["php artisan invoices:apply-fines", "Apply denda keterlambatan untuk semua invoice tertunggak"],
        ["php artisan schedule:run", "Jalankan semua proses terjadwal yang seharusnya berjalan saat ini"],
        ["php artisan schedule:list", "Tampilkan semua jadwal cron yang terdaftar beserta status"],
      ]
    ),
    emptyLine(),

    h2("15.4 Verifikasi Scheduler"),
    para("Untuk memastikan scheduler berjalan dengan benar:"),
    bullet("Buka terminal dan jalankan: php artisan schedule:list"),
    bullet("Output menampilkan semua scheduled commands beserta jadwal dan status terakhir berjalan"),
    bullet("Cek log di storage/logs/laravel.log untuk error terkait scheduler"),
    bullet("Setelah tanggal 1 bulan baru: verifikasi invoice SPP sudah terbuat di halaman Tagihan"),
    pageBreak(),
  ];
}

// ============================================================
// LAMPIRAN
// ============================================================
function makeLampiran() {
  return [
    h1("LAMPIRAN A: MATRIKS HAK AKSES LENGKAP"),
    emptyLine(),
    makeTable(
      ["Fitur / Aksi", "Owner", "Admin", "Auditor", "Guru"],
      [
        // Dashboard
        ["Dashboard — KPI Keuangan", "Ya", "Ya (terbatas)", "Ya (read)", "Tidak"],
        ["Dashboard — Grafik P&L", "Ya", "Tidak", "Tidak", "Tidak"],
        // Murid
        ["Lihat Daftar Murid", "Ya", "Ya", "Ya", "Tidak"],
        ["Tambah Murid Baru", "Ya", "Ya", "Tidak", "Tidak"],
        ["Edit Data Murid", "Ya", "Ya", "Tidak", "Tidak"],
        ["Aksi Lifecycle Murid (Trial/Aktif/Cuti/Mundur)", "Ya", "Ya", "Tidak", "Tidak"],
        ["Lihat Tab Riwayat + Metadata Teknis", "Ya (lengkap)", "Ya (terbatas)", "Ya", "Tidak"],
        // Jadwal
        ["Lihat Kalender Jadwal", "Ya", "Ya", "Ya", "Ya (sendiri)"],
        ["Generate Sesi", "Ya", "Ya", "Tidak", "Tidak"],
        ["Edit Sesi Manual", "Ya", "Ya", "Tidak", "Tidak"],
        // Absensi
        ["Input Absensi", "Ya", "Ya", "Tidak", "Ya (lapor izin)"],
        ["Reschedule Pengganti", "Ya", "Ya", "Tidak", "Tidak"],
        // Keuangan
        ["Lihat Invoice", "Ya", "Ya", "Ya", "Tidak"],
        ["Catat Pembayaran", "Ya", "Ya", "Tidak", "Tidak"],
        ["Void Pembayaran", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Beri Diskon Invoice", "Ya", "Ya", "Tidak", "Tidak"],
        ["Hapus Item Diskon", "Ya", "Tidak", "Tidak", "Tidak"],
        // Honor
        ["Lihat Slip Honor", "Ya", "Ya", "Ya", "Ya (sendiri)"],
        ["Hitung Honor Bulanan", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Edit Komponen Honor Manual", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Tandai Honor Dibayar", "Ya", "Tidak", "Tidak", "Tidak"],
        // Event
        ["Buat Event Baru", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Tambah Peserta Event", "Ya", "Ya", "Tidak", "Tidak"],
        ["Hapus Peserta Event", "Ya", "Ya", "Tidak", "Tidak"],
        ["Assign Guru Pendamping", "Ya", "Ya", "Tidak", "Tidak"],
        ["Selesaikan Event", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Input Hasil Ujian", "Ya", "Tidak", "Tidak", "Tidak"],
        // Pengeluaran
        ["Lihat Pengeluaran", "Ya", "Ya", "Ya", "Tidak"],
        ["Catat / Edit Pengeluaran", "Ya", "Ya", "Tidak", "Tidak"],
        ["Hapus Pengeluaran", "Ya", "Tidak", "Tidak", "Tidak"],
        // Master Data
        ["Kelola Guru", "Ya", "Ya", "Ya (read)", "Tidak"],
        ["Kelola Instrumen", "Ya", "Ya", "Ya (read)", "Tidak"],
        ["Kelola Ruangan", "Ya", "Ya", "Ya (read)", "Tidak"],
        ["Kelola Paket Kelas", "Ya", "Tidak", "Ya (read)", "Tidak"],
        ["Kelola Hari Libur", "Ya", "Ya", "Ya (read)", "Tidak"],
        ["Kelola Payroll Config", "Ya", "Tidak", "Ya (read)", "Tidak"],
        ["Kelola Katalog Item Tagihan", "Ya", "Tidak", "Ya (read)", "Tidak"],
        // User & Audit
        ["Kelola User", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Lihat Audit Log", "Ya", "Tidak", "Tidak", "Tidak"],
        // Laporan
        ["Laporan Keuangan P&L", "Ya", "Tidak", "Tidak", "Tidak"],
        ["Laporan Murid", "Ya", "Ya", "Ya", "Tidak"],
        // Import
        ["Import Data Murid Excel", "Ya", "Ya", "Tidak", "Tidak"],
      ]
    ),
    emptyLine(),
    pageBreak(),

    h1("LAMPIRAN B: STATUS MURID"),
    emptyLine(),
    makeTable(
      ["Status", "Warna Badge", "Keterangan"],
      [
        ["Calon", "Abu-abu", "Sudah mendaftar, belum menjalani trial"],
        ["Trial", "Kuning/Amber", "Sedang dalam atau sudah selesai sesi trial, belum aktif"],
        ["Aktif", "Hijau", "Murid berjalan normal, enrollment aktif, SPP ditagih setiap bulan"],
        ["Cuti", "Biru", "Sedang cuti berbayar (Rp 100.000), sesi di-pause, SPP tetap jalan"],
        ["Selesai", "Ungu", "Lulus Kids Class 6 bulan — transisi ke privat tanpa registrasi ulang"],
        ["Mengundurkan Diri", "Merah", "Keluar dari studio (manual oleh Owner/Admin atau otomatis sistem)"],
      ]
    ),
    emptyLine(),
    pageBreak(),

    h1("LAMPIRAN C: STATUS SESI"),
    emptyLine(),
    makeTable(
      ["Kode Status", "Keterangan", "Honor Guru", "Catatan"],
      [
        ["SCHEDULED", "Sesi terjadwal, belum diisi absensi", "—", "Status default saat sesi dibuat"],
        ["HADIR", "Murid hadir tepat waktu", "H_REG (penuh)", ""],
        ["HADIR_TERLAMBAT", "Murid hadir tapi terlambat", "H_REG (penuh)", "Isi menit keterlambatan"],
        ["IZIN_RESCHEDULE", "Izin sah, berhak dapat sesi pengganti", "H_IZIN (Rp 0)", "Dibayar via sesi pengganti"],
        ["IZIN_VIDEO", "Izin ke-2+ atau tidak memenuhi syarat reschedule", "H_VIDEO (penuh)", "Murid dianggap masuk"],
        ["HANGUS", "Tidak hadir tanpa izin cukup (<5 jam atau tanpa info)", "H_HANGUS (penuh)", "Murid dianggap masuk"],
        ["LIBUR", "Hari libur nasional atau studio", "H_LIBUR (penuh) atau Rp 0 jika Internal", "Set otomatis oleh generator"],
        ["DIGANTI", "Guru diganti, murid tetap belajar dengan guru lain", "H_PENG (ke guru pengganti)", "Wajib isi guru pengganti"],
        ["CANCELLED", "Sesi dibatalkan total (tidak ada pihak hadir)", "Rp 0", "Studio tutup, force majeure, dll"],
      ]
    ),
    emptyLine(),
    pageBreak(),

    h1("LAMPIRAN D: KODE HONOR GURU"),
    emptyLine(),
    makeTable(
      ["Kode", "Skenario", "Formula / Nominal", "Catatan"],
      [
        ["H_REG", "Sesi reguler terlaksana", "harga_paket × 50% ÷ 4", "Berlaku untuk HADIR dan HADIR_TERLAMBAT"],
        ["H_TRIAL", "Sesi trial murid HADIR", "Sama dengan H_REG sesuai paket calon", ""],
        ["TRIAL_NS", "Sesi trial murid NO-SHOW", "Rp 0", "Honor nol untuk no-show trial"],
        ["H_VIDEO", "Izin video pengganti", "Sama dengan H_REG", "Murid dianggap masuk"],
        ["H_LIBUR", "Libur nasional tanpa pengganti", "H_REG penuh", "BR-4.10: guru tetap dibayar"],
        ["H_HANGUS", "Murid no-show / hangus", "H_REG penuh", "Murid dianggap masuk"],
        ["H_PENG", "Guru pengganti mengajar", "H_REG → ke guru pengganti", "Guru utama tidak dapat honor"],
        ["H_KIDS", "Sesi Kids Class", "murid_terdaftar × Rp 42.500", "Tergantung jumlah murid di kelas"],
        ["H_UJIAN", "Pengawas ujian grade", "Rp 250.000 flat", "Per sesi ujian"],
        ["H_IZIN", "Sesi original saat murid izin reschedule", "Rp 0", "Honor dibayar via sesi pengganti"],
      ]
    ),
    emptyLine(),
    pageBreak(),

    h1("LAMPIRAN E: FORMAT NOMOR SERI"),
    emptyLine(),
    makeTable(
      ["Tipe Dokumen", "Format", "Contoh", "Keterangan"],
      [
        ["Invoice SPP / Tagihan", "INV/YYYY/MM/NNNN", "INV/2026/05/0001", "Nomor urut reset setiap bulan"],
        ["Kuitansi Pembayaran", "KW/YYYY/MM/NNNN", "KW/2026/05/0001", "Nomor urut reset setiap bulan"],
        ["Slip Honor Guru", "SLIP/YYYY/MM/NNNN", "SLIP/2026/05/0001", "Nomor urut reset setiap bulan"],
        ["Kode Murid", "M-YYYY-NNNN", "M-2026-0001", "YYYY = tahun daftar, urut global"],
      ]
    ),
    emptyLine(),
    pageBreak(),

    h1("LAMPIRAN F: KOMPONEN TAGIHAN (INVOICE ITEMS)"),
    emptyLine(),
    makeTable(
      ["Kode", "Nama", "Nominal / Formula", "Keterangan"],
      [
        ["REG", "Biaya Registrasi", "Rp 250.000", "Wajib saat murid baru aktif (atau re-aktivasi dari Mundur)"],
        ["SPP", "SPP Bulanan", "Sesuai harga paket", "Generate otomatis tgl 1 setiap bulan"],
        ["KIDS_FP", "Final Project Kids Class", "Rp 140.000 / murid", "Di bulan ke-6 program Kids Class"],
        ["CUTI", "Biaya Cuti", "Rp 100.000 / pengajuan", "Wajib saat ajukan cuti, maks 1x perpanjangan"],
        ["UJI", "Ujian + Mini Concert", "Rp 395.000", "Untuk peserta event yang ikut ujian grade DAN tampil"],
        ["MC", "Mini Concert Saja", "Rp 295.000", "Untuk peserta event yang hanya tampil tanpa ujian"],
        ["DENDA", "Denda Keterlambatan SPP", "Rp 5.000 × (hari - 10)", "Mulai tgl 11, kumulatif harian"],
        ["DISKON", "Diskon Manual", "NOMINAL (Rp) atau PERCENT (%)", "Harus punya parent_item_id + alasan wajib"],
      ]
    ),
    emptyLine(),
    pageBreak(),

    h1("LAMPIRAN G: ATURAN PENTING YANG PERLU DIINGAT"),
    emptyLine(),
    para("Berikut adalah 20 aturan bisnis kritis yang paling sering perlu dirujuk:"),
    emptyLine(),
    bullet("1. Trial SELALU 30 menit untuk semua tipe paket, tanpa kecuali."),
    bullet("2. Honor trial: murid HADIR = bayar penuh; murid NO-SHOW = Rp 0."),
    bullet("3. Void pembayaran hanya bisa dilakukan OWNER, bukan Admin."),
    bullet("4. Slip honor yang sudah PAID tidak bisa diedit atau di-reset."),
    bullet("5. Syarat reschedule: info minimal 5 jam SEBELUM sesi DAN izin pertama di bulan itu."),
    bullet("6. Izin ke-2 atau lebih di bulan yang sama = IZIN_VIDEO (video pengganti, bukan reschedule)."),
    bullet("7. Honor cut-off: H-2 sebelum akhir bulan. Sesi setelah cut-off masuk bulan depan."),
    bullet("8. Murid cuti TETAP bayar SPP. Sesi hanya di-pause."),
    bullet("9. SPP denda: Rp 5.000/hari mulai tanggal 11 (jatuh tempo tanggal 10)."),
    bullet("10. Guru pengganti (DIGANTI): honor ke guru pengganti, bukan guru utama."),
    bullet("11. Libur nasional dengan is_honor_paid=false (tipe Internal): honor guru Rp 0."),
    bullet("12. replacement_date hari libur harus di bulan yang sama. Internal tidak boleh punya replacement_date."),
    bullet("13. Harga paket dibaca LIVE saat invoice dibuat. Tidak ada price lock per enrollment."),
    bullet("14. Ubah harga paket hanya di awal bulan agar tidak mempengaruhi tagihan berjalan."),
    bullet("15. Hapus guru dengan riwayat sesi historis: TIDAK BISA. Gunakan Nonaktifkan."),
    bullet("16. Kids Class: usia 4 tahun hingga kurang dari 5 tahun. Minimal 3 anak per kelas."),
    bullet("17. Audit log tidak bisa dihapus melalui UI — ini adalah fitur keamanan permanen."),
    bullet("18. Invoice KIDS_FP Rp 140.000 hanya bisa dibuat satu kali per murid Kids Class."),
    bullet("19. User nonaktif tidak bisa login. Owner tidak bisa nonaktifkan akun dirinya sendiri."),
    bullet("20. Windows Task Scheduler WAJIB aktif agar semua cron job otomatis berjalan."),
    emptyLine(),
    pageBreak(),

    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { before: 400 },
      children: [new TextRun({ text: "— Akhir Dokumen —", size: 24, italic: true, color: "888888" })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      children: [new TextRun({ text: "User Manual Owner — Musik KITA Operations System v1.4", size: 20, color: "AAAAAA" })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      children: [new TextRun({ text: "Mei 2026", size: 20, color: "AAAAAA" })],
    }),
  ];
}

// ============================================================
// MAIN: ASSEMBLE DOCUMENT
// ============================================================
async function main() {
  console.log("Membuat dokumen User Manual Owner — Musik KITA...");

  const sections = [
    ...makeCover(),
    ...makeTOC(),
    ...makeBab1(),
    ...makeBab2(),
    ...makeBab3(),
    ...makeBab4(),
    ...makeBab5(),
    ...makeBab6(),
    ...makeBab7(),
    ...makeBab8(),
    ...makeBab9(),
    ...makeBab10(),
    ...makeBab11(),
    ...makeBab12(),
    ...makeBab13(),
    ...makeBab14(),
    ...makeBab15(),
    ...makeLampiran(),
  ];

  const doc = new Document({
    creator: "Musik KITA Operations System",
    title: "User Manual Owner — Musik KITA",
    description: "Panduan lengkap penggunaan sistem administrasi studio musik Musik KITA untuk Owner",
    sections: [
      {
        properties: {
          page: {
            margin: {
              top: convertInchesToTwip(1),
              right: convertInchesToTwip(1),
              bottom: convertInchesToTwip(1),
              left: convertInchesToTwip(1.25),
            },
          },
        },
        children: sections,
      },
    ],
  });

  const outputPath = path.join(__dirname, "User-Manual-Owner-Musik-KITA.docx");
  const buffer = await Packer.toBuffer(doc);
  fs.writeFileSync(outputPath, buffer);

  const sizeKB = Math.round(buffer.length / 1024);
  console.log(`SUCCESS: User Manual Owner berhasil dibuat!`);
  console.log(`File   : ${outputPath}`);
  console.log(`Ukuran : ${sizeKB} KB`);
  console.log(`Bab    : 15 bab + 7 lampiran`);
}

main().catch((err) => {
  console.error("ERROR:", err.message);
  process.exit(1);
});
