# Design Spec: Invoice Final Project Kids Class (KIDS_FP)

**Tanggal:** 2026-05-29
**Status:** Approved
**Modul:** M05 Keuangan

---

## Ringkasan

Fitur untuk men-generate invoice Final Project Kids Class (KIDS_FP) sebesar Rp 140.000/murid secara manual oleh Admin/Owner. Tombol generate muncul di tab Keuangan halaman detail murid, hanya untuk murid dengan enrollment `KIDS_CLASS` yang belum memiliki invoice KIDS_FP.

---

## Scope

Yang **termasuk**:
- Method `InvoiceController::generateKidsFp(Student)` 
- Route POST `/students/{student}/generate-kids-fp`
- Badge + tombol di tab Keuangan `students/show.blade.php`
- Modal konfirmasi Alpine.js sebelum generate
- Guard double-generate (cek duplikat server-side)
- Audit log

Yang **tidak termasuk**:
- Auto-trigger di bulan ke-6 (tetap manual)
- Transisi otomatis murid ke status "Selesai" setelah bayar (tetap manual)
- KIDS_CLASS_BUNDLE — sudah termasuk dalam bundle Rp 2.18 juta, tidak perlu KIDS_FP terpisah

---

## Arsitektur

### Route

```
POST /students/{student}/generate-kids-fp
→ InvoiceController@generateKidsFp
→ middleware: web, auth, verified, role:Owner|Admin
```

Route ditempatkan di grup `role:Owner|Admin` yang sudah ada di `routes/web.php`.

### Controller Method

`InvoiceController::generateKidsFp(Student $student)`:

1. **Guard 1** — Primary enrollment harus `KIDS_CLASS`:
   ```php
   $enrollment = $student->primaryEnrollment;
   if (!$enrollment || $enrollment->package->class_type !== 'KIDS_CLASS') {
       abort(403, 'Fitur ini hanya untuk murid Kids Class.');
   }
   ```

2. **Guard 2** — Belum ada invoice KIDS_FP untuk murid ini:
   ```php
   $sudahAda = InvoiceItem::whereHas('invoice', fn($q) =>
       $q->where('student_id', $student->id)
   )->where('item_code', 'KIDS_FP')->exists();

   if ($sudahAda) {
       return redirect()->route('students.show', $student)
           ->with('error', 'Invoice Final Project untuk murid ini sudah pernah dibuat.');
   }
   ```

3. **Generate invoice** via `InvoiceService::createOneOff()`:
   ```php
   $invoice = $this->invoiceService->createOneOff(
       student: $student,
       items: [[
           'code'        => 'KIDS_FP',
           'description' => 'Final Project Kids Class',
           'amount'      => InvoiceService::FEE_KIDS_FP,
       ]],
       classType:    'KIDS_CLASS',
       enrollmentId: $enrollment->id,
   );
   ```

4. **Audit log**:
   ```php
   AuditLog::record(
       AuditLog::ACTION_CREATE,
       $invoice,
       "Invoice KIDS_FP — {$student->full_name}",
       null,
       ['student_id' => $student->id, 'amount' => InvoiceService::FEE_KIDS_FP],
   );
   ```

5. **Redirect** ke halaman invoice baru:
   ```php
   return redirect()->route('invoices.show', $invoice)
       ->with('success', "Invoice Final Project {$student->full_name} berhasil dibuat.");
   ```

### Dependencies

- `InvoiceService::createOneOff()` — sudah ada, sudah mendukung KIDS_FP
- `InvoiceService::FEE_KIDS_FP = 140000` — sudah ada
- `InvoiceItem` model, `item_code = KIDS_FP` — sudah valid di enum migration

---

## View: students/show.blade.php — Tab Keuangan

Tambahkan **di header section invoice** (di atas daftar invoice), kondisional:

