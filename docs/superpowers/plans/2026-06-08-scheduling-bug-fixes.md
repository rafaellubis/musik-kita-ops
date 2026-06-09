# Scheduling Bug Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki 8 bug pada implementasi penjadwalan yang ditemukan lewat code review tanggal 2026-06-08.

**Architecture:** Setiap bug diperbaiki secara independen tanpa mengubah interface antar-service. Perbaikan difokuskan pada penambahan validasi/guard di lapisan yang tepat — bukan redesign. Setiap task menghasilkan satu commit yang bisa di-rollback secara mandiri.

**Tech Stack:** Laravel 11, PHP 8.3, PHPUnit (SQLite in-memory via phpunit.xml), Spatie Permission v6.

---

## Ringkasan Bug yang Diperbaiki

| Task | Kode Bug | File Utama | Deskripsi Singkat |
|------|----------|-----------|-------------------|
| 1 | SG1 | SessionGeneratorService | Status IZIN_RESCHEDULE/PENDING tidak dikecualikan dari conflict check |
| 2 | T1 | SessionGeneratorService | DUO max-2 limit tidak dicek di generator |
| 3 | T2+R1 | AttendanceService | DIGANTI tidak cek konflik guru dan ruang pengganti |
| 4 | S1 | ScheduleController | Tidak ada validasi konflik jadwal murid yang sama |
| 5 | RS2 | ScheduleController | Conflict detector tidak cek class_sessions konkret |
| 6 | RE2 | AbsensiController | Tidak ada guard transisi IZIN_RESCHEDULE → IZIN_PENDING |
| 7 | RE1 | AbsensiController | scheduleReplacement() bypass AttendanceService |
| 8 | RS1 | ScheduleController | Update jadwal mingguan tidak sinkronisasi sesi SCHEDULED |

---

## Task 1: [SG1] Fix Status Exclusion di SessionGeneratorService::findConflictOnDate()

**Masalah:** Generator hanya mengecualikan `CANCELLED` dari conflict detection, sedangkan `RescheduleService` sudah benar menggunakan `ClassSession::statusesExcludedFromScheduleConflict()` yang juga mencakup `IZIN_RESCHEDULE` dan `IZIN_PENDING`. Inkonsistensi ini menyebabkan generator salah mendeteksi "konflik" dari slot yang sebenarnya sudah kosong.

**Files:**
- Modify: `app/Services/SessionGeneratorService.php` (baris 411–418 dan 425–432)
- Test: `tests/Feature/SessionGeneratorConflictTest.php` (tambahkan test baru)

---

- [ ] **Step 1: Tulis failing test**

Tambahkan method berikut di akhir class `SessionGeneratorConflictTest` (sebelum kurung kurawal penutup):

```php
public function test_generator_tidak_anggap_izin_reschedule_sebagai_konflik(): void
{
    $teacher = Teacher::factory()->create(['is_active' => true]);
    $room    = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
    $package = Package::factory()->create([
        'duration_min'    => 30,
        'class_type'      => 'REGULER',
        'price_per_month' => 370000,
        'is_active'       => true,
    ]);

    // Murid 1 punya sesi IZIN_RESCHEDULE di Senin 15:00 (slot kosong)
    $student1    = Student::factory()->create(['status' => 'Aktif']);
    $enrollment1 = Enrollment::factory()->create([
        'student_id' => $student1->id,
        'teacher_id' => $teacher->id,
        'package_id' => $package->id,
        'status'     => 'ACTIVE',
    ]);
    $schedule1 = Schedule::factory()->create([
        'enrollment_id' => $enrollment1->id,
        'day_of_week'   => 1,
        'start_time'    => '15:00',
        'end_time'      => '15:30',
        'room_id'       => $room->id,
        'is_active'     => true,
    ]);

    $targetMonth = Carbon::now()->addMonth()->startOfMonth();
    $senin       = $targetMonth->copy()->next('Monday');

    // Buat sesi murid 1 sudah IZIN_RESCHEDULE (slot dianggap kosong)
    ClassSession::factory()->create([
        'schedule_id'   => $schedule1->id,
        'enrollment_id' => $enrollment1->id,
        'student_id'    => $student1->id,
        'teacher_id'    => $teacher->id,
        'session_date'  => $senin->toDateString(),
        'start_time'    => '15:00:00',
        'end_time'      => '15:30:00',
        'room_id'       => $room->id,
        'status'        => 'IZIN_RESCHEDULE',
    ]);

    // Murid 2 punya jadwal yang sama (guru & ruang sama, jam sama)
    // Seharusnya berhasil di-generate karena slot murid 1 sudah kosong
    $student2    = Student::factory()->create(['status' => 'Aktif']);
    $enrollment2 = Enrollment::factory()->create([
        'student_id' => $student2->id,
        'teacher_id' => $teacher->id,
        'package_id' => $package->id,
        'status'     => 'ACTIVE',
    ]);
    Schedule::factory()->create([
        'enrollment_id' => $enrollment2->id,
        'day_of_week'   => 1,
        'start_time'    => '15:00',
        'end_time'      => '15:30',
        'room_id'       => $room->id,
        'is_active'     => true,
    ]);

    $report = app(\App\Services\SessionGeneratorService::class)
        ->generateForMonth($targetMonth->year, $targetMonth->month);

    // Murid 2 harus berhasil dapat sesi (tidak di-skip sebagai konflik)
    $this->assertEquals(0, $report['skipped_conflict'],
        'Generator seharusnya tidak menghitung IZIN_RESCHEDULE sebagai konflik');

    $this->assertTrue(
        ClassSession::where('student_id', $student2->id)
            ->whereDate('session_date', $senin->toDateString())
            ->exists(),
        'Sesi murid 2 harus ter-generate meski murid 1 punya IZIN_RESCHEDULE di slot yang sama'
    );
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test --filter="SessionGeneratorConflictTest::test_generator_tidak_anggap_izin_reschedule_sebagai_konflik"
```

Expected output: `FAILED` — `skipped_conflict` lebih dari 0, atau sesi murid 2 tidak ter-generate.

- [ ] **Step 3: Implementasi fix**

Di `app/Services/SessionGeneratorService.php`, ganti **dua** baris `->whereNotIn('status', ['CANCELLED'])` di method `findConflictOnDate()`.

