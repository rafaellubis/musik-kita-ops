"use strict";

/**
 * generate_guru_manual.cjs
 * Generator User Manual GURU — Portal Guru Musik KITA
 *
 * Jalankan dengan: node generate_guru_manual.cjs
 * Output: User-Manual-Guru-Musik-KITA.docx
 */

const fs = require("fs");
const path = require("path");
const {
  Document,
  Packer,
  Paragraph,
  TextRun,
  HeadingLevel,
  Table,
  TableRow,
  TableCell,
  WidthType,
  BorderStyle,
  AlignmentType,
  PageBreak,
  ShadingType,
  VerticalAlign,
  convertInchesToTwip,
} = require("docx");

// ─────────────────────────────────────────────
// CONSTANTS
// ─────────────────────────────────────────────

const COLOR = {
  headerBg: "1B4F72",    // biru tua — header tabel
  headerText: "FFFFFF",  // putih — teks header tabel
  altRowBg: "D6EAF8",    // biru muda — baris alt tabel
  accent: "2E86C1",      // biru aksen — teks berwarna
  warnBg: "FEF9E7",      // kuning muda — kotak warning
  warnBorder: "F39C12",  // oranye — border warning
  noteBg: "EBF5FB",      // biru sangat muda — kotak catatan
  noteBorder: "2E86C1",  // biru — border catatan
  successBg: "EAFAF1",   // hijau muda — kotak sukses
  successBorder: "1E8449",
  bodyText: "1C1C1C",    // teks utama
  mutedText: "5D6D7E",   // teks abu
};

const FONT = {
  body: "Calibri",
  heading: "Calibri",
  mono: "Courier New",
};

const SIZE = {
  h1: 36,       // 18pt
  h2: 28,       // 14pt
  h3: 24,       // 12pt
  body: 22,     // 11pt
  small: 20,    // 10pt
  caption: 18,  // 9pt
};

// ─────────────────────────────────────────────
// HELPER: PARAGRAPH BUILDERS
// ─────────────────────────────────────────────

/**
 * Membuat heading level 1 (BAB)
 */
function h1(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 400, after: 200 },
    children: [
      new TextRun({
        text,
        bold: true,
        size: SIZE.h1,
        font: FONT.heading,
        color: COLOR.headerBg,
      }),
    ],
  });
}

/**
 * Membuat heading level 2 (sub-bab)
 */
function h2(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 300, after: 120 },
    children: [
      new TextRun({
        text,
        bold: true,
        size: SIZE.h2,
        font: FONT.heading,
        color: COLOR.accent,
      }),
    ],
  });
}

/**
 * Membuat heading level 3 (sub-sub-bab)
 */
function h3(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_3,
    spacing: { before: 200, after: 100 },
    children: [
      new TextRun({
        text,
        bold: true,
        size: SIZE.h3,
        font: FONT.heading,
        color: COLOR.bodyText,
      }),
    ],
  });
}

/**
 * Paragraf teks biasa.
 * @param {string|Array} textOrRuns - string biasa atau array TextRun
 */
function para(textOrRuns, options = {}) {
  const runs =
    typeof textOrRuns === "string"
      ? [
          new TextRun({
            text: textOrRuns,
            size: SIZE.body,
            font: FONT.body,
            color: COLOR.bodyText,
          }),
        ]
      : textOrRuns;

  return new Paragraph({
    spacing: { before: 80, after: 80 },
    alignment: options.align || AlignmentType.JUSTIFIED,
    children: runs,
  });
}

/**
 * Paragraf bullet / list.
 * @param {string} text
 * @param {number} level - 0 = level pertama, 1 = sub-bullet
 */
function bullet(text, level = 0) {
  return new Paragraph({
    bullet: { level },
    spacing: { before: 60, after: 60 },
    indent: { left: convertInchesToTwip(0.25 + level * 0.25) },
    children: [
      new TextRun({
        text,
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.bodyText,
      }),
    ],
  });
}

/**
 * Paragraf bullet dengan teks bold di awal.
 * @param {string} boldPart - teks bold
 * @param {string} rest - lanjutan teks biasa
 * @param {number} level
 */
function bulletBold(boldPart, rest, level = 0) {
  return new Paragraph({
    bullet: { level },
    spacing: { before: 60, after: 60 },
    indent: { left: convertInchesToTwip(0.25 + level * 0.25) },
    children: [
      new TextRun({
        text: boldPart,
        bold: true,
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.bodyText,
      }),
      new TextRun({
        text: rest,
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.bodyText,
      }),
    ],
  });
}

/**
 * Kotak catatan (biru muda).
 */
function note(text) {
  return new Paragraph({
    spacing: { before: 120, after: 120 },
    indent: { left: convertInchesToTwip(0.2), right: convertInchesToTwip(0.2) },
    shading: {
      type: ShadingType.CLEAR,
      fill: COLOR.noteBg,
    },
    border: {
      left: { style: BorderStyle.THICK, size: 12, color: COLOR.noteBorder },
    },
    children: [
      new TextRun({
        text: "📝 Catatan: ",
        bold: true,
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.accent,
      }),
      new TextRun({
        text,
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.bodyText,
      }),
    ],
  });
}

/**
 * Kotak peringatan (kuning).
 */
function warning(text) {
  return new Paragraph({
    spacing: { before: 120, after: 120 },
    indent: { left: convertInchesToTwip(0.2), right: convertInchesToTwip(0.2) },
    shading: {
      type: ShadingType.CLEAR,
      fill: COLOR.warnBg,
    },
    border: {
      left: { style: BorderStyle.THICK, size: 12, color: COLOR.warnBorder },
    },
    children: [
      new TextRun({
        text: "⚠️  Perhatian: ",
        bold: true,
        size: SIZE.body,
        font: FONT.body,
        color: "C0392B",
      }),
      new TextRun({
        text,
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.bodyText,
      }),
    ],
  });
}

/**
 * Kotak tips / sukses (hijau).
 */
function tip(text) {
  return new Paragraph({
    spacing: { before: 120, after: 120 },
    indent: { left: convertInchesToTwip(0.2), right: convertInchesToTwip(0.2) },
    shading: {
      type: ShadingType.CLEAR,
      fill: COLOR.successBg,
    },
    border: {
      left: { style: BorderStyle.THICK, size: 12, color: COLOR.successBorder },
    },
    children: [
      new TextRun({
        text: "✅  Tips: ",
        bold: true,
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.successBorder,
      }),
      new TextRun({
        text,
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.bodyText,
      }),
    ],
  });
}

/**
 * Garis kosong / spasi antar bagian.
 */
function emptyLine() {
  return new Paragraph({ spacing: { before: 80, after: 80 }, children: [] });
}

/**
 * Page break eksplisit.
 */
function pageBreak() {
  return new Paragraph({
    children: [new PageBreak()],
  });
}

/**
 * Teks inline bold dalam paragraf normal.
 */
function bold(text) {
  return new TextRun({
    text,
    bold: true,
    size: SIZE.body,
    font: FONT.body,
    color: COLOR.bodyText,
  });
}

/**
 * Teks inline biasa dalam paragraf campuran.
 */
function run(text) {
  return new TextRun({
    text,
    size: SIZE.body,
    font: FONT.body,
    color: COLOR.bodyText,
  });
}

/**
 * Teks inline berwarna aksen.
 */
function runAccent(text) {
  return new TextRun({
    text,
    size: SIZE.body,
    font: FONT.body,
    color: COLOR.accent,
    bold: true,
  });
}

/**
 * Teks kode inline (monospace).
 */
