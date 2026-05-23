# Jadwal Otomatis dengan Kalender Akademik — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite session generator agar mendukung kalender akademik tahunan dengan tanggal pengganti per hari libur, honor rules yang benar untuk sesi LIBUR dan IZIN_RESCHEDULE, serta tracking guru pendamping Konser KITA di M08.

**Architecture:** Tambah dua kolom DB (`replacement_date`, `is_honor_paid` di holidays; `accompanying_teacher_id` di event_participants), rewrite `SessionGeneratorService::generateForSchedule()` dengan algoritma 5-fase yang memisahkan replacement sessions dari counter 4-sesi, set `honor_code`+`honor_amount` langsung saat sesi LIBUR dibuat.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL, Blade + Alpine.js, PHPUnit (feature tests dengan RefreshDatabase)

**Spec:** `docs/superpowers/specs/2026-05-23-jadwal-otomatis-design.md`

---

## Task 1: Migrasi — Tambah `replacement_date` + `is_honor_paid` ke tabel `holidays`

**Files:**
- Create: `database/migrations/[timestamp]_add_replacement_date_is_honor_paid_to_holidays.php`

- [ ] **Step 1: Buat file migrasi**

```bash
php artisan make:migration add_replacement_date_is_honor_paid_to_holidays --table=holidays
```

- [ ] **Step 2: Isi konten migrasi**

Buka file migrasi yang baru dibuat, ganti isi `up()` dan `down()`:

```php
public function up(): void
{
    Schema::table('holidays', function (Blueprint $table) {
        // replacement_date: tanggal sesi pengganti (dalam bulan yang sama)
        // unique: tidak boleh dua holiday berbagi tanggal pengganti yang sama
        $table->date('replacement_date')->nullable()->unique()->after('date');

        // is_honor_paid: false untuk Internal/Konser KITA — honor Rp 0
        $table->boolean('is_honor_paid')->default(true)->after('notes');
    });
}

public function down(): void
{
    Schema::table('holidays', function (Blueprint $table) {
        $table->dropUnique(['replacement_date']);
        $table->dropColumn(['replacement_date', 'is_honor_paid']);
    });
}
```

- [ ] **Step 3: Jalankan migrasi**

```bash
php artisan migrate
```

Expected output: `Migrating: ...add_replacement_date_is_honor_paid_to_holidays` lalu `Migrated`.

- [ ] **Step 4: Verifikasi schema**

```bash
php artisan tinker --execute="Schema::getColumnListing('holidays');"
```

Expected: array berisi `replacement_date` dan `is_honor_paid`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/
git commit -m "M03: Migrasi tambah replacement_date dan is_honor_paid ke tabel holidays"
```

---

## Task 2: Migrasi — Tambah `accompanying_teacher_id` ke tabel `event_participants`

**Files:**
- Create: `database/migrations/[timestamp]_add_accompanying_teacher_to_event_participants.php`

- [ ] **Step 1: Buat file migrasi**

```bash
php artisan make:migration add_accompanying_teacher_to_event_participants --table=event_participants
```

- [ ] **Step 2: Isi konten migrasi**

```php
public function up(): void
{
    Schema::table('event_participants', function (Blueprint $table) {
        // Guru yang mendampingi murid di event (Konser KITA)
        // NULL = tidak ada pendamping / guru tidak bisa hadir
        $table->foreignId('accompanying_teacher_id')
            ->nullable()
            ->after('enrollment_id')
            ->constrained('teachers')
            ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('event_participants', function (Blueprint $table) {
        $table->dropForeignIdFor(\App\Models\Teacher::class, 'accompanying_teacher_id');
        $table->dropColumn('accompanying_teacher_id');
    });
}
```

- [ ] **Step 3: Jalankan migrasi**

```bash
php artisan migrate
```

Expected: kolom `accompanying_teacher_id` berhasil ditambah.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "M08: Migrasi tambah accompanying_teacher_id ke event_participants"
```

---

## Task 3: Migrasi — Backfill honor LIBUR sessions yang sudah ada

**Files:**
- Create: `database/migrations/[timestamp]_backfill_honor_libur_sessions.php`

- [ ] **Step 1: Buat file migrasi**

```bash
php artisan make:migration backfill_honor_libur_sessions
```

- [ ] **Step 2: Isi konten migrasi**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill honor_code dan honor_amount untuk sesi LIBUR yang sudah ada.
     * Semua LIBUR existing dianggap "libur nasional tanpa pengganti" (BR-4.10) karena
     * kolom replacement_date baru saja ditambah dan semua nilai-nya masih NULL.
     *
     * Kids Class (KIDS_CLASS / KIDS_CLASS_BUNDLE) dikecualikan — honor-nya
     * dihitung per jumlah murid aktif, tidak bisa di-backfill secara otomatis.
     */
    public function up(): void
    {
        // Set honor untuk sesi LIBUR paket reguler/hobby (bukan Kids Class)
        DB::statement("
            UPDATE class_sessions cs
            INNER JOIN enrollments e ON cs.enrollment_id = e.id
            INNER JOIN packages p ON e.package_id = p.id
            SET cs.honor_code   = 'H_LIBUR',
                cs.honor_amount = ROUND(p.price_per_month * 0.5 / 4)
            WHERE cs.status     = 'LIBUR'
            AND   cs.honor_code IS NULL
            AND   p.class_type  NOT IN ('KIDS_CLASS', 'KIDS_CLASS_BUNDLE')
        ");
    }

    public function down(): void
    {
        // Rollback: kembalikan honor LIBUR ke NULL
        DB::statement("
            UPDATE class_sessions
            SET honor_code   = NULL,
                honor_amount = NULL
            WHERE status     = 'LIBUR'
            AND   honor_code = 'H_LIBUR'
        ");
    }
};
```

- [ ] **Step 3: Jalankan migrasi**

```bash
php artisan migrate
```

- [ ] **Step 4: Verifikasi backfill**

```bash
php artisan tinker --execute="
    echo 'LIBUR dengan honor: ' . App\Models\ClassSession::where('status','LIBUR')->whereNotNull('honor_amount')->count();
    echo '\nLIBUR tanpa honor: ' . App\Models\ClassSession::where('status','LIBUR')->whereNull('honor_amount')->count();
"
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/
git commit -m "M03: Migrasi backfill honor_code dan honor_amount untuk sesi LIBUR existing"
```

---

## Task 4: Update Model `Holiday` dan `EventParticipant`

**Files:**
- Modify: `app/Models/Holiday.php`
- Modify: `app/Models/EventParticipant.php`

- [ ] **Step 1: Update `app/Models/Holiday.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'date',
        'name',
        'type',
        'is_active',
        'notes',
        'replacement_date',
        'is_honor_paid',
    ];

    protected $casts = [
        'date'             => 'date',
        'replacement_date' => 'date',
        'is_active'        => 'boolean',
        'is_honor_paid'    => 'boolean',
    ];
}
```

- [ ] **Step 2: Update `app/Models/EventParticipant.php`**

Tambah `accompanying_teacher_id` ke `$fillable` dan tambah relationship. File lengkap bagian yang diubah:

```php
protected $fillable = [
    'event_id',
    'student_id',
    'enrollment_id',
    'accompanying_teacher_id',   // ← tambah ini
    'participation_type',
    'fee_amount',
    'invoice_id',
    'invoice_item_id',
    'exam_result',
    'grade_before',
    'grade_after',
    'exam_notes',
];
```

Tambah relationship setelah relationship `enrollment()`:

```php
/** Guru yang mendampingi murid ini di event (opsional, terutama untuk Konser KITA). */
public function accompanyingTeacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(Teacher::class, 'accompanying_teacher_id');
}
```

- [ ] **Step 3: Verifikasi model dengan tinker**

```bash
php artisan tinker --execute="
    \$h = new App\Models\Holiday;
    echo implode(', ', \$h->getFillable());
