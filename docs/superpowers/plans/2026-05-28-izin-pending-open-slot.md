# IZIN_PENDING — Reschedule Tanggal Menyusul & Open Slot Board

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah status `IZIN_PENDING` agar Admin bisa catat izin murid tanpa harus langsung isi tanggal pengganti; slot yang kosong tampil di Open Slot Board Admin; guru lihat daftar "Sesi Pending" di portal mereka.

**Architecture:** Satu status enum baru di `class_sessions`. Open Slot Board = query `IZIN_PENDING` tanpa replacement. Guru portal menambah halaman dan tab nav baru. Tidak ada tabel baru — saran tanggal guru disimpan ke kolom `notes`.

**Tech Stack:** Laravel 11, Blade + Tailwind CSS, Alpine.js, Spatie Permission, PHPUnit Feature Tests.

**Spec:** `docs/superpowers/specs/2026-05-28-izin-pending-open-slot.md`

---

## File Map

| File | Aksi |
|---|---|
| `database/migrations/2026_05_28_XXXXXX_add_izin_pending_to_class_sessions.php` | Create |
| `app/Models/ClassSession.php` | Modify — tambah konstanta STATUS_IZIN_PENDING |
| `app/Services/AttendanceService.php` | Modify — handle IZIN_PENDING di calculateHonor() dan VALID_ATTENDANCE_STATUSES |
| `app/Services/HonorCalculationService.php` | Modify — exclude IZIN_PENDING di 2 tempat whereNotIn() |
| `app/Http/Requests/UpdateAbsensiRequest.php` | Modify — tambah IZIN_PENDING ke Rule::in() |
| `app/Http/Controllers/AbsensiController.php` | Modify — handle IZIN_PENDING di update(); tambah openSlotBoard(), assignOpenSlot(), scheduleReplacement() |
| `app/Http/Controllers/GuruController.php` | Modify — tambah sesiPending(), suggestDate(); update dashboard() |
| `resources/views/guru/dashboard.blade.php` | Modify — tambah banner + kartu counter |
| `resources/views/guru/sesi-pending.blade.php` | Create |
| `resources/views/layouts/guru.blade.php` | Modify — tambah nav item "Sesi Pending" |
| `resources/views/absensi/open-slots.blade.php` | Create |
| `routes/web.php` | Modify — 5 route baru |
| `tests/Feature/IzinPendingTest.php` | Create |

---

## Task 1: Migration — Tambah IZIN_PENDING ke Enum

**Files:**
- Create: `database/migrations/2026_05_28_000001_add_izin_pending_to_class_sessions.php`

- [ ] **Buat migration**

```bash
php artisan make:migration add_izin_pending_to_class_sessions
```

- [ ] **Isi migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL perlu ubah definisi enum secara eksplisit
        DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM(
            'SCHEDULED','HADIR','HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE','IZIN_PENDING',
            'IZIN_VIDEO','HANGUS','LIBUR','DIGANTI','CANCELLED'
        ) NOT NULL DEFAULT 'SCHEDULED'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE class_sessions MODIFY COLUMN status ENUM(
            'SCHEDULED','HADIR','HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE',
            'IZIN_VIDEO','HANGUS','LIBUR','DIGANTI','CANCELLED'
        ) NOT NULL DEFAULT 'SCHEDULED'");
    }
};
```

- [ ] **Jalankan migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_05_28_000001_add_izin_pending_to_class_sessions` ... `Migrated`

- [ ] **Commit**

```bash
git add database/migrations/2026_05_28_000001_add_izin_pending_to_class_sessions.php
git commit -m "DB: Tambah status IZIN_PENDING ke enum class_sessions"
```

---

## Task 2: Model + AttendanceService + HonorCalculationService

**Files:**
- Modify: `app/Models/ClassSession.php`
- Modify: `app/Services/AttendanceService.php`
- Modify: `app/Services/HonorCalculationService.php`
- Create: `tests/Feature/IzinPendingTest.php`

- [ ] **Tulis test yang gagal**

