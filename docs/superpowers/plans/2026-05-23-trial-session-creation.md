# Fix Trial Session Creation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Saat admin jadwalkan trial murid Calon, buat satu `ClassSession` berstatus SCHEDULED agar absensi dapat dicatat dan honor guru terbayar.

**Architecture:** Tiga titik perubahan: (1) validasi controller jadikan `assigned_teacher_id` wajib; (2) service `mulaiTrial()` buat `ClassSession` di dalam transaksi DB; (3) form UI tambah atribut `required` dan tanda asterisk pada select Guru Trial. Tidak ada tabel baru, tidak ada migration.

**Tech Stack:** Laravel 11, PHPUnit (SQLite in-memory), Blade, Tailwind CSS, Carbon

---

## File Map

| File | Aksi | Tanggung Jawab |
|------|------|----------------|
| `tests/Feature/TrialSessionCreationTest.php` | Buat baru | 4 test: sesi terbuat, durasi 30 menit, room tersimpan, guru wajib |
| `app/Http/Controllers/StudentController.php` | Modifikasi baris 315–324 | `startTrial()` — ubah `assigned_teacher_id` dari `nullable` ke `required` |
| `app/Services/StudentLifecycleService.php` | Modifikasi baris 70–94 | `mulaiTrial()` — tambah `ClassSession::create()` di dalam transaksi |
| `resources/views/students/show.blade.php` | Modifikasi baris 249–257 | Form trial — `required` + asterisk pada select Guru Trial |

---

### Task 1: Tulis test yang gagal (TDD: red)

**Files:**
- Buat: `tests/Feature/TrialSessionCreationTest.php`

- [ ] **Step 1: Tulis test file**

```php
<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialSessionCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** mulaiTrial harus membuat ClassSession dengan enrollment_id = NULL */
    public function test_mulaiTrial_membuat_class_session(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $trialAt = now()->addDay()->setTime(10, 0, 0);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => $trialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_sessions', [
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'enrollment_id' => null,
            'session_date'  => $trialAt->toDateString(),
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);
    }

    /** Durasi trial selalu 30 menit untuk semua tipe paket (BR-1.3) */
    public function test_mulaiTrial_durasi_sesi_30_menit(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $trialAt = now()->addDay()->setTime(14, 30, 0);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => $trialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_sessions', [
            'student_id' => $student->id,
            'start_time' => '14:30:00',
            'end_time'   => '15:00:00',
        ]);
    }

    /** room_id tersimpan saat admin memilih ruangan */
    public function test_mulaiTrial_menyimpan_room_id(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $room    = Room::factory()->create();
        $trialAt = now()->addDay()->setTime(9, 0, 0);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => $trialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'assigned_room_id'    => $room->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_sessions', [
            'student_id' => $student->id,
            'room_id'    => $room->id,
        ]);
    }

    /** Guru trial wajib diisi — request tanpa guru dikembalikan dengan error validasi */
    public function test_startTrial_wajib_isi_guru(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date' => now()->addDay()->format('Y-m-d\TH:i'),
                // tidak ada assigned_teacher_id
            ])
            ->assertSessionHasErrors(['assigned_teacher_id']);

        // Pastikan tidak ada ClassSession yang terbuat
        $this->assertDatabaseCount('class_sessions', 0);
    }

    /** Service-level guard: mulaiTrial() tanpa assigned_teacher_id lempar InvalidArgumentException */
    public function test_mulaiTrial_tanpa_teacher_lempar_exception(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('assigned_teacher_id wajib diisi');

        $lifecycle = new \App\Services\StudentLifecycleService(
            new \App\Services\InvoiceService()
        );
        $lifecycle->mulaiTrial($student, [
            'trial_date' => now()->addDay()->format('Y-m-d\TH:i'),
            // tidak ada assigned_teacher_id
        ]);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan GAGAL**

```
php artisan test tests/Feature/TrialSessionCreationTest.php
```

Expected output (4 test gagal):
```
FAILED  Tests\Feature\TrialSessionCreationTest > test_mulaiTrial_membuat_class_session
FAILED  Tests\Feature\TrialSessionCreationTest > test_mulaiTrial_durasi_sesi_30_menit
FAILED  Tests\Feature\TrialSessionCreationTest > test_mulaiTrial_menyimpan_room_id
FAILED  Tests\Feature\TrialSessionCreationTest > test_startTrial_wajib_isi_guru
```

Test 1–3 gagal karena `ClassSession` tidak terbuat. Test 4 gagal karena `assigned_teacher_id` masih `nullable` di validasi controller.

- [ ] **Step 3: Commit test file**

```bash
git add tests/Feature/TrialSessionCreationTest.php
git commit -m "Test: Trial session creation — 4 failing tests (TDD red)"
```

---

### Task 2: Implementasi — buat test hijau

**Files:**
- Modifikasi: `app/Http/Controllers/StudentController.php`
- Modifikasi: `app/Services/StudentLifecycleService.php`
- Modifikasi: `resources/views/students/show.blade.php`

- [ ] **Step 1: Jadikan `assigned_teacher_id` required di controller**

Di `app/Http/Controllers/StudentController.php` baris 316–324, ubah blok `validate` di `startTrial()`:

```php
// SEBELUM
$data = $request->validate([
    'trial_date'          => 'required|date|after:now',
    'package_id'          => 'nullable|exists:packages,id',
    'assigned_teacher_id' => 'nullable|exists:teachers,id',
    'assigned_room_id'    => 'nullable|exists:rooms,id',
    'notes'               => 'nullable|string|max:500',
], [
    'trial_date.required' => 'Tanggal trial wajib diisi.',
    'trial_date.after'    => 'Jadwal trial harus setelah sekarang.',
]);

