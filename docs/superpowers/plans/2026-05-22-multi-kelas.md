# Multi-Kelas per Murid — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memungkinkan satu murid memiliki N kelas aktif sekaligus, masing-masing dengan paket, guru, jadwal, dan invoice SPP tersendiri.

**Architecture:** Primary Pointer — `students.primary_enrollment_id` menunjuk ke enrollment utama untuk display. Semua data kelas hidup di `enrollments`. Accessor di Student model menjaga backward compatibility view lama. Invoice SPP di-generate per enrollment, bukan per student.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL, Blade + Alpine.js, Spatie Permission, php artisan test (SQLite in-memory)

**Spec:** `docs/superpowers/specs/2026-05-22-multi-kelas-design.md`

---

## File Map

**Baru dibuat:**
- `database/migrations/2026_05_22_000001_multi_kelas_schema.php` — schema + data migration
- `app/Http/Controllers/EnrollmentController.php` — store, setPrimary, destroy
- `app/Http/Requests/StoreEnrollmentRequest.php` — validasi tambah kelas
- `resources/views/students/partials/tab-kelas.blade.php` — UI kelas berjalan + riwayat
- `tests/Feature/EnrollmentControllerTest.php` — test controller enrollment
- `tests/Feature/MultiKelasInvoiceTest.php` — test generateMonthlySPP multi-enrollment

**Dimodifikasi:**
- `app/Models/Student.php` — hapus relasi lama, tambah primaryEnrollment + accessors
- `app/Models/Enrollment.php` — tambah is_primary ke fillable
- `app/Services/StudentLifecycleService.php` — update openEnrollment + hapus referensi kolom lama
- `app/Services/InvoiceService.php` — update generateMonthlySPP loop per enrollment
- `resources/views/students/show.blade.php` — tambah tab Kelas
- `routes/web.php` — tambah routes enrollment
- `database/factories/StudentFactory.php` — hapus package_id, assigned_teacher_id, dll

---

## Task 1: Migration Schema + Data

**Files:**
- Create: `database/migrations/2026_05_22_000001_multi_kelas_schema.php`

- [ ] **Step 1: Buat file migration**

```bash
php artisan make:migration multi_kelas_schema
```

Rename file yang ter-generate ke `2026_05_22_000001_multi_kelas_schema.php`, lalu isi:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom baru ke enrollments dulu (sebelum student butuh FK-nya)
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('notes');
            // Extend enum status: tambah ON_LEAVE
            $table->enum('status', ['ACTIVE', 'ON_LEAVE', 'INACTIVE', 'COMPLETED'])
                  ->default('ACTIVE')->change();
        });

        // 2. Tambah kolom enrollment_id ke invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('enrollment_id')
                  ->nullable()
                  ->after('student_id')
                  ->constrained('enrollments')
                  ->nullOnDelete();
        });

        // 3. Tambah primary_enrollment_id ke students (nullable dulu, isi data dulu)
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('primary_enrollment_id')
                  ->nullable()
                  ->after('status')
                  ->constrained('enrollments')
                  ->nullOnDelete();
        });

        // 4. Data migration: isi is_primary + primary_enrollment_id dari data existing
        // Set is_primary = true untuk enrollment ACTIVE pertama per murid
        DB::statement("
            UPDATE enrollments e
            INNER JOIN (
                SELECT MIN(id) as min_id, student_id
                FROM enrollments
                WHERE status = 'ACTIVE'
                GROUP BY student_id
            ) first_active ON e.id = first_active.min_id
            SET e.is_primary = 1
        ");

        // Set primary_enrollment_id di students dari enrollment is_primary-nya
        DB::statement("
            UPDATE students s
            INNER JOIN enrollments e ON e.student_id = s.id AND e.is_primary = 1
            SET s.primary_enrollment_id = e.id
        ");

        // 5. Hapus kolom lama dari students
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['assigned_teacher_id']);
            $table->dropForeign(['assigned_room_id']);
            $table->dropColumn([
                'package_id',
                'assigned_teacher_id',
                'assigned_room_id',
                'preferred_day',
                'preferred_time',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->constrained('packages');
            $table->foreignId('assigned_teacher_id')->nullable()->constrained('teachers');
            $table->foreignId('assigned_room_id')->nullable()->constrained('rooms');
            $table->string('preferred_day')->nullable();
            $table->time('preferred_time')->nullable();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['primary_enrollment_id']);
            $table->dropColumn('primary_enrollment_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['enrollment_id']);
            $table->dropColumn('enrollment_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('is_primary');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'COMPLETED'])
                  ->default('ACTIVE')->change();
        });
    }
};
```

- [ ] **Step 2: Jalankan migration di database development**

```bash
php artisan migrate
```

Expected output: `Running migrations... 2026_05_22_000001_multi_kelas_schema .... DONE`

- [ ] **Step 3: Verifikasi schema dan data migration**

```bash
php artisan tinker --execute="
echo 'students columns: ';
var_dump(Schema::getColumnListing('students'));
echo 'enrollments is_primary count: ';
echo \App\Models\Enrollment::where('is_primary', true)->count();
echo ' primary_enrollment_id filled: ';
echo \App\Models\Student::whereNotNull('primary_enrollment_id')->count();
"
```

Expected: kolom `package_id`, `assigned_teacher_id`, `assigned_room_id` tidak ada. `is_primary` count sama dengan jumlah murid Aktif.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "M-Kelas: Migration schema multi-kelas — Primary Pointer + enrollment_id invoice"
```

---

