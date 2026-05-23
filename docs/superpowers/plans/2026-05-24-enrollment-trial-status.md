# Enrollment TRIAL Status — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah status `TRIAL` ke enum `enrollments.status` agar sesi trial punya `enrollment_id` valid, sehingga `calculateHonor()` bisa resolve paket dan menghitung honor guru dengan benar.

**Architecture:** Saat `mulaiTrial()` dipanggil, buat Enrollment status=TRIAL (is_primary=false) menggunakan package_id yang dipilih admin, lalu ClassSession trial pakai `enrollment_id` dari enrollment itu. `calculateHonor()` deteksi trial via `enrollment->status === 'TRIAL'` (bukan `enrollment_id === null` seperti sebelumnya). Saat `konversiAktif()` atau `mundurkan()`, enrollment TRIAL ditutup (→COMPLETED) sebelum enrollment ACTIVE baru dibuka.

**Tech Stack:** Laravel 11, PHP 8.3, PHPUnit (SQLite in-memory), MySQL enum ALTER

**Root cause yang diperbaiki:** `AttendanceService::calculateHonor()` tidak bisa resolve `$package` untuk sesi trial karena `enrollment_id = null` → `$package = null` → `honor_amount = 0`.

---

## File Map

| Aksi | File | Tanggung Jawab |
|------|------|----------------|
| BUAT | `database/migrations/2026_05_24_add_trial_to_enrollments_status.php` | ALTER TABLE — tambah `TRIAL` ke enum |
| UBAH | `app/Models/Enrollment.php` | Konstanta `STATUS_TRIAL` + scope `trial()` |
| UBAH | `app/Http/Controllers/StudentController.php` (baris 315–325) | `startTrial()` — `package_id` jadi `required` |
| UBAH | `app/Services/StudentLifecycleService.php` (baris 76–122) | `mulaiTrial()` — buat Enrollment TRIAL + ClassSession pakai enrollment_id |
| UBAH | `app/Services/StudentLifecycleService.php` (baris 135–175) | `konversiAktif()` — close TRIAL sebelum openEnrollment |
| UBAH | `app/Services/StudentLifecycleService.php` (baris 373–411) | `mundurkan()` — close TRIAL saat mundur |
| UBAH | `app/Services/StudentLifecycleService.php` (baris 609–621) | `closeActiveEnrollments()` — tambah helper `closeTrialEnrollments()` |
| UBAH | `app/Services/AttendanceService.php` (baris 147–199) | `calculateHonor()` — deteksi trial via enrollment status |
| UBAH | `tests/Feature/TrialSessionCreationTest.php` | Update assertion `enrollment_id => null` → cek enrollment_id ada |
| BUAT | `tests/Feature/TrialEnrollmentTest.php` | Test enrollment TRIAL lifecycle + honor calculation |
| UBAH | `CLAUDE.md` | Update schema enrollments + catatan TRIAL |

---

## Task 1: Migration — Tambah TRIAL ke Enum

**Files:**
- Buat: `database/migrations/2026_05_24_add_trial_to_enrollments_status.php`

- [ ] **Step 1.1: Buat migration**

```bash
php artisan make:migration add_trial_to_enrollments_status
```

- [ ] **Step 1.2: Isi migration**

