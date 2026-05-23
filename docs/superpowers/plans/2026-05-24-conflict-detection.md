# Conflict Detection — 3-Gap Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup 3 celah yang memungkinkan jadwal konflik masuk ke database — session generator FASE 2, tambah kelas manual (EnrollmentController), dan import murid massal (StudentImportService).

**Architecture:** Semua fix menggunakan layanan yang sudah ada. FASE 2 memakai `hasConflictOnDate()` private method yang sudah dipakai di FASE 3. EnrollmentController inject `ScheduleConflictDetector` via constructor. Import menambah `_conflict_warning` non-blocking (sama polanya dengan `_has_warning` ruangan yang sudah ada).

**Tech Stack:** Laravel 11, PHPUnit, `ScheduleConflictDetector` (existing), `hasConflictOnDate()` (existing private method di `SessionGeneratorService`)

---

## File Structure

| File | Aksi | Perubahan |
|------|------|-----------|
| `app/Services/SessionGeneratorService.php` | Modify | Tambah conflict guard sebelum `ClassSession::create()` di FASE 2 (baris ~167) |
| `app/Http/Controllers/EnrollmentController.php` | Modify | Inject `ScheduleConflictDetector` via constructor, cek konflik sebelum `DB::transaction` |
| `app/Services/StudentImportService.php` | Modify | Inject `ScheduleConflictDetector` via constructor, tambah `_conflict_warning` di `validateRow()` |
| `resources/views/imports/index.blade.php` | Modify | Tampilkan `_conflict_warning` di kolom status tabel preview (2 lokasi: valid + overwrite) |
| `tests/Feature/SessionGeneratorConflictTest.php` | Create | Test FASE 2 skip saat guru sama/jam sama |
| `tests/Feature/EnrollmentControllerTest.php` | Modify | Tambah 2 test: teacher conflict + room full |
| `tests/Unit/StudentImportConflictTest.php` | Create | Test `_conflict_warning` muncul di `validateRow()` |

---

## Task 1: SessionGeneratorService FASE 2 — Conflict Guard

**Files:**
- Modify: `app/Services/SessionGeneratorService.php` baris 166-182 (blok `else { // Sesi normal`)
- Create: `tests/Feature/SessionGeneratorConflictTest.php`

- [ ] **Step 1: Tulis failing test**