## Task 2: Update Model Student + Enrollment + Factory

**Files:**
- Modify: `app/Models/Student.php`
- Modify: `app/Models/Enrollment.php`
- Modify: `database/factories/StudentFactory.php`

- [ ] **Step 1: Tulis test dulu (TDD)**

Buat `tests/Feature/MultiKelasModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiKelasModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_package_accessor_via_primary_enrollment(): void
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->for($student)->create([
            'is_primary' => true,
            'status'     => 'ACTIVE',
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        // Accessor harus delegasikan ke primaryEnrollment
        $this->assertNotNull($student->fresh()->package);
        $this->assertEquals($enrollment->package_id, $student->fresh()->package->id);
    }

    public function test_student_package_accessor_null_when_no_primary_enrollment(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);

        $this->assertNull($student->package);
        $this->assertNull($student->assignedTeacher);
    }

    public function test_student_dapat_punya_dua_enrollment_active(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);

        Enrollment::factory()->for($student)->create([
            'is_primary' => true,
            'status'     => 'ACTIVE',
        ]);
        Enrollment::factory()->for($student)->create([
            'is_primary' => false,
            'status'     => 'ACTIVE',
        ]);

        $this->assertEquals(2, $student->enrollments()->active()->count());
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL karena model belum diupdate**

```bash
php artisan test tests/Feature/MultiKelasModelTest.php
```

Expected: FAIL karena Student masih punya accessor lama / kolom lama.

- [ ] **Step 3: Update `app/Models/Student.php`**

Ganti isi file dengan versi yang bersih (hapus relasi lama, tambah primaryEnrollment + accessors):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        // Identitas
        'student_code', 'full_name', 'nickname', 'gender',
        // Kontak
        'birth_date', 'phone', 'email', 'address', 'notes',
        // Parent
        'parent_name', 'parent_phone', 'parent_email', 'parent_relationship',
        // Status
        'status', 'primary_enrollment_id',
        'trial_date', 'active_since', 'last_session_at',
        // Cuti
        'cuti_from', 'cuti_until',
    ];

    protected $casts = [
        'birth_date'      => 'date',
        'trial_date'      => 'datetime',
        'active_since'    => 'date',
        'last_session_at' => 'datetime',
        'cuti_from'       => 'date',
        'cuti_until'      => 'date',
    ];

    // ============= RELATIONSHIPS =============

    public function primaryEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'primary_enrollment_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class)->latest();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issued_at');
    }

    // ============= ACCESSORS (backward compat) =============

    // View lama yang pakai $student->package / $student->assignedTeacher
    // tetap berfungsi tanpa perlu diubah satu per satu.

    public function getPackageAttribute(): ?Package
    {
        return $this->primaryEnrollment?->package;
    }

    public function getAssignedTeacherAttribute(): ?Teacher
    {
        return $this->primaryEnrollment?->teacher;
    }

    public function getAssignedRoomAttribute(): ?Room
    {
        // Enrollment punya schedules() hasMany — ambil schedule aktif pertama
        return $this->primaryEnrollment?->schedules()->active()->first()?->room;
    }

    // ============= STATIC METHODS =============

    public static function generateCode(): string
    {
        $year   = now()->year;
        $prefix = "M-{$year}-";

        $latest = static::where('student_code', 'like', $prefix . '%')
            ->orderBy('student_code', 'desc')
            ->first();

        if (!$latest) {
            return $prefix . '0001';
        }

        $lastNumber = (int) substr($latest->student_code, -4);
        return $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }

    // ============= ACCESSOR =============

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }
}
```

- [ ] **Step 4: Update `app/Models/Enrollment.php`**

Tambah `is_primary` ke `$fillable`:

```php
protected $fillable = [
    'student_id', 'package_id', 'teacher_id',
    'effective_date', 'end_date', 'status', 'notes',
    'is_primary',   // ← tambah ini
];
```

- [ ] **Step 5: Update `database/factories/StudentFactory.php`**

Hapus `package_id`, `assigned_teacher_id`, `assigned_room_id`, `preferred_day`, `preferred_time` dari definisi factory. Pastikan tidak ada kolom yang tidak ada di schema:

```php
// Cari baris-baris seperti ini dan hapus:
// 'package_id'          => Package::factory(),
// 'assigned_teacher_id' => Teacher::factory(),
// 'assigned_room_id'    => Room::factory(),
// 'preferred_day'       => 'Senin',
// 'preferred_time'      => '15:00',
```

Jika factory tidak ada, buat dengan perintah:
```bash
php artisan make:factory StudentFactory --model=Student
```

- [ ] **Step 6: Buat `database/factories/EnrollmentFactory.php`** (jika belum ada)

```bash
php artisan make:factory EnrollmentFactory --model=Enrollment
```

Isi:

```php
<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'package_id'     => Package::factory(),
            'teacher_id'     => Teacher::factory(),
            'effective_date' => now()->toDateString(),
            'end_date'       => null,
            'status'         => 'ACTIVE',
            'is_primary'     => false,
            'notes'          => null,
        ];
    }
}
```

- [ ] **Step 7: Jalankan test**

```bash
php artisan test tests/Feature/MultiKelasModelTest.php
```

Expected: 3 tests PASS

- [ ] **Step 8: Jalankan full test suite — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test pass. Jika ada test yang fail karena referensi kolom `package_id` dll di Student, update test tersebut agar tidak set kolom yang sudah dihapus.

- [ ] **Step 9: Commit**