function code(text) {
  return new TextRun({
    text,
    font: FONT.mono,
    size: SIZE.small,
    color: "C0392B",
    shading: { type: ShadingType.CLEAR, fill: "F2F3F4" },
  });
}

// ─────────────────────────────────────────────
// HELPER: TABLE BUILDER
// ─────────────────────────────────────────────

/**
 * Membuat tabel dengan header berwarna.
 *
 * @param {string[]} headers - judul kolom
 * @param {Array<string[]>} rows - baris data (array of array of string)
 * @param {number[]} widths - lebar kolom dalam persen (total 100)
 */
function makeTable(headers, rows, widths) {
  const totalWidth = 9360; // twip untuk lebar halaman A4 minus margin

  // Hitung lebar tiap kolom
  const colWidths = widths
    ? widths.map((w) => Math.round((w / 100) * totalWidth))
    : headers.map(() => Math.round(totalWidth / headers.length));

  // Baris header
  const headerRow = new TableRow({
    tableHeader: true,
    children: headers.map((h, i) =>
      new TableCell({
        width: { size: colWidths[i], type: WidthType.DXA },
        shading: { type: ShadingType.CLEAR, fill: COLOR.headerBg },
        verticalAlign: VerticalAlign.CENTER,
        children: [
          new Paragraph({
            alignment: AlignmentType.CENTER,
            spacing: { before: 60, after: 60 },
            children: [
              new TextRun({
                text: h,
                bold: true,
                size: SIZE.small,
                font: FONT.body,
                color: COLOR.headerText,
              }),
            ],
          }),
        ],
      })
    ),
  });

  // Baris data
  const dataRows = rows.map((row, rowIdx) =>
    new TableRow({
      children: row.map((cell, colIdx) =>
        new TableCell({
          width: { size: colWidths[colIdx], type: WidthType.DXA },
          shading:
            rowIdx % 2 === 1
              ? { type: ShadingType.CLEAR, fill: COLOR.altRowBg }
              : { type: ShadingType.CLEAR, fill: "FFFFFF" },
          verticalAlign: VerticalAlign.CENTER,
          children: [
            new Paragraph({
              spacing: { before: 60, after: 60 },
              indent: { left: convertInchesToTwip(0.05) },
              children: [
                new TextRun({
                  text: String(cell),
                  size: SIZE.small,
                  font: FONT.body,
                  color: COLOR.bodyText,
                }),
              ],
            }),
          ],
        })
      ),
    })
  );

  return new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    margins: { top: 60, bottom: 60, left: 80, right: 80 },
    rows: [headerRow, ...dataRows],
  });
}

// ─────────────────────────────────────────────
// KONTEN DOKUMEN
// ─────────────────────────────────────────────

// ── Halaman Judul ──────────────────────────────────────────────────────────

const halamanJudul = [
  emptyLine(),
  emptyLine(),
  emptyLine(),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 400, after: 200 },
    children: [
      new TextRun({
        text: "MUSIK KITA",
        bold: true,
        size: 72,
        font: FONT.heading,
        color: COLOR.headerBg,
      }),
    ],
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 0, after: 80 },
    children: [
      new TextRun({
        text: "Sistem Administrasi Studio Musik",
        size: 32,
        font: FONT.body,
        color: COLOR.mutedText,
      }),
    ],
  }),
  emptyLine(),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 600, after: 200 },
    children: [
      new TextRun({
        text: "USER MANUAL",
        bold: true,
        size: 56,
        font: FONT.heading,
        color: COLOR.accent,
      }),
    ],
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 0, after: 600 },
    children: [
      new TextRun({
        text: "PORTAL GURU",
        bold: true,
        size: 48,
        font: FONT.heading,
        color: COLOR.headerBg,
      }),
    ],
  }),
  emptyLine(),
  emptyLine(),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 400, after: 80 },
    children: [
      new TextRun({
        text: "Panduan Lengkap Penggunaan Aplikasi untuk Guru",
        size: SIZE.h3,
        font: FONT.body,
        color: COLOR.bodyText,
      }),
    ],
  }),
  emptyLine(),
  emptyLine(),
  emptyLine(),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 600, after: 60 },
    children: [
      new TextRun({
        text: "Versi 1.0  |  Mei 2026",
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.mutedText,
      }),
    ],
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 0, after: 60 },
    children: [
      new TextRun({
        text: "Musik KITA — Dokumen Internal",
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.mutedText,
      }),
    ],
  }),
  pageBreak(),
];

// ── BAB 1: PENDAHULUAN ─────────────────────────────────────────────────────

const bab1 = [
  h1("BAB 1 — PENDAHULUAN"),

  h2("1.1  Tentang Portal Guru"),
  para(
    "Portal Guru adalah antarmuka khusus di sistem Musik KITA yang dirancang " +
    "untuk memudahkan guru dalam mengelola kehadiran, melihat jadwal, memantau " +
    "honor, dan berkomunikasi dengan Admin terkait sesi yang tertunda. Portal " +
    "ini dirancang mobile-first, sehingga paling nyaman diakses melalui " +
    "smartphone selama jam operasional studio."
  ),
  emptyLine(),
  para(
    "Sistem Musik KITA memiliki empat peran pengguna: Owner, Admin, Auditor, " +
    "dan Guru. Setiap peran memiliki akses yang berbeda. Guru hanya dapat " +
    "mengakses halaman yang berkaitan dengan kegiatan mengajar mereka sendiri " +
    "— data guru lain tidak dapat dilihat."
  ),

  h2("1.2  Cara Mengakses Portal"),
  para("Portal Guru dapat diakses melalui browser di perangkat apa pun yang terhubung ke jaringan WiFi studio."),
  emptyLine(),
  h3("Alamat Akses"),
  para([
    run("Buka browser (Chrome, Firefox, Safari, atau Edge), kemudian ketik alamat: "),
    code("http://[IP-Studio]/guru/dashboard"),
    run(" — tanyakan alamat IP yang tepat kepada Admin atau Owner."),
  ]),
  note(
    "Sistem Musik KITA berjalan di jaringan lokal (LAN) studio. " +
    "Portal tidak dapat diakses dari luar studio kecuali terhubung ke WiFi yang sama."
  ),
  emptyLine(),
  h3("Perangkat yang Disarankan"),
  bullet("Smartphone Android atau iOS (layar ≥ 5 inci) — paling praktis"),
  bullet("Tablet atau iPad"),
  bullet("Laptop atau PC (semua browser modern didukung)"),

  h2("1.3  Cara Login"),
  para("Ikuti langkah-langkah berikut untuk masuk ke portal:"),
  emptyLine(),
  h3("Langkah Login"),
  bulletBold("Langkah 1 — Buka halaman login:", " Buka browser dan navigasi ke alamat studio. Sistem akan menampilkan halaman login Musik KITA."),
  bulletBold("Langkah 2 — Masukkan Username atau Email:", " Isi kolom pertama dengan username Anda (misalnya: thomas) ATAU alamat email yang terdaftar. Keduanya bisa digunakan."),
  bulletBold("Langkah 3 — Masukkan Password:", " Isi kolom kedua dengan password Anda."),
  bulletBold("Langkah 4 — Klik tombol Masuk:", " Sistem akan memverifikasi identitas Anda dan langsung mengarahkan ke Dashboard Guru."),
  emptyLine(),
  h3("Format Username"),
  para([
    run("Username Anda adalah nama Anda dalam format "),
    bold("huruf kecil semua"),
    run(". Contoh: nama "),
    bold("THOMAS"),
    run(" → username "),
    code("thomas"),
    run(". Nama "),
    bold("T. HADI"),
    run(" → username "),
    code("t-hadi"),
    run(" (titik dan spasi diganti tanda hubung)."),
  ]),
  emptyLine(),
  h3("Password Awal"),
  para([
    run("Password awal Anda "),
    bold("sama dengan username"),
    run(" Anda. Contoh: username "),
    code("thomas"),
    run(", password awal "),
    code("thomas"),
    run(". Segera ganti password setelah login pertama melalui menu Profil."),
  ]),
  warning(
    "Jangan bagikan password kepada siapa pun, termasuk sesama guru. " +
    "Setiap akun guru hanya boleh digunakan oleh satu orang. " +
    "Jika merasa akun dikompromikan, segera hubungi Admin atau Owner."
  ),

  h2("1.4  Hak Akses Guru"),
  para("Sebagai guru, Anda hanya dapat melakukan hal-hal berikut di portal:"),
  emptyLine(),
  makeTable(
    ["Yang BISA Dilakukan", "Yang TIDAK BISA Dilakukan"],
    [
      ["Melihat sesi hari ini (dashboard)", "Mengubah data murid"],
      ["Input status Hadir / Hadir Terlambat", "Input status Izin, Hangus, atau Libur"],
      ["Melihat jadwal minggu berjalan & depan", "Mengubah jadwal mengajar"],
      ["Memberikan saran tanggal sesi pengganti", "Menyetujui reschedule (hak Admin)"],
      ["Melihat slip honor yang sudah dihitung", "Mengubah nominal honor"],
      ["Mengganti password sendiri", "Melihat data guru lain"],
      ["Melihat sesi pending milik Anda", "Membuat atau membatalkan invoice"],
    ],
    [50, 50]
  ),
  emptyLine(),
  note(
    "Input status IZIN, HANGUS, LIBUR, dan DIGANTI dilakukan oleh Admin, bukan guru. " +
    "Jika murid izin atau ada perubahan jadwal, informasikan ke Admin studio."
  ),

  h2("1.5  Cara Logout"),
  para(
    "Untuk keluar dari sistem, klik nama Anda di pojok kanan atas halaman, " +
    "kemudian pilih menu Keluar. Selalu logout setelah selesai menggunakan " +
    "portal, terutama jika menggunakan perangkat bersama."
  ),
  pageBreak(),
];