// SESUDAH
$data = $request->validate([
    'trial_date'          => 'required|date|after:now',
    'package_id'          => 'nullable|exists:packages,id',
    'assigned_teacher_id' => 'required|exists:teachers,id',
    'assigned_room_id'    => 'nullable|exists:rooms,id',
    'notes'               => 'nullable|string|max:500',
], [
    'trial_date.required'          => 'Tanggal trial wajib diisi.',
    'trial_date.after'             => 'Jadwal trial harus setelah sekarang.',
    'assigned_teacher_id.required' => 'Guru trial wajib dipilih.',
]);
```

- [ ] **Step 2: Buat `ClassSession` di dalam `mulaiTrial()`**

Di `app/Services/StudentLifecycleService.php`, ganti seluruh method `mulaiTrial()` (baris 70–94):

```php
/**
 * Calon -> Trial. Jadwalkan trial 30 menit (BR-1.3).
 * Buat ClassSession dengan enrollment_id=NULL — marker bahwa ini sesi trial.
 * AbsensiService mendeteksi enrollment_id=NULL untuk menentukan honor code:
 * H_TRIAL (murid hadir) atau TRIAL_NS (no-show, Rp 0) sesuai BR-1.4.
 *
 * @param array{
 *     trial_date: string,           // datetime-local string, mis. "2026-06-01T10:00"
 *     assigned_teacher_id: int,     // wajib — FK ke teachers
 *     assigned_room_id?: int|null,  // opsional
 *     package_id?: int|null,        // opsional — hanya info minat paket
 *     notes?: string|null,
 * } $data
 */
