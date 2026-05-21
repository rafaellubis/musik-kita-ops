# Cuti Murid Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 3 gap di fitur Cuti yang sudah 80% selesai — simpan cuti_from/cuti_until di student record, cancel sesi dalam periode cuti, enforce cuti tidak bisa diakhiri lebih awal, dan update template import.

**Architecture:** Perubahan tersebar di 6 file existing. Service layer menanggung logika bisnis; controller hanya tangani validasi HTTP; UI blade menampilkan gate berbasis tanggal. Tidak ada file baru kecuali migration dan test.

**Tech Stack:** Laravel 11, PHP 8.3, SQLite in-memory (tests), Alpine.js (UI gate), Tailwind CSS

**Spec:** `docs/superpowers/specs/2026-05-22-cuti-design.md`

---

## File Map

| File | Aksi | Tanggung Jawab |
|---|---|---|
| `database/migrations/XXXX_add_cuti_columns_to_students_table.php` | BARU | Kolom cuti_from + cuti_until di tabel students |
| `app/Services/StudentLifecycleService.php` | EDIT | ajukanCuti() + aktifkanDariCuti() |
| `app/Http/Controllers/StudentController.php` | EDIT | Validasi startCuti() beda untuk new vs perpanjang |
| `resources/views/students/show.blade.php` | EDIT | Badge cuti_until + disable tombol sebelum cuti_until |
| `database/seeders/StudentSeeder.php` | EDIT | Sample murid Cuti dengan cuti_from + cuti_until |
| `app/Http/Controllers/ImportController.php` | EDIT | Template kolom cuti_until + catatan referensi |
| `app/Services/StudentImportService.php` | EDIT | Validasi + parsing cuti_until |
| `tests/Feature/StudentCutiTest.php` | BARU | Feature tests lifecycle cuti |
| `tests/Unit/StudentImportCutiTest.php` | BARU | Unit tests import service cuti |

---

## Task 1: Migration — tambah kolom cuti_from + cuti_until

**Files:**
- Create: `database/migrations/XXXX_add_cuti_columns_to_students_table.php`

- [ ] **Step 1: Buat migration**

```bash
php artisan make:migration add_cuti_columns_to_students_table --table=students
```

- [ ] **Step 2: Isi migration**

Buka file migration yang baru dibuat, isi dengan:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Tanggal mulai cuti — diisi saat ajukanCuti(), di-clear saat aktifkanDariCuti()
            $table->date('cuti_from')->nullable()->after('active_since');
            // Tanggal akhir cuti — dipakai untuk enforce tidak bisa akhiri lebih awal
            $table->date('cuti_until')->nullable()->after('cuti_from');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['cuti_from', 'cuti_until']);
        });
    }
};
```

- [ ] **Step 3: Jalankan migration**

```bash
php artisan migrate
```

Expected output:
```
INFO  Running migrations.
  XXXX_add_cuti_columns_to_students_table ............. DONE
