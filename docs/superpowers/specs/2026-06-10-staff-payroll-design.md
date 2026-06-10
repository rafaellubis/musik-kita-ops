# Desain M12 — Penggajian Staff Non-Guru

> Tanggal: 2026-06-10 | Status: Disetujui untuk implementasi

## Ringkasan

Modul M12 menambahkan payroll karyawan non-guru (admin, staff operasional) dengan pola mirror M06 Honor Guru: master karyawan, slip bulanan `GAJI/YYYY/MM/NNNN`, komponen variabel/potongan manual, dan auto-post ke pengeluaran kategori `GAJI_STAFF` saat ditandai dibayar.

## Scope Fase 1

- Master `employees` terpisah dari `teachers`
- Slip `staff_payroll_slips` + baris `staff_payroll_items`
- Generate bulanan, input tunjangan/lembur/potongan manual (Owner)
- Cetak slip, tandai dibayar → buat `expenses` terlink
- Laporan P&L: breakdown gaji staff per karyawan

## Out of Scope

- BPJS/PPh 21 otomatis
- Portal staff self-service
- Integrasi absensi
- THR otomatis

## RBAC

| Aksi | Role |
|------|------|
| CRUD karyawan | Owner |
| Lihat slip & karyawan | Owner, Admin, Auditor |
| Kelola komponen slip | Owner |
| Generate / mark paid / void paid | Owner |
