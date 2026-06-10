# SRS M12 — Penggajian Staff Non-Guru

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Kelola gaji bulanan karyawan non-guru (admin, staff operasional): master data, slip per bulan, komponen variabel/potongan manual, cetak slip, tandai dibayar, dan posting otomatis ke M07 Pengeluaran.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Index/show employee | `employees.index`, `show` | Owner\|Admin\|Auditor |
| CRUD employee | `employees.*` (write) | **Owner** |
| Index/show/print slip | `staff-payrolls.index`, `show`, `print` | Owner\|Admin\|Auditor |
| Generate slip | `staff-payrolls.generate` | **Owner** |
| Tambah/edit/hapus item | `staff-payrolls.items.*` | **Owner** |
| Mark paid | `staff-payrolls.mark-paid` | **Owner** |
| Void paid | `staff-payrolls.void-paid` | **Owner** |

**Service:** `StaffPayrollService`

**Model:** `Employee`, `StaffPayrollSlip`, `StaffPayrollItem`

## 3. Schema

### employees

`employee_code` STAFF-NNN (auto-generate), `full_name`, `position`, `user_id` (nullable FK users), `base_salary`, bank fields, `joined_date`, `is_active`, `notes`

### staff_payroll_slips

`slip_number` LMK/SLIP/YYYY/MM/NNN, `employee_id`, `month`, `year`, `base_salary`, `total_allowances`, `total_deductions`, `net_salary`, `status` DRAFT\|CALCULATED\|PAID, `paid_at`, `paid_by`, `expense_id`, `created_by`

### staff_payroll_items

`staff_payroll_slip_id`, `item_type` ALLOWANCE\|OVERTIME\|DEDUCTION, `item_code`, `description`, `amount`, `metadata` JSON

## 4. Business Rules

| BR | Aturan |
|----|--------|
| BR-M12.1 | Guru tidak masuk tabel `employees` — honor via M06 |
| BR-M12.2 | Satu slip per karyawan per bulan (unique employee_id+year+month) |
| BR-M12.3 | Slip PAID terkunci; void paid hanya Owner |
| BR-M12.4 | Mark paid membuat Expense kategori GAJI_STAFF + link `expense_id` |
| BR-M12.5 | Potongan (BPJS, kasbon, absen) input manual oleh Owner |
| BR-M12.6 | `net_salary` = base_salary + allowances + overtime - deductions |

## 5. File Scope

```
app/Http/Controllers/EmployeeController.php
app/Http/Controllers/StaffPayrollController.php
app/Services/StaffPayrollService.php
app/Models/Employee.php
app/Models/StaffPayrollSlip.php
app/Models/StaffPayrollItem.php
resources/views/employees/
resources/views/staff-payrolls/
tests/Feature/StaffPayrollTest.php
```

## 6. Acceptance Criteria

- [ ] Owner bisa CRUD karyawan dan generate slip bulanan
- [ ] Owner bisa tambah tunjangan/lembur/potongan manual
- [ ] Mark paid membuat expense GAJI_STAFF dan mengunci slip
- [ ] Void paid menghapus expense terlink dan mengembalikan slip ke CALCULATED
- [ ] Laporan P&L menampilkan breakdown gaji staff