Cari baris pertama (sekitar baris 417) — dalam blok teacher conflict untuk kelas non-DUO reguler:
```php
// SEBELUM:
->whereNotIn('status', ['CANCELLED'])
->first();

if ($teacherConflict) {
    return $teacherConflict;
}

if ($schedule->room_id) {
    $roomConflict = ClassSession::with('student')
        ->where('room_id', $schedule->room_id)
        ->whereDate('session_date', $date)
        ->where('start_time', '<', $schedule->end_time)
        ->where('end_time', '>', $schedule->start_time)
        ->where('schedule_id', '!=', $schedule->id)
        ->whereNotIn('status', ['CANCELLED'])
        ->first();
```

```php
// SESUDAH (ubah KEDUA-DUA whereNotIn di blok non-DUO reguler):
->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
->first();

if ($teacherConflict) {
    return $teacherConflict;
}

if ($schedule->room_id) {
    $roomConflict = ClassSession::with('student')
        ->where('room_id', $schedule->room_id)
        ->whereDate('session_date', $date)
        ->where('start_time', '<', $schedule->end_time)
        ->where('end_time', '>', $schedule->start_time)
        ->where('schedule_id', '!=', $schedule->id)
        ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
        ->first();
```

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test --filter="SessionGeneratorConflictTest::test_generator_tidak_anggap_izin_reschedule_sebagai_konflik"
```

Expected output: `PASSED`

- [ ] **Step 5: Pastikan test suite tidak rusak**

```bash
php artisan test --filter="SessionGeneratorConflictTest"
```

Expected output: semua test PASSED.

- [ ] **Step 6: Commit**

```bash
git add app/Services/SessionGeneratorService.php tests/Feature/SessionGeneratorConflictTest.php
git commit -m "Fix: Generator kecualikan IZIN_RESCHEDULE dan IZIN_PENDING dari conflict detection"
```

---

## Task 2: [T1] Fix DUO Max-2 Limit di SessionGeneratorService::findConflictOnDate()

**Masalah:** Untuk class type DUO, generator hanya mengecek apakah ada sesi non-DUO di slot tersebut, tapi tidak menghitung apakah slot sudah penuh (≥2 sesi DUO). `RescheduleService::findDuoSlotConflict()` sudah benar — generator harus konsisten.

**Files:**
- Modify: `app/Services/SessionGeneratorService.php` (baris 372–406)
- Test: `tests/Feature/SessionGeneratorConflictTest.php` (tambahkan test baru)

---

- [ ] **Step 1: Tulis failing test**

Tambahkan method berikut di `SessionGeneratorConflictTest`:

```php
public function test_generator_tidak_buat_sesi_duo_ketiga_di_slot_yang_sama(): void
{
    $teacher = Teacher::factory()->create(['is_active' => true]);
    $room    = Room::factory()->create(['capacity' => 2, 'is_active' => true]);
    $pkgDuo  = Package::factory()->create([
        'duration_min'    => 30,
        'class_type'      => 'DUO',
        'price_per_month' => 370000,
        'is_active'       => true,
    ]);

    $targetMonth = Carbon::now()->addMonth()->startOfMonth();
    $senin       = $targetMonth->copy()->next('Monday');

    // Buat 2 murid DUO yang sudah punya sesi di slot yang sama (slot penuh)
    foreach ([1, 2] as $i) {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $pkgDuo->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room->id,
            'is_active'     => true,
        ]);
        ClassSession::factory()->create([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => $senin->toDateString(),
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'room_id'       => $room->id,
            'status'        => 'SCHEDULED',
        ]);
    }

    // Murid DUO ke-3 mencoba masuk ke slot yang sama — harus di-block
    $student3    = Student::factory()->create(['status' => 'Aktif']);
    $enrollment3 = Enrollment::factory()->create([
        'student_id' => $student3->id,
        'teacher_id' => $teacher->id,
        'package_id' => $pkgDuo->id,
        'status'     => 'ACTIVE',
    ]);
    Schedule::factory()->create([
        'enrollment_id' => $enrollment3->id,
        'day_of_week'   => 1,
        'start_time'    => '15:00',
        'end_time'      => '15:30',
        'room_id'       => $room->id,
        'is_active'     => true,
    ]);

    $report = app(\App\Services\SessionGeneratorService::class)
        ->generateForMonth($targetMonth->year, $targetMonth->month);

    // Murid 3 harus di-skip sebagai konflik (slot DUO penuh)
    $this->assertGreaterThan(0, $report['skipped_conflict'],
        'Generator harus mendeteksi slot DUO penuh sebagai konflik');

    $this->assertFalse(
        ClassSession::where('student_id', $student3->id)
            ->whereDate('session_date', $senin->toDateString())
            ->exists(),
        'Sesi murid DUO ke-3 tidak boleh ter-generate karena slot sudah penuh'
    );
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test --filter="SessionGeneratorConflictTest::test_generator_tidak_buat_sesi_duo_ketiga_di_slot_yang_sama"
```

Expected output: `FAILED` — sesi murid ke-3 ter-generate.

- [ ] **Step 3: Implementasi fix**

Di `app/Services/SessionGeneratorService.php`, di method `findConflictOnDate()`, setelah blok check `$teacherConflict` untuk DUO (sekitar baris 383–387), tambahkan pengecekan jumlah DUO di slot yang sama sebelum `return null`:

```php
// SEBELUM (cuplikan akhir blok DUO, sekitar baris 388–406):
            if ($teacherConflict) {
                return $teacherConflict;
            }

            // Cek ruang: konflik hanya jika sesi lain di slot ini BUKAN DUO
            if ($schedule->room_id) {
                $roomConflict = ClassSession::where('room_id', $schedule->room_id)
                    ->whereDate('session_date', $date)
                    ->where('start_time', '<', $schedule->end_time)
                    ->where('end_time', '>', $schedule->start_time)
                    ->where('schedule_id', '!=', $schedule->id)
                    ->whereNotIn('status', ['CANCELLED'])
                    ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', '!=', 'DUO'))
                    ->first();

                if ($roomConflict) {
                    return $roomConflict;
                }
            }

            return null;
        }