```php
// Kondisi tampil badge + tombol:
$isKidsClass = $student->primaryEnrollment?->package?->class_type === 'KIDS_CLASS';
$sudahAdaKidsFp = InvoiceItem::whereHas('invoice', fn($q) =>
    $q->where('student_id', $student->id)
)->where('item_code', 'KIDS_FP')->exists();
$tampilKidsFpButton = $isKidsClass && !$sudahAdaKidsFp;
```

Logika ini dihitung di `StudentController::show()` dan di-pass ke view sebagai `$tampilKidsFpButton`.

**Badge + Tombol (jika `$tampilKidsFpButton`):**

```blade
@if($tampilKidsFpButton)
<div class="flex items-center gap-2">
    <span class="text-xs px-3 py-1 rounded-full"
          style="background:rgba(212,168,83,0.12);border:1px solid rgba(212,168,83,0.3);color:#D4A853">
        🎓 Final Project belum ditagih
    </span>
    <button @click="showKidsFpModal = true"
            class="px-3 py-1.5 text-xs font-semibold rounded-lg btn-mk-primary">
        Generate Invoice Final Project
    </button>
</div>
@endif
```

**Modal konfirmasi (Alpine.js):**

```blade
<div x-show="showKidsFpModal" x-cloak ...>
    <!-- Info: nama murid, program, item, total Rp 140.000 -->
    <form method="POST" action="{{ route('students.generate-kids-fp', $student) }}">
        @csrf
        <!-- Tombol Batal + Buat Invoice -->
    </form>
</div>
```

Alpine state: `x-data="{ showKidsFpModal: false }"` di wrapper section keuangan (atau merge ke x-data yang sudah ada di halaman).

---

## StudentController::show() — Perubahan

Tambah dua variabel ke data yang di-pass ke view:

```php
// Cek apakah tombol KIDS_FP perlu ditampilkan
$isKidsClass = $student->primaryEnrollment?->package?->class_type === 'KIDS_CLASS';
$tampilKidsFpButton = $isKidsClass
    && !InvoiceItem::whereHas('invoice', fn($q) =>
        $q->where('student_id', $student->id)
    )->where('item_code', 'KIDS_FP')->exists();

return view('students.show', compact(
    // ... variabel yang sudah ada ...
    'tampilKidsFpButton',
));
```

---

## Audit Log

| Aksi | action | entity_type | Catatan |
|---|---|---|---|
| Generate KIDS_FP | CREATE | Invoice | new_values: student_id, amount=140000 |

---

## Validasi Business Rules

- Hanya untuk `class_type = KIDS_CLASS` — bukan KIDS_CLASS_BUNDLE
- Hanya satu KIDS_FP per murid — cek duplikat di server sebelum create
- Admin dan Owner boleh generate — cukup role:Owner\|Admin
- Transisi murid ke Selesai tetap manual terpisah

---

## Testing

File: `tests/Feature/KidsFpInvoiceTest.php`

- [ ] Admin bisa generate KIDS_FP untuk murid KIDS_CLASS
- [ ] Owner bisa generate KIDS_FP untuk murid KIDS_CLASS  
- [ ] Generate gagal jika murid bukan KIDS_CLASS (403)
- [ ] Generate gagal jika KIDS_FP sudah ada untuk murid ini (redirect dengan error)
- [ ] Invoice yang dibuat: class_type=KIDS_CLASS, item_code=KIDS_FP, amount=140000
- [ ] Redirect ke halaman invoice setelah berhasil
- [ ] Auditor tidak bisa generate (403)

---

## File yang Dibuat / Dimodifikasi

| File | Status |
|---|---|
| `app/Http/Controllers/InvoiceController.php` | Modifikasi — tambah method `generateKidsFp()` |
| `app/Http/Controllers/StudentController.php` | Modifikasi — pass `$tampilKidsFpButton` ke view |
| `resources/views/students/show.blade.php` | Modifikasi — badge + tombol + modal di tab Keuangan |
| `routes/web.php` | Modifikasi — tambah 1 route POST |
| `tests/Feature/KidsFpInvoiceTest.php` | Baru |
