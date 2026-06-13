# SRS M07 — Pengeluaran & Kas

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Catat **pengeluaran operasional** per kategori (TRANSFER) dan kelola **petty cash terpisah** (isi saldo Owner + pengeluaran kas kecil Admin). Keduanya masuk agregasi laporan keuangan dengan aturan P&L berbeda: top-up petty cash = pengeluaran P&L; expense petty cash = hanya mengurangi saldo (tidak double-hit P&L).

## 2. Controller & Route

### Pengeluaran operasional (`expenses`)

| Aksi | Route | Role |
|------|-------|------|
| Index/show expense | `expenses.index`, `show` | Owner\|Admin\|Auditor |
| Create/edit expense | `expenses.create`, `store`, `edit`, `update` | Owner\|Admin |
| Delete expense | `expenses.destroy` | **Owner** |
| Index kategori | `expense-categories.index` | Owner\|Admin\|Auditor |
| CRUD kategori | `expense-categories.*` (write) | **Owner** |

**Controller:** `ExpenseController`, `ExpenseCategoryController`

### Petty Cash (`petty-cash`)

| Aksi | Route | Role |
|------|-------|------|
| Index modul | `petty-cash.index` | Owner\|Admin\|Auditor |
| Show top-up / expense | `petty-cash.topups.show`, `petty-cash.expenses.show` | Owner\|Admin\|Auditor |
| Create/edit expense | `petty-cash.expenses.create`, `store`, `edit`, `update` | Owner\|Admin |
| Create/edit top-up | `petty-cash.topups.create`, `store`, `edit`, `update` | **Owner** |
| Delete top-up / expense | `petty-cash.topups.destroy`, `petty-cash.expenses.destroy` | **Owner** |
| Cetak laporan PDF | `GET /petty-cash/print?year=&month=` (`petty-cash.print`) | Owner\|Admin\|Auditor |

**Controller:** `PettyCashController` · **Service:** `PettyCashService`

## 3. Schema

### expenses (operasional)

Terhubung `expense_category_id`, amount, tanggal, deskripsi, `payment_method` — **hanya TRANSFER** (CASH tidak dipakai lagi; data legacy dimigrasi ke petty cash).

### expense_categories

Master kategori (Sewa, Listrik, GAJI_STAFF, dll.) — dipakai bersama oleh `expenses` dan `petty_cash_expenses`. Owner kelola.

### petty_cash_topups

| Kolom | Keterangan |
|-------|------------|
| `topup_number` | PCU/YYYY/MM/NNNN (reset per bulan) |
| `amount` | Nominal isi saldo (Rp) |
| `topup_date` | Tanggal top-up |
| `description` | Keterangan singkat |
| `notes` | Catatan opsional |
| `receipt_image` | Bukti foto (opsional) |
| `created_by` | FK users |

### petty_cash_expenses

| Kolom | Keterangan |
|-------|------------|
| `expense_number` | PCE/YYYY/MM/NNNN (reset per bulan) |
| `expense_category_id` | FK `expense_categories` |
| `amount` | Nominal pengeluaran (Rp) |
| `description` | Keterangan singkat |
| `expense_date` | Tanggal pengeluaran |
| `receipt_image` | Bukti foto (opsional) |
| `notes` | Catatan opsional |
| `created_by` | FK users |

## 4. Business Rules — Pengeluaran Operasional

- `payment_method` wajib **TRANSFER** — tidak ada opsi CASH di form baru
- Data `expenses` dengan CASH lama dimigrasi ke `petty_cash_expenses` (migration one-time)
- Laporan P&L: `expenses` operasional masuk komponen **Pengeluaran Operasional**
- Hapus pengeluaran operasional: Owner only (koreksi data)

## 5. Business Rules — Petty Cash

| BR | Aturan |
|----|--------|
| BR-PC.1 | **Saldo petty cash** = `SUM(petty_cash_topups.amount)` − `SUM(petty_cash_expenses.amount)` — independen dari pembayaran tunai murid |
| BR-PC.2 | **Owner** isi saldo via `petty_cash_topups` — masuk **P&L** sebagai pengeluaran (single hit) |
| BR-PC.3 | **Admin** catat pengeluaran via `petty_cash_expenses` — **tidak** masuk P&L (hanya mengurangi saldo) |
| BR-PC.4 | Validasi saldo: expense tidak boleh melebihi saldo tersedia (`PettyCashService`) |
| BR-PC.5 | Top-up create/edit/delete: **Owner only**; expense create/edit: Owner\|Admin; delete expense: **Owner** |
| BR-PC.6 | Export PDF bulanan: `GET /petty-cash/print?year=&month=` — Blade printable + `window.print()` (Save as PDF via browser), bukan DomPDF |

**Formula P&L (Owner):**

```
laba_bersih = revenue − honor − staff_payroll − pengeluaran_operasional(TRANSFER) − SUM(topup_bulan_ini)
```

Pengeluaran petty cash bulan berjalan hanya ditampilkan informatif di section terpisah laporan keuangan — tidak mengurangi laba lagi.

## 6. Export PDF Petty Cash

Route: `petty-cash.print` — query `year`, `month`.

**Isi laporan per bulan:**
- Header: logo + judul "Laporan Petty Cash" + periode
- Ringkasan: total top-up, total pengeluaran, saldo awal bulan, saldo akhir bulan
- Tabel mutasi kronologis: tanggal, nomor (PCU/PCE), tipe, keterangan, kategori (expense), debit/kredit, saldo berjalan

Terpisah dari `reports.finance` — dicetak dari halaman modul petty cash.

## 7. File Scope

```
app/Http/Controllers/ExpenseController.php
app/Http/Controllers/ExpenseCategoryController.php
app/Http/Controllers/PettyCashController.php
app/Services/PettyCashService.php
app/Models/Expense.php
app/Models/ExpenseCategory.php
app/Models/PettyCashTopup.php
app/Models/PettyCashExpense.php
resources/views/expenses/
resources/views/expense-categories/
resources/views/petty-cash/
database/migrations/2026_06_13_*_petty_cash_*.php
tests/Feature/PettyCashTest.php
tests/Feature/PettyCashReportTest.php
tests/Feature/PettyCashPrintTest.php
```

## 8. Acceptance Criteria

- [ ] Admin tidak bisa DELETE expense operasional maupun petty cash
- [ ] Admin tidak bisa create/edit top-up petty cash
- [ ] Form expense operasional hanya menawarkan TRANSFER (tanpa CASH)
- [ ] Expense petty cash ditolak jika saldo tidak cukup
- [ ] Dashboard menampilkan **Saldo Petty Cash** dari `PettyCashService::getCurrentBalance()`
- [ ] P&L tidak double-count: top-up masuk P&L, petty expense tidak
- [ ] `GET /petty-cash/print` dapat diakses Owner\|Admin\|Auditor dengan filter bulan