Buat `tests/Feature/IzinPendingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceService;
use App\Services\HonorCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IzinPendingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(array $attrs = []): ClassSession
    {
        $teacher  = Teacher::factory()->create();
        $student  = Student::factory()->create(['status' => 'Aktif']);
        $package  = Package::factory()->create(['price_per_month' => 400000, 'class_type' => 'REGULER']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create(array_merge([
            'teacher_id'    => $teacher->id,
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'status'        => 'SCHEDULED',
            'session_date'  => today()->toDateString(),
            'start_time'    => '09:00',
            'end_time'      => '09:30',
        ], $attrs));
    }

    /** @test */
    public function attendance_service_sets_honor_nol_for_izin_pending(): void
    {
        $session = $this->makeSession();
        $service = app(AttendanceService::class);

        $result = $service->recordAttendance($session, ['status' => 'IZIN_PENDING']);

        $this->assertEquals('IZIN_PENDING', $result->status);
        $this->assertEquals('H_IZIN', $result->honor_code);
        $this->assertEquals(0, $result->honor_amount);
    }

    /** @test */
    public function honor_calculation_excludes_izin_pending_sessions(): void
    {
        $teacher = Teacher::factory()->create();
        $bulan   = now()->month;
        $tahun   = now()->year;

        // Satu sesi HADIR (harus masuk hitungan)
        $package = Package::factory()->create(['price_per_month' => 400000, 'class_type' => 'REGULER']);
        $student1 = Student::factory()->create(['status' => 'Aktif']);
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id, 'teacher_id' => $teacher->id, 'package_id' => $package->id, 'status' => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'teacher_id'    => $teacher->id,
            'student_id'    => $student1->id,
            'enrollment_id' => $enrollment1->id,
            'status'        => 'HADIR',
            'honor_code'    => 'H_REG',
            'honor_amount'  => 50000,
            'session_date'  => now()->startOfMonth()->toDateString(),
        ]);

        // Satu sesi IZIN_PENDING (harus TIDAK masuk hitungan)
        $student2 = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id, 'teacher_id' => $teacher->id, 'package_id' => $package->id, 'status' => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'teacher_id'    => $teacher->id,
            'student_id'    => $student2->id,
            'enrollment_id' => $enrollment2->id,
            'status'        => 'IZIN_PENDING',
            'honor_code'    => 'H_IZIN',
            'honor_amount'  => 0,
            'session_date'  => now()->startOfMonth()->toDateString(),
        ]);

        $service = app(HonorCalculationService::class);
        $service->calculateForTeacher($teacher, $tahun, $bulan, 1);

        $slip = $teacher->honorSlips()->where('month', $bulan)->where('year', $tahun)->first();
        $this->assertEquals(50000, $slip->base_honor); // hanya HADIR, bukan IZIN_PENDING
    }
}
```

- [ ] **Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/IzinPendingTest.php
```

Expected: FAIL — `IZIN_PENDING` belum ada di konstanta / VALID_ATTENDANCE_STATUSES.

- [ ] **Tambah konstanta di ClassSession** (`app/Models/ClassSession.php` setelah baris `STATUS_IZIN_RESCHEDULE`)

```php
public const STATUS_IZIN_PENDING     = 'IZIN_PENDING';
```

- [ ] **Update AttendanceService** (`app/Services/AttendanceService.php`)

Tambah `'IZIN_PENDING'` ke array `VALID_ATTENDANCE_STATUSES` (setelah `'IZIN_RESCHEDULE'`):

```php
public const VALID_ATTENDANCE_STATUSES = [
    'HADIR',
    'HADIR_TERLAMBAT',
    'IZIN_RESCHEDULE',
    'IZIN_PENDING',    // ← tambah
    'IZIN_VIDEO',
    'HANGUS',
    'LIBUR',
    'DIGANTI',
    'CANCELLED',
];
```

Di method `calculateHonor()`, tambah handler IZIN_PENDING tepat setelah handler IZIN_RESCHEDULE (sekitar baris 157):

```php
// IZIN_PENDING: murid izin tanpa tanggal pengganti, honor nol seperti IZIN_RESCHEDULE.
if ($status === 'IZIN_PENDING') {
    return ['code' => 'H_IZIN', 'amount' => 0];
}
```

Catatan: IZIN_RESCHEDULE yang existing return `['code' => null, 'amount' => 0]` — IZIN_PENDING pakai `'H_IZIN'` agar lebih eksplisit di breakdown slip.

- [ ] **Update HonorCalculationService** — tambah `STATUS_IZIN_PENDING` ke **dua** `whereNotIn()`:

Di `calculateForTeacher()` (~baris 86):
```php
->whereNotIn('status', [
    ClassSession::STATUS_SCHEDULED,
    ClassSession::STATUS_IZIN_RESCHEDULE,
    ClassSession::STATUS_IZIN_PENDING,   // ← tambah
    ClassSession::STATUS_CANCELLED,
])
```

Di `getSessionBreakdown()` (~baris 153):
```php
->whereNotIn('status', [
    ClassSession::STATUS_SCHEDULED,
    ClassSession::STATUS_IZIN_RESCHEDULE,
    ClassSession::STATUS_IZIN_PENDING,   // ← tambah
    ClassSession::STATUS_CANCELLED,
])
```

- [ ] **Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Feature/IzinPendingTest.php
```

