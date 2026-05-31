# SRS — Musik KITA Operations System

Dokumen **Software Requirements Specification** sesuai kondisi kode aktual.

## Cara pakai dengan AI

```
Implementasi [fitur] sesuai docs/srs/modules/M05-keuangan.md §Validasi.
Patuhi role di SRS utama §2. Cek divergensi di SRS utama §10.
```

## Daftar dokumen

| File | Modul |
|------|--------|
| [SRS-musik-kita-ops-2026-05-31.md](./SRS-musik-kita-ops-2026-05-31.md) | **SRS utama** — ringkasan sistem, role, divergensi |
| [modules/M01-master-data.md](./modules/M01-master-data.md) | Master data |
| [modules/M02-pendaftaran.md](./modules/M02-pendaftaran.md) | Murid, lifecycle, multi-kelas, import |
| [modules/M03-penjadwalan.md](./modules/M03-penjadwalan.md) | Jadwal, generator sesi, kalender |
| [modules/M04-absensi.md](./modules/M04-absensi.md) | Absensi, reschedule, open slot |
| [modules/M05-keuangan.md](./modules/M05-keuangan.md) | Invoice, pembayaran, diskon, denda |
| [modules/M06-honor.md](./modules/M06-honor.md) | Honor guru & slip |
| [modules/M07-pengeluaran.md](./modules/M07-pengeluaran.md) | Pengeluaran & petty cash |
| [modules/M08-event.md](./modules/M08-event.md) | Mini Concert & Ujian |
| [modules/M09-laporan.md](./modules/M09-laporan.md) | Dashboard, laporan, audit |
| [modules/M10-guru-portal.md](./modules/M10-guru-portal.md) | Portal login Guru |
| [modules/M11-laporan-progres.md](./modules/M11-laporan-progres.md) | Laporan progres murid |

## Pembaruan

- **2026-05-31** — Versi awal dari audit `routes/web.php`, migrasi, services.
- Saat schema/BR berubah: update modul terkait + §10 di SRS utama.

## Dokumen terkait

- `CLAUDE.md` — briefing lengkap (bisa lebih baru/lama dari SRS)
- `docs/ONBOARDING.md` — onboarding developer
- `.cursor/rules/*.mdc` — rules Cursor (sinkronkan dengan §10 SRS utama)
