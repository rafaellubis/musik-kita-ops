# Desain PDF Laporan Progres — Konsep "Kartu Bulanan"

**Status:** Menunggu persetujuan — mockup HTML tersedia, belum diimplementasikan ke `pdf.blade.php`  
**Tanggal:** 2026-05-31  
**Modul:** M11 Laporan Progres  
**Mockup:** `docs/mockups/laporan-progres-pdf-kartu-bulanan.html`

---

## 1. Masalah

- PDF saat ini (`resources/views/progress-reports/pdf.blade.php`) sulit dipahami orang tua awam.
- Urutan informasi: checklist teknis dulu, highlight di bawah.
- Legenda teks panjang; simbol ○ terasa negatif.
- Tampilan generik (DejaVu 11px, layout admin).

## 2. Solusi: Kartu Bulanan

Laporan seperti **surat bulanan guru → orang tua**:

1. **Halaman inti** — kabar baik, pesan guru, latihan rumah, lagu, ringkasan visual per area.
2. **Lampiran rincian** — checklist dipisah kolom "Sudah berkembang baik" vs "Akan dilatih bulan berikutnya".
3. **Timeline** pertemuan bernomor.

Tidak mengacu pada `public/mockup-laporan-progres.html`.

## 3. Audiens & Bahasa

| Label lama | Label baru (PDF) |
|------------|------------------|
| Petunjuk checklist | *(dihapus — diganti header kolom)* |
| Pencapaian Utama | Kabar baik bulan ini |
| Pesan Guru untuk Murid & Orang Tua | Pesan dari [Nama Guru] |
| Fokus Latihan Bulan Depan | Saran latihan di rumah |
| Repertoar | Yang dipelajari bulan ini |
| X/Y tercapai | X dari Y aspek sudah berkembang baik |
| Ringkasan Per Pertemuan | Cerita per pertemuan |

## 4. Layout (DomPDF-safe saat implement)

- Strip header mahoni full width.
- Identitas anak + badge skor global (%).
- Kartu editorial (border-left gold / mahoni).
- Tabel overview progres per seksi (sel terisi, bukan CSS grid rumit).
- `page-break-before` sebelum lampiran rincian.
- Split table 2 kolom per seksi checklist.
- Timeline: table dengan nomor bulat.
- Footer: TTD + kontak admin.

## 5. Data (tidak ubah schema)

Tetap pakai field existing: `highlight`, `summary_notes`, `target_notes`, `repertoire`, sections/items, `sessionNotes`.

PHP tambahan di Blade: hitung `$globalChecked`, `$globalTotal`, `$overallPct`, skip seksi DUO (logic existing).

## 6. Batasan implement nanti

- Font: DejaVu Sans + DejaVu Serif (DomPDF).
- Hindari flex/grid kompleks; prioritaskan `<table>`.
- Warna cetak: palette krem/gold/mahoni (lihat mockup HTML).

## 7. Acceptance (orang tua)

- [ ] Dalam 10 detik: nama anak, bulan, kabar utama terbaca.
- [ ] Beda "sudah bagus" vs "nanti" tanpa legenda panjang.
- [ ] Narasi penting min ~12px, line-height ≥ 1.7.
- [ ] Cetak A4 dan zoom HP tetap terbaca.

## 8. Gate

**Jangan merge ke `pdf.blade.php` sebelum user menyetujui mockup HTML.**

Setelah approve → invoke `writing-plans` untuk rencana implement.