Expected: 2 tests PASS.

- [ ] **Commit**

```bash
git add app/Models/ClassSession.php app/Services/AttendanceService.php \
        app/Services/HonorCalculationService.php tests/Feature/IzinPendingTest.php
git commit -m "M04/M06: Tambah STATUS_IZIN_PENDING — honor nol, exclude dari slip"
```

---

## Task 3: UpdateAbsensiRequest + AbsensiController::update()

**Files:**
- Modify: `app/Http/Requests/UpdateAbsensiRequest.php`
- Modify: `app/Http/Controllers/AbsensiController.php`
- Modify: `tests/Feature/IzinPendingTest.php`

- [ ] **Tulis test yang gagal** — tambah ke `IzinPendingTest.php`

```php
/** @test */
public function admin_dapat_set_izin_pending_tanpa_isi_tanggal_pengganti(): void
{
    $admin   = \App\Models\User::factory()->create();
    $admin->assignRole('Admin');
    $session = $this->makeSession();

    $response = $this->actingAs($admin)->patchJson(
        route('absensi.update', $session),
        ['status' => 'IZIN_PENDING']
    );

    $response->assertOk()->assertJson(['success' => true]);
    $this->assertDatabaseHas('class_sessions', [
        'id'           => $session->id,
        'status'       => 'IZIN_PENDING',
        'honor_code'   => 'H_IZIN',
        'honor_amount' => 0,
    ]);
    // Tidak ada sesi pengganti yang dibuat
    $this->assertDatabaseCount('class_sessions', 1);
}
```

- [ ] **Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/IzinPendingTest.php --filter=admin_dapat_set_izin_pending
```

Expected: FAIL — status `IZIN_PENDING` ditolak validator.

- [ ] **Update UpdateAbsensiRequest** — tambah `ClassSession::STATUS_IZIN_PENDING` ke `Rule::in()`:

```php
Rule::in([
    ClassSession::STATUS_HADIR,
    ClassSession::STATUS_HADIR_TERLAMBAT,
    ClassSession::STATUS_HANGUS,
    ClassSession::STATUS_IZIN_RESCHEDULE,
    ClassSession::STATUS_IZIN_PENDING,    // ← tambah
    ClassSession::STATUS_IZIN_VIDEO,
    ClassSession::STATUS_DIGANTI,
    ClassSession::STATUS_CANCELLED,
]),
```

- [ ] **Update AbsensiController::update()** — blok `if ($request->status === ClassSession::STATUS_IZIN_RESCHEDULE)` yang ada saat ini (~baris 145) diperluas agar IZIN_PENDING lewat tanpa memanggil RescheduleService:

```php
// IZIN_RESCHEDULE: wajib ada tanggal pengganti → buat sesi pengganti
if ($request->status === ClassSession::STATUS_IZIN_RESCHEDULE) {
    $hasReplacement = ClassSession::where('origin_session_id', $classSession->id)
        ->whereNull('split_part')
        ->exists();
    if ($hasReplacement) {
        throw new \InvalidArgumentException(
            'Sesi ini sudah memiliki sesi pengganti dan tidak bisa di-reschedule ulang.'
        );
    }
    $this->rescheduleService->createReplacement(
        $classSession,
        $request->replacement_date,
        $request->replacement_time,
        $request->replacement_room_id,
    );
}
// IZIN_PENDING: izin tanpa tanggal pengganti — tidak buat sesi apapun
// (slot terbuka di Open Slot Board, guru lihat di Sesi Pending)
```

- [ ] **Jalankan seluruh test AbsensiController agar tidak ada regresi**

```bash
php artisan test tests/Feature/Admin/AbsensiControllerTest.php tests/Feature/IzinPendingTest.php
```

Expected: semua PASS.

- [ ] **Commit**

```bash
git add app/Http/Requests/UpdateAbsensiRequest.php \
        app/Http/Controllers/AbsensiController.php \
        tests/Feature/IzinPendingTest.php
