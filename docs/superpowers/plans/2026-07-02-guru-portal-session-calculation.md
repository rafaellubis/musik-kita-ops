# Perbaikan Kalkulasi Sesi Terlaksana Portal Guru Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki kalkulasi metrik "Sesi Terlaksana" pada dashboard guru dengan menggunakan Eloquent Query Scopes baru agar sinkron dengan rincian slip honor bulanan (menggunakan Opsi B - Finansial).

**Architecture:** Logika penyaringan sesi berbayar akan dipusatkan dalam dua scope baru (`scopeHonoredForTeacher` dan `scopeHonoredStatus`) di model `ClassSession`. Dashboard controller (`GuruController`) dan service payroll (`HonorCalculationService`) akan diperbarui untuk menggunakan scope tersebut guna menghindari duplikasi kode (*DRY*).

**Tech Stack:** Laravel 11, PHP 8.3, PHPUnit

## Global Constraints

- Tulis kode yang clean, mudah dibaca, dan well-commented dalam Bahasa Indonesia.
- Jangan `migrate:fresh` tanpa konfirmasi eksplisit.
- Testing WAJIB menggunakan SQLite `:memory:` (diatur otomatis oleh phpunit.xml).
- Gunakan bahasa Indonesia untuk komentar, pesan UI, dan validasi.
- Komit perubahan ke git dengan format: `[Modul/Area] Deskripsi singkat`

---

### Task 1: Model Query Scopes & Tests

**Files:**
- Create: `tests/Feature/GuruSessionCalculationTest.php`
- Modify: `app/Models/ClassSession.php`

**Interfaces:**
- Consumes: `App\Models\ClassSession`, `Database\Factories\ClassSessionFactory`
- Produces: `ClassSession::scopeHonoredForTeacher(int $teacherId)`, `ClassSession::scopeHonoredStatus()`

- [ ] **Step 1: Tulis tes PHPUnit yang akan gagal (failing test)**

Create: `tests/Feature/GuruSessionCalculationTest.php`
```php
<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruSessionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher1;
    private Teacher $teacher2;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        
        $this->teacher1 = Teacher::factory()->create();
        $this->teacher2 = Teacher::factory()->create();
    }

    public function test_scopes_calculate_honored_sessions_correctly(): void
    {
        $today = today()->toDateString();

        // 1. Sesi biasa HADIR -> terhitung untuk teacher1
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_HADIR,
            'honor_code' => 'H_REG',
            'honor_amount' => 42500,
        ]);

        // 2. Sesi IZIN_RESCHEDULE -> tidak terhitung untuk teacher1
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_IZIN_RESCHEDULE,
            'honor_code' => 'H_IZIN',
            'honor_amount' => 0,
        ]);

        // 3. Sesi DIGANTI (substitute_teacher_id = teacher2) - belum dikonfirmasi (honor_code null)
        // -> tidak terhitung untuk teacher1 maupun teacher2
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'substitute_teacher_id' => $this->teacher2->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_DIGANTI,
            'honor_code' => null,
            'honor_amount' => null,
        ]);

        // 4. Sesi DIGANTI (substitute_teacher_id = teacher2) - sudah dikonfirmasi (honor_code filled)
        // -> terhitung untuk teacher2, TIDAK untuk teacher1
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'substitute_teacher_id' => $this->teacher2->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_DIGANTI,
            'honor_code' => 'H_PENG',
            'honor_amount' => 42500,
        ]);

        // 5. Sesi LIBUR nasional berbayar -> terhitung untuk teacher1
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_LIBUR,
            'honor_code' => 'H_LIBUR',
            'honor_amount' => 42500,
        ]);

        // Test scope honoredForTeacher & honoredStatus
        $count1 = ClassSession::honoredForTeacher($this->teacher1->id)->honoredStatus()->count();
        $count2 = ClassSession::honoredForTeacher($this->teacher2->id)->honoredStatus()->count();

        $this->assertEquals(2, $count1, 'Teacher 1 harus memiliki 2 sesi berbayar (Hadir, Libur)');
        $this->assertEquals(1, $count2, 'Teacher 2 harus memiliki 1 sesi berbayar (Pengganti terkonfirmasi)');
    }
}
```

- [ ] **Step 2: Jalankan tes untuk memverifikasi kegagalannya**

Run: `php artisan test tests/Feature/GuruSessionCalculationTest.php`
Expected: FAIL dengan pesan method `honoredForTeacher` tidak ditemukan.

- [ ] **Step 3: Implementasikan kode minimal agar tes berhasil**