Buat file baru `tests/Feature/SessionGeneratorConflictTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionGeneratorConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_fase2_skip_regular_session_jika_guru_sudah_terpakai(): void
    {
        $teacher  = Teacher::factory()->create(['is_active' => true]);
        $room1    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $room2    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $package  = Package::factory()->create(['duration_min' => 30, 'class_type' => 'REGULER', 'is_active' => true]);

        // Murid 1 — sudah punya sesi SCHEDULED di Senin 15:00 bulan target
        $student1    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule1 = Schedule::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'day_of_week'   => 1, // Senin
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room1->id,
            'is_active'     => true,
        ]);

        // Buat sesi konkret murid 1 di Senin pertama bulan depan
        $targetMonth = Carbon::now()->addMonth()->startOfMonth();
        $senin       = $targetMonth->copy()->next('Monday');
        ClassSession::factory()->create([
            'schedule_id'   => $schedule1->id,
            'enrollment_id' => $enrollment1->id,
            'student_id'    => $student1->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => $senin->toDateString(),
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room1->id,
            'status'        => 'SCHEDULED',
        ]);

        // Murid 2 — guru sama, jam sama, ruang beda — belum punya sesi
        $student2    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule2 = Schedule::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room2->id,
            'is_active'     => true,
        ]);

        // Jalankan generator untuk bulan target
        $report = app(SessionGeneratorService::class)->generateForMonth(
            $targetMonth->year,
            $targetMonth->month
        );

        // Sesi murid 2 di Senin tsb tidak boleh terbuat — konflik guru
        $konflikTerbuat = ClassSession::where('schedule_id', $schedule2->id)
            ->whereDate('session_date', $senin)
            ->exists();

        $this->assertFalse($konflikTerbuat, 'Sesi konflik seharusnya tidak dibuat');
        $this->assertGreaterThan(0, $report['skipped_conflict'], 'skipped_conflict harus bertambah');
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

```bash
php artisan test tests/Feature/SessionGeneratorConflictTest.php
```

Expected output: `FAIL` — sesi konflik terbuat, `skipped_conflict` tetap 0.

- [ ] **Step 3: Implementasi di SessionGeneratorService FASE 2**

Di `app/Services/SessionGeneratorService.php`, cari blok `} else {` di sekitar baris 166 (FASE 2 sesi normal).
Tambah conflict guard tepat sebelum `ClassSession::create()` sesi SCHEDULED:

```php
} else {
    // Guard: skip jika guru atau ruang sudah punya sesi di jam yang sama
    if ($this->hasConflictOnDate($schedule, $date)) {
        Log::warning("[SessionGenerator] Skip regular {$dateStr}: konflik guru/ruang untuk schedule #{$schedule->id}");
        $report['skipped_conflict']++;
        continue;
    }

    // Sesi normal
    ClassSession::create([
        'schedule_id'   => $schedule->id,
        'enrollment_id' => $enrollment->id,
        'student_id'    => $enrollment->student_id,
        'teacher_id'    => $enrollment->teacher_id,
        'session_date'  => $dateStr,
        'start_time'    => $schedule->start_time,
        'end_time'      => $schedule->end_time,
        'room_id'       => $schedule->room_id,
        'status'        => 'SCHEDULED',
    ]);

    $report['created']++;
    $scheduledCount++;
}
```

- [ ] **Step 4: Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Feature/SessionGeneratorConflictTest.php
```

Expected: PASS

- [ ] **Step 5: Full test suite hijau**

```bash
php artisan test
```

Expected: semua test PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/SessionGeneratorService.php tests/Feature/SessionGeneratorConflictTest.php
git commit -m "Fix: SessionGenerator FASE 2 — skip sesi reguler jika guru/ruang konflik"
```

---

## Task 2: EnrollmentController::store() — Conflict Validation

**Files:**
- Modify: `app/Http/Controllers/EnrollmentController.php`
- Modify: `tests/Feature/EnrollmentControllerTest.php`

- [ ] **Step 1: Tulis failing tests**

Tambahkan 2 test method berikut ke `tests/Feature/EnrollmentControllerTest.php`, di bagian `// ===== STORE =====`:

```php
public function test_tambah_kelas_gagal_jika_guru_sudah_punya_jadwal_di_jam_sama(): void
{
    $room  = Room::factory()->create(['capacity' => 1]);
    $room2 = Room::factory()->create(['capacity' => 1]);

    // Guru sudah punya jadwal hari Senin 15:00 untuk murid lain
    $otherStudent    = Student::factory()->create(['status' => 'Aktif']);
    $otherEnrollment = Enrollment::factory()->for($otherStudent)->create([
        'teacher_id' => $this->teacher->id,
        'status'     => 'ACTIVE',
    ]);
    Schedule::factory()->create([
        'enrollment_id' => $otherEnrollment->id,
        'day_of_week'   => 1,
        'start_time'    => '15:00',
        'end_time'      => '15:30',
        'room_id'       => $room->id,
        'is_active'     => true,
    ]);

    // Buat enrollment utama agar $this->student lolos lifecycle gate
    $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true, 'status' => 'ACTIVE']);
    $this->student->update(['primary_enrollment_id' => $e1->id]);

    $response = $this->actingAs($this->admin)->post(
        route('students.enrollments.store', $this->student),
        [
            'package_id'     => $this->package->id,
            'teacher_id'     => $this->teacher->id,
            'room_id'        => $room2->id, // ruang beda — tetap konflik karena gurunya sama
            'day_of_week'    => 1,
            'start_time'     => '15:00',
            'effective_date' => now()->addDay()->format('Y-m-d'),
        ]
    );

    $response->assertStatus(422);
    // Hanya e1 yang ada, enrollment baru tidak terbuat
    $this->assertEquals(1, $this->student->enrollments()->active()->count());
}

public function test_tambah_kelas_gagal_jika_ruangan_sudah_penuh(): void
{
    $room = Room::factory()->create(['capacity' => 1]);

    // Ruangan sudah terisi murid lain di hari Rabu 14:00
    $otherTeacher    = Teacher::factory()->create();
    $otherStudent    = Student::factory()->create(['status' => 'Aktif']);
    $otherEnrollment = Enrollment::factory()->for($otherStudent)->create([
        'teacher_id' => $otherTeacher->id,
        'status'     => 'ACTIVE',
    ]);
    Schedule::factory()->create([
        'enrollment_id' => $otherEnrollment->id,
        'day_of_week'   => 3, // Rabu
        'start_time'    => '14:00',
        'end_time'      => '14:30',
        'room_id'       => $room->id,
        'is_active'     => true,
    ]);

    $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true, 'status' => 'ACTIVE']);
    $this->student->update(['primary_enrollment_id' => $e1->id]);

    $response = $this->actingAs($this->admin)->post(
        route('students.enrollments.store', $this->student),
        [
            'package_id'     => $this->package->id,
            'teacher_id'     => $this->teacher->id, // guru beda — tetap konflik karena ruangnya penuh
            'room_id'        => $room->id,
            'day_of_week'    => 3,
            'start_time'     => '14:00',
            'effective_date' => now()->addDay()->format('Y-m-d'),
        ]
    );

    $response->assertStatus(422);
    $this->assertEquals(1, $this->student->enrollments()->active()->count());
}
```

Tambahkan juga `use App\Models\Schedule;` ke bagian `use` di atas file jika belum ada.

- [ ] **Step 2: Jalankan, pastikan GAGAL**

```bash
php artisan test tests/Feature/EnrollmentControllerTest.php --filter="test_tambah_kelas_gagal"
```

Expected: FAIL — saat ini tidak ada validasi konflik di `store()`

- [ ] **Step 3: Implementasi di EnrollmentController**

Ganti seluruh isi `app/Http/Controllers/EnrollmentController.php` baris 1-87 (bagian class declaration + store method) menjadi:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\ScheduleConflictDetector;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller untuk manajemen kelas per murid (fitur multi-kelas).
 *
 * Satu murid bisa punya beberapa enrollment ACTIVE secara bersamaan.
 * Setiap enrollment memiliki 1 jadwal mingguan tetap (Schedule).
 * Tepat 1 enrollment per murid ditandai is_primary = true.
 */
class EnrollmentController extends Controller
{
    public function __construct(private ScheduleConflictDetector $conflictDetector) {}