// ── BAB 2: DASHBOARD ───────────────────────────────────────────────────────

const bab2 = [
  h1("BAB 2 — DASHBOARD"),

  h2("2.1  Gambaran Umum Dashboard"),
  para(
    "Dashboard adalah halaman utama yang muncul setelah Anda login. Halaman ini " +
    "menampilkan semua informasi penting yang Anda butuhkan untuk mengajar hari ini: " +
    "sesi yang harus dilakukan, ringkasan kinerja bulan ini, dan notifikasi jika " +
    "ada sesi yang membutuhkan perhatian."
  ),
  emptyLine(),
  para("Dashboard dibagi menjadi tiga bagian utama:"),
  bulletBold("Banner Sesi Pending", " — muncul di bagian paling atas jika ada murid yang izin tanpa jadwal pengganti."),
  bulletBold("Sesi Hari Ini", " — daftar semua sesi yang harus Anda tangani hari ini."),
  bulletBold("Ringkasan Bulan Ini", " — kartu statistik honor dan kehadiran bulan berjalan."),

  h2("2.2  Banner Sesi Pending"),
  para(
    "Banner berwarna kuning/oranye ini muncul di bagian paling atas dashboard " +
    "jika ada satu atau lebih sesi Anda yang berstatus IZIN_PENDING — artinya " +
    "murid sudah izin, namun belum ada tanggal sesi pengganti yang ditentukan."
  ),
  emptyLine(),
  para([
    run("Banner ini bersifat "),
    bold("klikable"),
    run(". Klik banner untuk langsung menuju halaman Sesi Pending dan melihat detail sesi mana saja yang masih belum terjadwal penggantinya."),
  ]),
  emptyLine(),
  tip(
    "Perhatikan banner ini setiap kali login. Sesi pending yang terlalu lama " +
    "(lebih dari 14 hari) ditandai merah dan perlu segera diselesaikan dengan " +
    "mengusulkan tanggal pengganti ke Admin."
  ),

  h2("2.3  Sesi Hari Ini"),
  para(
    "Bagian ini menampilkan semua sesi yang dijadwalkan hari ini, baik sebagai " +
    "guru utama maupun sebagai guru pengganti. Sesi diurutkan berdasarkan jam mulai."
  ),
  emptyLine(),
  h3("Informasi yang Ditampilkan per Sesi"),
  bulletBold("Nama Murid", " — nama lengkap murid yang diajar."),
  bulletBold("Jam Mulai – Jam Selesai", " — contoh: 14:00 – 14:30."),
  bulletBold("Ruang", " — kode ruang studio (contoh: R2 = Studio 2)."),
  bulletBold("Kode Paket", " — kode singkat paket yang diambil murid (contoh: REG-PIANO-L2)."),
  bulletBold("Badge Status", " — status sesi saat ini (Terjadwal, Hadir, Terlambat, dll)."),
  emptyLine(),
  h3("Kondisi Tombol Aksi"),
  para("Tombol aksi hanya muncul untuk sesi yang statusnya masih SCHEDULED (belum diabsen):"),
  emptyLine(),
  makeTable(
    ["Kondisi Sesi", "Tampilan Tombol / Informasi"],
    [
      ["Status: SCHEDULED (belum diabsen)", "Tombol biru \"Hadir\" + tombol abu \"Terlambat\""],
      ["Status: HADIR (sudah diabsen hadir)", "Badge hijau \"Hadir\" — tidak ada tombol lagi"],
      ["Status: HADIR_TERLAMBAT (sudah diabsen)", "Badge kuning \"Terlambat X menit\" — tidak ada tombol lagi"],
      ["Status: LIBUR (hari libur resmi)", "Teks abu: \"Sesi libur — tidak perlu absensi.\""],
      ["Status lain (Izin, Hangus, dll)", "Badge status — tidak ada tombol (dikelola Admin)"],
      ["Anda sebagai guru pengganti", "Label biru \"Anda sebagai pengganti\" di atas nama murid"],
    ],
    [45, 55]
  ),
  emptyLine(),
  h3("Cara Input Absensi Hadir dari Dashboard"),
  bulletBold("Langkah 1:", " Temukan sesi yang akan diabsen (badge status: Terjadwal)."),
  bulletBold("Langkah 2:", " Klik tombol biru \"Hadir\"."),
  bulletBold("Langkah 3:", " Konfirmasi jika ada dialog konfirmasi. Sistem langsung menyimpan status HADIR."),
  bulletBold("Langkah 4:", " Tombol akan hilang dan badge hijau \"Hadir\" muncul sebagai konfirmasi."),
  emptyLine(),
  h3("Cara Input Absensi Terlambat dari Dashboard"),
  bulletBold("Langkah 1:", " Temukan sesi yang akan diabsen (badge status: Terjadwal)."),
  bulletBold("Langkah 2:", " Klik tombol abu \"Terlambat\"."),
  bulletBold("Langkah 3:", " Panel input menit terlambat akan muncul di bawah sesi tersebut."),
  bulletBold("Langkah 4:", " Isi kolom menit terlambat (angka antara 1 sampai 60)."),
  bulletBold("Langkah 5:", " Klik tombol \"Simpan\". Sistem menyimpan status HADIR_TERLAMBAT."),
  note(
    "Menit terlambat yang diisi hanya untuk catatan internal studio. " +
    "Honor tetap dihitung penuh — tidak ada pemotongan honor karena terlambat."
  ),

  h2("2.4  Ringkasan Bulan Ini"),
  para(
    "Di bagian bawah dashboard terdapat tiga kartu statistik yang merangkum " +
    "kinerja Anda di bulan berjalan:"
  ),
  emptyLine(),
  makeTable(
    ["Kartu", "Isi Informasi", "Keterangan"],
    [
      [
        "Sesi Terlaksana",
        "Jumlah sesi yang sudah diabsen (Hadir / Terlambat)",
        "Menghitung semua sesi bulan berjalan yang sudah selesai",
      ],
      [
        "Honor",
        "Estimasi total honor bulan berjalan",
        "Label \"Estimasi\" jika belum dikalkulasi; \"✓ Dibayar\" jika slip sudah PAID",
      ],
      [
        "Sesi Pending",
        "Jumlah sesi yang masih IZIN_PENDING",
        "Hanya muncul jika ada sesi pending; 0 berarti semua beres",
      ],
    ],
    [20, 45, 35]
  ),
  emptyLine(),
  note(
    "Honor di kartu Ringkasan adalah angka estimasi berdasarkan sesi yang sudah " +
    "tercatat. Angka final ditetapkan oleh Owner saat slip honor dibuat " +
    "(biasanya H-2 sebelum akhir bulan) dan bisa dilihat di halaman Slip Honor."
  ),
  pageBreak(),
];

