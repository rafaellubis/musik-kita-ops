# SRS M01 — Master Data

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

CRUD data referensi: instrumen, paket, guru (+ matriks instrumen), ruang, hari libur, formula honor, komponen tagihan, kategori pengeluaran.

## 2. Controller & Route

| Entitas | Controller | Write | Read (index) |
|---------|------------|-------|--------------|
| Instruments | `InstrumentController` | Owner\|Admin | + Auditor |
| Teachers | `TeacherController` | Owner\|Admin | + Auditor |
| Rooms | `RoomController` | Owner\|Admin | + Auditor |
| Holidays | `HolidayController` | Owner\|Admin | + Auditor |
| Packages | `PackageController` | **Owner** | Owner\|Admin\|Auditor |
| Payroll configs | `PayrollConfigController` | **Owner** | + Auditor |
| Invoice components | `InvoiceComponentController` | **Owner** | + Auditor |
| Expense categories | `ExpenseCategoryController` | **Owner** | + Auditor |

Route names: `instruments.*`, `teachers.*`, `rooms.*`, `holidays.*`, `packages.*`, `payroll-configs.*`, `invoice-components.*`, `expense-categories.*`

## 3. Schema Kritis

### packages

`code`, `instrument_id`, `class_type`, `grade` (nullable REGULER), `duration_min`, `price_per_month`, `is_active`, `sort_order`

**class_type:** `REGULER`, `HOBBY`, `DUO`, `KIDS_CLASS`, `KIDS_CLASS_BUNDLE`

Honor per sesi **tidak** disimpan di paket — formula via `PayrollConfig`.

### teachers

`code`, `name`, `email`, `phone`, `bank_name`, `bank_account`, `bank_account_holder`, `joined_date`, `is_active`, `user_id` (nullable — akun login Guru)

Matriks instrumen: pivot `teacher_instruments`.

### rooms

`code`, `name`, `capacity`, `supported_instruments` (JSON array), `is_active`

Kolom `has_piano` / `has_drum` / `has_amplifier` — **dihapus**.

### holidays

`date`, `name`, `type` (`Nasional` | `Cuti Bersama` | `Internal`), `replacement_date`, `is_honor_paid`, `is_active`, `notes`

- `replacement_date`: unik, harus bulan yang sama dengan libur
- Tipe **Internal**: `replacement_date` NULL, `is_honor_paid` = false

### invoice_components

Katalog item tagihan manual; Owner kelola. Field `amount_or_formula`, `type`, `is_active`.

### payroll_configs

Formula honor string (mis. `package_price * 0.5 / 4`) — Owner ubah tanpa deploy.

## 4. Business Rules

- Hanya **Owner** ubah harga paket (`price_per_month`)
- Guru dengan historis sesi: **nonaktifkan**, jangan hapus
- Saxophone: nonaktif jika tidak ada guru aktif untuk instrumen
- Bass → hanya YUAN; Violin → hanya RIBKA; Kids Class → ICA (domain reference)

## 5. File Scope

```
app/Http/Controllers/InstrumentController.php
app/Http/Controllers/TeacherController.php
app/Http/Controllers/RoomController.php
app/Http/Controllers/HolidayController.php
app/Http/Controllers/PackageController.php
app/Http/Controllers/PayrollConfigController.php
app/Http/Controllers/InvoiceComponentController.php
app/Http/Controllers/ExpenseCategoryController.php
resources/views/instruments/
resources/views/teachers/
resources/views/rooms/
resources/views/holidays/
resources/views/packages/
resources/views/payroll-configs/
resources/views/invoice-components/
resources/views/expense-categories/
```

## 6. Acceptance Criteria

- [ ] Admin tidak bisa POST/PATCH/DELETE packages, payroll-configs, invoice-components, expense-categories
- [ ] Holiday Internal menolak `replacement_date` di validasi
- [ ] Room form menyimpan `supported_instruments` sebagai JSON
- [ ] Toggle active instrumen/guru/ruang tercatat audit log (jika ada di controller)
