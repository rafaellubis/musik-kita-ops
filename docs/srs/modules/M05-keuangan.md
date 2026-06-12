# SRS M05 — Keuangan Murid

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Invoice (SPP, registrasi, denda, item manual), pembayaran, diskon, cicilan Kids Bundle, kuitansi, cron SPP & denda.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Index/show/print invoice | `invoices.index`, `show`, `print` | + Auditor |
| Generate SPP | `invoices.generate-spp` | Owner\|Admin |
| Apply fines | `invoices.apply-fines` | Owner\|Admin |
| Kids bundle 3 termin | `invoices.generate-bundle` | Owner\|Admin |
| Kids final project | `invoices.generate-kids-fp` | Owner\|Admin |
| Store payment | `payments.store` | Owner\|Admin |
| Void invoice | `invoices.void` | Owner\|Admin |
| Void payment | `payments.void` | **Owner** |
| Receipt | `payments.receipt` | + Auditor |
| Store item manual | `invoice-items.store` | Owner\|Admin |
| Destroy item manual | `invoice-items.destroy` | Owner\|Admin |
| Store/destroy diskon | `invoice-items.discount.*` | Owner\|Admin |
| Hapus denda (waiver) | `invoice-items.remove-fine` | Owner\|Admin |

**Services:** `InvoiceService`, `PaymentService`, `DiscountService`

**Cron:** `invoices:generate-spp` (tgl 1), `invoices:apply-fines` (hari ≥11), `students:check-overdue` (tgl 1)

## 3. Schema

### invoices

`invoice_number` INV/YYYY/MM/NNNN, `student_id`, `enrollment_id` (**wajib** untuk SPP), `month`, `year`, `class_type` snapshot, `payment_mode` FULL|INSTALLMENT, `installment_number`, `installment_group_id`, `total_amount`, `paid_amount`, `status`, `due_date`

Kolom waiver denda: `fine_waived_at`, `fine_waived_by`, `fine_waive_reason` — set saat Owner/Admin hapus denda; cron skip invoice ini.

INSTALLMENT hanya `KIDS_CLASS_BUNDLE`.

### invoice_items

`item_code`: REG, SPP, KIDS_FP, CUTI, UJI, MC, DENDA, DISKON

Diskon: `parent_item_id`, `discount_type` NOMINAL|PERCENT, `discount_value`, `discount_reason` (wajib)

### payments

`receipt_number` KW/YYYY/MM/NNNN, `method` CASH|TRANSFER|QRIS|DEBIT, `voided_at`, `voided_by`, `voided_reason`, `created_by`

## 4. Komponen Tagihan (nominal)

| Kode | Nominal |
|------|---------|
| REG | Rp 250.000 |
| SPP | `packages.price_per_month` |
| KIDS_FP | Rp 140.000 |
| CUTI | Rp 100.000 |
| UJI | Rp 395.000 |
| MC | Rp 295.000 |
| DENDA | Rp 5.000 × max(0, hari−10) |

## 5. Business Rules

| BR | Aturan |
|----|--------|
| SPP auto | Tgl 1; **per enrollment ACTIVE** (multi-kelas = multi invoice) |
| Skip SPP | TRIAL, ON_LEAVE, INACTIVE, COMPLETED, KIDS_CLASS_BUNDLE (pakai cicilan) |
| Tempo | 1–10; denda dari tgl 11 |
| Lunas | SPP + seluruh denda terbayar |
| Denda | Independen per invoice |
| Diskon | Hanya UNPAID/PARTIAL; max 90% (kecuali DENDA boleh 100%) |
| Void invoice | Owner\|Admin; ditolak jika ada pembayaran aktif |
| Void payment | Owner only |
| Tunggakan | Notifikasi >1 bulan; auto-mundur cron **belum** ada |

**Catatan divergensi:** BR-DSK.5 hapus diskon Owner-only — route aktual Owner|Admin.

## 6. File Scope

```
app/Http/Controllers/InvoiceController.php
app/Http/Controllers/PaymentController.php
app/Http/Controllers/InvoiceItemController.php
app/Http/Controllers/DiscountController.php
app/Services/InvoiceService.php
app/Services/PaymentService.php
app/Services/DiscountService.php
app/Console/Commands/GenerateMonthlySpp.php
app/Console/Commands/ApplyLateFines.php
app/Console/Commands/CheckOverdueStudents.php
resources/views/invoices/
resources/views/payments/
```

## 7. Acceptance Criteria

- [ ] Invoice SPP selalu punya `enrollment_id`
- [ ] Diskon = item terpisah amount negatif + parent_item_id
- [ ] Void invoice boleh Admin; void payment menolak Admin (403)
- [ ] Recalc `total_amount` / status setelah payment & diskon
- [ ] Tests: `MultiKelasInvoiceTest`, `DiscountTest`, `KidsFpInvoiceTest`, `KidsBundleInstallmentUiTest`