```bash
git add app/Models/Student.php app/Models/Enrollment.php database/factories/
git add tests/Feature/MultiKelasModelTest.php
git commit -m "M-Kelas: Update Student model — Primary Pointer accessors + Enrollment is_primary"
```

---

## Task 3: Update StudentLifecycleService

**Files:**
- Modify: `app/Services/StudentLifecycleService.php`

Perubahan yang dibutuhkan:
1. `openEnrollment()`: set `is_primary = true` + update `student.primary_enrollment_id`
2. `mulaiTrial()`, `konversiAktif()`, `skipTrial()`, `aktifkanKembali()`: hapus referensi kolom lama (`package_id`, `assigned_teacher_id`, `assigned_room_id`)

- [ ] **Step 1: Update `openEnrollment()` di baris ~706**

```php
private function openEnrollment(Student $student, int $packageId, int $teacherId): Enrollment
{
    // Tutup enrollment ACTIVE existing (defensive untuk transisi lifecycle)
    $this->closeActiveEnrollments($student, status: 'INACTIVE');

    $enrollment = Enrollment::create([
        'student_id'     => $student->id,
        'package_id'     => $packageId,
        'teacher_id'     => $teacherId,
        'effective_date' => now()->toDateString(),
        'status'         => 'ACTIVE',
        'is_primary'     => true,
    ]);

    // Update pointer kelas utama di student
    $student->update(['primary_enrollment_id' => $enrollment->id]);

    return $enrollment;
}
```

- [ ] **Step 2: Update `mulaiTrial()` — hapus referensi kolom yang sudah dihapus**

Cari method `mulaiTrial()` (sekitar baris 73). Ganti blok `$student->update([...])` menjadi:

```php
$student->update([
    'status'     => 'Trial',
    'trial_date' => $data['trial_date'],
    // package_id, assigned_teacher_id, assigned_room_id
    // sudah dihapus dari students — info paket ada di trial session
]);
```

- [ ] **Step 3: Update `konversiAktif()` — hapus kolom lama dari update student**

Cari method `konversiAktif()` (sekitar baris 113). Ganti blok update student:

```php
$student->update([
    'status'       => 'Aktif',
    'active_since' => now()->toDateString(),
    // package_id, assigned_teacher_id, assigned_room_id
    // sekarang dikelola via openEnrollment → primary_enrollment_id
]);
```

- [ ] **Step 4: Lakukan hal yang sama untuk `skipTrial()` dan `aktifkanKembali()`**

Cari semua kemunculan `'package_id'` dan `'assigned_teacher_id'` di dalam blok `$student->update([...])` di service ini dan hapus. Gunakan:

```bash
grep -n "package_id\|assigned_teacher_id\|assigned_room_id\|preferred_day\|preferred_time" app/Services/StudentLifecycleService.php
```

Hapus semua kemunculan di dalam `$student->update()`. Jangan hapus yang ada di `Enrollment::create()` atau `$data[...]`.

- [ ] **Step 5: Jalankan test**

```bash
php artisan test
```

Expected: semua test pass. Jika ada test lifecycle yang fail karena tidak ada `package_id` di student response, update assertion test tersebut.

- [ ] **Step 6: Commit**

```bash
git add app/Services/StudentLifecycleService.php
git commit -m "M-Kelas: Update StudentLifecycleService — openEnrollment set is_primary + primary_enrollment_id"
```

---

## Task 4: Update InvoiceService::generateMonthlySPP

**Files:**
- Modify: `app/Services/InvoiceService.php` (sekitar baris 199–254)
- Create: `tests/Feature/MultiKelasInvoiceTest.php`

- [ ] **Step 1: Tulis test dulu**

Buat `tests/Feature/MultiKelasInvoiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Student;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiKelasInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceService::class);
    }

    public function test_generate_spp_dua_invoice_untuk_murid_dua_kelas(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);

        $pkg1 = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);
        $pkg2 = Package::factory()->create([
            'class_type'      => 'HOBBY',
            'price_per_month' => 390000,
        ]);

        $e1 = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg1->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $e2 = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg2->id,
            'status'     => 'ACTIVE',
            'is_primary' => false,
        ]);
        $student->update(['primary_enrollment_id' => $e1->id]);

        $report = $this->service->generateMonthlySPP(2026, 6);

        $this->assertEquals(2, $report['created']);

        $invoices = Invoice::where('student_id', $student->id)->get();
        $this->assertCount(2, $invoices);
        $this->assertEquals(340000, $invoices->firstWhere('enrollment_id', $e1->id)->total_amount);
        $this->assertEquals(390000, $invoices->firstWhere('enrollment_id', $e2->id)->total_amount);
    }

    public function test_generate_spp_idempotent_per_enrollment(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg     = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
        $e1      = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id, 'status' => 'ACTIVE', 'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $e1->id]);

        // Jalankan dua kali
        $this->service->generateMonthlySPP(2026, 6);
        $report = $this->service->generateMonthlySPP(2026, 6);

        $this->assertEquals(0, $report['created']);
        $this->assertEquals(1, $report['skipped']);
    }

    public function test_enrollment_inactive_tidak_dapat_spp(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg     = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
        $e1      = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id, 'status' => 'ACTIVE', 'is_primary' => true,
        ]);
        Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id, 'status' => 'INACTIVE', 'is_primary' => false,
        ]);
        $student->update(['primary_enrollment_id' => $e1->id]);

        $report = $this->service->generateMonthlySPP(2026, 6);

        // Hanya 1 invoice — enrollment INACTIVE tidak dapat SPP
        $this->assertEquals(1, $report['created']);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/MultiKelasInvoiceTest.php
```