git commit -m "M04: Admin bisa set IZIN_PENDING tanpa tanggal pengganti"
```

---

## Task 4: Open Slot Board — Admin

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php` — tambah 3 method
- Create: `resources/views/absensi/open-slots.blade.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/IzinPendingTest.php`

- [ ] **Tulis test yang gagal** — tambah ke `IzinPendingTest.php`

```php
/** @test */
public function open_slot_board_hanya_tampilkan_izin_pending_tanpa_replacement(): void
{
    $admin = \App\Models\User::factory()->create();
    $admin->assignRole('Admin');

    $sessionPending   = $this->makeSession(['status' => 'IZIN_PENDING', 'honor_code' => 'H_IZIN', 'honor_amount' => 0]);
    $sessionSudahAda  = $this->makeSession(['status' => 'IZIN_PENDING', 'honor_code' => 'H_IZIN', 'honor_amount' => 0]);

    // sessionSudahAda sudah punya replacement → tidak muncul di board
    ClassSession::factory()->create([
        'origin_session_id' => $sessionSudahAda->id,
        'status'            => 'SCHEDULED',
        'teacher_id'        => $sessionSudahAda->teacher_id,
        'student_id'        => $sessionSudahAda->student_id,
        'enrollment_id'     => $sessionSudahAda->enrollment_id,
        'session_date'      => today()->addDays(3)->toDateString(),
        'start_time'        => '09:00',
        'end_time'          => '09:30',
    ]);

    $response = $this->actingAs($admin)->getJson(route('absensi.open-slots'));

    $response->assertOk();
    $ids = collect($response->json('slots'))->pluck('id');
    $this->assertTrue($ids->contains($sessionPending->id));
    $this->assertFalse($ids->contains($sessionSudahAda->id));
}
```

- [ ] **Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/IzinPendingTest.php --filter=open_slot_board
```

Expected: FAIL — route tidak ada.

- [ ] **Tambah routes** di `routes/web.php` (di dalam grup `middleware(['auth', 'role:Owner|Admin'])`)

```php
// Open Slot Board
Route::get('/absensi/open-slots', [AbsensiController::class, 'openSlotBoard'])->name('absensi.open-slots');
Route::post('/absensi/open-slots/{session}/assign', [AbsensiController::class, 'assignOpenSlot'])->name('absensi.open-slots.assign');
Route::post('/absensi/open-slots/{session}/schedule', [AbsensiController::class, 'scheduleReplacement'])->name('absensi.open-slots.schedule');
```

- [ ] **Tambah method di AbsensiController**

```php
/**
 * Open Slot Board — daftar sesi IZIN_PENDING yang belum punya replacement.
 * Slot "terbuka" = belum ada sesi lain dengan origin_session_id = id sesi ini.
 */
public function openSlotBoard(Request $request): View|JsonResponse
{
    $sessionIdsWithReplacement = ClassSession::whereNotNull('origin_session_id')
        ->whereNull('split_part')
        ->pluck('origin_session_id');

    $slots = ClassSession::with(['student', 'teacher', 'room', 'enrollment.package'])
        ->where('status', ClassSession::STATUS_IZIN_PENDING)
        ->whereNotIn('id', $sessionIdsWithReplacement)
        ->orderBy('session_date')
        ->get();

    $teachers   = Teacher::where('is_active', true)->orderBy('name')->get();
    $rooms      = Room::where('is_active', true)->orderBy('code')->get();

    if ($request->wantsJson()) {
        return response()->json(['slots' => $slots]);
    }

    return view('absensi.open-slots', compact('slots', 'teachers', 'rooms'));
}