```

- [ ] **Step 4: Tambah cuti_from + cuti_until ke `$fillable` model Student**

Buka `app/Models/Student.php`. Cari array `$fillable` dan tambahkan dua entry:

```php
// Tambahkan di dalam array $fillable yang sudah ada:
'cuti_from',
'cuti_until',
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/Student.php
git commit -m "M05: Migration + fillable cuti_from + cuti_until di tabel students"
```

---

## Task 2: Test + Fix `StudentLifecycleService::ajukanCuti()`

**Files:**
- Create: `tests/Feature/StudentCutiTest.php`
- Modify: `app/Services/StudentLifecycleService.php`
- Modify: `app/Http/Controllers/StudentController.php`

- [ ] **Step 1: Buat file test**

```bash
php artisan make:test StudentCutiTest
```

- [ ] **Step 2: Tulis failing tests untuk ajukanCuti()**

Isi `tests/Feature/StudentCutiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StudentLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCutiTest extends TestCase
{
    use RefreshDatabase;

    private StudentLifecycleService $lifecycle;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $user = User::factory()->create()->assignRole('Admin');
        $this->actingAs($user);

        $this->lifecycle = new StudentLifecycleService(new InvoiceService());
        $this->student   = Student::factory()->create(['status' => 'Aktif']);
    }

    // ===== ajukanCuti() =====

    public function test_ajukan_cuti_menyimpan_cuti_from_dan_cuti_until(): void
    {
        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_from'  => '2026-07-01',
            'cuti_until' => '2026-07-31',
            'reason'     => 'UAS sekolah',
        ]);

        $this->student->refresh();
        $this->assertEquals('Cuti',       $this->student->status);
        $this->assertEquals('2026-07-01', $this->student->cuti_from);
        $this->assertEquals('2026-07-31', $this->student->cuti_until);
    }

    public function test_ajukan_cuti_cancel_sesi_scheduled_dalam_periode(): void
    {
        // Buat enrollment langsung (SQLite in-memory: foreign key tidak dienforce)
        $enrollment = Enrollment::create([
            'student_id'     => $this->student->id,
            'package_id'     => 1,
            'teacher_id'     => 1,
            'effective_date' => now()->subMonth()->toDateString(),
            'status'         => 'ACTIVE',
        ]);

        // Sesi di dalam periode cuti — harus di-cancel
        $sesiDalam = ClassSession::create([
            'schedule_id'   => 1,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => 1,
            'session_date'  => '2026-07-10',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        // Sesi di luar periode cuti — tidak boleh tersentuh
        $sesiLuar = ClassSession::create([
            'schedule_id'   => 1,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => 1,
            'session_date'  => '2026-08-05',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_from'  => '2026-07-01',
            'cuti_until' => '2026-07-31',
            'reason'     => 'UAS sekolah',
        ]);

        $this->assertEquals(ClassSession::STATUS_CANCELLED, $sesiDalam->fresh()->status);
        $this->assertEquals(ClassSession::STATUS_SCHEDULED, $sesiLuar->fresh()->status);
    }

    public function test_perpanjang_cuti_tidak_override_cuti_from(): void
    {
        // Setup: murid sudah Cuti dengan cuti_from terdaftar
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => '2026-07-01',
            'cuti_until' => '2026-07-31',
        ]);

        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_until' => '2026-08-15',
            'reason'     => 'Perpanjang karena sakit',
        ]);

        $this->student->refresh();
        $this->assertEquals('2026-07-01', $this->student->cuti_from);  // tidak berubah
        $this->assertEquals('2026-08-15', $this->student->cuti_until); // di-update
    }

    public function test_perpanjang_cuti_melewati_62_hari_ditolak(): void
    {
        $this->student->update([
            'status'    => 'Cuti',
            'cuti_from' => '2026-07-01',
            'cuti_until'=> '2026-07-31',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/maks.*2 bulan/i');

        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_until' => '2026-09-15', // 76 hari dari cuti_from — melebihi batas
            'reason'     => 'Terlalu panjang',
        ]);
    }

    // ===== aktifkanDariCuti() =====

    public function test_akhiri_cuti_sebelum_cuti_until_ditolak(): void
    {
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => now()->subDays(5)->toDateString(),
            'cuti_until' => now()->addDays(10)->toDateString(), // belum lewat
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/belum selesai/i');

        $this->lifecycle->aktifkanDariCuti($this->student);
    }

    public function test_akhiri_cuti_setelah_cuti_until_berhasil(): void
    {
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => now()->subDays(30)->toDateString(),
            'cuti_until' => now()->subDay()->toDateString(), // sudah lewat
        ]);

        $result = $this->lifecycle->aktifkanDariCuti($this->student);

        $this->assertEquals('Aktif', $result->status);
        $this->assertNull($result->cuti_from);
        $this->assertNull($result->cuti_until);
    }

    public function test_akhiri_cuti_pada_hari_cuti_until_diizinkan(): void
    {
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => now()->subDays(30)->toDateString(),
            'cuti_until' => now()->toDateString(), // hari ini = hari terakhir cuti
        ]);

        $result = $this->lifecycle->aktifkanDariCuti($this->student);

        $this->assertEquals('Aktif', $result->status);
    }
}
```

- [ ] **Step 3: Jalankan test — pastikan semua FAIL**

```bash
php artisan test tests/Feature/StudentCutiTest.php --stop-on-pass
```

Expected: semua test FAIL (kolom belum ada di service logic).

- [ ] **Step 4: Update `StudentLifecycleService::ajukanCuti()`**

Buka `app/Services/StudentLifecycleService.php`. Tambahkan `use App\Models\ClassSession;` di bagian use jika belum ada. Ganti seluruh method `ajukanCuti()` dengan:

```php
public function ajukanCuti(Student $student, array $data): Student
{
    if (!in_array($student->status, ['Aktif', 'Cuti'], true)) {
        throw new InvalidArgumentException(
            'Cuti hanya bisa dari Aktif atau perpanjangan dari Cuti. Status sekarang: ' . $student->status
        );
    }

    $hasUnpaidSpp = $student->invoices()
        ->whereIn('status', ['UNPAID', 'PARTIAL'])
        ->whereHas('items', fn ($q) => $q->where('item_code', 'SPP'))
        ->exists();

    if ($hasUnpaidSpp) {
        throw new InvalidArgumentException(
            'Selesaikan tagihan SPP bulan berjalan sebelum mengajukan cuti.'
        );
    }

    $isExtension = $student->status === 'Cuti';

    // Validasi maks 2 bulan total (berlaku hanya untuk perpanjang)
    if ($isExtension) {
        $originalFrom = \Carbon\Carbon::parse($student->cuti_from);
        $newUntil     = \Carbon\Carbon::parse($data['cuti_until']);
        if ($originalFrom->diffInDays($newUntil) > 62) {
            throw new InvalidArgumentException(
                'Total cuti melebihi batas maksimal 2 bulan.'
            );
        }
    }

    return DB::transaction(function () use ($student, $data, $isExtension) {
        $from = $student->status;

        // Simpan cuti_until lama sebelum diupdate — dipakai untuk range cancel pada perpanjang
        $oldCutiUntil = $student->cuti_until;

        // Update student: cuti_from hanya diset pada pengajuan baru, bukan perpanjang
        $updateData = ['status' => 'Cuti', 'cuti_until' => $data['cuti_until']];
        if (!$isExtension) {
            $updateData['cuti_from'] = $data['cuti_from'];
        }
        $student->update($updateData);
        $student->refresh();

        // Cancel sesi SCHEDULED dalam range cuti
        // Perpanjang: cancel hanya range tambahan (oldCutiUntil → newCutiUntil)
        // Baru: cancel seluruh range (cuti_from → cuti_until)
        $cancelFrom = $isExtension ? $oldCutiUntil : $data['cuti_from'];
        ClassSession::whereIn('enrollment_id', $student->enrollments()->pluck('id'))
            ->where('status', ClassSession::STATUS_SCHEDULED)
            ->whereBetween('session_date', [$cancelFrom, $data['cuti_until']])
            ->update([
                'status' => ClassSession::STATUS_CANCELLED,
                'notes'  => 'Sesi dibatalkan otomatis — murid cuti ' .
                            $student->cuti_from . ' s/d ' . $data['cuti_until'],
            ]);

        // Terbitkan invoice biaya cuti Rp 100.000
        $invoice = $this->invoiceService->createOneOff(
            student: $student,
            items: [[
                'code'        => 'CUTI',
                'description' => 'Biaya cuti ' .
                    \Carbon\Carbon::parse($student->cuti_from)->format('d M') . ' - ' .
                    \Carbon\Carbon::parse($data['cuti_until'])->format('d M Y') .
                    ($isExtension ? ' (perpanjangan)' : ''),
                'amount'      => InvoiceService::FEE_CUTI,
                'metadata'    => [
                    'cuti_from'    => $student->cuti_from,
                    'cuti_until'   => $data['cuti_until'],
                    'is_extension' => $isExtension,
                ],
            ]],
            description: $isExtension ? 'Perpanjangan Cuti' : 'Pengajuan Cuti',
        );

        $this->recordHistory(
            student:  $student,
            from:     $from,
            to:       'Cuti',
            reason:   $data['reason'],
            metadata: [
                'cuti_from'      => $student->cuti_from,
                'cuti_until'     => $data['cuti_until'],
                'is_extension'   => $isExtension,
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ],
        );

        return $student->fresh();
    });
}
```

- [ ] **Step 5: Update `StudentLifecycleService::aktifkanDariCuti()`**

Ganti seluruh method `aktifkanDariCuti()` dengan:

```php
public function aktifkanDariCuti(Student $student, array $data = []): Student
{
    if ($student->status !== 'Cuti') {
        throw new InvalidArgumentException(
            'Akhiri cuti hanya bisa dari status Cuti. Status sekarang: ' . $student->status
        );
    }

    // Hard block: cuti belum selesai — bandingkan tanggal saja (abaikan jam)
    if ($student->cuti_until && now()->toDateString() < $student->cuti_until) {
        throw new InvalidArgumentException(
            'Cuti belum selesai. Cuti berlaku hingga ' .
            \Carbon\Carbon::parse($student->cuti_until)->format('d M Y') . '.'
        );
    }

    return DB::transaction(function () use ($student, $data) {
        $from = $student->status;

        $student->update([
            'status'     => 'Aktif',
            'cuti_from'  => null,
            'cuti_until' => null,
        ]);

        $this->recordHistory(
            student: $student,
            from:    $from,
            to:      'Aktif',
            reason:  $data['notes'] ?? 'Cuti berakhir, murid kembali aktif',
        );

        return $student->fresh();
    });
}
```

- [ ] **Step 6: Update `StudentController::startCuti()` — validasi beda untuk perpanjang**

Buka `app/Http/Controllers/StudentController.php`. Ganti method `startCuti()`:

```php
public function startCuti(Request $request, string $id)
{
    $student     = Student::findOrFail($id);
    $isExtension = $student->status === 'Cuti';

    // Perpanjang: hanya butuh cuti_until baru (cuti_from tetap dari pengajuan awal)
    // Baru: butuh cuti_from dan cuti_until
    $rules    = ['reason' => 'required|string|max:500'];
    $messages = ['reason.required' => 'Alasan cuti wajib diisi.'];

    if ($isExtension) {
        $rules['cuti_until']             = 'required|date|after:today';
        $messages['cuti_until.required'] = 'Tanggal akhir cuti baru wajib diisi.';
        $messages['cuti_until.after']    = 'Tanggal akhir cuti harus setelah hari ini.';
    } else {
        $rules['cuti_from']              = 'required|date|after_or_equal:today';
        $rules['cuti_until']             = 'required|date|after:cuti_from';
        $messages['cuti_from.required']  = 'Tanggal mulai cuti wajib diisi.';
        $messages['cuti_until.required'] = 'Tanggal akhir cuti wajib diisi.';
        $messages['cuti_until.after']    = 'Tanggal akhir cuti harus setelah tanggal mulai.';
    }

    $data = $request->validate($rules, $messages);

    return $this->runLifecycle(
        fn () => $this->lifecycle->ajukanCuti($student, $data),
        $student,
        'Pengajuan cuti tercatat. Tagihan biaya cuti Rp 100.000 telah diterbitkan.'
    );
}
```

- [ ] **Step 7: Jalankan test — pastikan semua PASS**

```bash
php artisan test tests/Feature/StudentCutiTest.php
```

Expected: 7 tests passed.

- [ ] **Step 8: Commit**

```bash
git add app/Services/StudentLifecycleService.php app/Http/Controllers/StudentController.php tests/Feature/StudentCutiTest.php
git commit -m "M05: Fix ajukanCuti() + aktifkanDariCuti() — simpan kolom, cancel sesi, enforce cuti_until"
```

---

## Task 3: Update UI `students/show.blade.php`

**Files:**
- Modify: `resources/views/students/show.blade.php`

- [ ] **Step 1: Tampilkan periode cuti di badge status**

Cari blok yang menampilkan badge status murid (sekitar baris 140-160, dekat tombol "Ajukan Cuti"). Tambahkan tampilan periode cuti tepat di bawah badge status, di dalam blok `@if($student->status === 'Cuti')`:

```blade
@if($student->status === 'Cuti' && $student->cuti_until)
<div class="text-xs mt-1" style="color:#FBBF24">
    Cuti s/d: {{ \Carbon\Carbon::parse($student->cuti_until)->format('d M Y') }}
