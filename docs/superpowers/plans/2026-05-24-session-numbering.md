# Session Numbering Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambahkan penomoran urutan sesi per murid per bulan (`session_sequence`) dan referensi sesi asal saat reschedule (`origin_session_id`) sehingga admin dapat dengan mudah melacak "ini sesi ke berapa bulan ini".

**Architecture:** Dua kolom baru di `class_sessions` — `session_sequence` (tinyint, nullable) diisi saat generator membuat sesi, `origin_session_id` (FK self-ref, nullable) menghubungkan sesi reschedule/pengganti ke sesi asalnya. Label diformat via method `getSessionLabel()` di model, ditampilkan di 3 halaman.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL, Blade + Tailwind CSS + Alpine.js

---

## File Map

| File | Aksi |
|------|------|
| `database/migrations/2026_05_24_000001_add_session_sequence_to_class_sessions.php` | **CREATE** — 2 kolom baru |
| `app/Models/ClassSession.php` | **MODIFY** — fillable, casts, relasi, `getSessionLabel()` |
| `app/Services/SessionGeneratorService.php` | **MODIFY** — `slotCounter`, `replacementQueue` upgrade |
| `app/Services/RescheduleService.php` | **MODIFY** — copy sequence + origin ke replacement |
| `app/Http/Controllers/AbsensiController.php` | **MODIFY** — tambah eager-load `originSession` |
| `app/Http/Controllers/SessionController.php` | **MODIFY** — tambah eager-load `originSession` |
| `app/Http/Controllers/StudentController.php` | **MODIFY** — tambah eager-load `originSession` |
| `resources/views/absensi/_row.blade.php` | **MODIFY** — label di bawah nama murid |
| `resources/views/sessions/index.blade.php` | **MODIFY** — kolom "Label Sesi" |
| `resources/views/students/show.blade.php` | **MODIFY** — kolom "Label" di tabel Sesi Mendatang |
| `tests/Feature/Services/SessionGeneratorSequenceTest.php` | **CREATE** — test sequence generator |
| `tests/Feature/SessionLabelTest.php` | **CREATE** — test `getSessionLabel()` + reschedule |

---

## Task 1: Migration — Tambah 2 Kolom

**Files:**
- Create: `database/migrations/2026_05_24_000001_add_session_sequence_to_class_sessions.php`

- [ ] **Step 1: Buat file migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // Nomor urut sesi dalam bulan untuk murid ini (1–4).
            // NULL untuk sesi LIBUR yang punya replacement_date.
            $table->tinyInteger('session_sequence')->unsigned()->nullable()->after('honor_amount');

            // FK ke sesi asal saat reschedule atau pengganti holiday.
            // nullOnDelete: jika sesi asal dihapus, kolom ini jadi NULL (tidak cascade).
            $table->foreignId('origin_session_id')->nullable()->after('session_sequence')
                  ->constrained('class_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropForeign(['origin_session_id']);
            $table->dropColumn(['session_sequence', 'origin_session_id']);
        });
    }
};
```

- [ ] **Step 2: Jalankan migration**

```bash
php artisan migrate
```

Expected output: `Migrating: 2026_05_24_000001_add_session_sequence_to_class_sessions ... Migrated`

- [ ] **Step 3: Verifikasi kolom ada di DB**

```bash
php artisan tinker --execute="echo implode(', ', array_column(\DB::select('DESCRIBE class_sessions'), 'Field'));"
```

Expected: output menyebut `session_sequence` dan `origin_session_id`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_24_000001_add_session_sequence_to_class_sessions.php
git commit -m "DB: Migration tambah session_sequence dan origin_session_id ke class_sessions"
```

---

## Task 2: Model ClassSession — Fillable, Casts, Relasi, Label

**Files:**
- Modify: `app/Models/ClassSession.php`
- Create: `tests/Feature/SessionLabelTest.php`

- [ ] **Step 1: Tulis failing test untuk `getSessionLabel()`**

