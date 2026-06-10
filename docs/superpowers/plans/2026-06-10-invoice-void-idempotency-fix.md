# Invoice Void Idempotency Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Invoice VOID tidak memblokir regenerate SPP, KIDS_FP, atau cicilan Kids Bundle; Admin tetap boleh void.

**Architecture:** Scope Eloquent `notVoid()` di model `Invoice`, diterapkan ke guard idempotency di service + controller. Test feature TDD per skenario.

**Tech Stack:** Laravel 11, PHPUnit, MySQL/SQLite test DB

---

### Task 1: Scope `notVoid()` + fix guard SPP

**Files:**
- Modify: `app/Models/Invoice.php`
- Modify: `app/Services/InvoiceService.php` (method `sppInvoiceExistsForEnrollment`)
- Test: `tests/Feature/InvoiceVoidRegenerateTest.php`

- [ ] **Step 1: Write failing test**

```php
public function test_generate_spp_bisa_setelah_invoice_spp_di_void(): void
{
    $student = Student::factory()->create(['status' => 'Aktif']);
    $pkg = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
    $e1 = Enrollment::factory()->for($student)->create([
        'package_id' => $pkg->id, 'status' => 'ACTIVE', 'is_primary' => true,
    ]);
    $student->update(['primary_enrollment_id' => $e1->id]);

    $this->service->generateMonthlySPP(2026, 6);
    $invoice = Invoice::where('student_id', $student->id)->first();
    $this->service->voidInvoice($invoice, User::factory()->create(), 'Duplikat');

    $report = $this->service->generateMonthlySPP(2026, 6);
    $this->assertEquals(1, $report['created']);
    $this->assertCount(2, Invoice::where('student_id', $student->id)->get());
}
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `php artisan test --filter=test_generate_spp_bisa_setelah_invoice_spp_di_void`
Expected: FAIL (`created` = 0)

- [ ] **Step 3: Implement scope + guard**

`Invoice.php`:
```php
public function scopeNotVoid($query)
{
    return $query->where('status', '!=', self::STATUS_VOID);
}
```

`InvoiceService.php` — tambahkan `->notVoid()` pada kedua query di `sppInvoiceExistsForEnrollment()`.

- [ ] **Step 4: Run test — expect PASS**

Run: `php artisan test --filter=InvoiceVoidRegenerateTest`
Expected: PASS

---

### Task 2: Fix guard KIDS_FP

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (`generateKidsFp`)
- Modify: `app/Http/Controllers/StudentController.php` (`show` — `$tampilKidsFpButton`)
- Test: `tests/Feature/InvoiceVoidRegenerateTest.php`

- [ ] **Step 1: Write failing test**

```php
public function test_generate_kids_fp_bisa_setelah_invoice_di_void(): void
{
    // setup KIDS_CLASS student + admin user (copy pattern KidsFpInvoiceTest)
    // generate KIDS_FP, void via InvoiceService, post generate-kids-fp again → success
}
```

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Add `->notVoid()` to invoice whereHas in both controllers**

- [ ] **Step 4: Run test — expect PASS**

---

### Task 3: Fix guard Kids Bundle cicilan

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (`generateBundle`)
- Modify: `app/Http/Controllers/StudentController.php` (`show` — `$latestGroup` query)
- Test: `tests/Feature/InvoiceVoidRegenerateTest.php`

- [ ] **Step 1: Write failing test**

```php
public function test_generate_bundle_bisa_setelah_semua_cicilan_di_void(): void
{
    // buat murid bundle, generate 3 cicilan, void all 3, post generate-bundle → 3 invoice baru
}
```

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Add `->notVoid()` to generateBundle exists check + latestGroup query**

- [ ] **Step 4: Run test — expect PASS**

---

### Task 4: Regression suite

- [ ] **Step 1: Run full related tests**

Run:
```bash
php artisan test --filter=InvoiceVoid
php artisan test --filter=MultiKelasInvoice
php artisan test --filter=KidsFpInvoice
php artisan test --filter=KidsBundleInstallment
php artisan test --filter=InvoiceVoidRegenerate
```

Expected: ALL PASS