```

```php
// SESUDAH — tambahkan pengecekan DUO count setelah room conflict check:
            if ($teacherConflict) {
                return $teacherConflict;
            }

            // Cek ruang: konflik hanya jika sesi lain di slot ini BUKAN DUO
            if ($schedule->room_id) {
                $roomConflict = ClassSession::where('room_id', $schedule->room_id)
                    ->whereDate('session_date', $date)
                    ->where('start_time', '<', $schedule->end_time)
                    ->where('end_time', '>', $schedule->start_time)
                    ->where('schedule_id', '!=', $schedule->id)
                    ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                    ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', '!=', 'DUO'))
                    ->first();

                if ($roomConflict) {
                    return $roomConflict;
                }
            }

            // Cek slot DUO tidak melebihi 2 murid (BR-3.11)
            $duoCount = ClassSession::where('teacher_id', $teacherId)
                ->whereDate('session_date', $date)
                ->where('start_time', '<', $schedule->end_time)
                ->where('end_time', '>', $schedule->start_time)
                ->where('schedule_id', '!=', $schedule->id)
                ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', 'DUO'))
                ->count();

            if ($duoCount >= 2) {
                // Kembalikan salah satu sesi DUO yang sudah ada sebagai referensi konflik
                return ClassSession::where('teacher_id', $teacherId)
                    ->whereDate('session_date', $date)
                    ->where('start_time', '<', $schedule->end_time)
                    ->where('end_time', '>', $schedule->start_time)
                    ->where('schedule_id', '!=', $schedule->id)
                    ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                    ->whereHas('enrollment.package', fn ($q) => $q->where('class_type', 'DUO'))
                    ->first();
            }

            return null;
        }
```

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test --filter="SessionGeneratorConflictTest::test_generator_tidak_buat_sesi_duo_ketiga_di_slot_yang_sama"
```

Expected output: `PASSED`

- [ ] **Step 5: Pastikan test DUO lainnya tidak rusak**

```bash
php artisan test --filter="DuoClass"
```

Expected output: semua test PASSED.

- [ ] **Step 6: Commit**

```bash
git add app/Services/SessionGeneratorService.php tests/Feature/SessionGeneratorConflictTest.php
git commit -m "Fix: Generator terapkan batas maksimal 2 murid DUO per slot"
```

---

## Task 3: [T2+R1] Validasi Konflik Guru dan Ruang Pengganti di AttendanceService

**Masalah:** Saat status diubah ke `DIGANTI`, `AttendanceService::validateStatusFields()` hanya mengecek bahwa guru pengganti tidak sama dengan guru asli — tidak ada pengecekan apakah guru atau ruangan pengganti sudah dipakai di waktu yang bersamaan.

**Files:**
- Modify: `app/Services/AttendanceService.php` (method `validateStatusFields`, baris 129–154)
- Test: `tests/Feature/Services/` (buat file baru)

---

- [ ] **Step 1: Tulis failing test**

Buat file baru `tests/Feature/Services/AttendanceServiceDigantiConflictTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AttendanceServiceDigantiConflictTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(Teacher $teacher, Room $room, string $date, string $start, string $end, string $status = 'SCHEDULED'): ClassSession
    {
        $package    = Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30, 'price_per_month' => 370000, 'is_active' => true]);
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        return ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => $date,
            'start_time'    => $start,
            'end_time'      => $end,
            'status'        => $status,
        ]);
    }

    public function test_diganti_diblokir_jika_guru_pengganti_sudah_ada_sesi_bersamaan(): void
    {
        $guruAsli     = Teacher::factory()->create(['is_active' => true]);
        $guruPengganti = Teacher::factory()->create(['is_active' => true]);
        $room         = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

        // Sesi yang akan di-DIGANTI
        $sesiTarget = $this->makeSession($guruAsli, $room, '2026-07-01', '15:00:00', '15:30:00', 'SCHEDULED');

        // Guru pengganti sudah punya sesi lain di jam yang sama
        $this->makeSession($guruPengganti, $room, '2026-07-01', '15:00:00', '15:30:00', 'SCHEDULED');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sudah memiliki sesi lain/i');

        app(AttendanceService::class)->recordAttendance($sesiTarget, [
            'status'                => 'DIGANTI',
            'substitute_teacher_id' => $guruPengganti->id,
            '__session'             => $sesiTarget,
        ]);
    }

    public function test_diganti_diblokir_jika_ruangan_pengganti_sudah_dipakai(): void
    {
        $guruAsli     = Teacher::factory()->create(['is_active' => true]);
        $guruPengganti = Teacher::factory()->create(['is_active' => true]);
        $roomAsli     = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $roomPengganti = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

        $sesiTarget = $this->makeSession($guruAsli, $roomAsli, '2026-07-01', '15:00:00', '15:30:00', 'SCHEDULED');

        // Ruangan pengganti sudah dipakai di jam yang sama oleh sesi lain
        $package2    = Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30, 'price_per_month' => 370000, 'is_active' => true]);
        $student2    = Student::factory()->create(['status' => 'Aktif']);
        $guruLain    = Teacher::factory()->create(['is_active' => true]);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id,
            'teacher_id' => $guruLain->id,
            'package_id' => $package2->id,
            'status'     => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'student_id'    => $student2->id,
            'teacher_id'    => $guruLain->id,
            'room_id'       => $roomPengganti->id,
            'session_date'  => '2026-07-01',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => 'SCHEDULED',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ruangan pengganti/i');

        app(AttendanceService::class)->recordAttendance($sesiTarget, [
            'status'                => 'DIGANTI',
            'substitute_teacher_id' => $guruPengganti->id,
            'substitute_room_id'    => $roomPengganti->id,
            '__session'             => $sesiTarget,
        ]);
    }

    public function test_diganti_berhasil_jika_guru_dan_ruang_pengganti_bebas(): void
    {
        $guruAsli     = Teacher::factory()->create(['is_active' => true]);
        $guruPengganti = Teacher::factory()->create(['is_active' => true]);
        $room         = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

        $sesiTarget = $this->makeSession($guruAsli, $room, '2026-07-01', '15:00:00', '15:30:00', 'SCHEDULED');

        // Tidak ada sesi lain yang konflik — harus berhasil
        $result = app(AttendanceService::class)->recordAttendance($sesiTarget, [
            'status'                => 'DIGANTI',
            'substitute_teacher_id' => $guruPengganti->id,
            '__session'             => $sesiTarget,
        ]);

        $this->assertEquals('DIGANTI', $result->status);
        $this->assertEquals($guruPengganti->id, $result->substitute_teacher_id);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test --filter="AttendanceServiceDigantiConflictTest"
```

Expected output: `test_diganti_diblokir_jika_guru_pengganti_sudah_ada_sesi_bersamaan` dan `test_diganti_diblokir_jika_ruangan_pengganti_sudah_dipakai` FAILED. `test_diganti_berhasil_...` PASSED.

- [ ] **Step 3: Implementasi fix**

Di `app/Services/AttendanceService.php`, di method `validateStatusFields()`, tambahkan blok konflik check setelah validasi "tidak boleh sama dengan guru asli" (setelah baris 153):

