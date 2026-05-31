# SRS M09 — Laporan, Dashboard & Audit

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Dashboard operasional & P&L, laporan murid, laporan keuangan (Owner), notifikasi tunggakan, audit log.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Dashboard | `dashboard` | Owner\|Admin\|Auditor |
| Laporan murid | `reports.students` | Owner\|Admin\|Auditor |
| Laporan keuangan | `reports.finance` | **Owner** |
| Audit log | `audit-logs.index` | **Owner** |
| Notifikasi read | `notifications.read`, `read-all` | Owner\|Admin |

**Controller:** `DashboardController`, `ReportController`, `AuditLogController`, `NotificationController`

**Command:** `students:check-overdue` → notifikasi ke Owner/Admin

## 3. Dashboard (DashboardController)

**Semua role:** statistik murid per status, aging piutang, invoice terlama, honor belum dibayar, petty cash ringkas.

**Owner only:** revenue vs pengeluaran, laba/rugi, chart 6 bulan, distribusi instrumen.

## 4. Audit Log

`audit_logs`: action CREATE|UPDATE|DELETE|LOGIN|LOGOUT|PRINT|VOID, `entity_type`, `entity_id`, old/new JSON.

Direkam dari services/controllers untuk aksi penting (payment void, diskon, dll.).

## 5. Scope OUT (belum / terbatas)

- Export Excel/PDF laporan lengkap (retensi, okupansi) — tidak full BRD
- Broadcast template WhatsApp
- Audit log hapus via UI — **dilarang**

## 6. File Scope

```
app/Http/Controllers/DashboardController.php
app/Http/Controllers/ReportController.php
app/Http/Controllers/AuditLogController.php
app/Http/Controllers/NotificationController.php
app/Console/Commands/CheckOverdueStudents.php
app/Models/AuditLog.php
resources/views/dashboard/
resources/views/reports/
resources/views/audit-logs/
```

## 7. Acceptance Criteria

- [ ] Admin/Auditor tidak melihat P&L detail Owner di dashboard
- [ ] `reports.finance` 403 untuk Admin
- [ ] Notifikasi overdue idempotent per bulan
- [ ] Tests: `DashboardRoleViewTest`, `ReportFinanceMethodTest`, `CheckOverdueStudentsTest`