"
```

Expected output mengandung: `replacement_date, is_honor_paid`.

- [ ] **Step 4: Commit**

```bash
git add app/Models/Holiday.php app/Models/EventParticipant.php
git commit -m "M03/M08: Update model Holiday dan EventParticipant — fillable + casts baru"
```

---

## Task 5: Rewrite `SessionGeneratorService`

Ini adalah task inti. Rewrite penuh `generateForSchedule()` dan update `loadHolidayDates()`.

**Files:**
- Modify: `app/Services/SessionGeneratorService.php`
- Create: `tests/Feature/Services/SessionGeneratorServiceTest.php`

- [ ] **Step 1: Tulis test file terlebih dahulu**

Buat `tests/Feature/Services/SessionGeneratorServiceTest.php`:

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

class SessionGeneratorServiceTest extends TestCase
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

    /**
     * Buat schedule aktif dengan enrollment + murid Aktif.
     * dayOfWeek menggunakan konstanta Carbon (0=Minggu, 1=Senin, ..., 6=Sabtu).
     */
    private function createSchedule(int $dayOfWeek, ?Package $package = null): Schedule
    {
        $pkg     = $package ?? $this->package;
        $student = Student::factory()->create(['status' => 'Aktif']);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $pkg->id,
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

    // ─────────────────────────────────────────────
    // R3: 5 occurrence, 0 libur → 4 SCHEDULED (week 5 skip)
    // ─────────────────────────────────────────────
    public function test_r3_five_occurrences_no_holiday_creates_four_scheduled(): void
    {
        // Januari 2026 punya 5 Kamis: 1, 8, 15, 22, 29
        $schedule = $this->createSchedule(Carbon::THURSDAY);

        $this->service->generateForMonth(2026, 1);

        $sessions = ClassSession::where('schedule_id', $schedule->id)->get();
        $this->assertCount(4, $sessions);
        $this->assertTrue($sessions->every(fn ($s) => $s->status === 'SCHEDULED'));
        // Kamis ke-5 (29 Jan) tidak dibuat
        $this->assertFalse(
            $sessions->contains('session_date', '2026-01-29'),
            'Week 5 seharusnya di-skip'
        );
    }

    // ─────────────────────────────────────────────
    // R4: 5 occurrence, 1 libur dengan replacement → 4 efektif
    // ─────────────────────────────────────────────
    public function test_r4_five_occurrences_holiday_with_replacement_creates_four_effective(): void
    {
        // Maret 2026 punya 5 Senin: 2, 9, 16, 23, 30
        // Libur 23 Mar → replacement 30 Mar (Senin ke-5)
        $schedule = $this->createSchedule(Carbon::MONDAY);

        Holiday::create([
            'date'             => '2026-03-23',
            'name'             => 'Idul Fitri',
            'type'             => 'Nasional',
            'is_active'        => true,
            'is_honor_paid'    => true,
            'replacement_date' => '2026-03-30',
        ]);

        $this->service->generateForMonth(2026, 3);

        $sessions = ClassSession::where('schedule_id', $schedule->id)
            ->orderBy('session_date')->get();

        $this->assertCount(5, $sessions); // 3 SCHEDULED + 1 LIBUR + 1 replacement
        $this->assertEquals('LIBUR', $sessions->firstWhere('session_date', '2026-03-23')?->status);
        $this->assertEquals('SCHEDULED', $sessions->firstWhere('session_date', '2026-03-30')?->status);
        $this->assertEquals(4, $sessions->where('status', 'SCHEDULED')->count());
    }

    // ─────────────────────────────────────────────
    // R4b: 5 occurrence, 1 libur tanpa replacement → 3 efektif, week 5 skip
    // ─────────────────────────────────────────────
    public function test_r4b_five_occurrences_holiday_without_replacement_skips_week5(): void
    {
        // Januari 2026 punya 5 Jumat: 2, 9, 16, 23, 30
        // Isra Mikraj 16 Jan (Jumat ke-3) → tanpa replacement
        $schedule = $this->createSchedule(Carbon::FRIDAY);

        Holiday::create([
            'date'          => '2026-01-16',
            'name'          => 'Isra Mikraj',
            'type'          => 'Nasional',
            'is_active'     => true,
            'is_honor_paid' => true,
            // replacement_date sengaja tidak diisi
        ]);

        $this->service->generateForMonth(2026, 1);

        $sessions = ClassSession::where('schedule_id', $schedule->id)->get();
        $this->assertCount(4, $sessions); // 3 SCHEDULED + 1 LIBUR
        $this->assertEquals(3, $sessions->where('status', 'SCHEDULED')->count());
        $this->assertFalse(
            $sessions->contains('session_date', '2026-01-30'),
            'Week 5 tetap di-skip meski ada libur tanpa replacement'
        );
    }

    // ─────────────────────────────────────────────
    // R5: 4 occurrence, 0 libur → 4 SCHEDULED
    // ─────────────────────────────────────────────
    public function test_r5_four_occurrences_no_holiday_creates_four_scheduled(): void
    {
        // Februari 2026 punya 4 Selasa: 3, 10, 17, 24
        $schedule = $this->createSchedule(Carbon::TUESDAY);

        $this->service->generateForMonth(2026, 2);

        $sessions = ClassSession::where('schedule_id', $schedule->id)->get();
        $this->assertCount(4, $sessions);
        $this->assertTrue($sessions->every(fn ($s) => $s->status === 'SCHEDULED'));
    }

    // ─────────────────────────────────────────────
    // R6b: 4 occurrence, 1 libur tanpa replacement → 3 efektif
    // ─────────────────────────────────────────────
    public function test_r6b_four_occurrences_holiday_without_replacement_creates_three(): void
    {
        // Februari 2026 punya 4 Selasa: 3, 10, 17, 24
        // Imlek 17 Feb (Selasa ke-3) → tanpa replacement
        $schedule = $this->createSchedule(Carbon::TUESDAY);

        Holiday::create([
            'date'          => '2026-02-17',
            'name'          => 'Imlek',
            'type'          => 'Nasional',
            'is_active'     => true,
            'is_honor_paid' => true,
        ]);

        $this->service->generateForMonth(2026, 2);

        $sessions = ClassSession::where('schedule_id', $schedule->id)->get();
        $this->assertCount(4, $sessions); // 3 SCHEDULED + 1 LIBUR
        $this->assertEquals(3, $sessions->where('status', 'SCHEDULED')->count());
    }

    // ─────────────────────────────────────────────
    // Honor: LIBUR nasional tanpa replacement → H_LIBUR + honor penuh
    // ─────────────────────────────────────────────
    public function test_libur_without_replacement_sets_full_honor(): void
    {
        $schedule = $this->createSchedule(Carbon::TUESDAY);

        Holiday::create([
            'date'          => '2026-02-17',
            'name'          => 'Imlek',
            'type'          => 'Nasional',
            'is_active'     => true,
            'is_honor_paid' => true,
        ]);

        $this->service->generateForMonth(2026, 2);

        $libur = ClassSession::where('schedule_id', $schedule->id)
            ->where('status', 'LIBUR')->first();

        $this->assertNotNull($libur);
        $this->assertEquals('H_LIBUR', $libur->honor_code);
        $this->assertEquals(42500, $libur->honor_amount); // 340000 * 0.5 / 4
    }

    // ─────────────────────────────────────────────
    // Honor: LIBUR dengan replacement_date → honor Rp 0
    // ─────────────────────────────────────────────
    public function test_libur_with_replacement_sets_zero_honor(): void
    {
        $schedule = $this->createSchedule(Carbon::MONDAY);

        Holiday::create([
            'date'             => '2026-03-23',
            'name'             => 'Idul Fitri',
            'type'             => 'Nasional',
            'is_active'        => true,
            'is_honor_paid'    => true,
            'replacement_date' => '2026-03-30',
        ]);

        $this->service->generateForMonth(2026, 3);

        $libur = ClassSession::where('schedule_id', $schedule->id)
            ->where('status', 'LIBUR')->first();

        $this->assertNull($libur->honor_code);
        $this->assertEquals(0, $libur->honor_amount);
    }

    // ─────────────────────────────────────────────
    // Honor: Internal holiday (is_honor_paid=false) → honor Rp 0
    // ─────────────────────────────────────────────
    public function test_internal_holiday_sets_zero_honor(): void
    {
        // April 2026 punya 4 Sabtu: 4, 11, 18, 25
        $schedule = $this->createSchedule(Carbon::SATURDAY);

        Holiday::create([
            'date'          => '2026-04-18',
            'name'          => 'Konser KITA',
            'type'          => 'Internal',
            'is_active'     => true,
            'is_honor_paid' => false,
        ]);

        $this->service->generateForMonth(2026, 4);

        $libur = ClassSession::where('schedule_id', $schedule->id)
            ->where('status', 'LIBUR')->first();

        $this->assertNull($libur->honor_code);
        $this->assertEquals(0, $libur->honor_amount);
    }

    // ─────────────────────────────────────────────
    // Idempotency: aman dijalankan ulang
    // ─────────────────────────────────────────────
    public function test_generator_is_idempotent(): void
    {
        $schedule = $this->createSchedule(Carbon::TUESDAY);

        $this->service->generateForMonth(2026, 2);
        $countFirst = ClassSession::where('schedule_id', $schedule->id)->count();

        $this->service->generateForMonth(2026, 2);
        $countSecond = ClassSession::where('schedule_id', $schedule->id)->count();

        $this->assertEquals($countFirst, $countSecond);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL (fungsi belum diubah)**

```bash
php artisan test tests/Feature/Services/SessionGeneratorServiceTest.php
```

Expected: Beberapa test FAIL (terutama yang berkaitan replacement_date dan honor).

- [ ] **Step 3: Rewrite `app/Services/SessionGeneratorService.php`**

Ganti seluruh isi file dengan implementasi baru:

```php
<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Generator sesi mingguan ke tabel class_sessions (M03).
 *
 * Cara pakai:
 *   $report = app(SessionGeneratorService::class)->generateForMonth(2026, 6);
 *
 * Aturan yang diimplementasikan (BRD v1.3):
 *   R3: 5 occurrence, 0 libur → 4 SCHEDULED (week 5 skip)
 *   R4: 5 occurrence, 1 libur DGN replacement → 3 SCHEDULED + 1 LIBUR + 1 replacement
 *   R4b: 5 occurrence, 1 libur TANPA replacement → 3 SCHEDULED + 1 LIBUR (week 5 tetap skip)
 *   R5: 4 occurrence, 0 libur → 4 SCHEDULED
 *   R6: 4 occurrence, 1 libur DGN replacement → 3 SCHEDULED + 1 LIBUR + 1 replacement
 *   R6b: 4 occurrence, 1 libur TANPA replacement → 3 SCHEDULED + 1 LIBUR
 *
 *   Honor LIBUR:
 *   - is_honor_paid=false (Konser KITA) → honor Rp 0
 *   - Ada replacement_date → honor Rp 0 (dibayar via sesi pengganti)
 *   - Libur nasional tanpa replacement → H_LIBUR penuh (BR-4.10)
 *
 * Idempotent: aman dipanggil ulang. Tidak ada retroactive update jika holiday diubah
 * setelah sesi digenerate — admin handle manual via Reschedule.
 */