</div>
@endif
```

Tempatkan ini di dekat badge status, sebelum tombol-tombol aksi.

- [ ] **Step 2: Gate tombol "Akhiri Cuti → Aktif"**

Cari blok tombol `Akhiri Cuti → Aktif` (sekitar baris 165-174). Tambahkan variabel gate di blok `@php` di atas, lalu kondisikan tombol:

Di bagian `@php` di awal file (setelah baris `$activeEnrollment = ...`), tambahkan:
```php
$cutiSelesai = !$student->cuti_until || now()->toDateString() >= $student->cuti_until;
```

Kemudian ganti blok tombol "Akhiri Cuti":
```blade
@if($student->status === 'Cuti')
@if($cutiSelesai)
<form method="POST" action="{{ route('students.return-from-cuti', $student->id) }}"
      onsubmit="return confirm('Akhiri cuti dan kembalikan ke Aktif?')" class="inline">
    @csrf
    <button type="submit"
            class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
            style="background:rgba(52,211,153,0.15);color:#34D399;border:1px solid rgba(52,211,153,0.3)">
        ✅ Akhiri Cuti → Aktif
    </button>
</form>
@else
<button disabled
        title="Cuti berlaku hingga {{ \Carbon\Carbon::parse($student->cuti_until)->format('d M Y') }}"
        class="px-4 py-2 rounded-lg text-sm font-semibold cursor-not-allowed"
        style="background:rgba(52,211,153,0.05);color:rgba(52,211,153,0.35);border:1px solid rgba(52,211,153,0.1)">
    ✅ Akhiri Cuti → Aktif
