# KIDS_FP Invoice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah tombol generate invoice Final Project Kids Class (Rp 140.000) di halaman detail murid, hanya untuk murid KIDS_CLASS yang belum punya invoice KIDS_FP.

**Architecture:** Method `InvoiceController::generateKidsFp()` dipanggil via POST route. Guard double-generate di server. Badge + tombol + modal konfirmasi Alpine.js di tab Tagihan `students/show.blade.php`. Logic generate reuse `InvoiceService::createOneOff()` yang sudah ada.

**Tech Stack:** Laravel 11, Blade + Alpine.js, Tailwind CSS, PHPUnit (TDD).

---

## File Map

| File | Status | Tanggung Jawab |
|---|---|---|
| `app/Http/Controllers/InvoiceController.php` | Modifikasi | Tambah method `generateKidsFp(Student $student)` |
| `app/Http/Controllers/StudentController.php` | Modifikasi | Pass `$tampilKidsFpButton` ke view di method `show()` |
| `resources/views/students/show.blade.php` | Modifikasi | Badge + tombol + modal konfirmasi di tab Tagihan |
| `routes/web.php` | Modifikasi | Tambah 1 route POST `students/{student}/generate-kids-fp` |
| `tests/Feature/KidsFpInvoiceTest.php` | Baru | 7 test case (TDD) |

---

## Task 1: Route + Controller Stub

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/InvoiceController.php`

- [ ] **Step 1: Tambah route ke web.php**

Di `routes/web.php`, cari baris:
```php
Route::post('students/{student}/generate-bundle',
    [InvoiceController::class, 'generateBundle']
)->name('invoices.generate-bundle');
```

Tambah tepat setelahnya (setelah baris closing `);`):

```php
// M05: Generate invoice Final Project Kids Class (KIDS_FP)
Route::post('students/{student}/generate-kids-fp',
    [InvoiceController::class, 'generateKidsFp']
)->name('invoices.generate-kids-fp');
```

- [ ] **Step 2: Tambah method stub ke InvoiceController**

Di `app/Http/Controllers/InvoiceController.php`, tambah method berikut di akhir class (sebelum kurung kurawal penutup `}`):

```php
/**
 * Generate invoice Final Project Kids Class (KIDS_FP).
 * Hanya untuk murid KIDS_CLASS yang belum punya invoice KIDS_FP.
 */
public function generateKidsFp(Student $student): RedirectResponse
{
    abort(501); // TODO: implementasi Task 3
}
```

Pastikan `use App\Models\Student;` sudah ada di import. Jika belum, tambahkan.

- [ ] **Step 3: Verifikasi route terdaftar**

```bash
php artisan route:list --name=invoices.generate-kids-fp
```

Expected: 1 route POST `/students/{student}/generate-kids-fp`.

- [ ] **Step 4: Commit**

```bash
git add routes/web.php app/Http/Controllers/InvoiceController.php
git commit -m "M05: Tambah route + stub generateKidsFp di InvoiceController"
```

---

## Task 2: Feature Tests (TDD)

**Files:**
- Create: `tests/Feature/KidsFpInvoiceTest.php`

- [ ] **Step 1: Buat file test**

```bash
php artisan make:test KidsFpInvoiceTest
```

- [ ] **Step 2: Isi file test lengkap**

Tulis `tests/Feature/KidsFpInvoiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KidsFpInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    // ===== Helper =====

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Admin');
        return $user;
    }

    private function owner(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Owner');
        return $user;
    }

    /**
     * Buat murid KIDS_CLASS aktif dengan primary enrollment.
     * Mengembalikan ['student', 'package', 'enrollment'].
     */
    private function makeKidsClassStudent(): array
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $package    = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS',
            'price_per_month' => 340000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        return compact('student', 'package', 'enrollment');
    }

    // ===== Tests =====

    public function test_admin_bisa_generate_kids_fp_invoice(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertRedirect();

        $invoice = Invoice::where('student_id', $student->id)->first();
        $this->assertNotNull($invoice, 'Invoice KIDS_FP tidak dibuat.');
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'item_code'  => 'KIDS_FP',
            'amount'     => InvoiceService::FEE_KIDS_FP,
        ]);
    }

    public function test_owner_bisa_generate_kids_fp_invoice(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        $this->actingAs($this->owner())
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'class_type' => 'KIDS_CLASS',
        ]);
    }

    public function test_auditor_tidak_bisa_generate_kids_fp(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        $auditor = User::factory()->create(['is_active' => true]);
        $auditor->assignRole('Auditor');

        $this->actingAs($auditor)
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertForbidden();
    }

    public function test_generate_gagal_jika_bukan_kids_class(): void
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $package    = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 370000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student))
            ->assertForbidden();
    }

    public function test_generate_gagal_jika_kids_fp_sudah_ada(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        // Buat KIDS_FP pertama — harus berhasil
        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student));

        // Coba generate kedua kali — harus gagal
        $response = $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Hanya ada 1 invoice KIDS_FP untuk murid ini
        $this->assertEquals(1,
            Invoice::where('student_id', $student->id)
                ->whereHas('items', fn($q) => $q->where('item_code', 'KIDS_FP'))
                ->count()
        );
    }

    public function test_invoice_yang_dibuat_memiliki_data_benar(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->makeKidsClassStudent();

        $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student));

        $invoice = Invoice::where('student_id', $student->id)->first();
        $this->assertEquals('KIDS_CLASS', $invoice->class_type);
        $this->assertEquals(InvoiceService::FEE_KIDS_FP, $invoice->total_amount);
        $this->assertEquals('UNPAID', $invoice->status);
        $this->assertEquals($enrollment->id, $invoice->enrollment_id);

        $item = $invoice->items->first();
        $this->assertEquals('KIDS_FP', $item->item_code);
        $this->assertEquals(InvoiceService::FEE_KIDS_FP, $item->amount);
        $this->assertEquals('Final Project Kids Class', $item->description);
    }

    public function test_setelah_generate_redirect_ke_invoice_show(): void
    {
        ['student' => $student] = $this->makeKidsClassStudent();

        $response = $this->actingAs($this->admin())
            ->post(route('invoices.generate-kids-fp', $student));

        $invoice = Invoice::where('student_id', $student->id)->first();
        $response->assertRedirect(route('invoices.show', $invoice));
    }
}
```

- [ ] **Step 3: Jalankan tests — verifikasi FAIL dengan 501**

```bash
php artisan test tests/Feature/KidsFpInvoiceTest.php
```

Expected: Sebagian FAIL dengan status 501 (controller masih stub), auditor test PASS karena middleware 403, dan test bukan KIDS_CLASS PASS karena 403.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/KidsFpInvoiceTest.php
git commit -m "Test: Tambah KidsFpInvoiceTest — 7 test case TDD (failing)"
```