Expected: FAIL karena `generateMonthlySPP` masih pakai `$student->enrollments->first()`.

- [ ] **Step 3: Update `generateMonthlySPP()` di `InvoiceService.php`**

Ganti implementasi method (sekitar baris 199–254) dengan versi yang loop per enrollment:

```php
public function generateMonthlySPP(int $year, int $month): array
{
    $issuedAt = Carbon::create($year, $month, 1)->startOfMonth();
    $dueDate  = $issuedAt->copy()->day(self::DUE_DAY)->endOfDay();

    $report = ['created' => 0, 'skipped' => 0];

    // Ambil semua murid Aktif yang punya minimal 1 enrollment ACTIVE
    $students = Student::where('status', 'Aktif')
        ->whereHas('enrollments', fn ($q) => $q->where('status', 'ACTIVE')->whereNull('end_date'))
        ->with(['enrollments' => fn ($q) => $q->where('status', 'ACTIVE')->whereNull('end_date')->with('package.instrument')])
        ->get();

    foreach ($students as $student) {
        foreach ($student->enrollments as $enrollment) {
            $package = $enrollment->package;
            if (!$package) {
                $report['skipped']++;
                continue;
            }

            // KIDS_CLASS_BUNDLE pakai cicilan termin — tidak kena SPP bulanan (BR-10.10)
            if ($package->class_type === 'KIDS_CLASS_BUNDLE') {
                $report['skipped']++;
                continue;
            }

            // Idempotent: cek per (student, enrollment, year, month)
            $exists = Invoice::where('student_id', $student->id)
                ->where('enrollment_id', $enrollment->id)
                ->where('year', $year)
                ->where('month', $month)
                ->whereHas('items', fn ($q) => $q->where('item_code', 'SPP'))
                ->exists();

            if ($exists) {
                $report['skipped']++;
                continue;
            }

            $instrNama = $package->instrument->name ?? $package->code;

            $this->createOneOff(
                student:      $student,
                items:        [[
                    'code'        => 'SPP',
                    'description' => "SPP {$instrNama} — {$issuedAt->translatedFormat('F Y')}",
                    'amount'      => $package->price_per_month,
                    'metadata'    => [
                        'package_id'    => $package->id,
                        'enrollment_id' => $enrollment->id,
                    ],
                ]],
                description:  "SPP {$instrNama} — {$issuedAt->translatedFormat('F Y')}",
                dueDate:      $dueDate,
                issuedAt:     $issuedAt,
                classType:    $package->class_type,
                enrollmentId: $enrollment->id,
            );

            $report['created']++;
        }
    }

    return $report;
}
```

- [ ] **Step 4: Update signature `createOneOff()` — tambah parameter `enrollmentId`**

Di method `createOneOff()` (sekitar baris 66), tambah parameter baru dan simpan ke invoice:

```php
public function createOneOff(
    Student $student,
    array $items,
    ?string $description = null,
    ?Carbon $dueDate = null,
    ?Carbon $issuedAt = null,
    ?string $classType = null,
    string $paymentMode = Invoice::MODE_FULL,
    ?int $installmentNumber = null,
    ?string $installmentGroupId = null,
    ?int $enrollmentId = null,   // ← tambah ini
): Invoice {
```

Dan di dalam blok `Invoice::create([...])`, tambah:

```php
'enrollment_id' => $enrollmentId,
```

- [ ] **Step 5: Jalankan test**

```bash
php artisan test tests/Feature/MultiKelasInvoiceTest.php
```

Expected: 3 tests PASS

- [ ] **Step 6: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua test pass

- [ ] **Step 7: Commit**

```bash
git add app/Services/InvoiceService.php tests/Feature/MultiKelasInvoiceTest.php
git commit -m "M-Kelas: generateMonthlySPP loop per enrollment — invoice per kelas"
```

---

## Task 5: EnrollmentController + Routes + Request

**Files:**
- Create: `app/Http/Controllers/EnrollmentController.php`
- Create: `app/Http/Requests/StoreEnrollmentRequest.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/EnrollmentControllerTest.php`

- [ ] **Step 1: Tulis test dulu**