    /**
     * Tambah kelas baru ke murid yang sudah aktif.
     *
     * Membuat Enrollment baru + Schedule mingguan sekaligus dalam satu transaksi.
     * Jika jadikan_utama = true: enrollment lama dilepas status utama,
     * enrollment baru dijadikan utama, dan primary_enrollment_id di-update.
     */
    public function store(StoreEnrollmentRequest $request, Student $student): RedirectResponse
    {
        // Hanya murid Aktif yang boleh ditambah kelas baru via multi-kelas.
        abort_if(
            $student->status !== 'Aktif',
            422,
            'Kelas baru hanya bisa ditambah untuk murid yang sedang Aktif.'
        );

        $data = $request->validated();

        $package   = Package::findOrFail($data['package_id']);
        $startTime = $data['start_time'];
        $endTime   = Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($package->duration_min)
            ->format('H:i');

        // Cek konflik guru — 1 guru tidak boleh 2 jadwal bersamaan
        $teacherConflicts = $this->conflictDetector->findTeacherConflicts(
            teacherId: $data['teacher_id'],
            dayOfWeek: $data['day_of_week'],
            startTime: $startTime,
            endTime:   $endTime,
        );
        if ($teacherConflicts->isNotEmpty()) {
            return back()
                ->withErrors(['teacher_id' => 'Guru sudah punya jadwal di hari dan jam tersebut.'])
                ->withInput();
        }

        // Cek konflik ruang — kapasitas ruang tidak boleh terlampaui
        if ($this->conflictDetector->isRoomFull(
            roomId:    $data['room_id'],
            dayOfWeek: $data['day_of_week'],
            startTime: $startTime,
            endTime:   $endTime,
        )) {
            return back()
                ->withErrors(['room_id' => 'Ruangan sudah penuh di hari dan jam tersebut.'])
                ->withInput();
        }

        DB::transaction(function () use ($student, $data, $startTime, $endTime) {
            $jadikanUtama = (bool) ($data['jadikan_utama'] ?? false);

            if ($jadikanUtama) {
                $student->enrollments()->where('is_primary', true)->update(['is_primary' => false]);
            }

            $enrollment = Enrollment::create([
                'student_id'     => $student->id,
                'package_id'     => $data['package_id'],
                'teacher_id'     => $data['teacher_id'],
                'effective_date' => $data['effective_date'],
                'status'         => 'ACTIVE',
                'is_primary'     => $jadikanUtama,
            ]);

            Schedule::create([
                'enrollment_id' => $enrollment->id,
                'day_of_week'   => $data['day_of_week'],
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'room_id'       => $data['room_id'],
                'is_active'     => true,
            ]);

            if ($jadikanUtama) {
                $student->update(['primary_enrollment_id' => $enrollment->id]);
            }
        });

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Kelas berhasil ditambahkan.');
    }
```

Bagian `setPrimary`, `destroy`, dan `hentikanEnrollment` tidak berubah — biarkan apa adanya.

- [ ] **Step 4: Jalankan semua test EnrollmentController**

```bash
php artisan test tests/Feature/EnrollmentControllerTest.php
```

Expected: semua test PASS (termasuk 2 test baru + semua test lama)

- [ ] **Step 5: Full test suite hijau**

```bash
php artisan test
```

Expected: semua test PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/EnrollmentController.php tests/Feature/EnrollmentControllerTest.php
git commit -m "Fix: EnrollmentController — validasi konflik guru dan ruang sebelum tambah kelas"
```

---

## Task 3: StudentImportService — Conflict Warning

**Files:**
- Modify: `app/Services/StudentImportService.php`
- Modify: `resources/views/imports/index.blade.php` (2 lokasi)
- Create: `tests/Unit/StudentImportConflictTest.php`

- [ ] **Step 1: Tulis failing test**

Buat file baru `tests/Unit/StudentImportConflictTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentImportConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_validateRow_tambah_conflict_warning_jika_guru_sudah_terjadwal(): void
    {
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $package = Package::factory()->create([
            'is_active'    => true,
            'duration_min' => 30,
            'class_type'   => 'REGULER',
        ]);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 1]);

        // Jadwal existing: guru ini sudah mengajar Senin 15:00
        $existingStudent    = Student::factory()->create(['status' => 'Aktif']);
        $existingEnrollment = Enrollment::factory()->create([
            'student_id' => $existingStudent->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $existingEnrollment->id,
            'day_of_week'   => 1, // Senin
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room->id,
            'is_active'     => true,
        ]);

        $service = app(StudentImportService::class);
        $row = [
            'full_name'           => 'Murid Baru',
            'gender'              => 'L',
            'status'              => 'Aktif',
            'package_code'        => $package->code,
            'teacher_code'        => $teacher->code,
            'preferred_day'       => 'Senin',
            'preferred_time'      => '15:00',
            'kode_ruangan'        => null,
        ];

        $result = $service->validateRow(
            rowNum:             2,
            row:                $row,
            packageCodes:       [$package->code => $package->id],
            teacherCodes:       [$teacher->code => $teacher->id],
            packageDurationMap: [$package->code => 30],
        );

        // Harus return array (valid), bukan string error
        $this->assertIsArray($result, 'Konflik jadwal tidak boleh memblock import — hanya warning');
        // Harus ada conflict warning
        $this->assertNotNull($result['_conflict_warning']);
        $this->assertStringContainsString('Senin', $result['_conflict_warning']);
    }

    public function test_validateRow_tidak_ada_conflict_warning_jika_tidak_ada_jadwal_bentrok(): void
    {
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $package = Package::factory()->create(['is_active' => true, 'duration_min' => 30, 'class_type' => 'REGULER']);

        $service = app(StudentImportService::class);
        $row = [
            'full_name'      => 'Murid Baru',
            'gender'         => 'L',
            'status'         => 'Aktif',
            'package_code'   => $package->code,
            'teacher_code'   => $teacher->code,
            'preferred_day'  => 'Senin',
            'preferred_time' => '15:00',
            'kode_ruangan'   => null,
        ];

        $result = $service->validateRow(
            rowNum:             2,
            row:                $row,
            packageCodes:       [$package->code => $package->id],
            teacherCodes:       [$teacher->code => $teacher->id],
            packageDurationMap: [$package->code => 30],
        );

        $this->assertIsArray($result);
        $this->assertNull($result['_conflict_warning']);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

```bash
php artisan test tests/Unit/StudentImportConflictTest.php
```

Expected: FAIL — `_conflict_warning` key tidak ada di result

- [ ] **Step 3: Inject ScheduleConflictDetector ke StudentImportService**

Di `app/Services/StudentImportService.php`, tambah constructor dan import:

```php
use App\Services\ScheduleConflictDetector;

class StudentImportService
{
    public function __construct(private ScheduleConflictDetector $conflictDetector) {}