/**
 * Isi slot dengan murid lain — buat sesi baru untuk enrollment murid yang dipilih.
 * Sesi IZIN_PENDING asli (punya murid) tetap pending, belum selesai.
 */
public function assignOpenSlot(Request $request, ClassSession $session): JsonResponse
{
    $request->validate([
        'enrollment_id' => ['required', 'exists:enrollments,id'],
        'room_id'       => ['nullable', 'exists:rooms,id'],
    ]);

    abort_if($session->status !== ClassSession::STATUS_IZIN_PENDING, 422,
        'Sesi bukan IZIN_PENDING.');

    $enrollment = \App\Models\Enrollment::with('package', 'student')->findOrFail($request->enrollment_id);

    try {
        DB::transaction(function () use ($session, $enrollment, $request) {
            // Buat sesi untuk murid yang mengisi slot, di tanggal+jam+guru yang sama
            $this->rescheduleService->createReplacement(
                $session,
                $session->session_date,
                $session->start_time,
                $request->room_id ?? $session->room_id,
                overrideEnrollment: $enrollment,
            );
        });
    } catch (\InvalidArgumentException $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
    }

    return response()->json(['success' => true, 'message' => 'Slot berhasil diisi.']);
}

/**
 * Jadwalkan pengganti untuk murid asli (selesaikan IZIN_PENDING).
 * Status berubah ke IZIN_RESCHEDULE setelah replacement dibuat.
 */