```php
    private function validateStatusFields(string $status, array $data): void
    {
        if ($status === 'HADIR_TERLAMBAT') {
            // ... kode existing tidak berubah
        }

        if ($status === 'DIGANTI') {
            if (empty($data['substitute_teacher_id'])) {
                throw new InvalidArgumentException(
                    'Status DIGANTI wajib disertai substitute_teacher_id.'
                );
            }
            // Guru pengganti tidak boleh sama dengan guru asli
            $session = $data['__session'] ?? null;
            if ($session instanceof ClassSession
                && (int) $data['substitute_teacher_id'] === (int) $session->teacher_id) {
                throw new InvalidArgumentException(
                    'Guru pengganti tidak boleh sama dengan guru asli.'
                );
            }

            // === TAMBAHAN: Cek konflik waktu guru pengganti ===
            if ($session instanceof ClassSession) {
                // Gunakan jam pengganti jika disediakan, jika tidak gunakan jam sesi asli
                $effectiveStart = !empty($data['substitute_start_time'])
                    ? $data['substitute_start_time'] . ':00'
                    : $session->start_time;
                $effectiveEnd = !empty($data['substitute_end_time'])
                    ? $data['substitute_end_time'] . ':00'
                    : $session->end_time;

                $guruConflict = ClassSession::where(function ($q) use ($data) {
                        $q->where('teacher_id', $data['substitute_teacher_id'])
                          ->orWhere('substitute_teacher_id', $data['substitute_teacher_id']);
                    })
                    ->whereDate('session_date', $session->session_date)
                    ->where('start_time', '<', $effectiveEnd)
                    ->where('end_time', '>', $effectiveStart)
                    ->whereNotIn('status', self::VALID_ATTENDANCE_STATUSES_EXCLUDED_CONFLICT)
                    ->where('id', '!=', $session->id)
                    ->exists();

                if ($guruConflict) {
                    throw new InvalidArgumentException(
                        'Guru pengganti sudah memiliki sesi lain di waktu tersebut.'
                    );
                }

                // === TAMBAHAN: Cek konflik ruangan pengganti (jika substitute_room_id diisi) ===
                if (!empty($data['substitute_room_id'])) {
                    $roomConflict = ClassSession::where('room_id', (int) $data['substitute_room_id'])
                        ->whereDate('session_date', $session->session_date)
                        ->where('start_time', '<', $effectiveEnd)
                        ->where('end_time', '>', $effectiveStart)
                        ->whereNotIn('status', self::VALID_ATTENDANCE_STATUSES_EXCLUDED_CONFLICT)
                        ->where('id', '!=', $session->id)
                        ->exists();

                    if ($roomConflict) {
                        throw new InvalidArgumentException(
                            'Ruangan pengganti sudah dipakai di waktu tersebut.'
                        );
                    }
                }
            }
        }
    }
```

Tambahkan juga konstanta private di bagian atas class `AttendanceService` (setelah konstanta `KIDS_HONOR_PER_STUDENT`):

```php
    /**
     * Status yang tidak "memblok" slot saat cek konflik guru/ruang pengganti.
     * Sama dengan ClassSession::statusesExcludedFromScheduleConflict() tapi
     * didefinisikan di sini agar tidak perlu import model di service.
     */
    private const VALID_ATTENDANCE_STATUSES_EXCLUDED_CONFLICT = [
        'CANCELLED',
        'IZIN_RESCHEDULE',
        'IZIN_PENDING',
    ];
```

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test --filter="AttendanceServiceDigantiConflictTest"
```

Expected output: semua 3 test PASSED.

- [ ] **Step 5: Pastikan test absensi lainnya tidak rusak**

```bash
php artisan test --filter="GuruUpdateAbsensiTest"
```

Expected output: PASSED.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AttendanceService.php tests/Feature/Services/AttendanceServiceDigantiConflictTest.php
git commit -m "Fix: Validasi konflik guru dan ruangan pengganti saat status DIGANTI"
```

---

## Task 4: [S1] Validasi Konflik Jadwal Murid yang Sama di ScheduleController

**Masalah:** `ScheduleController::store()` dan `update()` mengecek konflik guru dan ruangan, tapi tidak mengecek apakah **murid yang sama** sudah memiliki jadwal aktif di hari dan jam yang overlap (skenario multi-enrollment double-booking).

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php` (method `store` baris 28–154 dan `update` baris 156–272)
- Test: `tests/Feature/ScheduleMultiEnrollmentTest.php` (tambahkan test baru)

---

- [ ] **Step 1: Tulis failing test**

Tambahkan method berikut di `ScheduleMultiEnrollmentTest`:

```php
public function test_store_menolak_jadwal_jika_murid_sudah_punya_jadwal_aktif_di_jam_yang_sama(): void
{
    [$student, $enrollPiano, $enrollGitar, $teacher] = $this->makeStudentWithTwoEnrollments();

    $room1 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
    $room2 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

    // Buat jadwal Piano di Senin 15:00–15:30
    Schedule::factory()->create([
        'enrollment_id' => $enrollPiano->id,
        'day_of_week'   => 1,
        'start_time'    => '15:00',
        'end_time'      => '15:30',
        'room_id'       => $room1->id,
        'is_active'     => true,
    ]);

    // Coba tambah jadwal Gitar di Senin 15:10–15:40 (overlap dengan Piano)
    $teacherGitar = Teacher::factory()->create(['is_active' => true]);
    $enrollGitar->update(['teacher_id' => $teacherGitar->id]);

    $response = $this->actingAs($this->admin)
        ->post(route('schedules.store', $student), [
            'enrollment_id' => $enrollGitar->id,
            'day_of_week'   => 1,
            'start_time'    => '15:10',
            'end_time'      => '15:40',
            'room_id'       => $room2->id,
        ]);

    $response->assertSessionHasErrors();
    $this->assertStringContainsString(
        'bentrok',
        session('error') ?? implode(' ', session('errors')?->all() ?? [])
    );
}