class SessionGeneratorService
{
    private const MAX_SESSIONS_PER_MONTH = 4;

    /**
     * Generate semua sesi yang seharusnya ada di bulan target.
     *
     * @return array{
     *     created: int,
     *     replacements_created: int,
     *     skipped_exists: int,
     *     skipped_libur: int,
     *     skipped_conflict: int,
     *     schedules_processed: int,
     *     month: string,
     * }
     */
    public function generateForMonth(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $report = [
            'created'              => 0,
            'replacements_created' => 0,
            'skipped_exists'       => 0,
            'skipped_libur'        => 0,
            'skipped_conflict'     => 0,
            'schedules_processed'  => 0,
            'month'                => $start->format('F Y'),
        ];

        // Pre-load holidays bulan ini sebagai [Y-m-d => Holiday]
        // Include 'Internal' agar Konser KITA terdeteksi (C1 fix dari review)
        $holidayMap = $this->loadHolidayDates($start, $end);

        // Ambil schedule aktif dengan enrollment ACTIVE dan murid berstatus Aktif
        // Eager-load enrollment.package untuk perhitungan honor
        $schedules = Schedule::query()
            ->active()
            ->whereHas('enrollment', fn ($q) => $q->active())
            ->whereHas('enrollment.student', fn ($q) => $q->where('status', 'Aktif'))
            ->with(['enrollment', 'enrollment.package'])
            ->get();

        foreach ($schedules as $schedule) {
            $report['schedules_processed']++;
            $this->generateForSchedule($schedule, $start, $end, $holidayMap, $report);
        }

        return $report;
    }