public function scheduleReplacement(Request $request, ClassSession $session): JsonResponse
{
    $request->validate([
        'replacement_date' => ['required', 'date', 'date_format:Y-m-d'],
        'replacement_time' => ['required', 'date_format:H:i'],
        'room_id'          => ['nullable', 'exists:rooms,id'],
    ]);

    abort_if($session->status !== ClassSession::STATUS_IZIN_PENDING, 422,
        'Sesi bukan IZIN_PENDING.');

    try {
        DB::transaction(function () use ($request, $session) {
            $this->rescheduleService->createReplacement(
                $session,
                $request->replacement_date,
                $request->replacement_time,
                $request->room_id,
            );
            // Setelah replacement dibuat, ubah status ke IZIN_RESCHEDULE
            $session->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);
        });
    } catch (\InvalidArgumentException $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
    }

    return response()->json(['success' => true, 'message' => 'Sesi pengganti berhasil dijadwalkan.']);
}
```

**Catatan:** `assignOpenSlot` memanggil `RescheduleService::createReplacement()` dengan parameter `overrideEnrollment`. Perlu tambah parameter opsional ini ke `RescheduleService` — lihat Task 4b.

- [ ] **Task 4b — Update RescheduleService::createReplacement()** (`app/Services/RescheduleService.php`)

Tambah parameter opsional `?Enrollment $overrideEnrollment = null`. Jika diisi, gunakan enrollment tersebut (student_id, enrollment_id, teacher_id) alih-alih dari sesi asli:

```php
public function createReplacement(
    ClassSession $original,
    string $date,
    string $startTime,
    ?int $roomId = null,
    ?Enrollment $overrideEnrollment = null,  // ← tambah
): ClassSession {
    $enrollment = $overrideEnrollment ?? $original->enrollment;
    $studentId  = $overrideEnrollment?->student_id ?? $original->student_id;
    $teacherId  = $original->teacher_id; // guru tetap guru asli di slot

    // ... sisa logika conflict detection dan create tetap sama,
    // tapi gunakan $enrollment->id dan $studentId untuk ClassSession::create()
```

- [ ] **Buat view** `resources/views/absensi/open-slots.blade.php`

Gunakan layout `layouts/app.blade.php`. Tabel dengan kolom: Tanggal, Jam, Guru, Ruang, Murid Asli, Sesi ke-, Sudah (hari), Aksi.

Tiap baris punya dua tombol:
1. **"Isi dengan murid lain"** — buka dropdown pilih murid (filter per guru/instrumen) + tombol konfirmasi → POST ke `absensi.open-slots.assign`
2. **"Jadwalkan pengganti"** — buka mini-form tanggal + jam + ruang → POST ke `absensi.open-slots.schedule`

Gunakan Alpine.js (`x-data`, `x-show`) untuk expand/collapse form per baris.

- [ ] **Jalankan semua test**

```bash
php artisan test tests/Feature/IzinPendingTest.php
```

Expected: semua PASS.

- [ ] **Commit**

```bash
git add app/Http/Controllers/AbsensiController.php \
        app/Services/RescheduleService.php \
        resources/views/absensi/open-slots.blade.php \
        routes/web.php \
        tests/Feature/IzinPendingTest.php
git commit -m "M04: Open Slot Board — Admin isi atau jadwalkan slot IZIN_PENDING"
```

---

## Task 5: Guru Portal — Halaman Sesi Pending

**Files:**
- Modify: `app/Http/Controllers/GuruController.php`
- Create: `resources/views/guru/sesi-pending.blade.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/IzinPendingTest.php`

- [ ] **Tulis test yang gagal** — tambah ke `IzinPendingTest.php`

```php
/** @test */
public function guru_hanya_lihat_sesi_pending_miliknya(): void
{
    $guruUser = \App\Models\User::factory()->create();
    $guruUser->assignRole('Guru');
    $teacher  = Teacher::factory()->create();
    $guruUser->update(['teacher_id' => $teacher->id]);

    $teacher2 = Teacher::factory()->create();

    // Sesi IZIN_PENDING milik guru ini
    $milik = $this->makeSession(['status' => 'IZIN_PENDING', 'honor_code' => 'H_IZIN', 'honor_amount' => 0,
                                  'teacher_id' => $teacher->id]);
    // Sesi IZIN_PENDING milik guru lain — tidak boleh tampil
    $bukan = $this->makeSession(['status' => 'IZIN_PENDING', 'honor_code' => 'H_IZIN', 'honor_amount' => 0,
                                  'teacher_id' => $teacher2->id]);

    $response = $this->actingAs($guruUser)->get(route('guru.sesi-pending.index'));

    $response->assertOk()
             ->assertSee($milik->student->full_name)
             ->assertDontSee($bukan->student->full_name);
}

/** @test */
public function guru_bisa_suggest_tanggal(): void
{
    $guruUser = \App\Models\User::factory()->create();
    $guruUser->assignRole('Guru');
    $teacher  = Teacher::factory()->create();
    $guruUser->update(['teacher_id' => $teacher->id]);

    $session = $this->makeSession([
        'status'      => 'IZIN_PENDING',
        'honor_code'  => 'H_IZIN',
        'honor_amount'=> 0,
        'teacher_id'  => $teacher->id,
    ]);

    $response = $this->actingAs($guruUser)->postJson(
        route('guru.sesi-pending.suggest', $session),
        ['tanggal' => '2026-06-10', 'jam' => '09:00', 'catatan' => 'Murid bilang bisa Rabu']
    );

    $response->assertOk()->assertJson(['success' => true]);
    $this->assertStringContainsString(
        '[SARAN GURU: 2026-06-10 09:00',
        ClassSession::find($session->id)->notes
    );
}
```

- [ ] **Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/IzinPendingTest.php --filter="guru_hanya_lihat|guru_bisa_suggest"
```

Expected: FAIL — route tidak ada.

- [ ] **Tambah routes** di `routes/web.php` (grup `middleware(['auth', 'role:Guru'])`)

```php
Route::get('/guru/sesi-pending', [GuruController::class, 'sesiPending'])->name('guru.sesi-pending.index');
Route::post('/guru/sesi-pending/{session}/suggest', [GuruController::class, 'suggestDate'])->name('guru.sesi-pending.suggest');
```

- [ ] **Tambah method di GuruController**

```php
/**
 * Daftar sesi IZIN_PENDING milik guru yang login.
 * Diurutkan dari yang paling lama pending (terlama di atas).
 */
public function sesiPending()
{
    $teacher = auth()->user()->teacher;
    abort_if(!$teacher, 403, 'Akun ini tidak terhubung ke data guru.');

    $sesiPending = ClassSession::where('teacher_id', $teacher->id)
        ->where('status', ClassSession::STATUS_IZIN_PENDING)
        ->with(['student', 'enrollment.package'])
        ->orderBy('session_date')
        ->get();

    return view('guru.sesi-pending', compact('teacher', 'sesiPending'));
}

/**
 * Guru submit saran tanggal pengganti untuk sesi IZIN_PENDING miliknya.
 * Saran disimpan ke kolom notes dengan prefix [SARAN GURU: ...].
 * Admin yang akan konfirmasi dan buat sesi penggantinya.
 */
public function suggestDate(Request $request, ClassSession $session)
{
    $teacher = auth()->user()->teacher;
    abort_if(!$teacher, 403);
    abort_if($session->teacher_id !== $teacher->id, 403, 'Bukan sesi Anda.');
    abort_if($session->status !== ClassSession::STATUS_IZIN_PENDING, 422, 'Sesi bukan IZIN_PENDING.');

    $request->validate([
        'tanggal' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
        'jam'     => ['required', 'date_format:H:i'],
        'catatan' => ['nullable', 'string', 'max:200'],
    ]);

    $saran   = "[SARAN GURU: {$request->tanggal} {$request->jam}" .
               ($request->catatan ? " — {$request->catatan}" : '') . ']';
    $notes   = $session->notes ? $session->notes . "\n" . $saran : $saran;

    $session->update(['notes' => $notes]);

    return response()->json(['success' => true, 'message' => 'Saran terkirim ke Admin.']);
}
```

- [ ] **Buat view** `resources/views/guru/sesi-pending.blade.php`

Gunakan `<x-guru-layout title="Sesi Pending">`. Tiap card murid menampilkan:
- Nama murid, sesi ke-, paket, tanggal asli izin
- Badge hari pending (merah jika > 14 hari): `{{ \Carbon\Carbon::parse($sesi->session_date)->diffInDays(today()) }} hari`
- Accordion "Suggest Tanggal ke Admin" (Alpine.js `x-data="{open:false}"`) dengan form input tanggal + jam + catatan
- Form POST ke `guru.sesi-pending.suggest` via fetch (JSON), tampilkan pesan sukses inline

Jika `$sesiPending->isEmpty()`:
```blade
<div class="text-center py-12 text-mk-muted text-sm">
    ✅ Tidak ada sesi pending. Semua sesi sudah terjadwal!
</div>
```

- [ ] **Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Feature/IzinPendingTest.php
```

Expected: semua PASS.

- [ ] **Commit**

```bash
git add app/Http/Controllers/GuruController.php \
        resources/views/guru/sesi-pending.blade.php \
        routes/web.php \
        tests/Feature/IzinPendingTest.php
git commit -m "M04: Portal Guru — halaman Sesi Pending + suggest tanggal"
```

---

## Task 6: Dashboard Guru + Navigasi

**Files:**
- Modify: `app/Http/Controllers/GuruController.php` — update dashboard()
- Modify: `resources/views/guru/dashboard.blade.php`
- Modify: `resources/views/layouts/guru.blade.php`

- [ ] **Update GuruController::dashboard()** — tambah query `$jumlahPending`

Setelah query `$slipBulanIni`, tambah:

```php
// Jumlah sesi IZIN_PENDING milik guru ini — untuk banner + counter dashboard
$jumlahPending = ClassSession::where('teacher_id', $teacher->id)
    ->where('status', ClassSession::STATUS_IZIN_PENDING)
    ->count();

return view('guru.dashboard', compact(
    'teacher', 'sesiHariIni', 'totalSesiBulan', 'slipBulanIni', 'jumlahPending'
));
```

- [ ] **Update dashboard.blade.php** — tambah banner kondisional dan kartu ketiga

Tepat setelah blok `<div class="px-4 pt-5 pb-2">` (greeting), tambah banner:

```blade
@if($jumlahPending > 0)
<div class="mx-4 mb-2">
    <a href="{{ route('guru.sesi-pending.index') }}"
       class="flex items-start gap-3 bg-mk-card border border-mk-accent/20 border-l-4
              border-l-mk-accent rounded-xl px-4 py-3 hover:bg-mk-cardHover transition-colors">
        <span class="text-xl shrink-0">📋</span>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-mk-accent text-sm">{{ $jumlahPending }} Sesi Pending</div>
            <div class="text-xs text-mk-muted mt-0.5">Murid izin, belum ada jadwal pengganti — tap untuk detail</div>
        </div>
        <span class="text-mk-muted self-center">›</span>
    </a>
</div>
@endif
```

Di grid ringkasan, ubah dari `grid-cols-2` ke `grid-cols-2 sm:grid-cols-3` dan tambah kartu ketiga setelah kartu honor:

```blade
@if($jumlahPending > 0)
<a href="{{ route('guru.sesi-pending.index') }}"
   class="bg-white rounded-xl border border-gray-100 px-4 py-4 shadow-sm hover:bg-gray-50 transition-colors">
    <div class="text-2xl font-bold text-red-500">{{ $jumlahPending }}</div>
    <div class="text-xs text-mk-muted mt-0.5">Sesi Pending</div>
</a>
@endif
```

- [ ] **Update layouts/guru.blade.php** — tambah nav item di sidebar desktop dan bottom nav mobile

Di sidebar desktop (setelah item "Jadwal Saya"):

```blade
<x-sidebar-item route="guru.sesi-pending.index" icon="📋" label="Sesi Pending"
    :active="request()->routeIs('guru.sesi-pending*')" />
```

Di bottom nav mobile (antara Jadwal dan Honor):

```blade
<a href="{{ route('guru.sesi-pending.index') }}"
   class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors
          {{ request()->routeIs('guru.sesi-pending*') ? 'text-mk-accent' : 'text-white/45 hover:text-white/75' }}">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
    </svg>
    Sesi Pending
</a>
```

- [ ] **Jalankan seluruh test guru**

```bash
php artisan test tests/Feature/GuruControllerAccessTest.php \
               tests/Feature/GuruUpdateAbsensiTest.php \
               tests/Feature/IzinPendingTest.php
```

Expected: semua PASS.

- [ ] **Commit**

```bash
git add app/Http/Controllers/GuruController.php \
        resources/views/guru/dashboard.blade.php \
        resources/views/layouts/guru.blade.php
git commit -m "M04: Dashboard guru tampilkan counter + banner Sesi Pending"
```

---

## Task 7: Build & Verifikasi Manual

- [ ] **Build assets**

```bash
npm run build
```

Expected: selesai tanpa error.

- [ ] **Jalankan seluruh test suite**

```bash
php artisan test
```

Expected: semua PASS, tidak ada regresi.

- [ ] **Verifikasi manual (browser)**

1. Login sebagai Admin → buka halaman absensi → klik sesi SCHEDULED milik murid mana saja → pilih dropdown "Izin — Tanggal Menyusul (Pending)" → simpan tanpa isi tanggal → status berubah ke IZIN_PENDING ✓
2. Buka `/absensi/open-slots` → sesi tadi muncul di tabel ✓
3. Klik "Isi dengan murid lain" → pilih murid lain → konfirmasi → sesi baru dibuat, sesi asli tetap IZIN_PENDING ✓
4. Klik "Jadwalkan pengganti" pada sesi yang sama → isi tanggal + jam → konfirmasi → status sesi asli berubah ke IZIN_RESCHEDULE, sesi pengganti untuk murid asli dibuat ✓
5. Login sebagai Guru → dashboard menampilkan banner "X Sesi Pending" dan kartu counter ✓
6. Buka tab "Sesi Pending" → list murid yang izin muncul ✓
7. Klik "Suggest Tanggal" → isi form → kirim → pesan sukses tampil, notes sesi diperbarui ✓

- [ ] **Commit final**

```bash
git commit -m "M04: IZIN_PENDING flow selesai — verifikasi manual OK"
```
