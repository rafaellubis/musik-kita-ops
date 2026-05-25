# Design: UAT Checklist & User Manual Admin

**Tanggal:** 2026-05-26
**Status:** Approved
**Scope:** Generate dua dokumen .docx untuk persiapan go-live sistem Musik KITA

---

## Konteks

Sistem akan masuk fase parallel run (sistem + Excel bersamaan) sebelum full cutover.
Owner akan menjalankan UAT sendiri dari kota B, lalu menyerahkan User Manual ke admin
untuk operasional harian. Kids Class Bundle dan Monthly dikecualikan sementara karena
keterbatasan data — tetap manual selama fase ini.

---

## Deliverable

| File | Lokasi | Audience |
|---|---|---|
| `UAT-Musik-KITA-v1.0.docx` | root project (sejajar BRD/SAD) | Owner |
| `UserManual-Admin-Musik-KITA-v1.0.docx` | root project | Admin |
| `docs/generate-docs/generate_uat.py` | docs/generate-docs/ | Developer |
| `docs/generate-docs/generate_manual.py` | docs/generate-docs/ | Developer |

---

## Teknis

- **Generator:** Python + `python-docx` library
- **Format:** .docx sederhana — bersih, printable, tidak harus identik dengan BRD/SAD
- **Re-runnable:** script bisa dijalankan ulang jika ada revisi konten
- **Instalasi:** `pip install python-docx`

---

## Dokumen 1 — UAT Checklist

### Format
Tabel per modul dengan kolom:
`No | Skenario | Langkah Uji | Expected Result | Status (✓/✗) | Catatan`

### Cover
- Judul: UAT Checklist — Sistem Administrasi Musik KITA
- Versi: v1.0
- Tanggal: [diisi saat generate]
- Tester: Owner
- Lingkup: M01–M08 + Import Excel
- Dikecualikan: Kids Class Bundle, Kids Class Monthly (manual sementara)

### Modul & Estimasi Skenario

| Modul | Skenario |
|---|---|
| M01 — Master Data | Tambah/edit paket, guru, ruangan, hari libur |
| M02 — Pendaftaran & Trial | Calon → Trial → Aktif, skip trial, murid no-show |
| M03 — Penjadwalan | Generate sesi bulanan, sesi libur + replacement |
| M04 — Absensi | HADIR, IZIN_RESCHEDULE, HANGUS, DIGANTI, reschedule |
| M05 — Keuangan | Generate SPP, catat bayar, denda, cetak invoice/kuitansi |
| M06 — Honor Guru | Kalkulasi honor, slip honor, cetak, tandai dibayar |
| M07 — Pengeluaran | Catat pengeluaran, lihat rekap |
| M08 — Event | Buat event, daftar murid, input hasil ujian |
| Import Excel | Dry-run preview, konfirmasi import, handling error |
| KIDS Bundle | Generate cicilan bundle (3 termin) |

**Estimasi total:** ~70 skenario

### Sumber Test Case
- Business rules `BR-*` dari CLAUDE.md
- Edge cases dari design specs di `docs/superpowers/specs/`
- Happy path + 1 negative case per fitur utama

---

## Dokumen 2 — User Manual Admin

### Format
Langkah bernomor, bahasa operasional (bukan teknis), tanpa jargon kode.

### Cover
- Judul: Panduan Penggunaan Sistem — Role: Admin
- Studio: Musik KITA
- Versi: v1.0
- Catatan: Kids Class Bundle/Monthly dikelola manual sementara

### Struktur Bab

**Bab 1 — Memulai**
- Login ke sistem
- Mengenal tampilan utama (sidebar, navigasi)
- Perbedaan role Owner vs Admin

**Bab 2 — Tugas Harian**
- 2.1 Input absensi sesi hari ini
- 2.2 Catat pembayaran murid
- 2.3 Cek status tagihan outstanding

**Bab 3 — Tugas Awal Bulan**
- 3.1 Generate SPP (lakukan tanggal 1)
- 3.2 Apply denda keterlambatan (lakukan tanggal 11+)
- 3.3 Kalkulasi honor guru
- 3.4 Cetak & distribusi slip honor guru

**Bab 4 — Tugas Insidental**
- 4.1 Daftar murid baru (status Calon)
- 4.2 Jadwalkan sesi trial
- 4.3 Konversi trial → Aktif (termasuk invoice registrasi + SPP)
- 4.4 Skip trial langsung Aktif
- 4.5 Pengajuan cuti murid
- 4.6 Reschedule sesi (izin murid)
- 4.7 Tambah kelas baru untuk murid existing (multi-kelas)
- 4.8 Hentikan kelas / murid mundur
- 4.9 Generate cicilan Kids Class Bundle

**Bab 5 — Referensi Cepat**
- Tabel status murid dan transisi yang valid
- Format nomor dokumen (INV/YYYY/MM/NNNN, KW/..., SLIP/...)
- Kode honor guru (H_REG, H_KIDS, H_LIBUR, dst.)
- Cara eskalasi masalah ke owner via WhatsApp

---

## Batasan Desain

- Dokumen tidak meng-capture screenshot UI — langkah dideskripsikan dengan
  nama menu/tombol yang muncul di layar
- Konten Kids Class Bundle/Monthly di User Manual hanya Bab 4.9 (generate cicilan)
  — operasional lainnya masih manual Excel sampai cutover penuh
- Versi dokumen harus di-update manual jika ada perubahan sistem signifikan