// ── BAB 3: INPUT ABSENSI ───────────────────────────────────────────────────

const bab3 = [
  h1("BAB 3 — INPUT ABSENSI"),

  h2("3.1  Prinsip Dasar Absensi"),
  para(
    "Absensi adalah pencatatan kehadiran per sesi mengajar. Di sistem Musik KITA, " +
    "guru hanya bisa menginput dua status kehadiran:"
  ),
  emptyLine(),
  makeTable(
    ["Status", "Kode Sistem", "Kapan Digunakan"],
    [
      ["Hadir", "HADIR", "Sesi berjalan normal — murid hadir tepat waktu atau lebih awal"],
      ["Hadir Terlambat", "HADIR_TERLAMBAT", "Sesi berjalan — murid hadir tapi terlambat (wajib isi menit)"],
    ],
    [20, 20, 60]
  ),
  emptyLine(),
  para("Status-status lain di bawah ini hanya bisa diinput oleh Admin:"),
  bullet("IZIN_RESCHEDULE — murid izin dan berhak mendapat sesi pengganti"),
  bullet("IZIN_VIDEO — murid izin dan mendapat pengganti berupa video"),
  bullet("HANGUS — murid tidak hadir tanpa kabar (tidak ada pengganti)"),
  bullet("LIBUR — sesi bertepatan dengan hari libur resmi"),
  bullet("DIGANTI — sesi diajar oleh guru pengganti"),
  bullet("CANCELLED — sesi dibatalkan karena alasan tertentu"),
  emptyLine(),
  warning(
    "Jika murid tidak hadir karena izin, hangus, atau kondisi lain, " +
    "JANGAN input apapun dari portal guru Anda. " +
    "Informasikan situasi ke Admin, dan biarkan Admin yang mencatat status yang sesuai."
  ),

  h2("3.2  Cara Input dari Dashboard"),
  para(
    "Cara tercepat untuk input absensi adalah langsung dari Dashboard, " +
    "karena semua sesi hari ini sudah tampil tanpa perlu navigasi tambahan."
  ),
  emptyLine(),
  h3("Input Status Hadir"),
  bulletBold("1.", " Buka Dashboard (halaman utama setelah login)."),
  bulletBold("2.", " Cari kartu sesi murid yang ingin diabsen (pastikan badge status masih \"Terjadwal\")."),
  bulletBold("3.", " Klik tombol biru \"Hadir\"."),
  bulletBold("4.", " Tunggu sebentar — sistem memproses dan badge langsung berubah menjadi \"Hadir\" (hijau)."),
  bulletBold("5.", " Selesai. Honor otomatis tercatat oleh sistem."),
  emptyLine(),
  h3("Input Status Hadir Terlambat"),
  bulletBold("1.", " Buka Dashboard."),
  bulletBold("2.", " Cari kartu sesi murid yang terlambat (badge status masih \"Terjadwal\")."),
  bulletBold("3.", " Klik tombol abu \"Terlambat\". Panel input muncul di bawah kartu sesi."),
  bulletBold("4.", " Isi kolom \"Menit Terlambat\" dengan angka 1–60."),
  bulletBold("5.", " Klik tombol \"Simpan\"."),
  bulletBold("6.", " Badge berubah menjadi \"Terlambat X menit\" (kuning)."),

  h2("3.3  Cara Input dari Halaman Jadwal"),
  para(
    "Selain dari Dashboard, absensi juga bisa dilakukan dari halaman Jadwal Saya. " +
    "Cara ini berguna jika Anda ingin melihat konteks jadwal minggu sebelum input absensi."
  ),
  emptyLine(),
  bulletBold("1.", " Klik menu \"Jadwal\" di navigasi bawah (smartphone) atau sidebar (desktop)."),
  bulletBold("2.", " Cari sesi hari ini — ditandai dengan latar berbeda dan badge emas \"Hari Ini\"."),
  bulletBold("3.", " Di baris sesi hari ini yang masih Terjadwal, klik tombol \"Hadir\" atau \"Terlambat\"."),
  bulletBold("4.", " Untuk Terlambat: isi menit, klik Simpan."),
  bulletBold("5.", " Halaman akan memperbarui tampilan setelah absensi berhasil."),
  note(
    "Tombol Hadir dan Terlambat hanya muncul untuk sesi hari ini yang masih berstatus Terjadwal. " +
    "Sesi hari-hari lain (kemarin atau besok) tidak bisa diabsen dari portal guru."
  ),

  h2("3.4  Batas Waktu Input Absensi"),
  para(
    "Secara teknis, sistem tidak membatasi jam berapa absensi bisa diinput " +
    "selama statusnya masih Terjadwal. Namun ada beberapa hal yang perlu diperhatikan:"
  ),
  emptyLine(),
  bulletBold("Absen secepatnya:", " Segera input setelah sesi selesai agar data akurat."),
  bulletBold("Jika lupa hari ini:", " Hubungi Admin untuk koreksi manual — guru tidak bisa mengubah absensi yang sudah diinput."),
  bulletBold("Sesi masa lalu:", " Hanya Admin yang bisa input absensi untuk sesi yang sudah lewat (misalnya kemarin)."),
  emptyLine(),
  tip(
    "Biasakan membuka Dashboard setelah setiap sesi selesai dan langsung " +
    "klik Hadir. Ini lebih mudah daripada menghafal dan input nanti sekaligus."
  ),

  h2("3.5  Honor Otomatis"),
  para(
    "Setiap kali Anda menginput status Hadir atau Hadir Terlambat, sistem " +
    "secara otomatis mencatat honor untuk sesi tersebut. Anda tidak perlu " +
    "menghitung atau memasukkan nominal honor."
  ),
  emptyLine(),
  para("Cara sistem menghitung honor:"),
  bulletBold("H_REG", " — honor reguler untuk sesi hadir normal: harga paket × 50% ÷ 4."),
  bulletBold("H_KIDS", " — honor Kids Class: jumlah murid terdaftar × Rp 42.500 per sesi."),
  bulletBold("H_PENG", " — honor pengganti: sama dengan H_REG, tetapi honor masuk ke Anda (bukan guru utama)."),
  emptyLine(),
  note(
    "Detail kalkulasi honor bisa dilihat di halaman Slip Honor setelah Owner " +
    "menyelesaikan perhitungan (biasanya dua hari sebelum akhir bulan)."
  ),
  pageBreak(),
];