---

## Task 3: Implementasi generateKidsFp()

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php`

- [ ] **Step 1: Tambah import InvoiceItem ke InvoiceController**

Di bagian `use` statements atas `InvoiceController.php`, tambahkan jika belum ada:

```php
use App\Models\InvoiceItem;
use App\Models\Student;
```

- [ ] **Step 2: Ganti stub generateKidsFp() dengan implementasi nyata**

```php
/**
 * Generate invoice Final Project Kids Class (KIDS_FP).
 * Hanya untuk murid KIDS_CLASS yang belum punya invoice KIDS_FP.
 */
public function generateKidsFp(Student $student): RedirectResponse
{
    // Guard 1: hanya untuk murid KIDS_CLASS
    $enrollment = $student->primaryEnrollment;
    if (! $enrollment || $enrollment->package->class_type !== 'KIDS_CLASS') {
        abort(403, 'Fitur ini hanya untuk murid Kids Class.');
    }

    // Guard 2: cegah double generate
    $sudahAda = InvoiceItem::whereHas('invoice', fn ($q) =>
        $q->where('student_id', $student->id)
    )->where('item_code', 'KIDS_FP')->exists();

    if ($sudahAda) {
        return redirect()->route('students.show', $student)
            ->with('error', 'Invoice Final Project untuk murid ini sudah pernah dibuat.');
    }

    // Generate invoice via InvoiceService
    $invoice = $this->invoiceService->createOneOff(
        student:      $student,
        items:        [[
            'code'        => 'KIDS_FP',
            'description' => 'Final Project Kids Class',
            'amount'      => InvoiceService::FEE_KIDS_FP,
        ]],
        classType:    'KIDS_CLASS',
        enrollmentId: $enrollment->id,
    );

    AuditLog::record(
        AuditLog::ACTION_CREATE,
        $invoice,
        "Invoice KIDS_FP — {$student->full_name}",
        null,
        ['student_id' => $student->id, 'amount' => InvoiceService::FEE_KIDS_FP],
    );

    return redirect()->route('invoices.show', $invoice)
        ->with('success', "Invoice Final Project {$student->full_name} berhasil dibuat.");
}
```

- [ ] **Step 3: Verifikasi `$this->invoiceService` sudah ada di InvoiceController**

Cek bahwa InvoiceController sudah inject `InvoiceService` via constructor. Cari baris:
```php
public function __construct(private readonly InvoiceService $invoiceService)
```

Jika belum ada, tambahkan constructor tersebut.

- [ ] **Step 4: Jalankan tests**

```bash
php artisan test tests/Feature/KidsFpInvoiceTest.php
```

Expected: **7/7 PASS**.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php
git commit -m "M05: Implementasi InvoiceController::generateKidsFp() — invoice Final Project KIDS_FP"
```

---

## Task 4: View — Badge + Tombol + Modal di students/show.blade.php