Buka file migration yang baru dibuat, ganti seluruh isinya:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah nilai TRIAL ke enum enrollments.status.
 * TRIAL dipakai saat murid Calon sedang menjalani sesi trial (belum jadi murid aktif).
 * Enrollment TRIAL → COMPLETED saat murid mundur atau lanjut jadi ACTIVE.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE enrollments
            MODIFY COLUMN status
            ENUM('ACTIVE','ON_LEAVE','INACTIVE','COMPLETED','TRIAL')
            NOT NULL DEFAULT 'ACTIVE'
        ");
    }

    public function down(): void
    {
        // Pastikan tidak ada row TRIAL sebelum rollback
        DB::table('enrollments')->where('status', 'TRIAL')->update(['status' => 'INACTIVE']);

        DB::statement("
            ALTER TABLE enrollments
            MODIFY COLUMN status
            ENUM('ACTIVE','ON_LEAVE','INACTIVE','COMPLETED')
            NOT NULL DEFAULT 'ACTIVE'
        ");
    }
};
```

- [ ] **Step 1.3: Jalankan migration**

```bash
php artisan migrate
```

Expected output: `Migrating: 2026_05_24_add_trial_to_enrollments_status` → `Migrated`

- [ ] **Step 1.4: Verifikasi enum di database**

```bash
php artisan tinker --execute="
\$col = DB::select(\"SHOW COLUMNS FROM enrollments WHERE Field = 'status'\");
echo \$col[0]->Type;
"
```

Expected output mengandung: `enum('ACTIVE','ON_LEAVE','INACTIVE','COMPLETED','TRIAL')`

- [ ] **Step 1.5: Commit**

```bash
git add database/migrations/
git commit -m "DB: Migration tambah TRIAL ke enum enrollments.status"
```

---

## Task 2: Update Enrollment Model

**Files:**
- Ubah: `app/Models/Enrollment.php`

- [ ] **Step 2.1: Tambah konstanta dan scope**

Buka `app/Models/Enrollment.php`. Tambahkan konstanta dan scope setelah baris `use HasFactory;`:

```php
// Status constants
public const STATUS_ACTIVE    = 'ACTIVE';
public const STATUS_ON_LEAVE  = 'ON_LEAVE';
public const STATUS_INACTIVE  = 'INACTIVE';
public const STATUS_COMPLETED = 'COMPLETED';
public const STATUS_TRIAL     = 'TRIAL';
```

Tambahkan scope `trial()` setelah scope `active()`:

```php
public function scopeTrial($query)
{
    return $query->where('status', self::STATUS_TRIAL);
}
```

- [ ] **Step 2.2: Verifikasi syntax**

```bash
php artisan tinker --execute="echo App\Models\Enrollment::STATUS_TRIAL;"
```

Expected: `TRIAL`

- [ ] **Step 2.3: Commit**

```bash
git add app/Models/Enrollment.php
git commit -m "M03: Tambah STATUS_TRIAL constant + scope ke Enrollment model"
```

---

## Task 3: Update Validasi Controller — package_id Wajib

**Files:**
- Ubah: `app/Http/Controllers/StudentController.php` (baris ~315–325)

- [ ] **Step 3.1: Tulis test yang gagal dulu**

Buka `tests/Feature/TrialSessionCreationTest.php`. Tambahkan test baru setelah `test_startTrial_wajib_isi_guru`:

```php
/** package_id wajib diisi — request tanpa package_id dikembalikan dengan error validasi */
public function test_startTrial_wajib_isi_package_id(): void
{
    $student = Student::factory()->create(['status' => 'Calon']);
    $teacher = Teacher::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('students.start-trial', $student->id), [
            'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
            'assigned_teacher_id' => $teacher->id,
            // tidak ada package_id
        ])
        ->assertSessionHasErrors(['package_id']);

    $this->assertDatabaseCount('class_sessions', 0);
}
```

- [ ] **Step 3.2: Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/TrialSessionCreationTest.php::test_startTrial_wajib_isi_package_id
```

Expected: FAIL — tidak ada error validasi package_id karena masih nullable.

- [ ] **Step 3.3: Update validasi di controller**

Di `app/Http/Controllers/StudentController.php` method `startTrial()`, ubah blok validate:

```php
// SEBELUM
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

// SESUDAH
$data = $request->validate([
    'trial_date'          => 'required|date|after:now',
    'package_id'          => 'required|exists:packages,id',
    'assigned_teacher_id' => 'required|exists:teachers,id',
    'assigned_room_id'    => 'nullable|exists:rooms,id',
    'notes'               => 'nullable|string|max:500',
], [
    'trial_date.required'          => 'Tanggal trial wajib diisi.',
    'trial_date.after'             => 'Jadwal trial harus setelah sekarang.',
    'package_id.required'          => 'Paket yang diminati wajib dipilih.',
    'assigned_teacher_id.required' => 'Guru trial wajib dipilih.',
]);
```