// ── BAB 4: JADWAL SAYA ─────────────────────────────────────────────────────

const bab4 = [
  h1("BAB 4 — JADWAL SAYA"),

  h2("4.1  Gambaran Halaman Jadwal"),
  para(
    "Halaman Jadwal Saya menampilkan semua sesi mengajar Anda dalam rentang " +
    "dua minggu: minggu berjalan (mulai Senin) sampai akhir minggu depan (Minggu). " +
    "Ini membantu Anda merencanakan waktu dan mempersiapkan materi mengajar."
  ),
  emptyLine(),
  para("Akses halaman ini melalui:"),
  bullet("Smartphone: menu \"Jadwal\" di navigasi bawah layar"),
  bullet("Desktop: menu \"Jadwal\" di sidebar kiri"),

  h2("4.2  Tampilan di Smartphone (Mobile)"),
  para(
    "Di smartphone, jadwal ditampilkan sebagai kartu per hari. Setiap hari " +
    "yang memiliki sesi ditampilkan sebagai satu kelompok kartu tersendiri."
  ),
  emptyLine(),
  h3("Penanda Hari Ini"),
  para([
    run("Hari ini ditandai dengan "),
    bold("badge emas"),
    run(" di sebelah nama hari. Contoh: \"Kamis\" dengan badge kuning emas \"Hari Ini\"."),
  ]),
  emptyLine(),
  h3("Informasi di Setiap Kartu Sesi"),
  bulletBold("Nama Murid", " — nama lengkap murid."),
  bulletBold("Waktu", " — jam mulai dan jam selesai sesi."),
  bulletBold("Ruang", " — kode dan nama ruang (contoh: R2 — Studio 2)."),
  bulletBold("Paket", " — kode paket murid."),
  bulletBold("Badge Status", " — status sesi saat ini."),
  bulletBold("Label Pengganti (jika ada)", " — label biru \"Anda sebagai pengganti\" jika Anda adalah guru pengganti untuk sesi ini."),
  bulletBold("Tombol Absensi (hari ini saja)", " — tombol Hadir dan Terlambat hanya muncul di sesi hari ini yang masih Terjadwal."),

  h2("4.3  Tampilan di Desktop"),
  para(
    "Di desktop atau tablet, jadwal ditampilkan dalam format tabel dengan kolom: " +
    "Tanggal, Murid, Waktu, Ruang, Status, dan Aksi."
  ),
  emptyLine(),
  h3("Kolom Aksi"),
  para(
    "Kolom Aksi hanya terisi tombol untuk sesi hari ini yang masih Terjadwal. " +
    "Sesi di tanggal lain tidak memiliki tombol aksi di portal guru."
  ),

  h2("4.4  Membaca Status Sesi"),
  para("Berikut arti badge status yang mungkin muncul di halaman jadwal:"),
  emptyLine(),
  makeTable(
    ["Badge", "Warna", "Artinya"],
    [
      ["Terjadwal", "Abu-abu", "Sesi belum berlangsung atau belum diabsen"],
      ["Hadir", "Hijau", "Guru sudah menginput status Hadir"],
      ["Terlambat", "Kuning", "Guru sudah menginput Hadir Terlambat + menit"],
      ["Izin Reschedule", "Biru", "Murid izin, berhak dapat sesi pengganti (Admin yang input)"],
      ["Izin Video", "Biru Muda", "Murid izin, pengganti berupa video (Admin yang input)"],
      ["Hangus", "Merah", "Murid tidak hadir tanpa kabar — honor tetap dihitung (Admin input)"],
      ["Libur", "Abu", "Sesi bertepatan hari libur — tidak perlu absensi"],
      ["Diganti", "Ungu", "Sesi diajar oleh guru pengganti"],
      ["Cancelled", "Merah Muda", "Sesi dibatalkan oleh Admin"],
    ],
    [25, 18, 57]
  ),

  h2("4.5  Rentang Waktu yang Ditampilkan"),
  para(
    "Halaman Jadwal hanya menampilkan sesi dalam rentang dua minggu ke depan " +
    "(minggu ini + minggu depan). Sesi yang sudah lewat lebih dari seminggu " +
    "tidak ditampilkan di halaman ini — untuk melihat riwayat, lihat di halaman " +
    "Slip Honor yang memuat rincian sesi per bulan."
  ),
  note(
    "Jadwal mingguan Anda ditentukan oleh Admin saat mendaftarkan murid. " +
    "Jika ada perubahan jadwal tetap (hari, jam, atau ruang), hubungi Admin."
  ),
  pageBreak(),
];

// ── BAB 5: SESI PENDING ────────────────────────────────────────────────────

const bab5 = [
  h1("BAB 5 — SESI PENDING"),

  h2("5.1  Apa Itu Sesi Pending?"),
  para(
    "Sesi Pending adalah sesi yang berstatus IZIN_PENDING — murid sudah menyampaikan " +
    "izin kepada Admin, Admin sudah mencatat, namun tanggal sesi pengganti belum " +
    "ditentukan. Status ini berarti sesi \"menggantung\" dan perlu diselesaikan."
  ),
  emptyLine(),
  para(
    "Sebagai guru, Anda bisa membantu proses ini dengan mengusulkan tanggal " +
    "pengganti yang cocok untuk Anda. Admin akan meninjau usulan Anda, mengecek " +
    "konflik jadwal, dan menetapkan tanggal resmi pengganti."
  ),
  emptyLine(),
  note(
    "Usulan tanggal dari guru bersifat saran — keputusan final ada di tangan Admin. " +
    "Admin akan menghubungi Anda jika ada masalah dengan tanggal yang Anda usulkan."
  ),

  h2("5.2  Mengakses Halaman Sesi Pending"),
  para("Ada dua cara mengakses halaman ini:"),
  bulletBold("Cara 1 — Dari Banner Dashboard:", " Klik banner kuning/oranye \"Sesi Pending\" yang muncul di bagian atas Dashboard."),
  bulletBold("Cara 2 — Dari Menu:", " Smartphone: menu \"Pending\" di navigasi bawah. Desktop: menu \"Sesi Pending\" di sidebar."),
  emptyLine(),
  tip("Jika tidak ada sesi pending, halaman akan menampilkan pesan \"Tidak ada sesi pending\" dan banner di Dashboard tidak akan muncul."),

  h2("5.3  Membaca Informasi Sesi Pending"),
  para("Setiap sesi pending ditampilkan sebagai kartu berisi informasi berikut:"),
  emptyLine(),
  makeTable(
    ["Informasi", "Penjelasan"],
    [
      ["Nama Murid", "Murid yang mengajukan izin"],
      ["Sesi ke-", "Urutan sesi murid ini di bulan berjalan (contoh: Sesi ke-2 dari 4)"],
      ["Kode Paket", "Jenis dan level paket yang diambil murid"],
      ["Tanggal Sesi Asli", "Tanggal sesi yang murid izinkan"],
      ["Badge Hari Pending", "Sudah berapa hari sesi ini berstatus pending"],
    ],
    [30, 70]
  ),
  emptyLine(),
  h3("Warna Badge Hari Pending"),
  bulletBold("Badge Merah:", " sesi sudah pending lebih dari 14 hari — perlu segera ditindaklanjuti."),
  bulletBold("Badge Kuning:", " sesi pending 14 hari atau kurang — masih dalam batas normal."),

  h2("5.4  Cara Mengusulkan Tanggal Pengganti"),
  para("Jika Anda ingin membantu Admin dengan memberikan usulan jadwal pengganti:"),
  emptyLine(),
  bulletBold("Langkah 1 — Buka Form Usulan:", " Klik area kartu sesi pending. Form accordion akan membuka di bawah informasi sesi."),
  bulletBold("Langkah 2 — Isi Tanggal Usulan:", " Pilih tanggal menggunakan date picker. Tanggal minimal adalah hari ini (tidak bisa masa lalu)."),
  bulletBold("Langkah 3 — Pilih Jam Mulai:", " Pilih jam mulai dari dropdown. Pilihan tersedia dari 07:00 sampai 21:00 per 30 menit."),
  bulletBold("Langkah 4 — Isi Catatan (Opsional):", " Jika ada informasi tambahan untuk Admin (contoh: \"Saya tersedia jam 15:00-16:00 di tanggal itu\"), isi di kolom Catatan. Maksimal 200 karakter."),
  bulletBold("Langkah 5 — Kirim:", " Klik tombol \"Kirim Saran ke Admin\"."),
  bulletBold("Langkah 6 — Konfirmasi:", " Jika berhasil, form akan hilang dan muncul pesan hijau \"Saran berhasil dikirim ke Admin\"."),
  emptyLine(),
  warning(
    "Mengisi form usulan tidak berarti jadwal pengganti otomatis terkonfirmasi. " +
    "Admin masih harus mengecek konflik dan menetapkan jadwal resmi. " +
    "Tunggu konfirmasi dari Admin sebelum menganggap jadwal pengganti sudah pasti."
  ),
  emptyLine(),
  h3("Validasi Form Usulan"),
  bullet("Tanggal Usulan wajib diisi (tidak boleh kosong)"),
  bullet("Tanggal tidak boleh di masa lalu"),
  bullet("Jam Mulai wajib dipilih"),
  bullet("Catatan opsional, maksimal 200 karakter"),

  h2("5.5  Setelah Usulan Dikirim"),
  para(
    "Setelah Anda mengirim usulan tanggal, Admin akan menerima informasi tersebut " +
    "dan mengecek konflik jadwal (guru dan ruang). Jika tidak ada konflik, Admin " +
    "akan membuat sesi pengganti dengan tanggal tersebut dan sesi tidak lagi " +
    "berstatus pending."
  ),
  emptyLine(),
  para("Sesi yang sudah memiliki jadwal pengganti tidak akan muncul lagi di halaman Sesi Pending."),
  pageBreak(),
];