Buat file `tests/Feature/SessionLabelTest.php`:

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionLabelTest extends TestCase
{
    use RefreshDatabase;

    // --- getSessionLabel() ---

    public function test_label_sesi_reguler_bernomor(): void
    {
        // Tidak perlu DB — hanya instansiasi model
        $session = new ClassSession();
        $session->session_date     = '2026-05-04';
        $session->session_sequence = 1;

        $this->assertSame('Sesi ke-1 Bulan Mei 2026', $session->getSessionLabel());
    }

    public function test_label_libur_dengan_replacement_adalah_dash(): void
    {
        $session = new ClassSession();
        $session->session_date     = '2026-05-11';
        $session->session_sequence = null;

        $this->assertSame('—', $session->getSessionLabel());
    }

    public function test_label_sesi_pengganti_menampilkan_asal(): void
    {
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create(['status' => 'Aktif']);
        $package = Package::factory()->create(['class_type' => 'REGULER']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'status'     => 'ACTIVE',
        ]);

        // Sesi LIBUR asal (Senin ke-2 Mei)
        $origin = ClassSession::create([
            'enrollment_id'    => $enrollment->id,
            'student_id'       => $student->id,
            'teacher_id'       => $teacher->id,
            'session_date'     => '2026-05-11',
            'start_time'       => '14:00:00',
            'end_time'         => '14:30:00',
            'status'           => 'LIBUR',
            'session_sequence' => null,
        ]);

        // Sesi pengganti (Kamis 28 Mei)
        $replacement = ClassSession::create([
            'enrollment_id'    => $enrollment->id,
            'student_id'       => $student->id,
            'teacher_id'       => $teacher->id,
            'session_date'     => '2026-05-28',
            'start_time'       => '14:00:00',
            'end_time'         => '14:30:00',
            'status'           => 'SCHEDULED',
            'session_sequence' => 2,
            'origin_session_id'=> $origin->id,
        ]);

        $replacement->load('originSession');

        $this->assertSame(
            'Reschedule dari Sesi ke-2 Bulan Mei 2026',
            $replacement->getSessionLabel()
        );
    }

    public function test_label_sesi_ke_empat(): void
    {
        $session = new ClassSession();
        $session->session_date     = '2026-05-25';
        $session->session_sequence = 4;

        $this->assertSame('Sesi ke-4 Bulan Mei 2026', $session->getSessionLabel());
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/SessionLabelTest.php
```

Expected: FAIL — `getSessionLabel()` not found / `session_sequence` not in fillable.

- [ ] **Step 3: Update `app/Models/ClassSession.php`**

Tambah `use Carbon\Carbon;` dan `use Illuminate\Database\Eloquent\Relations\HasMany;` di bagian use:

```php
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
```

Update `$fillable` — tambah dua kolom baru:

```php
protected $fillable = [
    'schedule_id', 'enrollment_id',
    'student_id', 'teacher_id', 'substitute_teacher_id',
    'session_date', 'start_time', 'end_time',
    'room_id', 'status',
    'late_minutes', 'notes',
    'honor_code', 'honor_amount',
    'session_sequence', 'origin_session_id',  // ← tambah ini
];
```

Update `$casts` — tambah cast integer untuk sequence:

```php
protected $casts = [
    'late_minutes'     => 'integer',
    'honor_amount'     => 'integer',
    'session_sequence' => 'integer',  // ← tambah ini
];
```

Tambah relasi (di bagian `// ============= RELATIONSHIPS =============`):

```php
/** Sesi asal yang di-reschedule atau yang digantikan (holiday replacement). */
public function originSession(): BelongsTo
{
    return $this->belongsTo(ClassSession::class, 'origin_session_id');
}

/** Sesi-sesi yang mengacu ke sesi ini sebagai origin. */
public function replacementSessions(): HasMany
{
    return $this->hasMany(ClassSession::class, 'origin_session_id');
}
```

Tambah method `getSessionLabel()` setelah relasi:

```php
/**
 * Format label urutan sesi untuk ditampilkan di UI.
 *
 * Contoh output:
 *   "Sesi ke-2 Bulan Mei 2026"
 *   "Reschedule dari Sesi ke-2 Bulan Mei 2026"
 *   "—" (LIBUR yang punya sesi pengganti)
 *
 * Requires: relasi originSession sudah di-eager-load oleh controller.
 */
public function getSessionLabel(): string
{
    // Sesi pengganti / reschedule — ada origin
    if ($this->origin_session_id && $this->originSession) {
        $bulan = Carbon::parse($this->originSession->session_date)
                       ->translatedFormat('F Y');
        $seq   = $this->originSession->session_sequence;
        return "Reschedule dari Sesi ke-{$seq} Bulan {$bulan}";
    }

    // Sesi biasa dengan sequence (SCHEDULED atau LIBUR tanpa replacement)
    if ($this->session_sequence) {
        $bulan = Carbon::parse($this->session_date)->translatedFormat('F Y');
        return "Sesi ke-{$this->session_sequence} Bulan {$bulan}";
    }

    // LIBUR yang punya replacement — sequence null
    return '—';
}
```

- [ ] **Step 4: Jalankan test — pastikan PASS**

```bash
php artisan test tests/Feature/SessionLabelTest.php
```

Expected: 4 tests, 4 assertions — OK.

- [ ] **Step 5: Pastikan test suite tidak regresi**

```bash
php artisan test
```

Expected: semua tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Models/ClassSession.php tests/Feature/SessionLabelTest.php
git commit -m "M03: ClassSession — tambah session_sequence, origin_session_id, getSessionLabel()"
```

---

## Task 3: SessionGeneratorService — slotCounter + sequence

**Files:**
- Modify: `app/Services/SessionGeneratorService.php`
- Create: `tests/Feature/Services/SessionGeneratorSequenceTest.php`

- [ ] **Step 1: Tulis failing tests**

Buat file `tests/Feature/Services/SessionGeneratorSequenceTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionGeneratorSequenceTest extends TestCase
{
    use RefreshDatabase;

    private SessionGeneratorService $service;
    private Teacher $teacher;
    private Package $package;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SessionGeneratorService::class);
        $this->teacher = Teacher::factory()->create(['is_active' => true]);
        $this->package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
            'is_active'       => true,
        ]);
        $this->room = Room::factory()->create(['is_active' => true]);
    }

    private function createSchedule(int $dayOfWeek): Schedule
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $this->package->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        return Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => $dayOfWeek,
            'start_time'    => '14:00:00',
            'end_time'      => '14:30:00',
            'room_id'       => $this->room->id,
            'is_active'     => true,
        ]);
    }

    /** Bulan tanpa libur → sesi ke-1,2,3,4 bernomor urut */
    public function test_sequence_tanpa_libur_adalah_1_sampai_4(): void
    {
        // Januari 2026: 5 Kamis (1,8,15,22,29) → 4 sesi (week 5 skip)
        $schedule = $this->createSchedule(Carbon::THURSDAY);

        $this->service->generateForMonth(2026, 1);

        $sessions = ClassSession::where('schedule_id', $schedule->id)
            ->orderBy('session_date')
            ->get();

        $this->assertCount(4, $sessions);
        $this->assertSame(1, $sessions[0]->session_sequence); // 1 Jan
        $this->assertSame(2, $sessions[1]->session_sequence); // 8 Jan
        $this->assertSame(3, $sessions[2]->session_sequence); // 15 Jan
        $this->assertSame(4, $sessions[3]->session_sequence); // 22 Jan
    }

    /** LIBUR tanpa replacement → sequence tetap bernomor */
    public function test_libur_tanpa_replacement_dapat_sequence(): void
    {
        // Mei 2026: Senin ke-1=4, ke-2=11, ke-3=18, ke-4=25
        $schedule = $this->createSchedule(Carbon::MONDAY);

        Holiday::factory()->create([
            'date'             => '2026-05-11',
            'name'             => 'Libur Test',
            'type'             => 'Nasional',
            'replacement_date' => null,
            'is_honor_paid'    => true,
            'is_active'        => true,
        ]);

        $this->service->generateForMonth(2026, 5);

        $libur = ClassSession::where('schedule_id', $schedule->id)
            ->where('session_date', '2026-05-11')
            ->first();

        $this->assertNotNull($libur);
        $this->assertSame('LIBUR', $libur->status);
        $this->assertSame(2, $libur->session_sequence); // Senin ke-2 = slot 2
    }

    /** LIBUR dengan replacement → LIBUR null, pengganti dapat slot LIBUR */
    public function test_libur_dengan_replacement_sequence_dan_origin(): void
    {
        // Mei 2026: Senin ke-2=11 libur, pengganti di 28 Mei
        $schedule = $this->createSchedule(Carbon::MONDAY);

        Holiday::factory()->create([
            'date'             => '2026-05-11',
            'name'             => 'Libur Nasional',
            'type'             => 'Nasional',
            'replacement_date' => '2026-05-28',
            'is_honor_paid'    => true,
            'is_active'        => true,
        ]);

        $this->service->generateForMonth(2026, 5);

        $allSessions = ClassSession::where(function ($q) use ($schedule) {
            $q->where('schedule_id', $schedule->id)
              ->orWhere('enrollment_id', $schedule->enrollment_id);
        })->orderBy('session_date')->get();

        // Senin 4 Mei → sequence 1
        $s1 = $allSessions->firstWhere('session_date', '2026-05-04');
        $this->assertSame(1, $s1->session_sequence);
        $this->assertNull($s1->origin_session_id);

        // Senin 11 Mei → LIBUR, sequence null
        $libur = $allSessions->firstWhere('session_date', '2026-05-11');
        $this->assertSame('LIBUR', $libur->status);
        $this->assertNull($libur->session_sequence);
        $this->assertNull($libur->origin_session_id);

        // Senin 18 Mei → sequence 3 (bukan 2!)
        $s3 = $allSessions->firstWhere('session_date', '2026-05-18');
        $this->assertSame(3, $s3->session_sequence);

        // Senin 25 Mei → sequence 4
        $s4 = $allSessions->firstWhere('session_date', '2026-05-25');
        $this->assertSame(4, $s4->session_sequence);

        // Kamis 28 Mei → pengganti, sequence 2, origin = LIBUR 11 Mei
        $rep = $allSessions->firstWhere('session_date', '2026-05-28');
        $this->assertNotNull($rep);
        $this->assertSame(2, $rep->session_sequence);
        $this->assertSame($libur->id, $rep->origin_session_id);
    }

    /** Idempotency: run kedua tidak ubah sequence yang sudah ada */
    public function test_idempotent_tidak_mengubah_sequence_yang_ada(): void
    {
        $schedule = $this->createSchedule(Carbon::THURSDAY);

        $this->service->generateForMonth(2026, 1);
        $this->service->generateForMonth(2026, 1); // run kedua

        $sequences = ClassSession::where('schedule_id', $schedule->id)
            ->orderBy('session_date')
            ->pluck('session_sequence')
            ->toArray();

        $this->assertSame([1, 2, 3, 4], $sequences);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/Services/SessionGeneratorSequenceTest.php
```

Expected: FAIL — sequence masih null.

- [ ] **Step 3: Update `generateForSchedule()` di `SessionGeneratorService.php`**

Ubah deklarasi variabel lokal di awal `generateForSchedule()` (sekarang di baris ~116):

```php
// Sebelum:
$replacementQueue = []; // Carbon[] — tanggal pengganti
$scheduledCount   = 0;  // hitung sesi efektif

// Sesudah:
$replacementQueue = []; // [['date'=>Carbon,'reserved_slot'=>int,'libur_session_id'=>int]]
$scheduledCount   = 0;
$slotCounter      = 0;  // nomor slot mingguan (1–4, sesuai urutan occurrence)
```

Ubah loop `foreach ($weekDates as $date)` — pindahkan increment counter setelah enrollment boundary check:

```php
foreach ($weekDates as $date) {
    // Guard: enrollment boundary — slot ini tidak dihitung untuk enrollment ini
    if ($enrollment->effective_date && $date->lt($enrollment->effective_date)) {
        continue;
    }
    if ($enrollment->end_date && $date->gte($enrollment->end_date)) {
        continue;
    }

    // Slot ke-N untuk murid ini di bulan ini — increment sebelum idempotency check
    $slotCounter++;
    $dateStr = $date->toDateString();

    // Idempotency: skip jika sesi sudah ada (session_sequence sudah ter-set saat pertama kali dibuat)
    if (ClassSession::where('schedule_id', $schedule->id)
        ->whereDate('session_date', $dateStr)->exists()) {
        $report['skipped_exists']++;
        continue;
    }

    if (isset($holidayMap[$dateStr])) {
        $holiday = $holidayMap[$dateStr];

        // Guard: skip jika guru sudah punya sesi LIBUR lain di jam yang sama
        $liburConflict = $this->findConflictOnDate($schedule, $date);
        if ($liburConflict) {
            $detail = $this->buildConflictDetail($enrollment, $dateStr, $liburConflict, 'LIBUR');
            Log::warning("[SessionGenerator] Skip {$detail}");
            $report['skipped_conflict']++;
            $report['skipped_conflict_details'][] = $detail;
            continue;
        }

        [$honorCode, $honorAmount] = $this->resolveLiburHonor($holiday, $enrollment);

        // LIBUR dengan replacement → sequence null (slot diserahkan ke sesi pengganti)
        // LIBUR tanpa replacement → sequence = slotCounter (honor dibayar penuh, BR-4.10)
        $liburSequence = $holiday->replacement_date ? null : $slotCounter;

        $liburSession = ClassSession::create([
            'schedule_id'      => $schedule->id,
            'enrollment_id'    => $enrollment->id,
            'student_id'       => $enrollment->student_id,
            'teacher_id'       => $enrollment->teacher_id,
            'session_date'     => $dateStr,
            'start_time'       => $schedule->start_time,
            'end_time'         => $schedule->end_time,
            'room_id'          => $schedule->room_id,
            'status'           => 'LIBUR',
            'honor_code'       => $honorCode,
            'honor_amount'     => $honorAmount,
            'notes'            => 'Auto-set LIBUR: ' . $holiday->name,
            'session_sequence' => $liburSequence,
        ]);

        $report['created']++;
        $report['skipped_libur']++;

        // Jika ada tanggal pengganti, antri dengan reserved_slot dan libur_session_id
        if ($holiday->replacement_date) {
            $replacementQueue[] = [
                'date'             => Carbon::parse($holiday->replacement_date),
                'reserved_slot'    => $slotCounter,
                'libur_session_id' => $liburSession->id,
            ];
        }
    } else {
        // Guard: skip jika guru atau ruang sudah punya sesi di jam yang sama
        $regularConflict = $this->findConflictOnDate($schedule, $date);
        if ($regularConflict) {
            $detail = $this->buildConflictDetail($enrollment, $dateStr, $regularConflict);
            Log::warning("[SessionGenerator] Skip {$detail}");
            $report['skipped_conflict_details'][] = $detail;
            $report['skipped_conflict']++;
            continue;
        }

        ClassSession::create([
            'schedule_id'      => $schedule->id,
            'enrollment_id'    => $enrollment->id,
            'student_id'       => $enrollment->student_id,
            'teacher_id'       => $enrollment->teacher_id,
            'session_date'     => $dateStr,
            'start_time'       => $schedule->start_time,
            'end_time'         => $schedule->end_time,
            'room_id'          => $schedule->room_id,
            'status'           => 'SCHEDULED',
            'session_sequence' => $slotCounter,
        ]);

        $report['created']++;
        $scheduledCount++;
    }
}
```

Ubah FASE 3 — loop `foreach ($replacementQueue as $repDate)` menjadi:

```php
// FASE 3: Buat replacement sessions
foreach ($replacementQueue as $repItem) {
    $repDate = $repItem['date'];
    $repStr  = $repDate->toDateString();

    // Guard 1: idempotency
    if (ClassSession::where('schedule_id', $schedule->id)
        ->whereDate('session_date', $repStr)->exists()) {
        $report['skipped_exists']++;
        continue;
    }

    // Guard 2: enrollment boundary
    if ($enrollment->effective_date && $repDate->lt($enrollment->effective_date)) {
        Log::info("[SessionGenerator] Skip replacement {$repStr}: sebelum effective_date enrollment #{$enrollment->id}");
        continue;
    }
    if ($enrollment->end_date && $repDate->gte($enrollment->end_date)) {
        Log::info("[SessionGenerator] Skip replacement {$repStr}: setelah end_date enrollment #{$enrollment->id}");
        continue;
    }

    // Guard 3: replacement_date bukan hari libur lain
    if (isset($holidayMap[$repStr])) {
        Log::warning("[SessionGenerator] Skip replacement {$repStr}: tanggal tersebut juga hari libur ({$holidayMap[$repStr]->name})");
        continue;
    }

    // Guard 4: conflict detection guru dan ruang
    $repConflict = $this->findConflictOnDate($schedule, $repDate);
    if ($repConflict) {
        $detail = $this->buildConflictDetail($enrollment, $repStr, $repConflict, 'Pengganti');
        Log::warning("[SessionGenerator] Skip {$detail}");
        $report['skipped_conflict']++;
        $report['skipped_conflict_details'][] = $detail;
        continue;
    }

    ClassSession::create([
        'schedule_id'      => $schedule->id,
        'enrollment_id'    => $enrollment->id,
        'student_id'       => $enrollment->student_id,
        'teacher_id'       => $enrollment->teacher_id,
        'session_date'     => $repStr,
        'start_time'       => $schedule->start_time,
        'end_time'         => $schedule->end_time,
        'room_id'          => $schedule->room_id,
        'status'           => 'SCHEDULED',
        'honor_code'       => 'H_REG',
        'honor_amount'     => $this->calculateBaseHonor($enrollment),
        'notes'            => 'Sesi pengganti dari tanggal libur',
        'session_sequence' => $repItem['reserved_slot'],   // ← mewarisi slot LIBUR
        'origin_session_id'=> $repItem['libur_session_id'],// ← referensi ke sesi LIBUR
    ]);

    $report['created']++;
    $report['replacements_created']++;
    $scheduledCount++;
}
```

- [ ] **Step 4: Jalankan tests sequence — pastikan PASS**

```bash
php artisan test tests/Feature/Services/SessionGeneratorSequenceTest.php
```

Expected: 4 tests pass.

- [ ] **Step 5: Jalankan full test suite — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/SessionGeneratorService.php tests/Feature/Services/SessionGeneratorSequenceTest.php
git commit -m "M03: SessionGeneratorService — tambah slotCounter dan session_sequence per sesi"
```

---

## Task 4: RescheduleService — Copy Sequence ke Replacement

**Files:**
- Modify: `app/Services/RescheduleService.php`
- Modify: `tests/Feature/SessionLabelTest.php` (tambah test reschedule)

- [ ] **Step 1: Tambah test reschedule ke `SessionLabelTest.php`**

Tambahkan method berikut di akhir class `SessionLabelTest`:

```php
/** Sesi reschedule mewarisi session_sequence dan origin_session_id dari sesi asli */
public function test_replacement_dari_reschedule_mewarisi_sequence(): void
{
    $teacher = Teacher::factory()->create(['is_active' => true]);
    $student = Student::factory()->create(['status' => 'Aktif']);
    $package = Package::factory()->create([
        'class_type'      => 'REGULER',
        'duration_min'    => 30,
        'price_per_month' => 340000,
        'is_active'       => true,
    ]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'package_id' => $package->id,
        'teacher_id' => $teacher->id,
        'status'     => 'ACTIVE',
    ]);

    // Sesi asli sudah IZIN_RESCHEDULE dengan sequence=3
    $original = ClassSession::create([
        'enrollment_id'    => $enrollment->id,
        'student_id'       => $student->id,
        'teacher_id'       => $teacher->id,
        'session_date'     => '2026-05-18',
        'start_time'       => '14:00:00',
        'end_time'         => '14:30:00',
        'status'           => ClassSession::STATUS_IZIN_RESCHEDULE,
        'session_sequence' => 3,
    ]);

    $service     = app(\App\Services\RescheduleService::class);
    $replacement = $service->createReplacement($original, '2026-06-10', '14:00', null);

    $this->assertSame(3, $replacement->session_sequence);
    $this->assertSame($original->id, $replacement->origin_session_id);

    // Label harus menunjuk ke sesi asal
    $replacement->load('originSession');
    $this->assertSame(
        'Reschedule dari Sesi ke-3 Bulan Mei 2026',
        $replacement->getSessionLabel()
    );
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/SessionLabelTest.php::test_replacement_dari_reschedule_mewarisi_sequence
```

Expected: FAIL — sequence dan origin masih null.

- [ ] **Step 3: Update `createReplacement()` di `RescheduleService.php`**

Di `ClassSession::create([...])` (baris ~83), tambahkan dua field baru:

```php
$replacement = ClassSession::create([
    'schedule_id'           => null,
    'enrollment_id'         => $original->enrollment_id,
    'student_id'            => $original->student_id,
    'teacher_id'            => $original->teacher_id,
    'substitute_teacher_id' => null,
    'session_date'          => $date,
    'start_time'            => $startTimeFull,
    'end_time'              => $endTime,
    'room_id'               => $roomId,
    'status'                => ClassSession::STATUS_SCHEDULED,
    'honor_code'            => null,
    'honor_amount'          => null,
    'notes'                 => "Sesi pengganti dari " . \Carbon\Carbon::parse($original->session_date)->format('d/m/Y'),
    'session_sequence'      => $original->session_sequence,   // ← mewarisi dari sesi asli
    'origin_session_id'     => $original->id,                 // ← referensi ke sesi asli
]);
```

- [ ] **Step 4: Jalankan tests — pastikan PASS**

```bash
php artisan test tests/Feature/SessionLabelTest.php
```

Expected: 5 tests pass.

- [ ] **Step 5: Full test suite**

```bash
php artisan test
```

Expected: semua tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/RescheduleService.php tests/Feature/SessionLabelTest.php
git commit -m "M04: RescheduleService — sesi pengganti mewarisi session_sequence dan origin_session_id"
```

---

## Task 5: Controllers — Tambah Eager Load `originSession`

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php` (line 44)
- Modify: `app/Http/Controllers/SessionController.php` (line 31)
- Modify: `app/Http/Controllers/StudentController.php` (line 121)

- [ ] **Step 1: Update `AbsensiController.php` line 44**

```php
// Sebelum:
$sessions = ClassSession::with(['student', 'teacher', 'substituteTeacher', 'room'])

// Sesudah:
$sessions = ClassSession::with(['student', 'teacher', 'substituteTeacher', 'room', 'originSession'])
```

- [ ] **Step 2: Update `SessionController.php` line 31**

```php
// Sebelum:
->with(['student', 'teacher', 'substituteTeacher', 'room'])

// Sesudah:
->with(['student', 'teacher', 'substituteTeacher', 'room', 'originSession'])
```

- [ ] **Step 3: Update `StudentController.php` line 121**

```php
// Sebelum:
$upcomingSessions = $student->classSessions()
    ->with('room', 'substituteTeacher')

// Sesudah:
$upcomingSessions = $student->classSessions()
    ->with('room', 'substituteTeacher', 'originSession')
```

- [ ] **Step 4: Full test suite**

```bash
php artisan test
```

Expected: semua tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php app/Http/Controllers/SessionController.php app/Http/Controllers/StudentController.php
git commit -m "M03/M04: Tambah eager-load originSession di AbsensiController, SessionController, StudentController"
```

---

## Task 6: Views — Tampilkan Label di 3 Halaman

**Files:**
- Modify: `resources/views/absensi/_row.blade.php`
- Modify: `resources/views/sessions/index.blade.php`
- Modify: `resources/views/students/show.blade.php`

### 6a. Absensi — Label di bawah nama murid

- [ ] **Step 1: Update `absensi/_row.blade.php`**

Cari baris yang menampilkan nama murid di `data-murid` attribute dan di dalam `<td>`. Cari sel yang berisi `$session->student->full_name` (sekitar baris 50+). Ganti konten sel tersebut menjadi:

```blade
<td class="px-3 py-2">
    <div class="font-medium text-gray-800">{{ $session->student->full_name }}</div>
    @php $label = $session->getSessionLabel(); @endphp
    @if($label !== '—')
        <div class="text-[11px] mt-0.5 {{ $session->origin_session_id ? 'text-blue-500' : 'text-yellow-600' }}">
            {{ $label }}
        </div>
    @endif
</td>
```

> **Catatan:** Warna gold (`text-yellow-600`) untuk sesi reguler, biru (`text-blue-500`) untuk reschedule/pengganti. Di dark mode sudah ter-override oleh `.dark-content`.

### 6b. Sessions List — Kolom "Label Sesi"

- [ ] **Step 2: Tambah header kolom di `sessions/index.blade.php`**

Di `<thead>` (sekitar baris 216–226), tambah kolom setelah `<th>Murid</th>`:

```blade
<th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Label Sesi</th>
```

- [ ] **Step 3: Tambah sel data di `sessions/index.blade.php`**

Di `<tbody>` (sekitar baris 240–248), setelah sel Murid, tambah:

```blade
<td class="px-2 py-1.5">
    @php $label = $s->getSessionLabel(); @endphp
    @if($label !== '—')
        <span class="px-1.5 py-0.5 rounded text-[11px] font-medium
            {{ $s->origin_session_id
                ? 'bg-blue-50 text-blue-600'
                : 'bg-yellow-50 text-yellow-700' }}">
            {{ $label }}
        </span>
    @else
        <span class="text-gray-400 text-xs">—</span>
    @endif
</td>
```

### 6c. Detail Murid — Kolom "Label" di Sesi Mendatang

- [ ] **Step 4: Tambah header kolom di `students/show.blade.php`**

Di `<thead>` tabel Sesi Mendatang (sekitar baris 942–950), tambah setelah `<th>Status</th>`:

Ganti baris headers menjadi:

```blade
<th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Tanggal</th>
<th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Jam</th>
<th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Label</th>
<th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Ruang</th>
<th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Guru</th>
<th class="pb-2 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Status</th>
@if($canEdit)
<th class="pb-2 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Aksi</th>
@endif
```

- [ ] **Step 5: Tambah sel data di `students/show.blade.php`**

Di `<tbody>` (sekitar baris 955–968), setelah sel Jam, tambah sel Label:

```blade
<td class="py-2">
    @php $label = $sess->getSessionLabel(); @endphp
    @if($label !== '—')
        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium
            {{ $sess->origin_session_id
                ? 'text-blue-500'
                : '' }}"
            style="{{ $sess->origin_session_id ? '' : 'color:#D4A853' }}">
            {{ $label }}
        </span>
    @else
        <span class="text-gray-400">—</span>
    @endif
</td>
```

- [ ] **Step 6: Build assets**

```bash
npm run build
```

- [ ] **Step 7: Verifikasi manual**

Buka browser:
1. `/absensi` — cek label kecil di bawah nama murid
2. `/sessions` — cek kolom Label Sesi muncul
3. `/students/{id}` — cek kolom Label di tab Jadwal bagian Sesi Mendatang

> Jika DB kosong, jalankan generator dulu: `php artisan sessions:generate-month --year=2026 --month=6` (sesuaikan command jika signature berbeda), atau buat data via Tinker.

- [ ] **Step 8: Full test suite**

```bash
php artisan test
```

Expected: semua tests pass.

- [ ] **Step 9: Commit**

```bash
git add resources/views/absensi/_row.blade.php resources/views/sessions/index.blade.php resources/views/students/show.blade.php
git commit -m "UI: Tampilkan label sesi (session_sequence) di halaman Absensi, Sessions, dan Detail Murid"
```

---

## Checklist Akhir

- [ ] `php artisan test` — semua pass
- [ ] Migration bisa `migrate:rollback` tanpa error
- [ ] Label tampil benar di 3 halaman
- [ ] Sesi reschedule menampilkan "Reschedule dari Sesi ke-X Bulan Y"
- [ ] LIBUR dengan replacement → label `—`
- [ ] LIBUR tanpa replacement → label `Sesi ke-N Bulan Y`