public function test_store_mengizinkan_jadwal_jika_murid_punya_jadwal_di_hari_berbeda(): void
{
    [$student, $enrollPiano, $enrollGitar, $teacher] = $this->makeStudentWithTwoEnrollments();

    $room1 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
    $room2 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

    Schedule::factory()->create([
        'enrollment_id' => $enrollPiano->id,
        'day_of_week'   => 1, // Senin
        'start_time'    => '15:00',
        'end_time'      => '15:30',
        'room_id'       => $room1->id,
        'is_active'     => true,
    ]);

    $teacherGitar = Teacher::factory()->create(['is_active' => true]);
    $enrollGitar->update(['teacher_id' => $teacherGitar->id]);

    // Rabu (hari berbeda) — harus diizinkan
    $response = $this->actingAs($this->admin)
        ->post(route('schedules.store', $student), [
            'enrollment_id' => $enrollGitar->id,
            'day_of_week'   => 3, // Rabu
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room2->id,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionMissing('error');
}
```

- [ ] **Step 2: Jalankan test — pastikan test pertama FAIL**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest::test_store_menolak_jadwal_jika_murid_sudah_punya_jadwal_aktif_di_jam_yang_sama"
```

Expected output: `FAILED`

- [ ] **Step 3: Implementasi fix — tambahkan student self-conflict check di store()**

Di `app/Http/Controllers/ScheduleController.php`, di method `store()`, tambahkan blok berikut **setelah** validasi instrumen ruangan (setelah baris ~141) dan **sebelum** `Schedule::create(...)`:

```php
        // Validasi: murid tidak boleh punya jadwal aktif lain di hari + jam yang overlap
        // (berlaku untuk multi-enrollment — Piano dan Gitar di waktu yang sama tidak masuk akal)
        $studentId        = $enrollment->student_id;
        $studentConflicts = Schedule::query()
            ->active()
            ->whereHas('enrollment', function ($q) use ($studentId) {
                $q->active()->where('student_id', $studentId);
            })
            ->where('day_of_week', $data['day_of_week'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->get();

        if ($studentConflicts->isNotEmpty()) {
            $namaKelas = $studentConflicts->map(function ($s) {
                $pkg = $s->enrollment?->package;
                return ($pkg?->instrument?->name ?? '?') . ' ' . ($s->start_time ? substr($s->start_time, 0, 5) : '');
            })->implode(', ');
            return back()->withInput()->with('error',
                "Murid sudah memiliki jadwal aktif di slot yang sama: {$namaKelas}.");
        }
```

- [ ] **Step 4: Terapkan hal yang sama di method update()**

Di method `update()`, tambahkan blok yang sama sebelum `$schedule->update($data)`, dengan tambahan `excludeScheduleId` agar schedule yang sedang diedit tidak menghitung dirinya sendiri:

```php
        // Validasi: murid tidak boleh punya jadwal aktif lain di hari + jam yang overlap
        $studentId        = $schedule->enrollment->student_id;
        $studentConflicts = Schedule::query()
            ->active()
            ->whereHas('enrollment', function ($q) use ($studentId) {
                $q->active()->where('student_id', $studentId);
            })
            ->where('day_of_week', $data['day_of_week'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->where('id', '!=', $schedule->id) // kecualikan diri sendiri
            ->get();

        if ($studentConflicts->isNotEmpty()) {
            $namaKelas = $studentConflicts->map(function ($s) {
                $pkg = $s->enrollment?->package;
                return ($pkg?->instrument?->name ?? '?') . ' ' . ($s->start_time ? substr($s->start_time, 0, 5) : '');
            })->implode(', ');
            return back()->withInput()->with('error',
                "Murid sudah memiliki jadwal aktif di slot yang sama: {$namaKelas}.");
        }
```

- [ ] **Step 5: Jalankan test — pastikan PASS**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest::test_store_menolak_jadwal_jika_murid_sudah_punya_jadwal_aktif_di_jam_yang_sama"
php artisan test --filter="ScheduleMultiEnrollmentTest::test_store_mengizinkan_jadwal_jika_murid_punya_jadwal_di_hari_berbeda"
```

Expected output: keduanya PASSED.

- [ ] **Step 6: Pastikan semua test schedule tidak rusak**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest"
```

Expected output: semua PASSED.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php tests/Feature/ScheduleMultiEnrollmentTest.php
git commit -m "Fix: Validasi konflik jadwal murid yang sama di ScheduleController (multi-enrollment)"
```

---

## Task 5: [RS2] Tambah Class_Sessions Check di ScheduleController untuk Manual/Replacement Sessions

**Masalah:** `ScheduleConflictDetector` hanya mengquery tabel `schedules` (jadwal mingguan). Jadwal mingguan baru yang dibuat bisa bertabrakan dengan **manual sessions** atau **replacement sessions** yang ada di `class_sessions` (tidak punya entri di `schedules`).

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php` (method `store`, setelah validasi student conflict)
- Test: `tests/Feature/ScheduleMultiEnrollmentTest.php` (tambahkan test baru)

---

- [ ] **Step 1: Tulis failing test**

Tambahkan method berikut di `ScheduleMultiEnrollmentTest`:

```php
public function test_store_memperingatkan_jika_ada_manual_session_di_slot_yang_sama(): void
{
    [$student, $enrollPiano, , $teacher] = $this->makeStudentWithTwoEnrollments();

    $room = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

    // Manual session yang sudah ada (schedule_id = null) untuk guru ini di Senin 15:00
    $package    = $enrollPiano->package;
    $studentLain = \App\Models\Student::factory()->create(['status' => 'Aktif']);
    $enrollLain  = \App\Models\Enrollment::factory()->create([
        'student_id' => $studentLain->id,
        'teacher_id' => $teacher->id,
        'package_id' => $package->id,
        'status'     => 'ACTIVE',
    ]);
    \App\Models\ClassSession::factory()->create([
        'schedule_id'   => null, // manual session
        'enrollment_id' => $enrollLain->id,
        'student_id'    => $studentLain->id,
        'teacher_id'    => $teacher->id,
        'room_id'       => $room->id,
        'session_date'  => now()->next('Monday')->toDateString(),
        'start_time'    => '15:00:00',
        'end_time'      => '15:30:00',
        'status'        => 'SCHEDULED',
    ]);

    // Buat jadwal mingguan baru untuk guru yang sama di Senin 15:00
    // Conflict detector hanya cek schedules table — manual session di atas tidak terdeteksi
    // Test ini memverifikasi bahwa class_sessions juga dicek (peringatan diberikan)
    $response = $this->actingAs($this->admin)
        ->post(route('schedules.store', $student), [
            'enrollment_id' => $enrollPiano->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room->id,
        ]);

    // Harusnya ada warning (flash message) tentang potential conflict dengan manual session
    // Bisa berupa error atau warning — implementasi memilih pendekatan yang sesuai
    $this->assertTrue(
        session()->has('error') || session()->has('warning'),
        'Controller harus memberikan peringatan saat ada manual session di slot yang sama'
    );
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest::test_store_memperingatkan_jika_ada_manual_session_di_slot_yang_sama"
```

Expected output: `FAILED` — tidak ada warning/error.

- [ ] **Step 3: Implementasi fix — secondary check di store()**

Di `app/Http/Controllers/ScheduleController.php`, di method `store()`, tambahkan blok berikut setelah `Schedule::create(...)` (sesi sudah disimpan, tapi tambahkan flash warning jika ada conflict dengan class_sessions):

```php
        Schedule::create([
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => $data['day_of_week'],
            'start_time'    => $data['start_time'],
            'end_time'      => $data['end_time'],
            'room_id'       => $data['room_id'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'is_active'     => true,
        ]);

        // Secondary check: cek class_sessions konkret untuk potensi konflik dengan
        // manual/replacement sessions di 30 hari ke depan. Hanya peringatan (warning),
        // bukan error — jadwal tetap tersimpan karena ini adalah template mingguan.
        $upcomingConflict = \App\Models\ClassSession::where('teacher_id', $enrollment->teacher_id)
            ->where('session_date', '>=', today()->toDateString())
            ->where('session_date', '<=', today()->addDays(30)->toDateString())
            ->whereNull('schedule_id')  // hanya manual/replacement sessions
            ->where('start_time', '<', $data['end_time'] . ':00')
            ->where('end_time', '>', $data['start_time'] . ':00')
            ->whereRaw('DAYOFWEEK(session_date) - 1 = ?', [$data['day_of_week']])
            ->whereNotIn('status', ['CANCELLED', 'IZIN_RESCHEDULE', 'IZIN_PENDING'])
            ->exists();

        if ($upcomingConflict) {
            return back()->with('warning',
                'Jadwal berhasil ditambahkan, namun terdeteksi potensi bentrok dengan sesi manual/pengganti yang sudah ada di 30 hari ke depan. Cek kalender untuk konfirmasi.');
        }

        return back()->with('success', 'Jadwal mingguan berhasil ditambahkan.');
```

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest::test_store_memperingatkan_jika_ada_manual_session_di_slot_yang_sama"
```

Expected output: `PASSED`

- [ ] **Step 5: Pastikan test schedule lainnya tidak rusak**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest"
```

Expected output: semua PASSED. (Catatan: test yang sebelumnya `assertSessionMissing('error')` masih valid karena ini adalah `warning`, bukan `error`.)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php tests/Feature/ScheduleMultiEnrollmentTest.php
git commit -m "Fix: Tambah secondary check class_sessions konkret saat buat jadwal mingguan baru"
```

---

## Task 6: [RE2] Guard Transisi IZIN_RESCHEDULE → IZIN_PENDING di AbsensiController

**Masalah:** Tidak ada guard yang mencegah admin mengubah status sesi dari `IZIN_RESCHEDULE` (yang sudah memiliki sesi pengganti) ke `IZIN_PENDING`. Hal ini membuat sesi pengganti yang sudah dibuat menjadi orphan.

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php` (method `update`, baris 103–196)
- Test: `tests/Feature/IzinPendingTest.php` (tambahkan test baru)

---

- [ ] **Step 1: Tulis failing test**

Tambahkan method berikut di `IzinPendingTest`:

```php
public function test_update_menolak_ubah_izin_reschedule_ke_izin_pending_jika_sudah_ada_sesi_pengganti(): void
{
    // Setup murid + enrollment + sesi asli
    $teacher    = Teacher::factory()->create(['is_active' => true]);
    $room       = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
    $package    = Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30, 'price_per_month' => 370000, 'is_active' => true]);
    $student    = Student::factory()->create(['status' => 'Aktif']);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'package_id' => $package->id,
        'status'     => 'ACTIVE',
    ]);

    // Sesi asli sudah IZIN_RESCHEDULE
    $sesiAsli = ClassSession::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id'    => $student->id,
        'teacher_id'    => $teacher->id,
        'room_id'       => $room->id,
        'session_date'  => '2026-07-07',
        'start_time'    => '15:00:00',
        'end_time'      => '15:30:00',
        'status'        => 'IZIN_RESCHEDULE',
        'honor_code'    => null,
        'honor_amount'  => 0,
    ]);

    // Sesi pengganti sudah ada
    ClassSession::factory()->create([
        'enrollment_id'     => $enrollment->id,
        'student_id'        => $student->id,
        'teacher_id'        => $teacher->id,
        'room_id'           => $room->id,
        'session_date'      => '2026-07-14',
        'start_time'        => '15:00:00',
        'end_time'          => '15:30:00',
        'status'            => 'SCHEDULED',
        'origin_session_id' => $sesiAsli->id,
        'split_part'        => null,
    ]);

    // Coba ubah sesi asli dari IZIN_RESCHEDULE → IZIN_PENDING — harus ditolak
    $response = $this->actingAs($this->admin)
        ->patchJson(route('absensi.update', $sesiAsli), [
            'status' => 'IZIN_PENDING',
        ]);

    $response->assertStatus(422);
    $response->assertJson(['success' => false]);
    $this->assertStringContainsString(
        'pengganti',
        $response->json('message')
    );

    // Status sesi asli tidak berubah
    $this->assertEquals('IZIN_RESCHEDULE', $sesiAsli->fresh()->status);
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test --filter="IzinPendingTest::test_update_menolak_ubah_izin_reschedule_ke_izin_pending_jika_sudah_ada_sesi_pengganti"
```

Expected output: `FAILED` — response 200/bukan 422, status berubah.

- [ ] **Step 3: Implementasi fix**

Di `app/Http/Controllers/AbsensiController.php`, di method `update()`, tambahkan guard baru **setelah** guard `STATUS_LIBUR` (setelah baris 110), **sebelum** guard `statusSudahHadir`:

```php
        // Guard: sesi IZIN_RESCHEDULE yang sudah punya pengganti tidak bisa diubah ke status lain
        // kecuali CANCELLED. Mencegah orphan replacement session.
        if ($classSession->status === ClassSession::STATUS_IZIN_RESCHEDULE) {
            $hasReplacement = ClassSession::where('origin_session_id', $classSession->id)
                ->whereNull('split_part')
                ->exists();
            if ($hasReplacement && $request->status !== ClassSession::STATUS_CANCELLED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi ini sudah memiliki sesi pengganti. Batalkan sesi (CANCELLED) jika perlu mengubah status.',
                ], 422);
            }
        }
```

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test --filter="IzinPendingTest::test_update_menolak_ubah_izin_reschedule_ke_izin_pending_jika_sudah_ada_sesi_pengganti"
```

Expected output: `PASSED`

- [ ] **Step 5: Pastikan test IzinPending lainnya tidak rusak**

```bash
php artisan test --filter="IzinPendingTest"
```

Expected output: semua PASSED.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php tests/Feature/IzinPendingTest.php
git commit -m "Fix: Guard transisi IZIN_RESCHEDULE ke status lain jika sudah ada sesi pengganti"
```

---

## Task 7: [RE1] Perbaiki scheduleReplacement() Lewat AttendanceService

**Masalah:** `AbsensiController::scheduleReplacement()` mengubah status sesi ke `IZIN_RESCHEDULE` via direct `$session->update(['status' => ...])` — bypass `AttendanceService`. Ini berarti `honor_code` dan `honor_amount` tidak di-reset melalui logika terpusat, sehingga bisa ada nilai stale jika sesi pernah di-set ke status lain sebelumnya.

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php` (method `scheduleReplacement`, baris 341–367)
- Test: `tests/Feature/IzinPendingTest.php` (tambahkan test baru)

---

- [ ] **Step 1: Tulis failing test**

Tambahkan method berikut di `IzinPendingTest`:

```php
public function test_schedule_replacement_mereset_honor_ke_nol_via_attendance_service(): void
{
    $teacher    = Teacher::factory()->create(['is_active' => true]);
    $room       = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
    $package    = Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30, 'price_per_month' => 370000, 'is_active' => true]);
    $student    = Student::factory()->create(['status' => 'Aktif']);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'package_id' => $package->id,
        'status'     => 'ACTIVE',
    ]);

    // Sesi IZIN_PENDING dengan honor_code stale dari status sebelumnya
    $sesi = ClassSession::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id'    => $student->id,
        'teacher_id'    => $teacher->id,
        'room_id'       => $room->id,
        'session_date'  => '2026-07-07',
        'start_time'    => '15:00:00',
        'end_time'      => '15:30:00',
        'status'        => 'IZIN_PENDING',
        'honor_code'    => 'H_IZIN',
        'honor_amount'  => 0,
    ]);

    $room2 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('absensi.open-slots.schedule', $sesi), [
            'replacement_date' => '2026-07-14',
            'replacement_time' => '15:00',
            'room_id'          => $room2->id,
        ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    $sesi->refresh();

    // Status harus berubah ke IZIN_RESCHEDULE
    $this->assertEquals('IZIN_RESCHEDULE', $sesi->status);

    // honor_code harus null (di-reset oleh AttendanceService, bukan H_IZIN dari IZIN_PENDING)
    $this->assertNull($sesi->honor_code,
        'honor_code harus null setelah IZIN_RESCHEDULE via AttendanceService');
    $this->assertEquals(0, $sesi->honor_amount);
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test --filter="IzinPendingTest::test_schedule_replacement_mereset_honor_ke_nol_via_attendance_service"
```

Expected output: `FAILED` — `honor_code` masih `H_IZIN` (bukan null).

- [ ] **Step 3: Implementasi fix**

Di `app/Http/Controllers/AbsensiController.php`, di method `scheduleReplacement()`, ganti baris direct update menjadi panggilan AttendanceService:

```php
    // SEBELUM (sekitar baris 357–362):
            $this->rescheduleService->createReplacement(
                $session,
                $request->replacement_date,
                $request->replacement_time,
                $request->room_id,
            );
            // Tandai sesi asli sebagai IZIN_RESCHEDULE — pengganti sudah dijadwalkan
            $session->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);