Buat `tests/Feature/EnrollmentControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private Student $student;
    private Package $package;
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->owner   = User::factory()->create()->assignRole('Owner');
        $this->admin   = User::factory()->create()->assignRole('Admin');
        $this->student = Student::factory()->create(['status' => 'Aktif']);
        $this->package = Package::factory()->create(['class_type' => 'REGULER']);
        $this->teacher = Teacher::factory()->create();
    }

    // ===== STORE =====

    public function test_admin_dapat_tambah_kelas_baru(): void
    {
        $room = Room::factory()->create();

        // Buat enrollment utama dulu
        $e1 = Enrollment::factory()->for($this->student)->create([
            'is_primary' => true, 'status' => 'ACTIVE',
        ]);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room->id,
                'day_of_week'    => 1, // Senin
                'start_time'     => '16:00',
                'effective_date' => '2026-06-01',
                'jadikan_utama'  => false,
            ]
        );

        $response->assertRedirect();
        $this->assertEquals(2, $this->student->enrollments()->active()->count());
        // Enrollment lama tetap utama
        $this->student->refresh();
        $this->assertEquals($e1->id, $this->student->primary_enrollment_id);
    }

    public function test_tambah_kelas_dengan_jadikan_utama(): void
    {
        $room = Room::factory()->create();
        $e1 = Enrollment::factory()->for($this->student)->create([
            'is_primary' => true, 'status' => 'ACTIVE',
        ]);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room->id,
                'day_of_week'    => 3,
                'start_time'     => '14:00',
                'effective_date' => '2026-06-01',
                'jadikan_utama'  => true,
            ]
        );

        $this->student->refresh();
        $e1->refresh();
        $this->assertFalse((bool) $e1->is_primary);
        $this->assertNotEquals($e1->id, $this->student->primary_enrollment_id);
    }

    // ===== SET PRIMARY =====

    public function test_admin_dapat_set_enrollment_sebagai_utama(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)
            ->patch(route('students.enrollments.set-primary', [$this->student, $e2]));

        $this->student->refresh();
        $e1->refresh();
        $e2->refresh();
        $this->assertEquals($e2->id, $this->student->primary_enrollment_id);
        $this->assertFalse((bool) $e1->is_primary);
        $this->assertTrue((bool) $e2->is_primary);
    }

    // ===== DESTROY =====

    public function test_hentikan_kelas_non_utama(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)
            ->delete(route('students.enrollments.destroy', [$this->student, $e2]));

        $e2->refresh();
        $this->assertEquals('INACTIVE', $e2->status);
        // e1 tetap ACTIVE dan tetap utama
        $this->student->refresh();
        $this->assertEquals($e1->id, $this->student->primary_enrollment_id);
    }

    public function test_hentikan_kelas_utama_minta_konfirmasi_jika_ada_kelas_lain(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('students.enrollments.destroy', [$this->student, $e1]));

        // Harus redirect balik dengan pesan konfirmasi — e1 belum INACTIVE
        $response->assertRedirect();
        $response->assertSessionHas('confirm_primary_swap');
        $e1->refresh();
        $this->assertEquals('ACTIVE', $e1->status);
    }

    public function test_hentikan_kelas_utama_dengan_konfirmasi_swap(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)->delete(
            route('students.enrollments.destroy', [$this->student, $e1]),
            ['new_primary_enrollment_id' => $e2->id]
        );

        $e1->refresh();
        $this->student->refresh();
        $this->assertEquals('INACTIVE', $e1->status);
        $this->assertEquals($e2->id, $this->student->primary_enrollment_id);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL (routes + controller belum ada)**

```bash
php artisan test tests/Feature/EnrollmentControllerTest.php
```

Expected: FAIL dengan error "Route not defined"

- [ ] **Step 3: Buat `StoreEnrollmentRequest`**

```bash
php artisan make:request StoreEnrollmentRequest
```

Isi `app/Http/Requests/StoreEnrollmentRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Admin']);
    }

    public function rules(): array
    {
        return [
            'package_id'     => ['required', 'exists:packages,id'],
            'teacher_id'     => ['required', 'exists:teachers,id'],
            'room_id'        => ['required', 'exists:rooms,id'],
            'day_of_week'    => ['required', 'integer', 'between:0,6'],
            'start_time'     => ['required', 'date_format:H:i'],
            'effective_date' => ['required', 'date', 'after_or_equal:today'],
            'jadikan_utama'  => ['sometimes', 'boolean'],
            // Untuk konfirmasi swap saat hentikan kelas utama
            'new_primary_enrollment_id' => ['sometimes', 'nullable', 'exists:enrollments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.required'     => 'Paket wajib dipilih.',
            'teacher_id.required'     => 'Guru wajib dipilih.',
            'room_id.required'        => 'Ruangan wajib dipilih.',
            'day_of_week.required'    => 'Hari wajib dipilih.',
            'start_time.required'     => 'Jam mulai wajib diisi.',
            'effective_date.required' => 'Tanggal mulai efektif wajib diisi.',
            'effective_date.after_or_equal' => 'Tanggal efektif tidak boleh sebelum hari ini.',
        ];
    }
}
```

- [ ] **Step 4: Buat `EnrollmentController`**

```bash
php artisan make:controller EnrollmentController
```

Isi `app/Http/Controllers/EnrollmentController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\ScheduleConflictDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly ScheduleConflictDetector $conflictDetector,
    ) {}

    /**
     * Tambah kelas baru ke murid yang sudah aktif.
     */
    public function store(StoreEnrollmentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();

        // Hitung end_time: paket durasi dalam menit
        $package   = \App\Models\Package::findOrFail($data['package_id']);
        $startTime = $data['start_time'];
        $endTime   = \Carbon\Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($package->duration_min)
            ->format('H:i');

        // Cek konflik guru
        $teacherConflicts = $this->conflictDetector->findTeacherConflicts(
            teacherId:  $data['teacher_id'],
            dayOfWeek:  $data['day_of_week'],
            startTime:  $startTime,
            endTime:    $endTime,
        );

        if ($teacherConflicts->isNotEmpty()) {
            return back()->withErrors(['teacher_id' => 'Guru sudah ada jadwal di hari dan jam yang sama.']);
        }

        // Cek konflik ruangan
        if ($this->conflictDetector->isRoomFull($data['room_id'], $data['day_of_week'], $startTime, $endTime)) {
            return back()->withErrors(['room_id' => 'Ruangan sudah penuh di hari dan jam yang sama.']);
        }

        DB::transaction(function () use ($student, $data, $package, $startTime, $endTime) {
            $jadikanUtama = (bool) ($data['jadikan_utama'] ?? false);

            if ($jadikanUtama) {
                // Reset is_primary enrollment lama
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

    /**
     * Jadikan enrollment ini sebagai kelas utama murid.
     */
    public function setPrimary(Student $student, Enrollment $enrollment): RedirectResponse
    {
        abort_if($enrollment->student_id !== $student->id, 403);
        abort_if($enrollment->status !== 'ACTIVE', 422, 'Hanya kelas yang berjalan bisa dijadikan utama.');

        DB::transaction(function () use ($student, $enrollment) {
            $student->enrollments()->where('is_primary', true)->update(['is_primary' => false]);
            $enrollment->update(['is_primary' => true]);
            $student->update(['primary_enrollment_id' => $enrollment->id]);
        });

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Kelas utama berhasil diperbarui.');
    }

    /**
     * Hentikan kelas (set INACTIVE).
     * Jika kelas yang dihentikan adalah kelas utama dan ada kelas lain,
     * minta konfirmasi pilih kelas utama baru via parameter new_primary_enrollment_id.
     */
    public function destroy(Student $student, Enrollment $enrollment): RedirectResponse
    {
        abort_if($enrollment->student_id !== $student->id, 403);
        abort_if($enrollment->status !== 'ACTIVE', 422, 'Kelas sudah tidak aktif.');

        $isPrimary    = (bool) $enrollment->is_primary;
        $otherActives = $student->enrollments()
            ->active()
            ->where('id', '!=', $enrollment->id)
            ->get();

        // Jika kelas utama dan masih ada kelas lain, perlu konfirmasi pilih utama baru
        if ($isPrimary && $otherActives->isNotEmpty()) {
            $newPrimaryId = request()->input('new_primary_enrollment_id');

            if (!$newPrimaryId) {
                // Kembalikan ke halaman dengan data untuk form konfirmasi
                return redirect()
                    ->route('students.show', $student)
                    ->with('confirm_primary_swap', [
                        'enrollment_id'  => $enrollment->id,
                        'other_actives'  => $otherActives->toArray(),
                    ]);
            }

            // Admin sudah konfirmasi — swap utama lalu hentikan
            $newPrimary = Enrollment::findOrFail($newPrimaryId);
            abort_if($newPrimary->student_id !== $student->id, 403);

            DB::transaction(function () use ($student, $enrollment, $newPrimary) {
                $student->enrollments()->where('is_primary', true)->update(['is_primary' => false]);
                $newPrimary->update(['is_primary' => true]);
                $student->update(['primary_enrollment_id' => $newPrimary->id]);
                $this->hentikanEnrollment($enrollment);
            });

            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Kelas dihentikan dan kelas utama diperbarui.');
        }

        // Bukan utama, atau utama tapi tidak ada kelas lain — langsung hentikan
        $this->hentikanEnrollment($enrollment);

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Kelas berhasil dihentikan.');
    }

    private function hentikanEnrollment(Enrollment $enrollment): void
    {
        $enrollment->update([
            'status'   => 'INACTIVE',
            'end_date' => now()->toDateString(),
        ]);

        // Nonaktifkan semua jadwal enrollment ini
        $enrollment->schedules()->update(['is_active' => false]);
    }
}
```

- [ ] **Step 5: Tambah routes di `routes/web.php`**

Cari blok `// Schedule selalu attach ke enrollment ACTIVE murid` (sekitar baris 121) dan tambah routes enrollment DI ATASNYA:

```php
// Manajemen kelas per murid (multi-kelas)
Route::post(
    'students/{student}/enrollments',
    [\App\Http\Controllers\EnrollmentController::class, 'store']
)->name('students.enrollments.store');

Route::patch(
    'students/{student}/enrollments/{enrollment}/primary',
    [\App\Http\Controllers\EnrollmentController::class, 'setPrimary']
)->name('students.enrollments.set-primary');

Route::delete(
    'students/{student}/enrollments/{enrollment}',
    [\App\Http\Controllers\EnrollmentController::class, 'destroy']
)->name('students.enrollments.destroy');
```

- [ ] **Step 6: Jalankan test**

```bash
php artisan test tests/Feature/EnrollmentControllerTest.php
```

Expected: semua test PASS

- [ ] **Step 7: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua test pass

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/EnrollmentController.php \
        app/Http/Requests/StoreEnrollmentRequest.php \
        routes/web.php \
        tests/Feature/EnrollmentControllerTest.php
git commit -m "M-Kelas: EnrollmentController — tambah kelas, set utama, hentikan kelas"
```

---

## Task 6: Views — Tab "Kelas" di Halaman Murid

**Files:**
- Create: `resources/views/students/partials/tab-kelas.blade.php`
- Modify: `resources/views/students/show.blade.php`

- [ ] **Step 1: Cek struktur tab yang ada di `students/show.blade.php`**

```bash
grep -n "tab\|Kelas\|Lifecycle\|Invoice" resources/views/students/show.blade.php | head -30
```

Perhatikan pattern tab yang sudah ada untuk dijaga konsistensinya.

- [ ] **Step 2: Buat `resources/views/students/partials/tab-kelas.blade.php`**

```blade
{{-- Tab Kelas — Manajemen kelas aktif dan riwayat kelas murid --}}

<div class="space-y-4">

  {{-- Notifikasi konfirmasi swap kelas utama --}}
  @if(session('confirm_primary_swap'))
    @php $swap = session('confirm_primary_swap'); @endphp
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
      <p class="text-sm text-yellow-800 font-medium mb-3">
        Kelas yang dihentikan adalah <strong>Kelas Utama</strong>.
        Pilih kelas pengganti sebagai kelas utama baru:
      </p>
      <form method="POST"
            action="{{ route('students.enrollments.destroy', [$student, $swap['enrollment_id']]) }}">
        @csrf @method('DELETE')
        <div class="flex items-center gap-3">
          <select name="new_primary_enrollment_id"
                  class="flex-1 border border-yellow-300 rounded px-3 py-2 text-sm">
            @foreach($swap['other_actives'] as $other)
              <option value="{{ $other['id'] }}">
                {{ optional(\App\Models\Package::find($other['package_id']))->code }}
              </option>
            @endforeach
          </select>
          <button type="submit"
                  class="px-4 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
            Hentikan & Ganti Utama
          </button>
          <a href="{{ route('students.show', $student) }}"
             class="px-4 py-2 text-sm text-gray-600 hover:underline">Batal</a>
        </div>
      </form>
    </div>
  @endif

  {{-- Kelas Berjalan --}}
  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 flex items-center justify-between border-b border-gray-100">
      <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Kelas Berjalan</h3>
      @can('tambah-kelas')
        <button onclick="document.getElementById('modal-tambah-kelas').classList.remove('hidden')"
                class="text-sm px-3 py-1.5 bg-amber-500 text-white rounded hover:bg-amber-600 font-medium">
          + Tambah Kelas
        </button>
      @endcan
    </div>

    @forelse($activeEnrollments as $enrollment)
      <div class="px-4 py-3 flex items-center gap-3 border-b border-gray-50 last:border-0">
        {{-- Ikon instrumen --}}
        <div class="w-8 h-8 rounded-md bg-amber-50 flex items-center justify-center text-sm flex-shrink-0">
          {{ $enrollment->package->instrument->icon ?? '🎵' }}
        </div>

        {{-- Info kelas --}}
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-medium text-gray-800">
              {{ $enrollment->package->instrument->name ?? '-' }}
              — {{ $enrollment->package->code }}
            </span>
            @if($enrollment->is_primary)
              <span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded font-semibold">
                ★ Kelas Utama
              </span>
            @endif
          </div>
          <p class="text-xs text-gray-400 mt-0.5">
            Guru: {{ $enrollment->teacher->name ?? '-' }}
            @if($schedule = $enrollment->schedules()->active()->first())
              &nbsp;·&nbsp; {{ ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][$schedule->day_of_week] }}
              {{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($schedule->end_time)->format('H:i') }}
              &nbsp;·&nbsp; {{ $schedule->room->name ?? '-' }}
            @endif
            &nbsp;·&nbsp; Mulai {{ $enrollment->effective_date?->format('d M Y') }}
          </p>
        </div>

        {{-- Badge status --}}
        <span class="text-xs px-2 py-0.5 bg-green-50 text-green-700 rounded">Berjalan</span>

        {{-- Aksi --}}
        <div class="flex items-center gap-2">
          @if(!$enrollment->is_primary)
            <form method="POST"
                  action="{{ route('students.enrollments.set-primary', [$student, $enrollment]) }}">
              @csrf @method('PATCH')
              <button type="submit"
                      class="text-xs px-2 py-1 border border-amber-300 text-amber-600 rounded hover:bg-amber-50">
                Jadikan Utama
              </button>
            </form>
          @endif
          <form method="POST"
                action="{{ route('students.enrollments.destroy', [$student, $enrollment]) }}"
                onsubmit="return confirm('Hentikan kelas ini?')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="text-xs px-2 py-1 border border-red-200 text-red-500 rounded hover:bg-red-50">
              Hentikan
            </button>
          </form>
        </div>
      </div>
    @empty
      <p class="px-4 py-4 text-sm text-gray-400">Belum ada kelas berjalan.</p>
    @endforelse
  </div>

  {{-- Riwayat Kelas --}}
  @if($historyEnrollments->isNotEmpty())
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Riwayat Kelas</h3>
      </div>
      @foreach($historyEnrollments as $enrollment)
        <div class="px-4 py-3 flex items-center gap-3 border-b border-gray-50 last:border-0 opacity-60">
          <div class="w-8 h-8 rounded-md bg-gray-50 flex items-center justify-center text-sm flex-shrink-0">
            {{ $enrollment->package->instrument->icon ?? '🎵' }}
          </div>
          <div class="flex-1 min-w-0">
            <span class="text-sm text-gray-700">
              {{ $enrollment->package->instrument->name ?? '-' }} — {{ $enrollment->package->code }}
            </span>
            <p class="text-xs text-gray-400">
              Guru: {{ $enrollment->teacher->name ?? '-' }}
              &nbsp;·&nbsp;
              {{ $enrollment->effective_date?->format('M Y') }}
              –
              {{ $enrollment->end_date?->format('M Y') ?? 'sekarang' }}
            </p>
          </div>
          <span class="text-xs px-2 py-0.5 rounded
            @if($enrollment->status === 'COMPLETED') bg-blue-50 text-blue-600
            @else bg-gray-100 text-gray-500 @endif">
            {{ $enrollment->status === 'COMPLETED' ? 'Selesai' : 'Dihentikan' }}
          </span>
        </div>
      @endforeach
    </div>
  @endif

</div>

{{-- Modal Tambah Kelas --}}
<div id="modal-tambah-kelas"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this) this.classList.add('hidden')">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-lg" onclick="event.stopPropagation()">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h4 class="font-semibold text-gray-800">Tambah Kelas — {{ $student->full_name }}</h4>
      <button onclick="document.getElementById('modal-tambah-kelas').classList.add('hidden')"
              class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
    </div>
    <form method="POST" action="{{ route('students.enrollments.store', $student) }}">
      @csrf
      <div class="px-5 py-4 grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Instrumen</label>
          <select name="instrument_id" id="modal-instrument"
                  class="w-full border border-gray-200 rounded px-3 py-2 text-sm">
            <option value="">— Pilih —</option>
            @foreach(\App\Models\Instrument::all() as $instr)
              <option value="{{ $instr->id }}">{{ $instr->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Paket</label>
          <select name="package_id" class="w-full border border-gray-200 rounded px-3 py-2 text-sm">
            <option value="">— Pilih instrumen dulu —</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Guru</label>
          <select name="teacher_id" class="w-full border border-gray-200 rounded px-3 py-2 text-sm">
            <option value="">— Pilih instrumen dulu —</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Ruangan</label>
          <select name="room_id" class="w-full border border-gray-200 rounded px-3 py-2 text-sm">
            <option value="">— Pilih —</option>
            @foreach(\App\Models\Room::all() as $room)
              <option value="{{ $room->id }}">{{ $room->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Hari</label>
          <select name="day_of_week" class="w-full border border-gray-200 rounded px-3 py-2 text-sm">
            @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $i => $hari)
              <option value="{{ $i }}">{{ $hari }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Jam Mulai</label>
          <input type="time" name="start_time" value="15:00"
                 class="w-full border border-gray-200 rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Berlaku Mulai</label>
          <input type="date" name="effective_date" value="{{ now()->addDay()->format('Y-m-d') }}"
                 class="w-full border border-gray-200 rounded px-3 py-2 text-sm">
        </div>
        <div class="flex items-center gap-2 pt-4">
          <input type="checkbox" name="jadikan_utama" value="1" id="jadikan-utama" class="rounded">
          <label for="jadikan-utama" class="text-sm text-gray-600">Jadikan kelas utama</label>
        </div>
      </div>
      <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
        <button type="button"
                onclick="document.getElementById('modal-tambah-kelas').classList.add('hidden')"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded hover:bg-gray-50">
          Batal
        </button>
        <button type="submit"
                class="px-4 py-2 text-sm bg-amber-500 text-white rounded hover:bg-amber-600 font-medium">
          Simpan & Buat Jadwal
        </button>
      </div>
    </form>
  </div>
</div>
```

- [ ] **Step 3: Update `StudentController::show()` — sediakan variabel untuk tab Kelas**

Cari method `show()` di `app/Http/Controllers/StudentController.php`. Tambahkan dua variabel sebelum `return view(...)`:

```php
$activeEnrollments  = $student->enrollments()
    ->active()
    ->with(['package.instrument', 'teacher', 'schedules.room'])
    ->orderByDesc('is_primary')
    ->get();

$historyEnrollments = $student->enrollments()
    ->whereIn('status', ['INACTIVE', 'COMPLETED'])
    ->with(['package.instrument', 'teacher'])
    ->orderByDesc('end_date')
    ->get();
```

Pass keduanya ke view:

```php
return view('students.show', compact('student', 'activeEnrollments', 'historyEnrollments', /* variabel lain yang sudah ada */));
```

- [ ] **Step 4: Tambah tab "Kelas" di `resources/views/students/show.blade.php`**

Cari tab navigation yang ada dan tambahkan tab Kelas. Cari juga area konten tab dan tambahkan panel untuk tab Kelas:

```blade
{{-- Di navigasi tab --}}
<button @click="tab = 'kelas'" :class="tab === 'kelas' ? 'border-amber-500 text-amber-600' : 'border-transparent text-gray-500'"
        class="py-3 px-1 border-b-2 text-sm font-medium">
    Kelas
</button>

{{-- Di area konten tab --}}
<div x-show="tab === 'kelas'">
    @include('students.partials.tab-kelas')
</div>
```

- [ ] **Step 5: Cek tampilan di browser**

```bash
php artisan serve
```

Buka `http://localhost:8000/students/{id}`, klik tab "Kelas". Verifikasi:
- Kelas berjalan tampil dengan badge "★ Kelas Utama"
- Tombol "Jadikan Utama" muncul hanya pada kelas non-utama
- Modal "Tambah Kelas" bisa dibuka dan ditutup
- Riwayat kelas tampil di bawah (jika ada)

- [ ] **Step 6: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua test pass

- [ ] **Step 7: Commit**

```bash
git add resources/views/students/partials/tab-kelas.blade.php \
        resources/views/students/show.blade.php \
        app/Http/Controllers/StudentController.php
git commit -m "M-Kelas: Tab Kelas di halaman murid — kelas berjalan, riwayat, modal tambah kelas"
```

---

## Verifikasi Akhir

- [ ] **Jalankan full test suite satu kali lagi**

```bash
php artisan test
```

Expected: semua test PASS, tidak ada yang skip.

- [ ] **Smoke test manual di browser**

1. Buka detail murid yang sudah Aktif
2. Tab "Kelas" → tambah kelas kedua via modal
3. Verifikasi 2 kelas muncul di "Kelas Berjalan"
4. Klik "Jadikan Utama" pada kelas kedua → badge berpindah
5. Klik "Hentikan" pada kelas utama → muncul form konfirmasi swap
6. Buka halaman Invoice murid → verifikasi ada 2 invoice SPP setelah `generate-spp` dijalankan

- [ ] **Commit final**

```bash
git add .
git commit -m "M-Kelas: Fitur multi-kelas per murid — complete"
git push
```
