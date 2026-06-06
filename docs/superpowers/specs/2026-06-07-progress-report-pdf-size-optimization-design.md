# Design Spec: Progress Report PDF Size Optimization + Header Redesign

**Tanggal:** 2026-06-07  
**Status:** Approved  
**Target ukuran:** Sesempit mungkin (< 150 KB ideal)

---

## 1. Masalah

PDF laporan progress ~**1.310 KB** karena:
- Logo PNG 266 KB di-embed full resolution
- Font DejaVu full embed (`enable_font_subsetting: false`)
- Emoji Unicode di footer
- Dua font family (Sans + Mono)

---

## 2. Solusi

| Optimasi | Detail |
|---|---|
| Logo JPEG cached | `ProgressReportPdfAssetService` resize max 160px, quality 72 → `storage/app/pdf/logo-musikkita.jpg` |
| Font subsetting | `enable_font_subsetting: true` pada generate PDF laporan progress saja |
| Satu font | DejaVu Sans only (nomor laporan tanpa Mono) |
| Hapus emoji PDF | Footer pakai `getReportInstrumentLabel()` tanpa emoji |
| Header baru | Atas: blok kuitansi (kiri) — logo + alamat + WA, **tanpa** tagline "Les Musik · Toko Alat Musik" |
| Branded strip | Navy compact, **tanpa logo**, judul + periode saja |
| Nomor laporan | Kanan atas (sejajar bar studio header) |

---

## 3. Layout PDF (atas → bawah)

```
┌─────────────────────────────────────────────────────────┐
│ [logo kecil]                              LMK/LPR/...   │
│ Ruko Serpong Garden...                                  │
│ WA: 0816-92-05-92                                      │
├─────────────────────────────────────────────────────────┤
│ MONTHLY PROGRESS REPORT          (navy strip, compact)  │
│ Periode: Juni 2026                                      │
├─────────────────────────────────────────────────────────┤
│ meta + sections ...                                     │
└─────────────────────────────────────────────────────────┘
```

Studio contact dari `config/studio.php`.

---

## 4. Out of Scope

- Redesign kuitansi/invoice PDF
- Ganti engine PDF (wkhtmltopdf)