```

```php
    // SESUDAH:
            $this->rescheduleService->createReplacement(
                $session,
                $request->replacement_date,
                $request->replacement_time,
                $request->room_id,
            );
            // Tandai sesi asli sebagai IZIN_RESCHEDULE via AttendanceService
            // agar honor_code dan honor_amount di-set secara konsisten (null/0)
            $this->attendanceService->recordAttendance($session, [
                'status'    => ClassSession::STATUS_IZIN_RESCHEDULE,
                'notes'     => $session->notes,
                '__session' => $session,
            ]);
```

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test --filter="IzinPendingTest::test_schedule_replacement_mereset_honor_ke_nol_via_attendance_service"
```

Expected output: `PASSED`

- [ ] **Step 5: Pastikan test IzinPending dan task sebelumnya tidak rusak**

```bash
php artisan test --filter="IzinPendingTest"
```

Expected output: semua PASSED.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php tests/Feature/IzinPendingTest.php
git commit -m "Fix: scheduleReplacement() gunakan AttendanceService untuk set status IZIN_RESCHEDULE"
```

---

## Task 8: [RS1] Sinkronisasi Sesi SCHEDULED Saat Update Jadwal Mingguan

**Masalah:** `ScheduleController::update()` hanya mengupdate template di tabel `schedules`. Sesi `SCHEDULED` yang sudah ter-generate dari jadwal ini **tidak diupdate**, sehingga terjadi inkonsistensi antara jadwal template dan sesi nyata. Murid/guru melihat jam berbeda antara "Jadwal Mingguan" dan kalender absensi.

**Cakupan sync:** `start_time`, `end_time`, `room_id` — hanya untuk sesi mendatang (`session_date >= hari ini`) dengan status `SCHEDULED`. Perubahan `day_of_week` **tidak** di-sync otomatis (terlalu kompleks, ditandai sebagai warning ke admin).

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php` (method `update`, baris 270–272)
- Test: `tests/Feature/ScheduleMultiEnrollmentTest.php` (tambahkan test baru)