// ── BAB 6: SLIP HONOR ──────────────────────────────────────────────────────

const bab6 = [
  h1("BAB 6 — SLIP HONOR"),

  h2("6.1  Tentang Slip Honor"),
  para(
    "Slip Honor adalah dokumen ringkasan honor mengajar Anda per bulan. " +
    "Slip disiapkan oleh sistem secara otomatis sekitar dua hari sebelum " +
    "akhir bulan, setelah semua sesi dalam bulan tersebut selesai."
  ),
  emptyLine(),
  para("Ada tiga status slip honor yang mungkin ada:"),
  emptyLine(),
  makeTable(
    ["Status", "Arti", "Bisa Dilihat Guru?"],
    [
      ["DRAFT", "Slip sedang dalam proses perhitungan oleh Owner", "Tidak — belum tampil di portal guru"],
      ["CALCULATED", "Honor sudah dihitung, menunggu pembayaran", "Ya — tampil dengan badge kuning \"Sudah Dihitung\""],
      ["PAID", "Honor sudah dibayarkan ke guru", "Ya — tampil dengan badge hijau \"✓ Dibayar\""],
    ],
    [18, 52, 30]
  ),
  note(
    "Slip berstatus DRAFT tidak ditampilkan di portal guru. " +
    "Anda hanya bisa melihat slip yang sudah selesai dihitung (CALCULATED atau PAID)."
  ),

  h2("6.2  Mengakses Halaman Slip Honor"),
  para("Buka halaman Slip Honor melalui:"),
  bulletBold("Smartphone:", " menu \"Honor\" di navigasi bawah layar."),
  bulletBold("Desktop:", " menu \"Slip Honor\" di sidebar kiri."),

  h2("6.3  Daftar Slip Honor"),
  para(
    "Halaman utama Slip Honor menampilkan daftar semua slip yang sudah " +
    "tersedia (CALCULATED atau PAID), diurutkan dari yang terbaru."
  ),
  emptyLine(),
  h3("Informasi di Setiap Kartu Slip"),
  bulletBold("Bulan dan Tahun", " — periode slip (contoh: Mei 2026)."),
  bulletBold("Nomor Slip", " — nomor unik format SLIP/YYYY/MM/NNNN (contoh: SLIP/2026/05/0001)."),
  bulletBold("Total Honor", " — jumlah total honor yang Anda terima bulan tersebut."),
  bulletBold("Badge Status", " — \"✓ Dibayar\" (hijau) atau \"Sudah Dihitung\" (kuning)."),
  emptyLine(),
  para([
    run("Klik kartu slip untuk melihat "),
    bold("detail lengkap"),
    run(" termasuk rincian per sesi."),
  ]),

  h2("6.4  Detail Slip Honor"),
  para("Halaman detail slip berisi dua bagian utama:"),
  emptyLine(),
  h3("A. Ringkasan Komponen Honor"),
  para("Kartu di bagian atas menampilkan semua komponen honor yang membentuk total slip:"),
  emptyLine(),
  makeTable(
    ["Komponen", "Sumber", "Keterangan"],
    [
      ["Honor Pokok", "Otomatis dari kalkulasi sesi", "Dihitung dari semua sesi bulan tersebut"],
      ["Honor Event", "Input manual oleh Owner", "Tampil hanya jika ada — untuk event Mini Concert atau ujian"],
      ["Honor Transport", "Input manual oleh Owner", "Tampil hanya jika ada — tunjangan transportasi"],
      ["Honor Lain-lain", "Input manual oleh Owner", "Tampil hanya jika ada — disertai keterangan"],
      ["TOTAL", "Penjumlahan semua komponen", "Ini yang akan Anda terima"],
    ],
    [22, 28, 50]
  ),
  emptyLine(),
  h3("B. Rincian Sesi"),
  para("Di bagian bawah halaman detail terdapat tabel rincian semua sesi yang masuk dalam slip ini:"),
  emptyLine(),
  makeTable(
    ["Kolom", "Isi"],
    [
      ["Nama Murid", "Nama murid yang diajar"],
      ["Tanggal", "Tanggal sesi berlangsung"],
      ["Jam", "Jam mulai sesi"],
      ["Ruang", "Kode ruang studio"],
      ["Kode Honor", "Kode kalkulasi (H_REG, H_KIDS, H_PENG, dll — lihat Lampiran)"],
      ["Nominal", "Honor untuk sesi ini dalam Rupiah"],
    ],
    [20, 80]
  ),
  emptyLine(),
  h3("Kode Honor yang Mungkin Muncul di Slip"),
  makeTable(
    ["Kode", "Artinya", "Nominal"],
    [
      ["H_REG", "Sesi terlaksana normal (hadir / terlambat)", "Harga paket × 50% ÷ 4"],
      ["H_TRIAL", "Sesi trial murid baru yang hadir", "Sama dengan H_REG"],
      ["H_VIDEO", "Murid izin dengan pengganti video", "Sama dengan H_REG"],
      ["H_LIBUR", "Sesi hari libur nasional — honor tetap dibayar", "Sama dengan H_REG"],
      ["H_HANGUS", "Murid no-show / hangus — honor tetap dibayar", "Sama dengan H_REG"],
      ["H_PENG", "Anda mengajar sebagai guru pengganti", "Sama dengan H_REG (ke Anda)"],
      ["H_KIDS", "Sesi Kids Class grup", "Jumlah murid × Rp 42.500"],
      ["H_UJIAN", "Mengawas ujian grade", "Rp 250.000 flat per ujian"],
      ["H_IZIN", "Sesi izin reschedule (sesi asli)", "Rp 0 — honor dibayar via sesi pengganti"],
      ["TRIAL_NS", "Sesi trial murid tidak hadir (no-show)", "Rp 0"],
    ],
    [15, 55, 30]
  ),

  h2("6.5  Sifat Read-Only Slip Honor"),
  para(
    "Portal guru hanya menampilkan slip honor — Anda tidak bisa mengubah, " +
    "menambah, atau menghapus apapun di slip. Jika ada ketidaksesuaian " +
    "dalam slip honor Anda, segera hubungi Owner atau Admin untuk klarifikasi."
  ),
  emptyLine(),
  warning(
    "Jika ada sesi yang Anda rasa mestinya ada di slip tapi tidak muncul, " +
    "atau nominalnya terlihat tidak sesuai, segera laporkan ke Owner. " +
    "Jangan tunggu sampai akhir bulan berikutnya."
  ),
  pageBreak(),
];

