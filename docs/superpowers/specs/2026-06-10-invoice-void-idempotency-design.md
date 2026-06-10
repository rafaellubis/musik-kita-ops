# Design: Perbaikan Idempotency Guard Setelah Void Invoice

**Tanggal:** 2026-06-10  
**Keputusan:** Opsi A — pertahankan void invoice (Owner|Admin), perbaiki guard regenerate.

## Goal

Invoice yang di-void tidak boleh memblokir regenerate SPP, KIDS_FP, atau cicilan Kids Bundle.

## Non-Goals

- Hard delete invoice
- Restrict void invoice ke Owner only (Admin tetap boleh void)

## Approach

1. Tambah scope Eloquent `Invoice::notVoid()` (`status != VOID`)
2. Terapkan scope di semua guard idempotency:
   - `InvoiceService::sppInvoiceExistsForEnrollment()`
   - `InvoiceController::generateKidsFp()`
   - `InvoiceController::generateBundle()`
   - `StudentController::show()` — guard tombol KIDS_FP + deteksi cicilan bundle
3. Test feature untuk setiap skenario void → regenerate

## Acceptance Criteria

- [ ] Void invoice SPP → `generateMonthlySPP` bisa buat invoice baru bulan/enrollment yang sama
- [ ] Void invoice KIDS_FP → tombol generate + endpoint bisa buat ulang
- [ ] Void semua cicilan bundle → tombol generate bundle muncul + endpoint sukses
- [ ] Invoice UNPAID/PAID non-void tetap memblokir duplicate (regression)
- [ ] Admin tetap bisa void invoice (test existing tetap pass)
