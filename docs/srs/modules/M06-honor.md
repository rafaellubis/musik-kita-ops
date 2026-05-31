# SRS M06 — Honor Guru

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Kalkulasi honor dari sesi, slip bulanan, komponen manual (event, transport, lain-lain), cetak, tandai dibayar.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Index/show/print | `honors.index`, `show`, `print` | + Auditor |
| Calculate | `honors.calculate` | **Owner** |
| Edit/update slip | `honors.edit`, `update` | **Owner** |
| Mark paid | `honors.mark-paid` | **Owner** |

**Services:** `HonorCalculationService`, `EventHonorService`

**Cron:** `honor:calculate` — harian 06:00, `when()` H-2 akhir bulan

**Model:** `HonorSlip` — tabel `teacher_honor_slips`

## 3. Schema teacher_honor_slips

`slip_number` SLIP/YYYY/MM/NNNN, `teacher_id`, `month`, `year`, `base_honor`, `event_honor`, `event_honor_note`, `transport_honor`, `other_honor`, `other_honor_note`, `total_honor`, `status` DRAFT|CALCULATED|PAID, `paid_at`, `paid_by`, `created_by`

Slip **PAID** tidak boleh di-edit (validasi di controller).

## 4. Formula & Kode Honor

Formula dasar: `price_per_month × 0.5 / 4`

Kids Class: `jumlah_murid_terdaftar × Rp 42.500` per sesi

Ujian pengawas: Rp 250.000 flat (`H_UJIAN`)

Cut-off: **H-2** sebelum akhir bulan

Detail kode honor: lihat [M04-absensi.md](./M04-absensi.md) §3.

**DUO class:** ada test `DuoClassHonorTest` — ikuti implementasi `HonorCalculationService`.

## 5. Business Rules

| BR | Aturan |
|----|--------|
| Transport | Input manual — tidak ada rumus otomatis |
| Event honor | Manual + keterangan; juga dari `EventHonorService` |
| Bank guru | Tampil di header slip cetak |
| Guru login | Lihat slip sendiri di portal M10 |

## 6. File Scope

```
app/Http/Controllers/HonorController.php
app/Services/HonorCalculationService.php
app/Services/EventHonorService.php
app/Console/Commands/CalculateHonor.php
app/Models/HonorSlip.php
resources/views/honors/
```

## 7. Acceptance Criteria

- [ ] Calculate hanya jalan efektif H-2 (schedule when)
- [ ] Slip CALCULATED bisa di-update; PAID terkunci
- [ ] Cetak memuat rincian per murid + rekening bank
- [ ] Tests: `HonorCalculationServiceStudentBreakdownTest`, `HonorControllerEventHonorTest`
