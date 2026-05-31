# SRS M07 — Pengeluaran & Kas

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Catat pengeluaran per kategori, ringkasan petty cash, masuk agregasi P&L dashboard.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Index/show expense | `expenses.index`, `show` | + Auditor |
| Create/edit expense | `expenses.create`, `store`, `edit`, `update` | Owner\|Admin |
| Delete expense | `expenses.destroy` | **Owner** |
| Index kategori | `expense-categories.index` | + Auditor |
| CRUD kategori | `expense-categories.*` (write) | **Owner** |

## 3. Schema

### expenses

Terhubung `expense_category_id`, amount, tanggal, deskripsi, `payment_method` (CASH dipakai untuk petty cash).

### expense_categories

Master kategori (Sewa, Listrik, dll.) — Owner kelola.

## 4. Business Rules

- Pengeluaran cash hari ini mempengaruhi **petty cash saldo** di index expenses & dashboard
- Laporan P&L Owner: revenue payments vs expenses bulan berjalan
- Hapus pengeluaran: Owner only (koreksi data)

## 5. File Scope

```
app/Http/Controllers/ExpenseController.php
app/Http/Controllers/ExpenseCategoryController.php
app/Models/Expense.php
app/Models/ExpenseCategory.php
resources/views/expenses/
resources/views/expense-categories/
```

## 6. Acceptance Criteria

- [ ] Admin tidak bisa DELETE expense
- [ ] Dashboard Owner menampilkan pengeluaran bulan ini
- [ ] Petty cash konsisten dengan sum expense CASH hari ini