    /**
     * Generate sesi untuk satu schedule pada bulan tertentu.
     * Modifikasi $report by-reference.
     */
    private function generateForSchedule(
        Schedule $schedule,
        Carbon $monthStart,
        Carbon $monthEnd,
        array $holidayMap,
        array &$report,
    ): void {
        $enrollment = $schedule->enrollment;
        if (!$enrollment) {
            return;
        }

        // FASE 1: Kumpulkan semua tanggal yang cocok dengan day_of_week di bulan ini
        // (bisa 4 atau 5 item tergantung bulan)
        $allDates = collect();
        $cursor   = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            if ($cursor->dayOfWeek === $schedule->day_of_week) {
                $allDates->push($cursor->copy());
            }
            $cursor->addDay();
        }

        $replacementQueue = []; // Carbon[] — tanggal pengganti yang akan dibuat setelah loop utama
        $scheduledCount   = 0;  // hitung sesi efektif (SCHEDULED, bukan LIBUR)

        // FASE 2: Proses minggu ke-1 sampai ke-4 saja
        $weekDates = $allDates->take(self::MAX_SESSIONS_PER_MONTH);

        foreach ($weekDates as $date) {
            // Guard: enrollment boundary
            if ($enrollment->effective_date && $date->lt($enrollment->effective_date)) {
                continue;
            }
            if ($enrollment->end_date && $date->gte($enrollment->end_date)) {
                continue;
            }

            $dateStr = $date->toDateString();

            // Idempotency: skip jika sesi sudah ada
            if (ClassSession::where('schedule_id', $schedule->id)
                ->whereDate('session_date', $dateStr)->exists()) {
                $report['skipped_exists']++;
                continue;
            }

            if (isset($holidayMap[$dateStr])) {
                // ── Tanggal ini adalah hari libur ──
                $holiday = $holidayMap[$dateStr];

                [$honorCode, $honorAmount] = $this->resolveLiburHonor($holiday, $enrollment);

                ClassSession::create([
                    'schedule_id'   => $schedule->id,
                    'enrollment_id' => $enrollment->id,
                    'student_id'    => $enrollment->student_id,
                    'teacher_id'    => $enrollment->teacher_id,
                    'session_date'  => $dateStr,
                    'start_time'    => $schedule->start_time,
                    'end_time'      => $schedule->end_time,
                    'room_id'       => $schedule->room_id,
                    'status'        => ClassSession::STATUS_LIBUR,
                    'honor_code'    => $honorCode,
                    'honor_amount'  => $honorAmount,
                    'notes'         => 'Auto-set LIBUR: ' . $holiday->name,
                ]);

                $report['created']++;
                $report['skipped_libur']++;

                // Jika ada tanggal pengganti, antri untuk FASE 3
                if ($holiday->replacement_date) {
                    $replacementQueue[] = Carbon::parse($holiday->replacement_date);
                }
            } else {
                // ── Sesi normal ──
                ClassSession::create([
                    'schedule_id'   => $schedule->id,
                    'enrollment_id' => $enrollment->id,
                    'student_id'    => $enrollment->student_id,
                    'teacher_id'    => $enrollment->teacher_id,
                    'session_date'  => $dateStr,
                    'start_time'    => $schedule->start_time,
                    'end_time'      => $schedule->end_time,
                    'room_id'       => $schedule->room_id,
                    'status'        => ClassSession::STATUS_SCHEDULED,
                ]);

                $report['created']++;
                $scheduledCount++;
            }
        }