    // ... (konstanta VALID_STATUSES dll tidak berubah)
```

- [ ] **Step 4: Tambah conflict warning di validateRow()**

Di `validateRow()`, cari blok resolusi ID di sekitar baris 352-371 (bagian setelah `if (!empty($errors))` return):

```php
// Resolve kode paket/guru/ruangan ke ID agar siap disimpan ke DB
$data                        = $row;
$data['package_id']          = ...;
$data['assigned_teacher_id'] = ...;
$data['room_id']             = ...;
$data['_room_code']          = ...;
$data['_duration_min']       = ...;
$data['_has_warning']        = $roomWarning !== null;
$data['_warning_message']    = $roomWarning;
unset($data['package_code'], $data['teacher_code'], $data['kode_ruangan']);
```

Tambahkan blok conflict warning SETELAH `unset(...)` dan SEBELUM `// Normalisasi string kosong`:

```php
// Cek konflik jadwal guru/ruang — non-blocking (warning saja, tidak block import).
// Import adalah migrasi data lama; konflik mungkin ada dan perlu diketahui admin.
$conflictWarning = null;
if (!empty($data['assigned_teacher_id'])
    && !empty($row['preferred_day'])
    && !empty($row['preferred_time'])
    && !empty($data['_duration_min'])) {
    $dayOfWeek = $this->parseDayOfWeek($row['preferred_day']);
    $startTime = $row['preferred_time'];
    $endTime   = Carbon::createFromFormat('H:i', $startTime)
        ->addMinutes((int) $data['_duration_min'])
        ->format('H:i');

    $teacherClash = $this->conflictDetector->findTeacherConflicts(
        teacherId: (int) $data['assigned_teacher_id'],
        dayOfWeek: $dayOfWeek,
        startTime: $startTime,
        endTime:   $endTime,
    );
    if ($teacherClash->isNotEmpty()) {
        $conflictWarning = "Guru sudah punya jadwal di {$row['preferred_day']} {$startTime}.";
    }

    if ($conflictWarning === null && !empty($data['room_id'])) {
        if ($this->conflictDetector->isRoomFull(
            roomId:    (int) $data['room_id'],
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime:   $endTime,
        )) {
            $conflictWarning = "Ruangan {$data['_room_code']} sudah penuh di {$row['preferred_day']} {$startTime}.";
        }
    }
}
$data['_conflict_warning'] = $conflictWarning;
```

- [ ] **Step 5: Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Unit/StudentImportConflictTest.php
```

Expected: 2 test PASS

- [ ] **Step 6: Tampilkan conflict warning di import preview view**

Di `resources/views/imports/index.blade.php`, ada **2 lokasi** yang menampilkan kolom status (satu untuk baris valid, satu untuk baris overwrite).

Temukan kedua blok ini (baris ~222 dan ~271):

```blade
@if(!empty($item['data']['_has_warning']))
    <span style="color:#FBBF24" title="{{ $item['data']['_warning_message'] ?? '' }}">⚠️ Warning ruangan</span>
@elseif(!empty($item['data']['preferred_day']) && ($item['data']['status'] ?? '') === 'Aktif')
```

Ganti keduanya dengan pola yang menambahkan pengecekan `_conflict_warning`:

```blade
@if(!empty($item['data']['_conflict_warning']))
    <span style="color:#FB923C" title="{{ $item['data']['_conflict_warning'] }}">⚠️ Konflik jadwal</span>
@elseif(!empty($item['data']['_has_warning']))
    <span style="color:#FBBF24" title="{{ $item['data']['_warning_message'] ?? '' }}">⚠️ Warning ruangan</span>
@elseif(!empty($item['data']['preferred_day']) && ($item['data']['status'] ?? '') === 'Aktif')
```

Lakukan penggantian ini di kedua lokasi (tabel baris valid dan tabel baris overwrite).

- [ ] **Step 7: Full test suite hijau**

```bash
php artisan test
```

Expected: semua test PASS

- [ ] **Step 8: Commit**

```bash
git add app/Services/StudentImportService.php resources/views/imports/index.blade.php tests/Unit/StudentImportConflictTest.php
git commit -m "Fix: StudentImportService — tambah warning konflik guru/ruang saat validate import"
```

---

## Self-Review

**Spec coverage check:**
- Gap 1 (FASE 2 generator): ✓ Task 1
- Gap 2 (EnrollmentController::store): ✓ Task 2
- Gap 3 (StudentImportService): ✓ Task 3

**Placeholder scan:** tidak ada TBD/TODO/placeholder

**Type consistency:**
- `hasConflictOnDate(Schedule $schedule, Carbon $date): bool` — dipakai sama di Task 1
- `findTeacherConflicts(teacherId:, dayOfWeek:, startTime:, endTime:): Collection` — sama di Task 2 + Task 3
- `isRoomFull(roomId:, dayOfWeek:, startTime:, endTime:): bool` — sama di Task 2 + Task 3
- `generateForMonth(int $year, int $month): array` — return array dengan key `skipped_conflict` sudah ada di report sejak baris 61

**Catatan penting:**
- Import (Task 3) adalah **warning non-blocking** — admin tetap bisa commit import meski ada konflik jadwal. Ini sesuai pola existing `_has_warning` untuk ruangan. Konsisten dengan filosofi migrasi data lama.
- EnrollmentController (Task 2) adalah **blocking** — admin harus pilih guru/ruang lain. Ini sesuai karena ini input real-time, bukan migrasi data lama.
- SessionGenerator (Task 1) adalah **skip + log** — konflik di-log sebagai warning dan sesi tidak dibuat. Admin bisa lihat di laporan generator.