- [ ] **Step 3.4: Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Feature/TrialSessionCreationTest.php::test_startTrial_wajib_isi_package_id
```

Expected: PASS

- [ ] **Step 3.5: Commit**

```bash
git add app/Http/Controllers/StudentController.php tests/Feature/TrialSessionCreationTest.php
git commit -m "M02: package_id wajib saat startTrial + test"
```

---

## Task 4: Update mulaiTrial() — Buat Enrollment TRIAL

**Files:**
- Ubah: `app/Services/StudentLifecycleService.php` (baris 76–122)
- Buat: `tests/Feature/TrialEnrollmentTest.php`

- [ ] **Step 4.1: Tulis test yang gagal**

Buat file baru `tests/Feature/TrialEnrollmentTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialEnrollmentTest extends TestCase
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

    /** mulaiTrial membuat Enrollment status=TRIAL untuk murid */
    public function test_mulaiTrial_membuat_enrollment_trial(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'status'     => Enrollment::STATUS_TRIAL,
            'is_primary' => false,
        ]);
    }

    /** ClassSession trial sekarang punya enrollment_id (bukan NULL) */
    public function test_mulaiTrial_class_session_punya_enrollment_id(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('status', Enrollment::STATUS_TRIAL)
            ->first();

        $this->assertNotNull($enrollment);

        $this->assertDatabaseHas('class_sessions', [
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    /** Honor trial dihitung berdasarkan paket — HADIR = H_TRIAL = harga x 50% / 4 */
    public function test_honor_hadir_trial_terhitung_sesuai_paket(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->subHour()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $session = ClassSession::where('student_id', $student->id)->first();

        // Input absensi HADIR
        $this->actingAs($this->admin)
            ->patchJson(route('absensi.update', $session->id), [
                'status' => 'HADIR',
            ])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('H_TRIAL', $session->honor_code);
        // Rp 340.000 × 50% / 4 = Rp 42.500
        $this->assertEquals(42500, $session->honor_amount);
    }

    /** Honor trial NO-SHOW = Rp 0 (TRIAL_NS) sesuai BR-1.4 */
    public function test_honor_no_show_trial_adalah_nol(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->subHour()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $session = ClassSession::where('student_id', $student->id)->first();

        $this->actingAs($this->admin)
            ->patchJson(route('absensi.update', $session->id), [
                'status' => 'HANGUS',
            ])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('TRIAL_NS', $session->honor_code);
        $this->assertEquals(0, $session->honor_amount);
    }

    /** konversiAktif menutup enrollment TRIAL (→ COMPLETED) dan buat ACTIVE baru */
    public function test_konversiAktif_menutup_enrollment_trial(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        // Jadwalkan trial dulu
        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->subDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'status'     => Enrollment::STATUS_TRIAL,
        ]);

        // Konversi ke aktif
        $this->actingAs($this->admin)
            ->post(route('students.convert-active', $student->id), [
                'package_id'          => $package->id,
                'assigned_teacher_id' => $teacher->id,
            ])
            ->assertRedirect();

        // Enrollment TRIAL harus COMPLETED
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'package_id' => $package->id,
            'status'     => Enrollment::STATUS_COMPLETED,
        ]);

        // Enrollment ACTIVE baru harus ada
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'status'     => Enrollment::STATUS_ACTIVE,
            'is_primary' => true,
        ]);
    }

    /** mundurkan menutup enrollment TRIAL (→ COMPLETED) */
    public function test_mundur_dari_trial_menutup_enrollment_trial(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        // Jadwalkan trial dulu
        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->subDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        // Mundurkan dari status Trial
        $this->actingAs($this->admin)
            ->post(route('students.mundur', $student->id), [
                'reason' => 'Tidak cocok dengan jadwal',
            ])
            ->assertRedirect();

        // Enrollment TRIAL harus COMPLETED
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'status'     => Enrollment::STATUS_COMPLETED,
        ]);

        // Student status harus Mengundurkan Diri
        $this->assertDatabaseHas('students', [
            'id'     => $student->id,
            'status' => 'Mengundurkan Diri',
        ]);
    }
}
```

- [ ] **Step 4.2: Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/TrialEnrollmentTest.php
```