Modify: `app/Models/ClassSession.php` (tambahkan method scope berikut sebelum class berakhir, misalnya di line 316 sebelum scopeForAttributionMonth)
```php
    /**
     * Scope untuk memfilter sesi yang honornya diberikan kepada guru tertentu.
     */
    public function scopeHonoredForTeacher($query, int $teacherId)
    {
        return $query->where(function ($q) use ($teacherId) {
            $q->where(function ($qq) use ($teacherId) {
                $qq->where('teacher_id', $teacherId)
                   ->whereNull('substitute_teacher_id');
            })->orWhere('substitute_teacher_id', $teacherId);
        });
    }

    /**
     * Scope untuk memfilter sesi yang statusnya menghasilkan honor (berbayar).
     */
    public function scopeHonoredStatus($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_SCHEDULED,
            self::STATUS_IZIN_RESCHEDULE,
            self::STATUS_IZIN_PENDING,
            self::STATUS_CANCELLED,
        ])->whereNotNull('honor_code');
    }
```

- [ ] **Step 4: Jalankan tes kembali untuk memverifikasi keberhasilannya**

Run: `php artisan test tests/Feature/GuruSessionCalculationTest.php`
Expected: PASS

- [ ] **Step 5: Komit perubahan**

```bash
git add tests/Feature/GuruSessionCalculationTest.php app/Models/ClassSession.php
git commit -m "[M10] Tambah query scopes untuk sesi berbayar guru"
```

---

### Task 2: Controller & Service Refactoring

**Files:**
- Modify: `app/Http/Controllers/GuruController.php`
- Modify: `app/Services/HonorCalculationService.php`
- Test: `tests/Feature/GuruSessionCalculationTest.php`

**Interfaces:**
- Consumes: `ClassSession::scopeHonoredForTeacher(int $teacherId)`, `ClassSession::scopeHonoredStatus()`
- Produces: Pembaruan visual data dashboard guru dan data slip honor bulanan

- [ ] **Step 1: Tulis tes integrasi tambahan di test file**

Modify: `tests/Feature/GuruSessionCalculationTest.php` (tambahkan method test berikut)
```php
    public function test_dashboard_shows_correct_honored_sessions_count(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $today = today()->toDateString();
        $startBulan = now()->startOfMonth()->toDateString();

        // Sesi terbayar guru
        ClassSession::factory()->create([
            'teacher_id' => $teacher->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_HADIR,
            'honor_code' => 'H_REG',
            'honor_amount' => 42500,
        ]);

        // Sesi reschedule (tidak terbayar)
        ClassSession::factory()->create([
            'teacher_id' => $teacher->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_IZIN_RESCHEDULE,
            'honor_code' => 'H_IZIN',
            'honor_amount' => 0,
        ]);

        $response = $this->actingAs($user)->get('/guru/dashboard');
        $response->assertOk();
        
        // Memastikan total sesi terlaksana di dashboard adalah 1 (hanya yang HADIR)
        $response->assertViewHas('totalSesiBulan', 1);
    }
```

- [ ] **Step 2: Jalankan tes untuk memverifikasi kegagalannya**

Run: `php artisan test tests/Feature/GuruSessionCalculationTest.php`
Expected: FAIL pada test dashboard karena metrik menghitung 2 sesi (HADIR dan IZIN_RESCHEDULE).

- [ ] **Step 3: Lakukan pembaruan di Controller dan Service**

Modify: `app/Http/Controllers/GuruController.php:95-103` (Ubah query `$totalSesiBulan`)
```php
        // Total sesi terlaksana bulan ini (sesuai sesi berbayar pada slip honor)
        $totalSesiBulan = ClassSession::honoredForTeacher($teacher->id)
            ->whereBetween('session_date', [$startBulan, $endBulan])
            ->honoredStatus()
            ->count();
```

Modify: `app/Services/HonorCalculationService.php:73-91` (Ganti query di `calculateForTeacher()`)
```php
        $sessions = ClassSession::query()
            ->honoredForTeacher($teacher->id)
            ->whereYear('session_date', $year)
            ->whereMonth('session_date', $month)
            ->honoredStatus()
            ->get(['id', 'honor_code', 'honor_amount', 'session_date', 'status',
                   'teacher_id', 'substitute_teacher_id', 'student_id', 'enrollment_id']);
```

Modify: `app/Services/HonorCalculationService.php:136-155` (Ganti query di `getSessionBreakdown()`)
```php
        $sessions = ClassSession::query()
            ->with('student:id,full_name,student_code', 'enrollment.package.instrument')
            ->honoredForTeacher($slip->teacher_id)
            ->whereYear('session_date', $slip->year)
            ->whereMonth('session_date', $slip->month)
            ->honoredStatus()
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();
```

- [ ] **Step 4: Jalankan tes untuk memverifikasi keberhasilannya**

Run: `php artisan test tests/Feature/GuruSessionCalculationTest.php`
Expected: PASS (Semua tes hijau!)

- [ ] **Step 5: Jalankan seluruh test suite aplikasi untuk memastikan tidak ada efek samping**

Run: `php artisan test`
Expected: PASS (Seluruh suite hijau)

- [ ] **Step 6: Komit perubahan**

```bash
git add app/Http/Controllers/GuruController.php app/Services/HonorCalculationService.php tests/Feature/GuruSessionCalculationTest.php
git commit -m "[M10] Hubungkan portal guru dan payroll service ke query scopes sesi berbayar"
```
