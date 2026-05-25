# Kids Class Import — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah tombol "Generate Cicilan Bundle" di halaman detail murid agar murid KIDS_CLASS_BUNDLE yang diimport dari Excel bisa langsung di-generate 3 invoice termin-nya.

**Architecture:** Satu controller action baru (`InvoiceController::generateBundle`) dipanggil dari form kecil di `students/show.blade.php`. Action memanggil `InvoiceService::createKidsBundleInstallments()` yang sudah ada. Tombol hanya tampil jika primary enrollment = KIDS_CLASS_BUNDLE dan belum ada invoice INSTALLMENT.

**Tech Stack:** Laravel 11, Blade, Alpine.js, PHPUnit (SQLite in-memory via phpunit.xml)

---

## File yang Diubah

| File | Perubahan |
|---|---|
| `routes/web.php` | Tambah 1 route POST `students/{student}/generate-bundle` |
| `app/Http/Controllers/InvoiceController.php` | Tambah method `generateBundle()` |
| `resources/views/students/show.blade.php` | Tambah blok tombol + modal sebelum baris 1135 |
| `tests/Feature/KidsBundleInstallmentUiTest.php` | Tambah 4 test case baru |

---

## Task 1: Tests (TDD — tulis dulu sebelum implementasi)

**Files:**
- Modify: `tests/Feature/KidsBundleInstallmentUiTest.php`

- [ ] **Step 1: Tambah 4 test case ke file yang ada**

Buka `tests/Feature/KidsBundleInstallmentUiTest.php`. Tambahkan 4 method berikut setelah method `test_student_show_menampilkan_kartu_cicilan_kids_bundle()` yang sudah ada (sebelum kurung kurawal penutup class):

```php
/** generate-bundle: tombol muncul di student show jika belum ada invoice cicilan */
public function test_student_show_menampilkan_tombol_generate_bundle_jika_belum_ada_cicilan(): void
{
    $student = Student::factory()->create(['status' => 'Aktif']);
    $pkg = Package::factory()->create([
        'class_type'      => 'KIDS_CLASS_BUNDLE',
        'price_per_month' => 340000,
    ]);
    $enrollment = Enrollment::factory()->for($student)->create([
        'package_id' => $pkg->id,
        'status'     => 'ACTIVE',
        'is_primary' => true,
    ]);
    $student->update(['primary_enrollment_id' => $enrollment->id]);

    $response = $this->actingAs($this->admin)
        ->get(route('students.show', $student));

    $response->assertOk();
    $response->assertSee('Generate Cicilan Bundle');
}

/** generate-bundle: happy path — 3 invoice termin terbuat */
public function test_generate_bundle_sukses_buat_3_invoice_cicilan(): void
{
    $student = Student::factory()->create(['status' => 'Aktif']);
    $pkg = Package::factory()->create([
        'class_type'      => 'KIDS_CLASS_BUNDLE',
        'price_per_month' => 340000,
    ]);
    $enrollment = Enrollment::factory()->for($student)->create([
        'package_id' => $pkg->id,
        'status'     => 'ACTIVE',
        'is_primary' => true,
    ]);
    $student->update(['primary_enrollment_id' => $enrollment->id]);

    $response = $this->actingAs($this->admin)
        ->post(route('invoices.generate-bundle', $student), [
            'program_start_date' => '2026-03-01',
        ]);

    $response->assertRedirect(route('students.show', $student));
    $response->assertSessionHas('success');

    $invoices = \App\Models\Invoice::where('student_id', $student->id)
        ->where('payment_mode', 'INSTALLMENT')
        ->orderBy('installment_number')
        ->get();

    $this->assertCount(3, $invoices);
    $this->assertEquals(1, $invoices[0]->installment_number);
    $this->assertEquals(2, $invoices[1]->installment_number);
    $this->assertEquals(3, $invoices[2]->installment_number);
    // Semua termin satu grup
    $this->assertNotNull($invoices[0]->installment_group_id);
    $this->assertEquals($invoices[0]->installment_group_id, $invoices[1]->installment_group_id);
    $this->assertEquals($invoices[0]->installment_group_id, $invoices[2]->installment_group_id);
    // Semua berstatus UNPAID dan class_type = KIDS_CLASS_BUNDLE
    foreach ($invoices as $inv) {
        $this->assertEquals('UNPAID', $inv->status);
        $this->assertEquals('KIDS_CLASS_BUNDLE', $inv->class_type);
        $this->assertEquals('INSTALLMENT', $inv->payment_mode);
    }
}

/** generate-bundle: ditolak jika invoice cicilan sudah ada */
public function test_generate_bundle_ditolak_jika_sudah_ada_cicilan(): void
{
    [$student] = $this->buatMuridDenganCicilan();

    $response = $this->actingAs($this->admin)
        ->post(route('invoices.generate-bundle', $student), [
            'program_start_date' => '2026-03-01',
        ]);

    $response->assertStatus(422);
    // Jumlah invoice tidak bertambah dari 3
    $this->assertCount(3, \App\Models\Invoice::where('student_id', $student->id)
        ->where('payment_mode', 'INSTALLMENT')->get());
}

/** generate-bundle: validasi — tanggal wajib diisi */
public function test_generate_bundle_validasi_tanggal_wajib(): void
{
    $student = Student::factory()->create(['status' => 'Aktif']);
    $pkg = Package::factory()->create([
        'class_type'      => 'KIDS_CLASS_BUNDLE',
        'price_per_month' => 340000,
    ]);
    $enrollment = Enrollment::factory()->for($student)->create([
        'package_id' => $pkg->id,
        'status'     => 'ACTIVE',
        'is_primary' => true,
    ]);
    $student->update(['primary_enrollment_id' => $enrollment->id]);

    $response = $this->actingAs($this->admin)
        ->post(route('invoices.generate-bundle', $student), []);

    $response->assertSessionHasErrors('program_start_date');
    $this->assertCount(0, \App\Models\Invoice::where('student_id', $student->id)->get());
}
```