Expected: semua test FAIL karena enrollment TRIAL belum dibuat.

- [ ] **Step 4.3: Update mulaiTrial() di StudentLifecycleService**

Di `app/Services/StudentLifecycleService.php`, ganti seluruh method `mulaiTrial()` (baris 62–123):

```php
/**
 * Calon -> Trial. Jadwalkan trial 30 menit (BR-1.3).
 * Buat Enrollment status=TRIAL (is_primary=false) + ClassSession dengan enrollment_id.
 * Enrollment TRIAL membawa package_id agar calculateHonor() bisa resolve paket.
 *
 * Honor ditentukan saat input absensi:
 * - Murid HADIR  → H_TRIAL = harga × 50% / 4 (BR-1.4)
 * - Murid HANGUS → TRIAL_NS = Rp 0 (BR-1.4 v1.1)
 *
 * @param array{
 *     trial_date: string,           // datetime-local string, mis. "2026-06-01T10:00"
 *     package_id: int,              // wajib — paket yang diminati murid
 *     assigned_teacher_id: int,     // wajib — FK ke teachers
 *     assigned_room_id?: int|null,  // opsional
 *     notes?: string|null,
 * } $data
 */
public function mulaiTrial(Student $student, array $data): Student
{
    $this->ensureTransition($student, 'Trial');

    if (empty($data['assigned_teacher_id'])) {
        throw new \InvalidArgumentException('assigned_teacher_id wajib diisi untuk membuat sesi trial.');
    }

    if (empty($data['package_id'])) {
        throw new \InvalidArgumentException('package_id wajib diisi untuk membuat sesi trial.');
    }

    return DB::transaction(function () use ($student, $data) {
        $from = $student->status;

        $student->update([
            'status'     => 'Trial',
            'trial_date' => $data['trial_date'],
        ]);

        // Buat enrollment TRIAL — membawa package_id agar honor bisa dihitung.
        // is_primary=false: tidak trigger invoice SPP otomatis.
        $enrollment = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $data['package_id'],
            'teacher_id'     => $data['assigned_teacher_id'],
            'effective_date' => now()->toDateString(),
            'status'         => Enrollment::STATUS_TRIAL,
            'is_primary'     => false,
        ]);

        // Buat sesi trial. Durasi 30 menit untuk semua tipe paket (BR-1.3).
        $trialDateTime = \Carbon\Carbon::parse($data['trial_date']);
        ClassSession::create([
            'schedule_id'   => null,
            'enrollment_id' => $enrollment->id,
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
                'package_id'          => $data['package_id'],
                'assigned_teacher_id' => $data['assigned_teacher_id'],
            ],
        );

        return $student->fresh();
    });
}
```

- [ ] **Step 4.4: Jalankan test TrialEnrollmentTest — cek progress**

```bash
php artisan test tests/Feature/TrialEnrollmentTest.php
```

Expected: 2 test pertama (membuat_enrollment_trial + class_session_punya_enrollment_id) LULUS. Test honor masih FAIL karena calculateHonor belum diupdate.

---

## Task 5: Update AttendanceService — Deteksi Trial via Enrollment Status

**Files:**
- Ubah: `app/Services/AttendanceService.php` (baris 147–199)

- [ ] **Step 5.1: Update calculateHonor()**

Di `app/Services/AttendanceService.php`, ganti method `calculateHonor()` (baris 147–200):