---

- [ ] **Step 1: Tulis failing test**

Tambahkan method berikut di `ScheduleMultiEnrollmentTest`:

```php
public function test_update_jadwal_menyinkronisasi_sesi_scheduled_mendatang(): void
{
    [$student, $enrollPiano, , $teacher] = $this->makeStudentWithTwoEnrollments();

    $room1 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
    $room2 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

    $schedule = Schedule::factory()->create([
        'enrollment_id' => $enrollPiano->id,
        'day_of_week'   => 1,
        'start_time'    => '15:00',
        'end_time'      => '15:30',
        'room_id'       => $room1->id,
        'is_active'     => true,
    ]);

    // Buat 2 sesi SCHEDULED mendatang dari jadwal ini
    $sesi1 = \App\Models\ClassSession::factory()->create([
        'schedule_id'   => $schedule->id,
        'enrollment_id' => $enrollPiano->id,
        'student_id'    => $student->id,
        'teacher_id'    => $teacher->id,
        'room_id'       => $room1->id,
        'session_date'  => now()->addWeek()->toDateString(),
        'start_time'    => '15:00:00',
        'end_time'      => '15:30:00',
        'status'        => 'SCHEDULED',
    ]);
    $sesi2 = \App\Models\ClassSession::factory()->create([
        'schedule_id'   => $schedule->id,
        'enrollment_id' => $enrollPiano->id,
        'student_id'    => $student->id,
        'teacher_id'    => $teacher->id,
        'room_id'       => $room1->id,
        'session_date'  => now()->addWeeks(2)->toDateString(),
        'start_time'    => '15:00:00',
        'end_time'      => '15:30:00',
        'status'        => 'SCHEDULED',
    ]);

    // Update jadwal: jam berubah dari 15:00 ke 16:00
    $teacherLain = Teacher::factory()->create(['is_active' => true]);
    $response = $this->actingAs($this->admin)
        ->patch(route('schedules.update', [$student, $schedule]), [
            'day_of_week' => 1,
            'start_time'  => '16:00',
            'end_time'    => '16:30',
            'room_id'     => $room2->id,
        ]);

    $response->assertSessionHasNoErrors();

    // Sesi SCHEDULED mendatang harus ter-update jam dan ruangannya
    $this->assertEquals('16:00:00', $sesi1->fresh()->start_time);
    $this->assertEquals('16:30:00', $sesi1->fresh()->end_time);
    $this->assertEquals($room2->id, $sesi1->fresh()->room_id);

    $this->assertEquals('16:00:00', $sesi2->fresh()->start_time);
    $this->assertEquals('16:30:00', $sesi2->fresh()->end_time);
    $this->assertEquals($room2->id, $sesi2->fresh()->room_id);
}

public function test_update_jadwal_tidak_mengubah_sesi_yang_sudah_hadir(): void
{
    [$student, $enrollPiano, , $teacher] = $this->makeStudentWithTwoEnrollments();

    $room1 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
    $room2 = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

    $schedule = Schedule::factory()->create([
        'enrollment_id' => $enrollPiano->id,
        'day_of_week'   => 1,
        'start_time'    => '15:00',
        'end_time'      => '15:30',
        'room_id'       => $room1->id,
        'is_active'     => true,
    ]);

    // Sesi yang sudah HADIR (tidak boleh diubah)
    $sesiHadir = \App\Models\ClassSession::factory()->create([
        'schedule_id'   => $schedule->id,
        'enrollment_id' => $enrollPiano->id,
        'student_id'    => $student->id,
        'teacher_id'    => $teacher->id,
        'room_id'       => $room1->id,
        'session_date'  => now()->subWeek()->toDateString(),
        'start_time'    => '15:00:00',
        'end_time'      => '15:30:00',
        'status'        => 'HADIR',
        'honor_amount'  => 46250,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('schedules.update', [$student, $schedule]), [
            'day_of_week' => 1,
            'start_time'  => '16:00',
            'end_time'    => '16:30',
            'room_id'     => $room2->id,
        ]);

    // Sesi HADIR tidak berubah
    $this->assertEquals('15:00:00', $sesiHadir->fresh()->start_time,
        'Sesi yang sudah HADIR tidak boleh diubah saat update jadwal');
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest::test_update_jadwal_menyinkronisasi_sesi_scheduled_mendatang"
```