public function mulaiTrial(Student $student, array $data): Student
{
    $this->ensureTransition($student, 'Trial');

    // Guard di level service: teacher_id wajib karena class_sessions.teacher_id NOT NULL.
    // Controller sudah enforce via 'required', tapi kalau service dipanggil langsung
    // (test, seeder, dll) tanpa key ini, kita dapat error yang jelas bukan crash diam-diam.
    if (empty($data['assigned_teacher_id'])) {
        throw new \InvalidArgumentException('assigned_teacher_id wajib diisi untuk membuat sesi trial.');
    }

    return DB::transaction(function () use ($student, $data) {
        $from = $student->status;

        $student->update([
            'status'     => 'Trial',
            'trial_date' => $data['trial_date'],
        ]);

        // Buat sesi trial. enrollment_id NULL karena murid belum punya enrollment.
        // Durasi selalu 30 menit untuk semua tipe paket (BR-1.3).
        $trialDateTime = \Carbon\Carbon::parse($data['trial_date']);
        ClassSession::create([
            'schedule_id'   => null,
            'enrollment_id' => null,
            'student_id'    => $student->id,
            'teacher_id'    => $data['assigned_teacher_id'],
            'room_id'       => $data['assigned_room_id'] ?? null,
            'session_date'  => $trialDateTime->toDateString(),
            'start_time'    => $trialDateTime->format('H:i:s'),
            'end_time'      => $trialDateTime->copy()->addMinutes(30)->format('H:i:s'),
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        $this->recordHistory(
            student:  $student,
            from:     $from,
            to:       'Trial',
            reason:   $data['notes'] ?? null,
            metadata: [
                'trial_date'          => $data['trial_date'],
                'assigned_teacher_id' => $data['assigned_teacher_id'],
            ],
        );

        return $student->fresh();
    });
}
```

`ClassSession` sudah diimport di baris 5 file ini (`use App\Models\ClassSession;`) — tidak perlu tambah `use` baru.

- [ ] **Step 3: Tambah `required` + asterisk pada Guru Trial di form**

Di `resources/views/students/show.blade.php` baris 249–257, ubah label + select Guru Trial:

```blade
{{-- SEBELUM --}}
<label class="block text-xs text-gray-500 mb-1">Guru Trial</label>
<select name="assigned_teacher_id" class="block w-full rounded-lg text-sm px-3 py-2">

{{-- SESUDAH --}}
<label class="block text-xs text-gray-500 mb-1">Guru Trial <span class="text-red-400">*</span></label>
<select name="assigned_teacher_id" required class="block w-full rounded-lg text-sm px-3 py-2">
```

- [ ] **Step 4: Jalankan test baru — pastikan semua 4 LULUS**

```
php artisan test tests/Feature/TrialSessionCreationTest.php
```

Expected:
```
PASS  Tests\Feature\TrialSessionCreationTest
Tests:  4 passed
```

- [ ] **Step 5: Jalankan semua test — pastikan tidak ada regresi**

```
php artisan test
```

Expected: semua test lulus. Sebelum perubahan ini ada 176 tests — sekarang harusnya 180 (176 + 4 baru).

- [ ] **Step 6: Commit implementasi**

```bash
git add app/Http/Controllers/StudentController.php \
        app/Services/StudentLifecycleService.php \
        resources/views/students/show.blade.php
git commit -m "M02: Fix trial — buat ClassSession di mulaiTrial(), guru wajib"
```

---

## Self-Review

**Spec coverage:**
- ✅ `ClassSession::create()` di `mulaiTrial()` — Task 2 Step 2
- ✅ `enrollment_id = null` (marker trial untuk AbsensiService) — Task 2 Step 2
- ✅ Durasi 30 menit (BR-1.3) — Task 2 Step 2, ditest Task 1
- ✅ `assigned_teacher_id` required di controller — Task 2 Step 1
- ✅ Guard `InvalidArgumentException` di service jika teacher absent — Task 2 Step 2
- ✅ Error message Bahasa Indonesia — Task 2 Step 1
- ✅ UI form `required` + asterisk — Task 2 Step 3
- ✅ `room_id` nullable, tersimpan jika diisi — Task 2 Step 2, ditest Task 1
- ✅ `schedule_id = null` (tidak ada jadwal reguler untuk sesi trial) — Task 2 Step 2

**Placeholder scan:** Tidak ada TBD/TODO/placeholder.

**Type consistency:** `$data['assigned_teacher_id']` divalidasi sebagai integer via `exists:teachers,id`, dipakai sebagai `teacher_id` di `ClassSession::create()` — konsisten. `ClassSession::STATUS_SCHEDULED` adalah konstanta string di model, dipakai di banyak tempat lain di codebase.

**Post-review fixes:** Ditambahkan guard `InvalidArgumentException` di service (sebelum transaksi DB) dan test ke-5 `test_mulaiTrial_tanpa_teacher_lempar_exception` untuk cover service-layer call tanpa controller.