// ── BAB 7: PROFIL ──────────────────────────────────────────────────────────

const bab7 = [
  h1("BAB 7 — PROFIL & KEAMANAN AKUN"),

  h2("7.1  Halaman Profil"),
  para(
    "Halaman Profil hanya memiliki satu fitur untuk guru: ganti password. " +
    "Informasi profil lainnya (nama, email, nomor telepon) dikelola oleh Owner " +
    "atau Admin dan tidak bisa diubah sendiri melalui portal guru."
  ),
  emptyLine(),
  para("Akses halaman Profil melalui:"),
  bullet("Smartphone: ikon profil atau menu di pojok kanan atas"),
  bullet("Desktop: menu \"Profil\" di sidebar atau klik nama Anda di topbar"),

  h2("7.2  Cara Ganti Password"),
  para("Ikuti langkah-langkah berikut untuk mengganti password:"),
  emptyLine(),
  bulletBold("Langkah 1:", " Buka halaman Profil."),
  bulletBold("Langkah 2:", " Isi kolom \"Password Lama\" dengan password yang sedang Anda gunakan saat ini."),
  bulletBold("Langkah 3:", " Isi kolom \"Password Baru\" dengan password baru yang ingin Anda gunakan."),
  bulletBold("Langkah 4:", " Isi kolom \"Konfirmasi Password Baru\" dengan password baru yang sama persis (untuk memastikan tidak ada typo)."),
  bulletBold("Langkah 5:", " Klik tombol \"Simpan Password\"."),
  bulletBold("Langkah 6:", " Jika berhasil, sistem menampilkan pesan konfirmasi. Anda mungkin diminta login ulang dengan password baru."),
  emptyLine(),
  h3("Aturan Password"),
  bullet("Minimal 8 karakter"),
  bullet("Boleh kombinasi huruf, angka, dan simbol"),
  bullet("Password baru dan konfirmasi password harus sama persis"),
  bullet("Tidak boleh sama dengan password lama (tergantung konfigurasi sistem)"),
  emptyLine(),
  warning(
    "Jika Anda lupa password dan tidak bisa login, hubungi Admin atau Owner. " +
    "Mereka dapat mereset password Anda. Tidak ada fitur \"Lupa Password\" " +
    "berbasis email di portal guru karena sistem berjalan secara lokal (offline)."
  ),

  h2("7.3  Tips Keamanan Akun"),
  tip("Ganti password setelah login pertama kali — password awal sama dengan username."),
  emptyLine(),
  bulletBold("Gunakan password yang kuat:", " minimal 8 karakter, campuran huruf besar, kecil, dan angka."),
  bulletBold("Jangan bagikan password:", " setiap akun guru bersifat personal dan bertanggung jawab atas aktivitas di akun tersebut."),
  bulletBold("Logout setelah selesai:", " terutama jika menggunakan perangkat bersama atau komputer umum."),
  bulletBold("Jangan screenshot halaman honor:", " informasi honor bersifat rahasia internal studio."),
  bulletBold("Laporkan jika ada masalah:", " jika Anda melihat aktivitas yang tidak Anda lakukan di akun Anda, segera hubungi Owner."),
  pageBreak(),
];

// ── LAMPIRAN ───────────────────────────────────────────────────────────────