- [ ] **Step 2: Jalankan test — semua harus FAIL karena route belum ada**

```bash
php artisan test tests/Feature/KidsBundleInstallmentUiTest.php --filter "generate_bundle|tombol_generate"
```

Expected: FAIL dengan `Route [invoices.generate-bundle] not defined.` atau `404`

---

## Task 2: Route + Controller Action

**Files:**
- Modify: `routes/web.php` — tambah 1 route
- Modify: `app/Http/Controllers/InvoiceController.php` — tambah 1 method

- [ ] **Step 3: Tambah route di `routes/web.php`**

Buka `routes/web.php`. Cari blok:
```php
        Route::post('invoices/apply-fines',
            [InvoiceController::class, 'applyFines']
        )->name('invoices.apply-fines');
```

Tambahkan route baru **setelah** baris `)->name('invoices.apply-fines');` dan **sebelum** `});` penutup group:

```php
        Route::post('students/{student}/generate-bundle',
            [InvoiceController::class, 'generateBundle']
        )->name('invoices.generate-bundle');
```

- [ ] **Step 4: Tambah method `generateBundle()` di `InvoiceController`**

Buka `app/Http/Controllers/InvoiceController.php`. Tambahkan import `Carbon` dan `RedirectResponse` jika belum ada di bagian atas:

```php
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
```

Tambahkan method berikut di dalam class, setelah method `applyFines()`:

```php
/**
 * Generate 3 invoice cicilan untuk murid KIDS_CLASS_BUNDLE yang diimport.
 * Hanya bisa dipanggil sekali — ditolak jika invoice cicilan sudah ada.
 */
public function generateBundle(Request $request, Student $student, InvoiceService $service): RedirectResponse
{
    abort_if($student->status !== 'Aktif', 422, 'Murid harus berstatus Aktif.');

    $enrollment = $student->primaryEnrollment;
    abort_if(
        !$enrollment || $enrollment->package?->class_type !== 'KIDS_CLASS_BUNDLE',
        422,
        'Kelas utama murid bukan Kids Class Bundle.'
    );

    $alreadyExists = Invoice::where('student_id', $student->id)
        ->where('payment_mode', Invoice::MODE_INSTALLMENT)
        ->exists();
    abort_if($alreadyExists, 422, 'Invoice cicilan sudah pernah dibuat untuk murid ini.');

    $data = $request->validate([
        'program_start_date' => ['required', 'date_format:Y-m-d'],
    ], [
        'program_start_date.required'    => 'Tanggal mulai program wajib diisi.',
        'program_start_date.date_format' => 'Format tanggal harus YYYY-MM-DD (contoh: 2026-03-01).',
    ]);

    $service->createKidsBundleInstallments(
        student:    $student,
        enrollment: $enrollment,
        startDate:  Carbon::parse($data['program_start_date']),
    );

    AuditLog::record(
        action:      AuditLog::ACTION_CREATE,
        entityLabel: "Generate cicilan bundle murid #{$student->id} ({$student->full_name})",
        newValues:   ['program_start_date' => $data['program_start_date']],
    );

    return redirect()
        ->route('students.show', $student)
        ->with('success', '3 invoice cicilan Kids Bundle berhasil dibuat.');
}
```

Pastikan `AuditLog` sudah ada di bagian `use` di atas file. Jika belum, tambahkan:
```php
use App\Models\AuditLog;
use App\Models\Student;
```

- [ ] **Step 5: Jalankan test — semua harus PASS**