        // FASE 3: Buat replacement sessions
        // Replacement session dibuat DI LUAR counter 4-sesi — tidak memblok week 5
        foreach ($replacementQueue as $repDate) {
            $repStr = $repDate->toDateString();

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

            // Guard 4: conflict detection guru dan ruang (BR-3.11, BR-3.12)
            if ($this->hasConflictOnDate($schedule, $repDate)) {
                Log::warning("[SessionGenerator] Skip replacement {$repStr}: konflik jadwal guru/ruang untuk schedule #{$schedule->id}");
                $report['skipped_conflict']++;
                continue;
            }

            ClassSession::create([
                'schedule_id'   => $schedule->id,
                'enrollment_id' => $enrollment->id,
                'student_id'    => $enrollment->student_id,
                'teacher_id'    => $enrollment->teacher_id,
                'session_date'  => $repStr,
                'start_time'    => $schedule->start_time,
                'end_time'      => $schedule->end_time,
                'room_id'       => $schedule->room_id,
                'status'        => ClassSession::STATUS_SCHEDULED,
                'honor_code'    => 'H_REG',
                'honor_amount'  => $this->calculateBaseHonor($enrollment),
                'notes'         => 'Sesi pengganti dari tanggal libur',
            ]);

            $report['created']++;
            $report['replacements_created']++;
            $scheduledCount++;
        }

        // FASE 4: Week 5 dipakai secara natural jika replacement_date jatuh di sana.
        // Tidak ada logika khusus — semua sudah ditangani FASE 3.