const lampiran = [
  h1("LAMPIRAN"),

  h2("Lampiran A — Tabel Status Sesi dan Artinya"),
  para("Referensi lengkap semua status sesi yang mungkin Anda temui di sistem:"),
  emptyLine(),
  makeTable(
    ["Status", "Kode Sistem", "Siapa yang Input", "Artinya"],
    [
      ["Terjadwal", "SCHEDULED", "—", "Sesi sudah dijadwalkan, belum berlangsung atau belum diabsen"],
      ["Hadir", "HADIR", "Guru", "Sesi berlangsung normal, murid hadir"],
      ["Hadir Terlambat", "HADIR_TERLAMBAT", "Guru", "Sesi berlangsung, murid terlambat (ada data menit)"],
      ["Izin Reschedule", "IZIN_RESCHEDULE", "Admin", "Murid izin ≥5 jam sebelum sesi, berhak dapat pengganti"],
      ["Izin Video", "IZIN_VIDEO", "Admin", "Murid izin, pengganti berupa rekaman video materi"],
      ["Hangus", "HANGUS", "Admin", "Murid tidak hadir tanpa izin — honor guru tetap dibayar"],
      ["Libur", "LIBUR", "Sistem Otomatis", "Sesi bertepatan hari libur resmi/nasional"],
      ["Diganti", "DIGANTI", "Admin", "Sesi diajar guru pengganti (bukan guru utama)"],
      ["Dibatalkan", "CANCELLED", "Admin", "Sesi dibatalkan karena alasan tertentu"],
    ],
    [18, 18, 18, 46]
  ),

  emptyLine(),
  h2("Lampiran B — Kode Paket yang Umum Ditemui"),
  para("Format kode paket: [TIPE]-[INSTRUMEN]-[GRADE/DURASI]"),
  emptyLine(),
  makeTable(
    ["Contoh Kode", "Artinya"],
    [
      ["REG-PIANO-BASIC", "Reguler Piano — Grade Basic (30 menit)"],
      ["REG-PIANO-L1", "Reguler Piano — Level 1 (30 menit)"],
      ["REG-PIANO-L4", "Reguler Piano — Level 4 (30 menit)"],
      ["HOB-GITAR-30", "Hobby Gitar — 30 menit"],
      ["HOB-VOCAL-45", "Hobby Vokal — 45 menit"],
      ["REG-DRUM-L2", "Reguler Drum — Level 2 (30 menit)"],
      ["KIDS-CLASS", "Kids Class grup (45 menit, 3–4 anak)"],
      ["REG-VIOLIN-L1", "Reguler Biola — Level 1 (30 menit)"],
      ["REG-BASS-BASIC", "Reguler Bass — Grade Basic (30 menit)"],
    ],
    [30, 70]
  ),

  emptyLine(),
  h2("Lampiran C — Kode Ruang Studio"),
  makeTable(
    ["Kode", "Nama", "Instrumen yang Dilayani"],
    [
      ["R1", "Studio 1", "Vokal, Kids Class, Gitar"],
      ["R2", "Studio 2", "Piano, Vokal, Gitar"],
      ["R3", "Studio 3", "Piano"],
      ["R4", "Studio 4", "Piano, Gitar"],
      ["R5", "Studio 5", "Bass, Gitar"],
      ["R6", "Studio 6", "Violin (Biola)"],
      ["R7", "Studio 7", "Piano, Vokal"],
      ["R8", "Studio 8", "Drum"],
      ["R9", "Studio 9", "Drum"],
    ],
    [12, 22, 66]
  ),

  emptyLine(),
  h2("Lampiran D — Tips Penting untuk Guru"),
  para("Rangkuman poin-poin penting yang perlu selalu diingat:"),
  emptyLine(),

  h3("Tentang Login"),
  bullet("Username = nama Anda dalam huruf kecil (thomas, adi, debora, dll)"),
  bullet("Password awal = username — segera ganti setelah login pertama"),
  bullet("Sistem hanya bisa diakses dari WiFi studio — tidak bisa dari luar"),

  emptyLine(),
  h3("Tentang Absensi"),
  bullet("Absen segera setelah sesi selesai — jangan tunda"),
  bullet("Guru hanya bisa input Hadir atau Hadir Terlambat"),
  bullet("Status Izin, Hangus, Libur, dan Diganti = urusan Admin"),
  bullet("Jika murid tidak hadir: hubungi Admin, jangan input apapun sendiri"),
  bullet("Tombol absensi hanya muncul untuk sesi hari ini yang masih Terjadwal"),

  emptyLine(),
  h3("Tentang Honor"),
  bullet("Honor dihitung otomatis oleh sistem — tidak perlu Anda hitung manual"),
  bullet("Slip honor muncul setelah Owner menyelesaikan kalkulasi (~H-2 akhir bulan)"),
  bullet("Ada ketidaksesuaian? Laporkan ke Owner — jangan tunggu lama"),
  bullet("Status DRAFT tidak tampil di portal guru"),

  emptyLine(),
  h3("Tentang Sesi Pending"),
  bullet("Sesi pending = murid izin, belum ada jadwal pengganti"),
  bullet("Anda bisa bantu dengan mengirim saran tanggal — bukan keputusan final"),
  bullet("Sesi pending merah (>14 hari) perlu segera ditindaklanjuti"),

  emptyLine(),
  h3("Jika Ada Masalah"),
  bullet("Lupa password: hubungi Admin atau Owner untuk reset"),
  bullet("Sesi tidak muncul di dashboard: hubungi Admin, mungkin jadwal belum di-generate"),
  bullet("Honornya berbeda dari yang diharapkan: hubungi Owner"),
  bullet("Tombol absensi tidak muncul: pastikan sesi memang hari ini dan statusnya masih Terjadwal"),

  emptyLine(),
  h2("Lampiran E — Pertanyaan yang Sering Ditanyakan (FAQ)"),
  emptyLine(),

  h3("Q: Apakah saya bisa lihat jadwal murid guru lain?"),
  para("Tidak. Portal guru menampilkan data Anda saja — jadwal dan slip honor guru lain tidak bisa diakses."),
  emptyLine(),

  h3("Q: Bagaimana jika saya salah input absensi?"),
  para("Guru tidak bisa mengubah absensi yang sudah diinput. Hubungi Admin segera untuk koreksi. Admin memiliki akses untuk mengubah status sesi."),
  emptyLine(),

  h3("Q: Kapan slip honor saya siap?"),
  para("Biasanya dua hari sebelum akhir bulan (H-2). Owner akan mengkalkulasi semua sesi dan menerbitkan slip. Anda akan bisa melihatnya di halaman Slip Honor."),
  emptyLine(),

  h3("Q: Kenapa ada sesi di jadwal saya yang bukan murid saya?"),
  para("Itu berarti Anda ditugaskan sebagai guru pengganti untuk sesi tersebut. Ada label biru \"Anda sebagai pengganti\" di kartu sesi. Honor untuk sesi tersebut masuk ke slip honor Anda."),
  emptyLine(),

  h3("Q: Apa yang terjadi jika saya tidak input absensi hari ini?"),
  para("Sesi tetap berstatus Terjadwal. Admin bisa melihat sesi yang belum diabsen dan bisa membantu koreksi. Namun lebih baik selalu absen sendiri setelah sesi selesai."),
  emptyLine(),

  h3("Q: Apakah saya bisa mencetak slip honor?"),
  para("Portal guru tidak memiliki fitur cetak slip honor mandiri. Jika Anda membutuhkan cetakan slip honor, minta ke Admin atau Owner untuk mencetaknya dari sistem mereka."),
  emptyLine(),

  h3("Q: Kenapa honor bulan lalu belum muncul di Slip Honor?"),
  para("Kemungkinan slip masih berstatus DRAFT (Owner belum selesai mengkalkulasi). Slip hanya tampil di portal guru jika sudah berstatus CALCULATED atau PAID. Tunggu atau hubungi Owner."),
  emptyLine(),

  h3("Q: Apakah ada aplikasi mobile khusus?"),
  para("Tidak ada aplikasi terpisah yang perlu diunduh. Cukup buka browser di smartphone dan akses alamat portal. Tampilan sudah dioptimalkan untuk layar kecil (mobile-first)."),
  emptyLine(),
  emptyLine(),

  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 400, after: 100 },
    children: [
      new TextRun({
        text: "— Akhir Dokumen —",
        size: SIZE.body,
        font: FONT.body,
        color: COLOR.mutedText,
        italics: true,
      }),
    ],
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 0, after: 0 },
    children: [
      new TextRun({
        text: "User Manual Portal Guru — Musik KITA  |  Versi 1.0  |  Mei 2026",
        size: SIZE.caption,
        font: FONT.body,
        color: COLOR.mutedText,
      }),
    ],
  }),
];

// ─────────────────────────────────────────────
// GENERATE DOKUMEN
// ─────────────────────────────────────────────

async function generateDocument() {
  const allChildren = [
    ...halamanJudul,
    ...bab1,
    ...bab2,
    ...bab3,
    ...bab4,
    ...bab5,
    ...bab6,
    ...bab7,
    ...lampiran,
  ];

  const doc = new Document({
    creator: "Musik KITA — Sistem Administrasi",
    title: "User Manual Portal Guru — Musik KITA",
    description: "Panduan lengkap penggunaan Portal Guru untuk guru-guru di Musik KITA.",
    subject: "Portal Guru",
    keywords: "musik kita, portal guru, user manual, absensi, honor",
    styles: {
      default: {
        document: {
          run: {
            font: FONT.body,
            size: SIZE.body,
            color: COLOR.bodyText,
          },
        },
      },
    },
    sections: [
      {
        properties: {
          page: {
            margin: {
              top: convertInchesToTwip(1.0),
              bottom: convertInchesToTwip(1.0),
              left: convertInchesToTwip(1.25),
              right: convertInchesToTwip(1.0),
            },
          },
        },
        children: allChildren,
      },
    ],
  });

  const outputPath = path.join(
    "C:\\laragon\\www\\musik-kita-ops",
    "User-Manual-Guru-Musik-KITA.docx"
  );

  const buffer = await Packer.toBuffer(doc);
  fs.writeFileSync(outputPath, buffer);

  console.log(`SUCCESS: User Manual berhasil digenerate di ${outputPath}`);
  console.log(`Ukuran file: ${(buffer.length / 1024).toFixed(1)} KB`);
}

// Jalankan
generateDocument().catch((err) => {
  console.error("ERROR: Gagal generate dokumen:", err);
  process.exit(1);
});