Expected output: `FAILED` — jam sesi tidak berubah.

- [ ] **Step 3: Implementasi fix**

Di `app/Http/Controllers/ScheduleController.php`, di method `update()`, ganti baris `$schedule->update($data);` dengan blok berikut:

```php
        $schedule->update($data);

        // Sinkronisasi sesi SCHEDULED mendatang jika start_time/end_time/room_id berubah.
        // Hanya sesi SCHEDULED (belum dihadiri) mulai hari ini ke depan yang diupdate.
        // Perubahan day_of_week tidak di-sync otomatis karena perlu mengubah session_date —
        // admin perlu cek kalender secara manual jika hari berubah.
        $fieldsChanged = $schedule->wasChanged(['start_time', 'end_time', 'room_id']);
        $dayChanged    = $schedule->wasChanged('day_of_week');

        if ($fieldsChanged) {
            \App\Models\ClassSession::where('schedule_id', $schedule->id)
                ->where('status', \App\Models\ClassSession::STATUS_SCHEDULED)
                ->where('session_date', '>=', today()->toDateString())
                ->update([
                    'start_time' => $schedule->start_time,
                    'end_time'   => $schedule->end_time,
                    'room_id'    => $schedule->room_id,
                ]);
        }

        if ($dayChanged) {
            return back()->with('warning',
                'Jadwal mingguan diperbarui. Perubahan HARI tidak diaplikasikan ke sesi yang sudah ada — cek kalender dan sesuaikan sesi bulan berjalan secara manual.');
        }

        return back()->with('success', 'Jadwal mingguan berhasil diperbarui.');
```

> **Catatan:** Hapus baris `return back()->with('success', ...)` yang sebelumnya ada di akhir method `update()` karena sudah diganti oleh blok di atas.

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest::test_update_jadwal_menyinkronisasi_sesi_scheduled_mendatang"
php artisan test --filter="ScheduleMultiEnrollmentTest::test_update_jadwal_tidak_mengubah_sesi_yang_sudah_hadir"
```

Expected output: keduanya PASSED.

- [ ] **Step 5: Pastikan semua test schedule tidak rusak**

```bash
php artisan test --filter="ScheduleMultiEnrollmentTest"
php artisan test --filter="SessionEditTest"
```

Expected output: semua PASSED.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php tests/Feature/ScheduleMultiEnrollmentTest.php
git commit -m "Fix: Sinkronisasi sesi SCHEDULED mendatang saat update jadwal mingguan (RS1)"
```

---

## Final Verification

Setelah semua task selesai, jalankan full test suite untuk memastikan tidak ada regresi:

- [ ] **Jalankan semua test**

```bash
php artisan test
```

Expected output: semua test PASSED, termasuk test baru dari plan ini.

- [ ] **Review perubahan**

```bash
git log --oneline -8
```

Expected: 8 commit baru terlihat dengan prefix `Fix:`.

---

## Self-Review

**Spec coverage check:**
- SG1 ✓ Task 1
- T1 ✓ Task 2
- T2+R1 ✓ Task 3
- S1 ✓ Task 4
- RS2 ✓ Task 5
- RE2 ✓ Task 6
- RE1 ✓ Task 7
- RS1 ✓ Task 8

**Placeholder scan:** Tidak ada TBD, TODO, atau kode yang tidak lengkap.

**Type consistency:** Semua referensi ke `ClassSession::STATUS_*`, `Enrollment::STATUS_ACTIVE`, dan method `recordAttendance()` konsisten dengan definisi di model/service yang ada.

**Dependency order:** Task 1–2 (generator) → Task 3 (attendance) → Task 4–5 (schedule controller) → Task 6–7 (absensi controller) → Task 8 (sync). Setiap task independen dan bisa di-rollback tersendiri.