        // FASE 5: Warning jika sesi efektif kurang dari minimum (BR-3.3)
        if ($scheduledCount > 0 && $scheduledCount < 3) {
            Log::warning(
                "[SessionGenerator] Peringatan: murid #{$enrollment->student_id} " .
                "hanya {$scheduledCount} sesi di bulan ini — cek hari libur"
            );
        }
    }

    /**
     * Tentukan honor_code dan honor_amount untuk sesi LIBUR.
     *
     * @return array{0: string|null, 1: int}  [honor_code, honor_amount]
     */
    private function resolveLiburHonor(Holiday $holiday, Enrollment $enrollment): array
    {
        // Konser KITA atau event studio yang tidak membayar honor via session
        if (!$holiday->is_honor_paid) {
            return [null, 0];
        }

        // Ada tanggal pengganti → honor akan dibayar via sesi pengganti (H_REG)
        if ($holiday->replacement_date) {
            return [null, 0];
        }

        // Libur nasional/cuti bersama tanpa pengganti → honor penuh (BR-4.10)
        return ['H_LIBUR', $this->calculateBaseHonor($enrollment)];
    }

    /**
     * Hitung honor dasar per sesi berdasarkan paket enrollment.
     * Formula: price_per_month × 50% / 4
     *
     * Kids Class dikecualikan — honor-nya dihitung per jumlah murid aktif (H_KIDS),
     * bukan per session. Set 0 dan biarkan HonorCalculationService handle via honor_amount
     * yang di-set saat absensi diinput.
     */
    private function calculateBaseHonor(Enrollment $enrollment): int
    {
        $package = $enrollment->package;
        if (!$package) {
            return 0;
        }

        if ($package->isKidsClass()) {
            return 0;
        }

        return (int) round($package->price_per_month * 0.5 / 4);
    }

    /**
     * Cek konflik guru atau ruang pada tanggal spesifik.
     *
     * Berbeda dari ScheduleConflictDetector (yang cek jadwal mingguan) —
     * ini cek class_sessions konkret di tanggal tertentu untuk replacement sessions.
     */
    private function hasConflictOnDate(Schedule $schedule, Carbon $date): bool
    {
        $teacherId = $schedule->enrollment->teacher_id;

        // Guru sudah punya sesi lain di jam yang sama pada tanggal ini
        $teacherBusy = ClassSession::where('teacher_id', $teacherId)
            ->whereDate('session_date', $date)
            ->where('start_time', $schedule->start_time)
            ->where('schedule_id', '!=', $schedule->id)
            ->whereNotIn('status', [ClassSession::STATUS_CANCELLED])
            ->exists();

        if ($teacherBusy) {
            return true;
        }

        // Ruangan sudah terpakai di jam yang sama pada tanggal ini
        if ($schedule->room_id) {
            $roomBusy = ClassSession::where('room_id', $schedule->room_id)
                ->whereDate('session_date', $date)
                ->where('start_time', $schedule->start_time)
                ->where('schedule_id', '!=', $schedule->id)
                ->whereNotIn('status', [ClassSession::STATUS_CANCELLED])
                ->exists();

            if ($roomBusy) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pre-load holidays dalam range sebagai [Y-m-d => Holiday].
     *
     * Include tipe 'Internal' agar Konser KITA terdeteksi dan sesinya
     * dibuat dengan status LIBUR + honor Rp 0 (is_honor_paid = false).
     *
     * @return array<string, Holiday>
     */
    private function loadHolidayDates(Carbon $start, Carbon $end): array
    {
        return Holiday::query()
            ->where('is_active', true)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('type', ['Nasional', 'Cuti Bersama', 'Internal'])
            ->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h])
            ->all();
    }
}
```

- [ ] **Step 4: Jalankan test — pastikan semua PASS**

```bash
php artisan test tests/Feature/Services/SessionGeneratorServiceTest.php
```

Expected: semua test PASS.

- [ ] **Step 5: Jalankan full test suite — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test PASS. Jika ada yang fail, perbaiki sebelum lanjut.

- [ ] **Step 6: Commit**

```bash
git add app/Services/SessionGeneratorService.php tests/Feature/Services/SessionGeneratorServiceTest.php
git commit -m "M03: Rewrite SessionGeneratorService — replacement_date, honor LIBUR, conflict detection"
```

---

## Task 6: Update `HolidayController` — Tambah Validasi `replacement_date` + `is_honor_paid`

**Files:**
- Modify: `app/Http/Controllers/HolidayController.php`

- [ ] **Step 1: Update method `store()` di HolidayController**

Ganti method `store()` yang ada (baris 30-41) dengan versi baru:

```php
public function store(Request $request)
{
    $data = $request->validate([
        'date'             => 'required|date|unique:holidays,date',
        'name'             => 'required|string|max:100',
        'type'             => 'required|in:Nasional,Cuti Bersama,Internal',
        'notes'            => 'nullable|string|max:500',
        'is_active'        => 'nullable|boolean',
        'replacement_date' => [
            'nullable',
            'date',
            'unique:holidays,replacement_date',
            // replacement_date harus dalam bulan yang sama dengan date
            function ($attribute, $value, $fail) use ($request) {
                if (!$value || !$request->date) return;
                if (date('Y-m', strtotime($value)) !== date('Y-m', strtotime($request->date))) {
                    $fail('Tanggal pengganti harus dalam bulan yang sama dengan tanggal libur.');
                }
                if ($value === $request->date) {
                    $fail('Tanggal pengganti tidak boleh sama dengan tanggal libur.');
                }
            },
            // Internal holiday tidak boleh punya replacement_date
            function ($attribute, $value, $fail) use ($request) {
                if ($value && $request->type === 'Internal') {
                    $fail('Event studio (Internal) tidak bisa punya tanggal pengganti. Gunakan fitur Reschedule.');
                }
            },
        ],
        'is_honor_paid'    => 'nullable|boolean',
    ], [
        'date.unique'             => 'Tanggal libur ini sudah ada di sistem.',
        'replacement_date.unique' => 'Tanggal pengganti ini sudah dipakai oleh hari libur lain.',
    ]);

    Holiday::create([
        'date'             => $data['date'],
        'name'             => $data['name'],
        'type'             => $data['type'],
        'notes'            => $data['notes'] ?? null,
        'is_active'        => $request->boolean('is_active', true),
        'replacement_date' => $data['replacement_date'] ?? null,
        // Internal selalu is_honor_paid=false; lainnya ikut checkbox
        'is_honor_paid'    => $data['type'] === 'Internal'
            ? false
            : $request->boolean('is_honor_paid', true),
    ]);

    return redirect()->route('holidays.index')
        ->with('success', 'Hari libur berhasil ditambahkan.');
}
```

- [ ] **Step 2: Update method `update()` di HolidayController**

Ganti method `update()` yang ada (baris 49-61) dengan versi baru:

```php
public function update(Request $request, string $id)
{
    $holiday = Holiday::findOrFail($id);

    $data = $request->validate([
        'date'             => 'required|date|unique:holidays,date,' . $id,
        'name'             => 'required|string|max:100',
        'type'             => 'required|in:Nasional,Cuti Bersama,Internal',
        'notes'            => 'nullable|string|max:500',
        'is_active'        => 'nullable|boolean',
        'replacement_date' => [
            'nullable',
            'date',
            'unique:holidays,replacement_date,' . $id,
            function ($attribute, $value, $fail) use ($request) {
                if (!$value || !$request->date) return;
                if (date('Y-m', strtotime($value)) !== date('Y-m', strtotime($request->date))) {
                    $fail('Tanggal pengganti harus dalam bulan yang sama dengan tanggal libur.');
                }
                if ($value === $request->date) {
                    $fail('Tanggal pengganti tidak boleh sama dengan tanggal libur.');
                }
            },
            function ($attribute, $value, $fail) use ($request) {
                if ($value && $request->type === 'Internal') {
                    $fail('Event studio (Internal) tidak bisa punya tanggal pengganti. Gunakan fitur Reschedule.');
                }
            },
        ],
        'is_honor_paid'    => 'nullable|boolean',
    ], [
        'date.unique'             => 'Tanggal libur ini sudah ada di sistem.',
        'replacement_date.unique' => 'Tanggal pengganti ini sudah dipakai oleh hari libur lain.',
    ]);

    $holiday->update([
        'date'             => $data['date'],
        'name'             => $data['name'],
        'type'             => $data['type'],
        'notes'            => $data['notes'] ?? null,
        'is_active'        => $request->boolean('is_active', true),
        'replacement_date' => $data['replacement_date'] ?? null,
        'is_honor_paid'    => $data['type'] === 'Internal'
            ? false
            : $request->boolean('is_honor_paid', true),
    ]);

    return redirect()->route('holidays.index')
        ->with('success', 'Hari libur berhasil diperbarui.');
}
```

- [ ] **Step 3: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/HolidayController.php
git commit -m "M03: HolidayController — validasi replacement_date + is_honor_paid"
```

---

## Task 7: Update Holiday Form View (`_form.blade.php`) — Alpine.js Auto-Suggest

**Files:**
- Modify: `resources/views/holidays/_form.blade.php`

- [ ] **Step 1: Ganti seluruh isi `resources/views/holidays/_form.blade.php`**