</button>
@endif
```

- [ ] **Step 3: Update form "Perpanjang Cuti" — hilangkan field cuti_from**

Cari form cuti (x-show="openForm === 'cuti'"). Untuk perpanjang (status Cuti), field `cuti_from` tidak dipakai — sembunyikan:

```blade
@if($student->status !== 'Cuti')
<div>
    <label class="block text-xs text-gray-500 mb-1">Mulai Cuti <span class="text-red-400">*</span></label>
    <input type="date" name="cuti_from" required min="{{ now()->toDateString() }}"
           class="block w-full rounded-lg text-sm px-3 py-2">
</div>
@endif
```

- [ ] **Step 4: Verifikasi manual di browser**

Buka halaman detail murid berstatus Aktif — pastikan tombol "Ajukan Cuti" muncul dengan form dua field.

Buka halaman detail murid berstatus Cuti dengan `cuti_until` di masa depan — pastikan tombol "Akhiri Cuti" disabled dan ada tooltip tanggal, badge menampilkan "Cuti s/d: XX".

Buka halaman detail murid berstatus Cuti dengan `cuti_until` hari ini atau kemarin — pastikan tombol "Akhiri Cuti" aktif dan bisa diklik.

- [ ] **Step 5: Commit**

```bash
git add resources/views/students/show.blade.php
git commit -m "M05: UI cuti — badge cuti_until + disable tombol akhiri sebelum cuti selesai"
```

---

## Task 4: Update `StudentSeeder`

**Files:**
- Modify: `database/seeders/StudentSeeder.php`

- [ ] **Step 1: Tambah cuti_from + cuti_until ke sample murid Cuti**

Buka `database/seeders/StudentSeeder.php`. Cari baris yang memiliki `'status' => 'Cuti'` (sekitar baris 120). Tambahkan dua field:

```php
'status'     => 'Cuti',
'cuti_from'  => now()->startOfMonth()->toDateString(),
'cuti_until' => now()->endOfMonth()->toDateString(),
```

- [ ] **Step 2: Verifikasi seeder tidak error**

```bash
php artisan db:seed --class=StudentSeeder
```

Expected: selesai tanpa error.

- [ ] **Step 3: Commit**

```bash
git add database/seeders/StudentSeeder.php
git commit -m "M05: Update StudentSeeder — tambah cuti_from + cuti_until ke sample murid Cuti"
```

---

## Task 5: Update `ImportController` — template Excel

**Files:**
- Modify: `app/Http/Controllers/ImportController.php`

- [ ] **Step 1: Tambah kolom cuti_until di `dataMuridRows()`**

Buka `app/Http/Controllers/ImportController.php`. Cari method `dataMuridRows()`. Ganti return array-nya:

```php
private function dataMuridRows(): array
{
    return [
        [
            'full_name', 'nickname', 'gender', 'birth_date', 'phone', 'email',
            'address', 'notes', 'parent_name', 'parent_phone', 'parent_email',
            'parent_relationship', 'status', 'package_code', 'teacher_code',
            'preferred_day', 'preferred_time', 'active_since', 'kode_ruangan',
            'cuti_until',
        ],
        [
            'Budi Santoso', 'Budi', 'L', '2010-05-15', '08111111111',
            'budi@email.com', 'Jl. Contoh No.1', 'Catatan contoh',
            'Ayah Budi', '08111111112', 'ayahbudi@email.com', 'Ayah',
            'Aktif', 'KODE-PAKET-CONTOH', 'KODE-GURU-CONTOH',
            'Senin', '15:30', '2026-01-15', 'R2',
            '',  // kosong untuk status bukan Cuti
        ],
    ];
}
```

- [ ] **Step 2: Tambah catatan di `referensiKodeRows()`**

Cari method `referensiKodeRows()`. Di bagian paling bawah, sebelum `return $rows;`, tambahkan:

```php
$rows[] = [];
$rows[] = ['=== CATATAN KOLOM CUTI_UNTIL ==='];
$rows[] = ['Wajib diisi jika status = Cuti (format: YYYY-MM-DD, contoh: 2026-07-31)'];
$rows[] = ['Kosongkan jika status bukan Cuti'];
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/ImportController.php
git commit -m "M05: Update template import — tambah kolom cuti_until + catatan referensi"
```

---

## Task 6: Update `StudentImportService` — validasi + parsing cuti_until

**Files:**
- Create: `tests/Unit/StudentImportCutiTest.php`
- Modify: `app/Services/StudentImportService.php`

- [ ] **Step 1: Tulis failing tests untuk cuti_until**

Buat file `tests/Unit/StudentImportCutiTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentImportCutiTest extends TestCase
{
    use RefreshDatabase;

    private StudentImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StudentImportService();
    }

    public function test_status_cuti_tanpa_cuti_until_error(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Sari Dewi',
            'gender'     => 'P',
            'status'     => 'Cuti',
            'cuti_until' => '',
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('cuti_until', $result);
    }

    public function test_status_cuti_format_cuti_until_salah_error(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Sari Dewi',
            'gender'     => 'P',
            'status'     => 'Cuti',
            'cuti_until' => '31-07-2026', // format salah — harus YYYY-MM-DD
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('cuti_until', $result);
    }

    public function test_status_cuti_dengan_cuti_until_valid_lulus(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Sari Dewi',
            'gender'     => 'P',
            'status'     => 'Cuti',
            'cuti_until' => '2026-07-31',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('2026-07-31', $result['cuti_until']);
    }

    public function test_status_aktif_cuti_until_kosong_tidak_error(): void
    {
        $result = $this->service->validateRow(1, [
            'full_name'  => 'Budi Santoso',
            'gender'     => 'L',
            'status'     => 'Aktif',
            'cuti_until' => '',
        ]);

        // Status Aktif — cuti_until boleh kosong, tidak boleh error
        $this->assertIsArray($result);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Unit/StudentImportCutiTest.php --stop-on-pass
```

Expected: semua FAIL.

- [ ] **Step 3: Update `validateRow()` di `StudentImportService`**

Buka `app/Services/StudentImportService.php`. Cari blok validasi tanggal (baris sekitar 246-255):

```php
foreach (['birth_date', 'active_since'] as $dateField) {
```

Ganti dengan validasi yang juga menangani `cuti_until`:

```php
// Tanggal opsional dengan format YYYY-MM-DD
foreach (['birth_date', 'active_since'] as $dateField) {
    if (!empty($row[$dateField])) {
        $parsed = \DateTime::createFromFormat('Y-m-d', $row[$dateField]);
        if (!$parsed || $parsed->format('Y-m-d') !== $row[$dateField]) {
            $errors[] = "{$dateField} harus format YYYY-MM-DD";
        }
    }
}

// cuti_until: wajib jika status = Cuti, harus format YYYY-MM-DD
if (($row['status'] ?? '') === 'Cuti') {
    if (empty($row['cuti_until'])) {
        $errors[] = 'cuti_until wajib diisi jika status = Cuti (format: YYYY-MM-DD)';
    } else {
        $parsed = \DateTime::createFromFormat('Y-m-d', $row['cuti_until']);
        if (!$parsed || $parsed->format('Y-m-d') !== $row['cuti_until']) {
            $errors[] = 'cuti_until harus format YYYY-MM-DD (contoh: 2026-07-31)';
        }
    }
} elseif (!empty($row['cuti_until'])) {
    // Status bukan Cuti tapi cuti_until diisi — validasi format saja (tidak block)
    $parsed = \DateTime::createFromFormat('Y-m-d', $row['cuti_until']);
    if (!$parsed || $parsed->format('Y-m-d') !== $row['cuti_until']) {
        $errors[] = 'cuti_until harus format YYYY-MM-DD jika diisi';
    }
}
```

`cuti_until` sudah otomatis masuk ke `$data = $row` di baris return — tidak perlu perubahan lagi di bagian resolve/return.

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test tests/Unit/StudentImportCutiTest.php
```

Expected: 4 tests passed.

- [ ] **Step 5: Jalankan seluruh test suite — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test pass, tidak ada yang baru gagal.

- [ ] **Step 6: Commit**

```bash
git add app/Services/StudentImportService.php tests/Unit/StudentImportCutiTest.php
git commit -m "M05: StudentImportService — validasi + parsing cuti_until untuk status Cuti"
```

---

## Checklist Akhir

Jalankan full test suite sekali lagi sebagai verifikasi final:

```bash
php artisan test
```

Verifikasi manual di browser:
- [ ] Murid Aktif: form ajukan cuti muncul dengan 2 field (cuti_from + cuti_until)
- [ ] Murid Cuti (cuti_until masa depan): badge "Cuti s/d: XX", tombol akhiri disabled
- [ ] Murid Cuti (cuti_until hari ini/kemarin): tombol akhiri aktif, bisa klik → status Aktif
- [ ] Murid Cuti: form perpanjang hanya minta cuti_until (tanpa cuti_from)
- [ ] Download template import: ada kolom cuti_until + catatan di sheet referensi