**Files:**
- Modify: `app/Http/Controllers/StudentController.php`
- Modify: `resources/views/students/show.blade.php`

- [ ] **Step 1: Tambah import InvoiceItem di StudentController**

Di bagian `use` statements atas `app/Http/Controllers/StudentController.php`, tambahkan jika belum ada:

```php
use App\Models\InvoiceItem;
```

- [ ] **Step 2: Tambah variabel $tampilKidsFpButton di StudentController::show()**

Di method `show(Student $student)` di `StudentController.php`, cari baris terakhir sebelum `return view(...)`. Tambahkan tepat sebelumnya:

```php
// Cek apakah tombol Generate Final Project perlu ditampilkan
// Hanya untuk murid KIDS_CLASS yang belum punya invoice KIDS_FP
$tampilKidsFpButton = $student->primaryEnrollment?->package?->class_type === 'KIDS_CLASS'
    && ! InvoiceItem::whereHas('invoice', fn ($q) =>
        $q->where('student_id', $student->id)
    )->where('item_code', 'KIDS_FP')->exists();
```

Lalu cari `return view('students.show', compact(` di akhir method `show()`. Tambahkan `'tampilKidsFpButton'` sebagai argumen baru di akhir daftar compact, **tanpa menghapus argumen lainnya**. Contoh (variabel yang sudah ada tidak perlu diubah):

```php
// Tambah 'tampilKidsFpButton' sebagai argumen terakhir di compact() yang sudah ada
return view('students.show', compact(
    'student', 'packages', 'teachers', 'rooms',
    // ... variabel lain yang sudah ada tetap di sini ...
    'tampilKidsFpButton',   // <-- tambahkan ini
));
```

- [ ] **Step 3: Tambah badge + tombol + modal di students/show.blade.php**

Buka `resources/views/students/show.blade.php`. Cari section tab Tagihan — cari string `activeTab === 'tagihan'` atau `'tagihan'`.

Di dalam section Tagihan, cari header **"Invoice & Pembayaran"** atau **"Tagihan Terbaru"** — bagian paling atas section keuangan.

Tambahkan wrapper Alpine dengan badge, tombol, dan modal tepat **sebelum** tabel invoice terbaru atau di atas section ringkasan saldo. Tambahkan blok ini:

```blade
{{-- ===== KIDS_FP: Badge + Tombol + Modal ===== --}}
@if($tampilKidsFpButton)
<div class="mb-5" x-data="{ showKidsFpModal: false }">

    {{-- Badge + Tombol --}}
    <div class="flex items-center justify-between p-3 rounded-lg mb-1"
         style="background:rgba(212,168,83,0.06);border:1px solid rgba(212,168,83,0.18)">
        <div class="flex items-center gap-2">
            <span class="text-xs" style="color:#D4A853">🎓</span>
            <span class="text-sm font-medium" style="color:#D4A853">Final Project belum ditagih</span>
            <span class="text-xs" style="color:#8A6848">· Kids Class Program 6 Bulan</span>
        </div>
        <button @click="showKidsFpModal = true"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg btn-mk-primary">
            Generate Invoice Final Project
        </button>
    </div>

    {{-- Modal Konfirmasi --}}
    <div x-show="showKidsFpModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.6)">
        <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">🎓 Generate Invoice Final Project</h3>
            <p class="text-sm text-gray-500 mb-5">Kids Class · Program 6 Bulan</p>

            <div class="bg-gray-50 rounded-lg p-4 mb-5 text-sm">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-500">Murid</span>
                    <span class="font-medium text-gray-800">{{ $student->full_name }}</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-500">Item</span>
                    <span class="text-gray-700">Final Project Kids Class</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-gray-200">
                    <span class="font-semibold" style="color:#D4A853">Total Invoice</span>
                    <span class="font-bold text-base" style="color:#D4A853">Rp 140.000</span>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('invoices.generate-kids-fp', $student) }}">
                @csrf
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showKidsFpModal = false"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-semibold rounded-lg btn-mk-primary">
                        Buat Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endif
```

- [ ] **Step 4: Jalankan full test suite**

```bash
php artisan test
```

Expected: Semua test hijau, tidak ada regresi.

- [ ] **Step 5: Build assets**

```bash
npm run build
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentController.php \
        resources/views/students/show.blade.php
git commit -m "M05: Tambah badge + tombol + modal KIDS_FP di tab Tagihan halaman murid"
```

---

## Ringkasan File yang Dibuat / Dimodifikasi

| File | Task |
|---|---|
| `routes/web.php` | 1 |
| `app/Http/Controllers/InvoiceController.php` | 1, 3 |
| `tests/Feature/KidsFpInvoiceTest.php` | 2 |
| `app/Http/Controllers/StudentController.php` | 4 |
| `resources/views/students/show.blade.php` | 4 |