```blade
@php $holiday = $holiday ?? null; @endphp

@if($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">
        <ul class="text-sm text-red-700 list-disc pl-5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- Alpine.js component untuk auto-suggest replacement_date --}}
<div x-data="{
    holidayDate: '{{ old('date', $holiday?->date?->format('Y-m-d') ?? '') }}',
    type: '{{ old('type', $holiday->type ?? '') }}',
    replacementDate: '{{ old('replacement_date', $holiday?->replacement_date?->format('Y-m-d') ?? '') }}',
    suggestion: '',
    isInternal: false,

    get replacementDisabled() {
        return this.type === 'Internal';
    },

    computeSuggestion() {
        this.isInternal = this.type === 'Internal';
        if (this.isInternal) {
            this.suggestion = '';
            return;
        }
        if (!this.holidayDate) { this.suggestion = ''; return; }

        const d   = new Date(this.holidayDate);
        const dow = d.getDay(); // 0=Minggu, 1=Senin, ..., 6=Sabtu
        const y   = d.getFullYear();
        const m   = d.getMonth(); // 0-indexed

        // Cari semua tanggal dengan day-of-week yang sama di bulan ini
        const occurrences = [];
        const daysInMonth = new Date(y, m + 1, 0).getDate();
        for (let day = 1; day <= daysInMonth; day++) {
            const candidate = new Date(y, m, day);
            if (candidate.getDay() === dow) occurrences.push(candidate);
        }

        if (occurrences.length < 5) {
            this.suggestion = 'Tidak ada minggu ke-5 di bulan ini';
        } else {
            const week5 = occurrences[4];
            const fmt   = week5.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            const ymd   = week5.toISOString().split('T')[0];
            this.suggestion = 'Saran: ' + fmt + ' (' + ymd + ')';
            this._suggestedDate = ymd;
        }
    },

    useSuggestion() {
        if (this._suggestedDate) this.replacementDate = this._suggestedDate;
    }
}" x-init="computeSuggestion()">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Tanggal Libur --}}
        <div>
            <label class="block text-sm font-medium">Tanggal <span class="text-red-500">*</span></label>
            <input type="date" name="date" required
                   x-model="holidayDate"
                   @change="computeSuggestion()"
                   class="mt-1 block w-full border-gray-300 rounded-md">
        </div>

        {{-- Tipe --}}
        <div>
            <label class="block text-sm font-medium">Tipe <span class="text-red-500">*</span></label>
            <select name="type" required
                    x-model="type"
                    @change="computeSuggestion()"
                    class="mt-1 block w-full border-gray-300 rounded-md">
                @php $types = ['Nasional', 'Cuti Bersama', 'Internal']; @endphp
                <option value="">— Pilih —</option>
                @foreach($types as $t)
                    <option value="{{ $t }}"
                        {{ old('type', $holiday->type ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
        </div>

        {{-- Nama Hari Libur --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Nama Hari Libur <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="100"
                   value="{{ old('name', $holiday->name ?? '') }}"
                   class="mt-1 block w-full border-gray-300 rounded-md"
                   placeholder="Tahun Baru Masehi">
        </div>

        {{-- Tanggal Pengganti --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">
                Tanggal Pengganti
                <span class="text-gray-400 text-xs font-normal ml-1">(opsional — dalam bulan yang sama)</span>
            </label>

            <div class="mt-1 flex gap-2 items-start">
                <input type="date" name="replacement_date"
                       x-model="replacementDate"
                       :disabled="replacementDisabled"
                       :class="replacementDisabled ? 'opacity-40 cursor-not-allowed bg-gray-100' : ''"
                       class="block w-full border-gray-300 rounded-md">

                <button type="button"
                        x-show="!replacementDisabled && suggestion && suggestion.startsWith('Saran:')"
                        @click="useSuggestion()"
                        class="shrink-0 px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                    Pakai Saran
                </button>
            </div>

            {{-- Hint / suggestion --}}
            <p class="mt-1 text-xs"
               :class="replacementDisabled ? 'text-amber-600' : 'text-gray-500'"
               x-text="replacementDisabled
                   ? 'Event studio (Internal) — gunakan fitur Reschedule untuk sesi pengganti lintas bulan.'
                   : suggestion">
            </p>
        </div>

        {{-- Catatan --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Catatan</label>
            <textarea name="notes" rows="2"
                      class="mt-1 block w-full border-gray-300 rounded-md"
                      placeholder="Tanggal estimasi, perlu konfirmasi SKB Menteri">{{ old('notes', $holiday->notes ?? '') }}</textarea>
        </div>

        {{-- Aktif + Honor --}}
        <div class="flex items-center gap-6">
            <label class="inline-flex items-center">
                <input type="checkbox" name="is_active" value="1"
                    {{ old('is_active', $holiday->is_active ?? true) ? 'checked' : '' }}
                    class="rounded border-gray-300">
                <span class="ml-2 text-sm">Aktif</span>
            </label>

            <label class="inline-flex items-center"
                   :class="isInternal ? 'opacity-40' : ''">
                <input type="checkbox" name="is_honor_paid" value="1"
                    :disabled="isInternal"
                    {{ old('is_honor_paid', $holiday->is_honor_paid ?? true) ? 'checked' : '' }}
                    class="rounded border-gray-300">
                <span class="ml-2 text-sm">Guru mendapat honor saat libur ini</span>
                <span x-show="isInternal" class="ml-1 text-xs text-amber-600">(otomatis tidak untuk Internal)</span>
            </label>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Build assets**

```bash
npm run build
```

- [ ] **Step 3: Verifikasi manual di browser**

Buka halaman tambah hari libur (`/holidays/create`), coba:
1. Pilih tipe "Internal" → field Tanggal Pengganti di-disable, checkbox honor otomatis uncheck
2. Pilih tipe "Nasional", isi tanggal yang punya minggu ke-5 → hint "Saran: ..." muncul, tombol "Pakai Saran" aktif
3. Pilih tipe "Nasional", isi tanggal di bulan tanpa minggu ke-5 → hint "Tidak ada minggu ke-5 di bulan ini"

- [ ] **Step 4: Commit**

```bash
git add resources/views/holidays/_form.blade.php
git commit -m "M03: Holiday form — tambah replacement_date field dengan Alpine.js auto-suggest"
```

---

## Task 8: M08 — Guru Pendamping di Event Show

**Files:**
- Modify: `app/Http/Controllers/EventController.php`
- Modify: `resources/views/events/show.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Tambah method `updateParticipantTeacher()` di `EventController`**

Tambah method baru setelah method `removeParticipant()`:

```php
/**
 * Update guru pendamping untuk satu peserta event.
 * Hanya bisa diubah selama event masih DRAFT.
 * Digunakan untuk Konser KITA — mencatat guru yang mendampingi murid saat konser.
 */
public function updateParticipantTeacher(Request $request, EventParticipant $participant): \Illuminate\Http\RedirectResponse
{
    $this->authorize('update', $participant->event);

    if ($participant->event->isCompleted()) {
        return back()->with('error', 'Tidak bisa ubah guru pendamping — event sudah selesai.');
    }

    $request->validate([
        'accompanying_teacher_id' => 'nullable|exists:teachers,id',
    ]);

    $participant->update([
        'accompanying_teacher_id' => $request->input('accompanying_teacher_id') ?: null,
    ]);

    return back()->with('success', 'Guru pendamping berhasil diperbarui.');
}
```

Tambah juga import di bagian atas file (jika belum ada):
```php
use App\Models\EventParticipant;
```

- [ ] **Step 2: Tambah route di `routes/web.php`**

Cari block route event `role:Owner|Admin` (sekitar baris 259-265) dan tambahkan route baru:

```php
Route::patch('event-participants/{participant}/teacher', [EventController::class, 'updateParticipantTeacher'])
    ->name('event-participants.update-teacher');
```

- [ ] **Step 3: Update participants table di `resources/views/events/show.blade.php`**

Di bagian thead tabel peserta, tambah kolom "Guru Pendamping" sebelum kolom "Hapus":

Cari baris thead (sekitar baris 68-77) dan tambah `<th>`:
```blade
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guru Pendamping</th>
```

Di dalam loop `@foreach($event->participants as $participant)`, tambah kolom baru sebelum kolom hapus:

```blade
{{-- Guru Pendamping (hanya tampil untuk MINI_CONCERT) --}}
@if(in_array($event->type, ['MINI_CONCERT', 'MINI_CONCERT_UJIAN']))
<td class="px-4 py-3">
    @if($event->isDraft() && (auth()->user()->hasRole('Owner') || auth()->user()->hasRole('Admin')))
        <form method="POST"
              action="{{ route('event-participants.update-teacher', $participant) }}"
              class="flex items-center gap-1">
            @csrf @method('PATCH')
            <select name="accompanying_teacher_id"
                    onchange="this.form.submit()"
                    class="text-sm border-gray-300 rounded-md py-1">
                <option value="">— Tidak ada —</option>
                @foreach($activeTeachers as $teacher)
                    <option value="{{ $teacher->id }}"
                        {{ $participant->accompanying_teacher_id == $teacher->id ? 'selected' : '' }}>
                        {{ $teacher->name }}
                    </option>
                @endforeach
            </select>
        </form>
    @else
        <span class="text-sm text-gray-600">
            {{ $participant->accompanyingTeacher?->name ?? '—' }}
        </span>
    @endif
</td>
@endif
```

- [ ] **Step 4: Pass `$activeTeachers` dari controller ke view**

Di `EventController::show()`, tambah query teachers aktif:

```php
public function show(Event $event): \Illuminate\View\View
{
    $event->load(['participants.student', 'participants.accompanyingTeacher']);

    $availableStudents = Student::where('status', 'Aktif')
        ->whereDoesntHave('eventParticipants', fn ($q) => $q->where('event_id', $event->id))
        ->orderBy('full_name')
        ->get();

    // Untuk dropdown guru pendamping di Konser KITA
    $activeTeachers = \App\Models\Teacher::where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name']);

    return view('events.show', compact('event', 'availableStudents', 'activeTeachers'));
}
```

- [ ] **Step 5: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua PASS.

- [ ] **Step 6: Verifikasi manual**

Buka halaman detail event bertipe MINI_CONCERT, pastikan kolom "Guru Pendamping" muncul dengan dropdown.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/EventController.php \
        resources/views/events/show.blade.php \
        routes/web.php
git commit -m "M08: Event show — tambah kolom guru pendamping per peserta Konser KITA"
```

---

## Task 9: Update `CLAUDE.md` — Tambah Honor Code `H_IZIN`

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Tambah `H_IZIN` ke tabel honor codes di CLAUDE.md**

Cari bagian "Honor Guru -- 9 Skenario" dan tambah baris `H_IZIN`:

```markdown
H_IZIN    | IZIN_RESCHEDULE sesi original (guru tidak datang) | Rp 0 (honor via sesi pengganti)
```

Sehingga tabelnya menjadi:

```
Kode      | Skenario                                        | Formula
H_REG     | Sesi terlaksana (hadir/telat)                   | harga x 50% / 4
H_TRIAL   | Sesi trial (murid HADIR)                        | Sama H_REG sesuai paket calon
TRIAL_NS  | Trial murid NO-SHOW [v1.1]                      | Rp 0 (honor NOL)
H_VIDEO   | Izin video pengganti                            | Sama H_REG
H_LIBUR   | Sesi libur nasional (tanpa replacement_date)    | Sama H_REG (full pay, BR-4.10)
H_HANGUS  | Murid no-show / hangus                         | Sama H_REG (full pay)
H_PENG    | Diajar guru pengganti                           | H_REG -> ke guru pengganti
H_KIDS    | Sesi Kids Class                                 | murid_terdaftar x Rp 42.500
H_UJIAN   | Pengawas ujian grade                            | Rp 250.000 flat/ujian
H_IZIN    | IZIN_RESCHEDULE sesi original (guru tidak datang)| Rp 0 — honor dibayar via sesi pengganti
```

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "Docs: Tambah H_IZIN ke daftar honor codes di CLAUDE.md"
```

---

## Ringkasan Urutan Eksekusi

```
Task 1 → Task 2 → Task 3 → Task 4 → Task 5 → Task 6 → Task 7 → Task 8 → Task 9
Migrasi   Migrasi  Migrasi   Model    Service   Controller  View     M08       Docs
holidays  evpart   backfill  update   rewrite   holiday     holiday  event     CLAUDE
```

Tasks 1-4 harus dikerjakan berurutan (dependency DB → Model).
Tasks 5-9 bisa dikerjakan setelah Task 4 selesai, urutan disarankan seperti di atas.
