# SRS M09 — Laporan, Dashboard & Audit

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Dashboard operasional & P&L, laporan murid, laporan keuangan (Owner), notifikasi tunggakan, audit log. Setelah pemisahan petty cash (Jun 2026): dashboard menampilkan saldo petty cash terpisah; laporan keuangan Owner punya **3 section** (ringkasan P&L, pengeluaran operasional, petty cash informatif); export PDF petty cash terpisah dari `reports.finance`.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Dashboard | `dashboard` | Owner\|Admin\|Auditor |
| Laporan murid | `reports.students` | Owner\|Admin\|Auditor |
| Laporan keuangan | `reports.finance` | **Owner** |
| Cetak PDF petty cash | `petty-cash.print` (`GET /petty-cash/print?year=&month=`) | Owner\|Admin\|Auditor |
| Audit log | `audit-logs.index` | **Owner** |
| Notifikasi read | `notifications.read`, `read-all` | Owner\|Admin |

**Controller:** `DashboardController`, `ReportController`, `AuditLogController`, `NotificationController`, `PettyCashController` (print)

**Command:** `students:check-overdue` → notifikasi ke Owner/Admin

## 3. Dashboard (DashboardController)

**Semua role:** statistik murid per status, aging piutang, invoice terlama, honor belum dibayar.

**Owner & Auditor:** kartu **Saldo Petty Cash** — nilai dari `PettyCashService::getCurrentBalance()` (bukan kalkulasi payment CASH murid).

**Owner only:** revenue vs pengeluaran (operasional + top-up petty), laba/rugi, chart 6 bulan, distribusi instrumen. Chart pengeluaran bulanan = `expenses(TRANSFER)` + `petty_cash_topups` — **bukan** `petty_cash_expenses`.

## 4. Laporan Keuangan (`reports.finance`)

Akses **Owner only**. Filter bulan/tahun. Tampilan **3 section**:

| Section | Isi | Dampak P&L |
|---------|-----|------------|
| **1. Ringkasan P&L** | Revenue, honor guru, gaji staff, pengeluaran operasional, isi saldo petty cash, laba bersih | Top-up petty masuk pengeluaran; petty expense **tidak** |
| **2. Pengeluaran Operasional** | Breakdown per kategori dari tabel `expenses` (TRANSFER only) | Sudah termasuk di section 1 |
| **3. Petty Cash (informatif)** | Total top-up bulan ini, total pengeluaran petty bulan ini, saldo akhir, daftar `petty_cash_expenses` | Hanya informasi saldo — tidak mengurangi laba lagi |

**Formula laba bersih:**

```
laba_bersih = revenue − honor − staff_payroll − pengeluaran_operasional − total_topup_petty_bulan_ini
```

## 5. Export PDF Petty Cash (terpisah)

Tidak bagian dari `reports.finance`. Route `petty-cash.print` — halaman Blade khusus cetak (`resources/views/petty-cash/print.blade.php`) dengan `@media print` dan tombol `window.print()` untuk Save as PDF via browser.

**Isi:** ringkasan bulan (top-up, pengeluaran, saldo awal/akhir) + tabel mutasi kronologis (PCU/PCE). Diakses dari halaman modul petty cash (`petty-cash.index`).

## 6. Audit Log

`audit_logs`: action CREATE|UPDATE|DELETE|LOGIN|LOGOUT|PRINT|VOID, `entity_type`, `entity_id`, old/new JSON.

Direkam dari services/controllers untuk aksi penting (payment void, diskon, top-up petty cash, dll.).

## 7. Scope OUT (belum / terbatas)

- Export Excel/PDF laporan lengkap (retensi, okupansi) — tidak full BRD
- Broadcast template WhatsApp
- Audit log hapus via UI — **dilarang**
- DomPDF untuk petty cash — tidak dipakai (cukup browser print)

## 8. File Scope

```
app/Http/Controllers/DashboardController.php
app/Http/Controllers/ReportController.php
app/Http/Controllers/PettyCashController.php
app/Http/Controllers/AuditLogController.php
app/Http/Controllers/NotificationController.php
app/Services/PettyCashService.php
app/Console/Commands/CheckOverdueStudents.php
app/Models/AuditLog.php
resources/views/dashboard/
resources/views/reports/finance.blade.php
resources/views/petty-cash/print.blade.php
resources/views/audit-logs/
tests/Feature/DashboardRoleViewTest.php
tests/Feature/PettyCashReportTest.php
tests/Feature/PettyCashPrintTest.php
tests/Feature/ReportFinanceMethodTest.php
tests/Feature/CheckOverdueStudentsTest.php
```

## 9. Acceptance Criteria

- [ ] Admin/Auditor tidak melihat P&L detail Owner di dashboard
- [ ] Owner & Auditor melihat kartu **Saldo Petty Cash** di dashboard
- [ ] `reports.finance` 403 untuk Admin
- [ ] Laporan keuangan punya 3 section terpisah (P&L, operasional, petty cash informatif)
- [ ] Petty expense tidak mengurangi laba bersih di ringkasan P&L
- [ ] PDF petty cash via `petty-cash.print` terpisah dari halaman `reports.finance`
- [ ] Notifikasi overdue idempotent per bulan
- [ ] Tests: `DashboardRoleViewTest`, `PettyCashReportTest`, `PettyCashPrintTest`, `ReportFinanceMethodTest`, `CheckOverdueStudentsTest`