```php
/**
 * Hitung honor berdasarkan status + paket di enrollment.
 *
 * Deteksi trial: enrollment->status === TRIAL.
 * Backward compat: enrollment_id === null juga dianggap trial (data lama sebelum fix).
 *
 * @return array{code: string|null, amount: int}
 */
private function calculateHonor(ClassSession $session): array
{
    $status = $session->status;

    // IZIN_RESCHEDULE: sesi tidak terjadi, honor di sesi pengganti.
    if ($status === 'IZIN_RESCHEDULE') {
        return ['code' => null, 'amount' => 0];
    }

    // SCHEDULED: belum ada absensi, jangan kalkulasi.
    if ($status === 'SCHEDULED') {
        return ['code' => null, 'amount' => 0];
    }

    // Resolve paket dari enrollment
    $package = $session->enrollment?->package;

    // Deteksi trial: enrollment TRIAL = murid belum jadi aktif.
    // Backward compat: enrollment_id NULL = data lama sebelum enrollment TRIAL diimplementasi.
    $isTrial = $session->enrollment_id === null
        || $session->enrollment?->status === \App\Models\Enrollment::STATUS_TRIAL;

    // Kids Class: pakai flat per murid
    if ($package && $package->isKidsClass()) {
        if ($isTrial && $status === 'HANGUS') {
            return ['code' => 'TRIAL_NS', 'amount' => 0];
        }
        return ['code' => 'H_KIDS', 'amount' => self::KIDS_HONOR_PER_STUDENT];
    }

    // Reguler/Hobby: honor = harga_paket * 50% / 4
    $baseHonor = $package
        ? (int) round($package->price_per_month * 0.5 / 4)
        : 0;

    // Trial khusus (BR-1.4)
    if ($isTrial) {
        return $status === 'HANGUS'
            ? ['code' => 'TRIAL_NS', 'amount' => 0]
            : ['code' => 'H_TRIAL', 'amount' => $baseHonor];
    }

    // Mapping status → honor_code (Reguler/Hobby)
    $code = match ($status) {
        'HADIR', 'HADIR_TERLAMBAT' => 'H_REG',
        'IZIN_VIDEO'               => 'H_VIDEO',
        'HANGUS'                   => 'H_HANGUS',
        'LIBUR'                    => 'H_LIBUR',
        'DIGANTI'                  => 'H_PENG',
        default                    => null,
    };

    return ['code' => $code, 'amount' => $code ? $baseHonor : 0];
}
```

- [ ] **Step 5.2: Pastikan enrollment di-load saat recordAttendance()**

Di `app/Services/AttendanceService.php`, di awal method `recordAttendance()` sebelum `$this->validateStatusFields()`, tambahkan:

```php
// Load enrollment dengan package agar calculateHonor() bisa resolve paket
// tanpa lazy-load yang tidak konsisten.
$session->loadMissing(['enrollment.package', 'student']);
```

Jadi awal method `recordAttendance()` menjadi:

```php
public function recordAttendance(ClassSession $session, array $data): ClassSession
{
    $status = $data['status'];

    if (!in_array($status, self::VALID_ATTENDANCE_STATUSES, true)) {
        throw new InvalidArgumentException(
            'Status absensi tidak valid: ' . $status
        );
    }

    // Load relasi yang dibutuhkan calculateHonor() agar tidak lazy-load
    $session->loadMissing(['enrollment.package', 'student']);

    $this->validateStatusFields($status, $data);
    // ... sisa method sama
```

- [ ] **Step 5.3: Jalankan test TrialEnrollmentTest — semua harus LULUS (kecuali lifecycle)**

```bash
php artisan test tests/Feature/TrialEnrollmentTest.php --filter="honor"
```

Expected: 2 test honor LULUS (H_TRIAL = 42500, TRIAL_NS = 0).

---

## Task 6: Update Lifecycle — konversiAktif() dan mundurkan()

**Files:**
- Ubah: `app/Services/StudentLifecycleService.php`

- [ ] **Step 6.1: Tambah helper closeTrialEnrollments()**

Di `app/Services/StudentLifecycleService.php`, tambahkan method private baru setelah `closeActiveEnrollments()` (sekitar baris 621):

```php
/**
 * Tutup semua enrollment TRIAL milik murid: status → COMPLETED + end_date = today.
 * Dipanggil saat murid konversi ke ACTIVE atau mundur tanpa lanjut.
 */
private function closeTrialEnrollments(Student $student): void
{
    $student->enrollments()
        ->where('status', Enrollment::STATUS_TRIAL)
        ->update([
            'status'   => Enrollment::STATUS_COMPLETED,
            'end_date' => now()->toDateString(),
        ]);
}
```

- [ ] **Step 6.2: Update konversiAktif() — close TRIAL sebelum openEnrollment**

Di method `konversiAktif()` (baris ~135), tambahkan `$this->closeTrialEnrollments($student);` sebelum `$this->openEnrollment(...)`:

```php
public function konversiAktif(Student $student, array $data): Student
{
    $this->ensureTransition($student, 'Aktif');

    return DB::transaction(function () use ($student, $data) {
        $from = $student->status;

        $student->update([
            'status'       => 'Aktif',
            'active_since' => now()->toDateString(),
        ]);

        // Tutup enrollment TRIAL jika ada (murid berhasil convert dari trial)
        $this->closeTrialEnrollments($student);

        // Bikin enrollment ACTIVE — sumber kebenaran untuk M03 (jadwal/sesi).
        $enrollment = $this->openEnrollment(
            $student,
            $data['package_id'],
            $data['assigned_teacher_id'],
        );

        // ... sisa method sama (issueActivationInvoice + recordHistory)
```

- [ ] **Step 6.3: Update mundurkan() — close TRIAL saat mundur**

Di method `mundurkan()` (baris ~373), tambahkan `$this->closeTrialEnrollments($student);` setelah `$this->closeActiveEnrollments(...)`:

```php
public function mundurkan(Student $student, array $data): Student
{
    $this->ensureTransition($student, 'Mengundurkan Diri');

    return DB::transaction(function () use ($student, $data) {
        $from = $student->status;

        $student->update(['status' => 'Mengundurkan Diri']);

        // Tutup enrollment ACTIVE: INACTIVE + end_date = today
        $this->closeActiveEnrollments($student, status: 'INACTIVE');

        // Tutup enrollment TRIAL jika ada (murid mundur tanpa jadi aktif)
        $this->closeTrialEnrollments($student);

        // Cancel semua sesi SCHEDULED ... (sisa sama)
```

- [ ] **Step 6.4: Jalankan TrialEnrollmentTest — semua harus LULUS**

```bash
php artisan test tests/Feature/TrialEnrollmentTest.php
```

Expected: semua 6 test LULUS.

---

## Task 7: Update Test Lama + Jalankan Full Suite

**Files:**
- Ubah: `tests/Feature/TrialSessionCreationTest.php`

- [ ] **Step 7.1: Update assertion enrollment_id di test lama**

Di `tests/Feature/TrialSessionCreationTest.php`, test `test_mulaiTrial_membuat_class_session` saat ini assert `enrollment_id => null`. Setelah perubahan ini, `enrollment_id` tidak lagi NULL. Update assertion:

```php
// SEBELUM
$this->assertDatabaseHas('class_sessions', [
    'student_id'    => $student->id,
    'teacher_id'    => $teacher->id,
    'enrollment_id' => null,      // ← hapus baris ini
    'session_date'  => $trialAt->toDateString(),
    'status'        => ClassSession::STATUS_SCHEDULED,
]);

// SESUDAH
$enrollment = \App\Models\Enrollment::where('student_id', $student->id)
    ->where('status', \App\Models\Enrollment::STATUS_TRIAL)
    ->first();

$this->assertNotNull($enrollment, 'Enrollment TRIAL harus dibuat');

$this->assertDatabaseHas('class_sessions', [
    'student_id'    => $student->id,
    'teacher_id'    => $teacher->id,
    'enrollment_id' => $enrollment->id,  // ← ada enrollment sekarang
    'session_date'  => $trialAt->toDateString(),
    'status'        => ClassSession::STATUS_SCHEDULED,
]);
```

Juga update test `test_mulaiTrial_tanpa_teacher_lempar_exception` — sekarang service juga throw untuk package_id kosong. Test ini perlu pass `package_id` agar hanya test teacher guard:

```php
public function test_mulaiTrial_tanpa_teacher_lempar_exception(): void
{
    $student = Student::factory()->create(['status' => 'Calon']);
    $package = \App\Models\Package::factory()->create(['class_type' => 'REGULER']);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('assigned_teacher_id wajib diisi');

    $lifecycle = new \App\Services\StudentLifecycleService(
        new \App\Services\InvoiceService()
    );
    $lifecycle->mulaiTrial($student, [
        'trial_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'package_id' => $package->id,
        // tidak ada assigned_teacher_id
    ]);
}
```