```bash
php artisan test tests/Feature/KidsBundleInstallmentUiTest.php --filter "generate_bundle|tombol_generate"
```

Expected: 3 test PASS, 1 test (`tombol_generate`) masih FAIL karena blade belum diubah.

- [ ] **Step 6: Commit backend**

```bash
git add routes/web.php app/Http/Controllers/InvoiceController.php tests/Feature/KidsBundleInstallmentUiTest.php
git commit -m "M05: Tambah generateBundle — generate 3 invoice cicilan Kids Bundle dari import"
```

---

## Task 3: Blade View — Tombol + Modal

**Files:**
- Modify: `resources/views/students/show.blade.php` — tambah blok sebelum baris 1135

- [ ] **Step 7: Tambah blok tombol + modal sebelum kartu cicilan yang sudah ada**

Buka `resources/views/students/show.blade.php`. Cari baris (sekitar baris 1135):

```blade
                {{-- Kartu Cicilan Kids Bundle (hanya muncul jika primary enrollment = KIDS_CLASS_BUNDLE INSTALLMENT) --}}
                @if(!empty($kidsInstallments) && $kidsInstallments->isNotEmpty())
```

Tambahkan blok berikut **tepat sebelum** komentar `{{-- Kartu Cicilan Kids Bundle --}}`:

```blade
                {{-- Tombol generate cicilan: muncul jika KIDS_CLASS_BUNDLE belum punya invoice cicilan --}}
                @if($student->primaryEnrollment?->package?->class_type === 'KIDS_CLASS_BUNDLE' && (empty($kidsInstallments) || $kidsInstallments->isEmpty()))
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden" x-data="{ showBundleModal: false }">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#D4A853">Cicilan Kids Class Bundle</div>
                    </div>
                    <div class="px-5 py-6 text-center">
                        <p class="text-sm text-gray-500 mb-3">Belum ada invoice cicilan untuk murid ini.</p>
                        <button @click="showBundleModal = true"
                                class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                            Generate Cicilan Bundle
                        </button>
                    </div>

                    {{-- Modal: input tanggal mulai program --}}
                    <template x-if="showBundleModal">
                        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                             @click.self="showBundleModal = false">
                            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                                <h3 class="text-base font-semibold text-gray-800 mb-1">Generate Cicilan Bundle</h3>
                                <p class="text-xs text-gray-500 mb-4">
                                    Sistem akan membuat 3 invoice: Termin 1 (bulan mulai), Termin 2 (+1 bulan), Termin 3 (+3 bulan).
                                </p>
                                <form method="POST" action="{{ route('invoices.generate-bundle', $student) }}">
                                    @csrf
                                    <div class="mb-5">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">
                                            Tanggal Mulai Program (bulan ke-1)
                                        </label>
                                        <input type="date" name="program_start_date" required
                                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                        <p class="text-xs text-gray-400 mt-1">
                                            Contoh: jika program mulai Maret 2026, isi 2026-03-01
                                        </p>
                                    </div>
                                    <div class="flex gap-2 justify-end">
                                        <button type="button" @click="showBundleModal = false"
                                                class="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                                            Batal
                                        </button>
                                        <button type="submit"
                                                class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                                            Generate
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                </div>
                @endif

```

- [ ] **Step 8: Jalankan seluruh test suite KidsBundleInstallmentUiTest**

```bash
php artisan test tests/Feature/KidsBundleInstallmentUiTest.php
```

Expected: semua PASS, termasuk `test_student_show_menampilkan_tombol_generate_bundle_jika_belum_ada_cicilan`.

- [ ] **Step 9: Jalankan full feature test untuk pastikan tidak ada regresi**

```bash
php artisan test --filter "Invoice|Student|Kids|Bundle"
```

Expected: semua PASS.

- [ ] **Step 10: Commit blade view**

```bash
git add resources/views/students/show.blade.php
git commit -m "M05: Tambah tombol Generate Cicilan Bundle di halaman detail murid"
```

---

## Cara Pakai Setelah Implementasi

**Untuk setiap murid KIDS_CLASS_BUNDLE yang diimport:**

1. Import via Excel seperti murid biasa (package_code = kode paket KIDS_CLASS_BUNDLE)
2. Buka halaman detail murid → tab Keuangan/Tagihan
3. Klik "Generate Cicilan Bundle" → isi tanggal mulai program → Submit
4. 3 invoice termin terbuat otomatis
5. Untuk termin yang sudah lunas: buka invoice detail → Catat Pembayaran → isi nominal + tanggal historis + notes "Lunas sebelum migrasi sistem"

**Untuk murid KIDS_CLASS (monthly):**
- Tidak perlu langkah tambahan — jalankan "Generate SPP" dari halaman Invoices setelah import selesai
