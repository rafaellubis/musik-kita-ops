# Design: Owner Edit Pesan Semangat Laporan Sesi

**Tanggal:** 2026-06-08  
**Modul:** M05 Keuangan / Master Data WA  
**Status:** Disetujui user (revisi: rating 1 & 2 terpisah)

---

## 1. Masalah

Placeholder `{pesan_semangat}` di template laporan sesi diisi kalimat hardcoded di `SessionReportWaService::encouragementLine()`. Owner tidak bisa mengubah isi pesan semangat lewat UI Template WA.

## 2. Tujuan

- Owner mengedit **6 variasi pesan semangat** per template langsung di halaman Edit Template
- Hanya untuk `SESSION_REPORT` (ortu) dan `SESSION_REPORT_STUDENT` (murid)
- Teks **statis** — sama untuk semua murid, tanpa placeholder dinamis `{nama_murid}`

## 3. Keputusan Desain

| Aspek | Keputusan |
|-------|-----------|
| Lokasi UI | Inline di form Edit Template WA |
| Penyimpanan | Kolom JSON `encouragement_lines` di `whatsapp_message_templates` |
| Placeholder | Tidak — teks dikirim persis seperti Owner tulis |
| Rating mapping | 5→rating_5, 4→rating_4, 3→rating_3, 2→rating_2, 1→rating_1, null/kosong→default |
| Fallback | Hardcoded generik di model jika DB kosong |
| Akses | Owner only |

## 4. Format JSON (6 key)

```json
{
  "rating_5": "...",
  "rating_4": "...",
  "rating_3": "...",
  "rating_2": "...",
  "rating_1": "...",
  "default": "..."
}
```

`default` hanya dipakai saat guru **tidak memilih rating** (opsional).

## 5. Default Seed (generik, tanpa nama murid)

**SESSION_REPORT (ortu):**

| Key | Kalimat default |
|-----|-----------------|
| rating_5 | Hari ini tampil sangat antusias dan fokus — perkembangannya terlihat jelas! |
| rating_4 | Menunjukkan kemajuan yang baik hari ini. Pertahankan semangatnya! |
| rating_3 | Sudah berusaha dengan baik. Sedikit latihan rutin di rumah akan membuat hasilnya makin terasa. |
| rating_2 | Perlu sedikit lebih fokus di sesi berikutnya. Dukungan latihan di rumah akan sangat membantu. |
| rating_1 | Hari ini agak kurang fokus — tidak apa-apa, setiap proses punya naik turunnya. Mari coba lagi dengan semangat baru. |
| default | Setiap sesi adalah langkah berharga. Mari terus mendampingi dengan sabar dan konsisten. |

**SESSION_REPORT_STUDENT (murid):**

| Key | Kalimat default |
|-----|-----------------|
| rating_5 | Kamu tampil sangat antusias dan fokus hari ini — keren banget! Pertahankan ya! |
| rating_4 | Kemajuanmu hari ini kelihatan banget. Terus semangat latihannya ya! |
| rating_3 | Kamu sudah berusaha dengan baik hari ini. Latihan singkat tiap hari akan bikin hasil makin terasa. |
| rating_2 | Kamu bisa lebih fokus lagi di sesi berikutnya. Latihan singkat di rumah akan banyak membantu! |
| rating_1 | Hari ini agak kurang fokus — tidak apa-apa. Setiap proses punya naik turunnya. Semangat lagi minggu depan ya! |
| default | Setiap sesi adalah langkah berharga — terus semangat ya! |

## 6. Out of Scope

- Placeholder dinamis di pesan semangat
- Edit encouragement untuk INVOICE_REMINDER / SCHEDULE_REMINDER
- Preview live pesan di form edit