Juga, test `test_mulaiTrial_membuat_class_session`, `test_mulaiTrial_durasi_sesi_30_menit`, `test_mulaiTrial_menyimpan_room_id`, dan `test_startTrial_wajib_isi_guru` semua perlu ditambahkan `package_id` di request karena sekarang wajib:

```php
// Tambahkan di setiap test yang post ke start-trial:
$package = \App\Models\Package::factory()->create([
    'class_type'      => 'REGULER',
    'price_per_month' => 340000,
]);

// Di request tambahkan:
'package_id' => $package->id,
```

- [ ] **Step 7.2: Jalankan TrialSessionCreationTest**

```bash
php artisan test tests/Feature/TrialSessionCreationTest.php
```

Expected: 5 tests LULUS.

- [ ] **Step 7.3: Jalankan semua test — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test lulus. Jumlah test bertambah dari 190 menjadi 196 (190 + 6 baru dari TrialEnrollmentTest).

- [ ] **Step 7.4: Commit**

```bash
git add tests/Feature/TrialSessionCreationTest.php \
        tests/Feature/TrialEnrollmentTest.php \
        app/Services/AttendanceService.php \
        app/Services/StudentLifecycleService.php
git commit -m "M02/M04: Enrollment TRIAL — buat enrollment saat trial, honor terhitung dari paket"
```

---

## Task 8: Update CLAUDE.md

**Files:**
- Ubah: `CLAUDE.md`

- [ ] **Step 8.1: Update schema enrollments**

Di CLAUDE.md bagian `**enrollments**`, ubah baris status:

```
// SEBELUM
status (enum: ACTIVE|ON_LEAVE|INACTIVE|COMPLETED), timestamps

// SESUDAH
status (enum: ACTIVE|ON_LEAVE|INACTIVE|COMPLETED|TRIAL), timestamps
```

Tambahkan catatan di bawah CATATAN ON_LEAVE:

```
CATATAN: `TRIAL` diset saat mulaiTrial() — enrollment sementara yang membawa package_id
agar honor guru bisa dihitung. is_primary=false (tidak trigger invoice SPP).
Enrollment TRIAL → COMPLETED saat murid konversiAktif() atau mundurkan().
```

- [ ] **Step 8.2: Commit CLAUDE.md**

```bash
git add CLAUDE.md
git commit -m "Docs: Update CLAUDE.md — tambah TRIAL ke enum enrollments.status"
```

---

## Task 9: Push & Verifikasi Final

- [ ] **Step 9.1: Jalankan seluruh test suite sekali lagi**

```bash
php artisan test
```

Expected: semua test hijau, tidak ada regresi.

- [ ] **Step 9.2: Push ke GitHub**

```bash
git push origin main
```

---

## Self-Review

**Spec coverage:**
- ✅ Enum TRIAL ditambah ke enrollments.status — Task 1
- ✅ Enrollment TRIAL dibuat saat mulaiTrial() — Task 4
- ✅ package_id wajib saat trial — Task 3
- ✅ ClassSession punya enrollment_id (bukan NULL) — Task 4
- ✅ calculateHonor() resolve paket dari enrollment TRIAL — Task 5
- ✅ H_TRIAL = harga × 50% / 4 — Task 5, ditest Task 4
- ✅ TRIAL_NS = Rp 0 saat HANGUS — Task 5, ditest Task 4
- ✅ Backward compat enrollment_id=NULL — Task 5
- ✅ konversiAktif() close TRIAL → COMPLETED — Task 6, ditest Task 4
- ✅ mundurkan() close TRIAL → COMPLETED — Task 6, ditest Task 4
- ✅ CLAUDE.md diupdate — Task 8
- ✅ Test lama TrialSessionCreationTest diupdate — Task 7

**Placeholder scan:** Tidak ada TBD/TODO/placeholder.

**Type consistency:**
- `Enrollment::STATUS_TRIAL` dipakai konsisten di semua task
- `closeTrialEnrollments()` didefinisikan Task 6 Step 1, dipanggil Task 6 Step 2 dan 3
- `$session->enrollment->status` konsisten dengan eager load di Task 5 Step 2
