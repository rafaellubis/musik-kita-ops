# Honor Guru Pendamping Konser KITA — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Inject honor Rp 250.000 otomatis ke slip guru pendamping saat event Konser KITA di-mark COMPLETED, dengan nominal yang bisa dikonfigurasi Owner via halaman Payroll Config.

**Architecture:** Service baru `EventHonorService` dipanggil dari `EventController::complete()`. Service membaca nominal dari `payroll_configs` (kode `H_PENDAMPING`), cari/buat slip honor bulan event, inject honor ke `event_honor`, kembalikan summary untuk flash message. Session zeroing tidak diperlukan — sudah ditangani Holiday Internal.

**Tech Stack:** Laravel 11, PHP 8.3, PHPUnit (Feature tests), Blade

**Spec:** `docs/superpowers/specs/2026-05-26-honor-guru-pendamping-design.md`

---

## File Structure

| File | Status | Tanggung Jawab |
|------|--------|----------------|
| `app/Services/EventHonorService.php` | **BARU** | Logic inject honor, warning check, slip number generator |
| `app/Http/Controllers/EventController.php` | **MODIFIKASI** | Inject service, panggil di `complete()`, format flash message |
| `database/seeders/PayrollConfigSeeder.php` | **MODIFIKASI** | Tambah entry `H_PENDAMPING` |
| `resources/views/events/show.blade.php` | **MODIFIKASI** | Tampilkan flash message hasil proses |
| `tests/Feature/EventHonorServiceTest.php` | **BARU** | Test suite untuk EventHonorService |

---

## Task 1: Tambah H_PENDAMPING ke PayrollConfigSeeder

**Files:**
- Modify: `database/seeders/PayrollConfigSeeder.php`

- [ ] **Step 1: Buka dan baca seeder yang ada**

Buka `database/seeders/PayrollConfigSeeder.php`. Temukan array data seeder (berisi H_UJIAN, H_KIDS, dll).

- [ ] **Step 2: Tambahkan entry H_PENDAMPING**

Tambahkan baris berikut ke array data seeder, setelah entry `H_UJIAN`:

```php
['H_PENDAMPING', 'Honor Guru Pendamping Konser KITA', 'FIXED', '250000',
 'Honor flat per event untuk guru yang mendampingi murid di Konser KITA. Bisa berbeda dengan H_UJIAN.'],
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/PayrollConfigSeeder.php
git commit -m "M06: Tambah H_PENDAMPING ke PayrollConfigSeeder"
```

---

## Task 2: Tulis Test Suite EventHonorService (Failing)

**Files:**
- Create: `tests/Feature/EventHonorServiceTest.php`

- [ ] **Step 1: Buat file test**

Buat `tests/Feature/EventHonorServiceTest.php` dengan isi berikut:

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Holiday;
use App\Models\HonorSlip;
use App\Models\PayrollConfig;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EventHonorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventHonorServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventHonorService $service;
    private int $createdBy = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventHonorService::class);
    }

    private function makeEvent(string $date = '2026-06-15'): Event
    {
        return Event::factory()->create([
            'event_date' => $date,
            'status'     => Event::STATUS_COMPLETED,
            'name'       => 'Konser KITA Juni 2026',
        ]);
    }

    private function makeTeacher(): Teacher
    {
        return Teacher::factory()->create(['is_active' => true]);
    }

    private function addParticipantWithTeacher(Event $event, Teacher $accompanyingTeacher): EventParticipant
    {
        $student = Student::factory()->create();
        return EventParticipant::factory()->create([
            'event_id'                => $event->id,
            'student_id'              => $student->id,
            'accompanying_teacher_id' => $accompanyingTeacher->id,
        ]);
    }

    // =========================================================
    // 1. Honor diinjeksi ke slip yang sudah ada
    // =========================================================

    public function test_inject_honor_into_existing_slip(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        // Slip sudah ada sebelumnya dengan event_honor = 0
        $slip = HonorSlip::factory()->create([
            'teacher_id'   => $teacher->id,
            'month'        => 6,
            'year'         => 2026,
            'base_honor'   => 100000,
            'event_honor'  => 0,
            'status'       => HonorSlip::STATUS_CALCULATED,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $slip->refresh();
        $this->assertEquals(1, $result['slips_updated']);
        $this->assertEquals(0, $result['slips_skipped']);
        $this->assertEquals(250000, $slip->event_honor);
        $this->assertEquals(350000, $slip->total_honor); // 100000 + 250000
    }

    // =========================================================
    // 2. Slip baru dibuat jika belum ada
    // =========================================================

    public function test_create_new_slip_when_none_exists(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(1, $result['slips_updated']);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)
            ->first();

        $this->assertNotNull($slip);
        $this->assertEquals(250000, $slip->event_honor);
        $this->assertStringStartsWith('SLIP/2026/06/', $slip->slip_number);
        $this->assertEquals(HonorSlip::STATUS_DRAFT, $slip->status);
    }

    // =========================================================
    // 3. Slip PAID dilewati
    // =========================================================

    public function test_skip_locked_slip(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        HonorSlip::factory()->create([
            'teacher_id'  => $teacher->id,
            'month'       => 6,
            'year'        => 2026,
            'event_honor' => 0,
            'status'      => HonorSlip::STATUS_PAID,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(0, $result['slips_updated']);
        $this->assertEquals(1, $result['slips_skipped']);
    }

    // =========================================================
    // 4. Warning jika tidak ada Holiday Internal di event_date
    // =========================================================

    public function test_warning_when_no_internal_holiday(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        // Tidak ada holiday sama sekali

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertTrue($result['holiday_warning']);
    }

    // =========================================================
    // 5. Tidak ada warning jika Holiday Internal tersedia
    // =========================================================

    public function test_no_warning_when_internal_holiday_exists(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        Holiday::factory()->create([
            'date'           => '2026-06-15',
            'type'           => 'Internal',
            'is_honor_paid'  => false,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertFalse($result['holiday_warning']);
    }

    // =========================================================
    // 6. Dua guru pendamping → dua slip diperbarui
    // =========================================================

    public function test_multiple_teachers_get_separate_honor(): void
    {
        $event    = $this->makeEvent('2026-06-15');
        $teacher1 = $this->makeTeacher();
        $teacher2 = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher1);
        $this->addParticipantWithTeacher($event, $teacher2);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(2, $result['slips_updated']);

        foreach ([$teacher1, $teacher2] as $teacher) {
            $slip = HonorSlip::where('teacher_id', $teacher->id)
                ->where('month', 6)->where('year', 2026)->first();
            $this->assertNotNull($slip);
            $this->assertEquals(250000, $slip->event_honor);
        }
    }

    // =========================================================
    // 7. Satu guru dampingi banyak murid → honor tetap sekali
    // =========================================================

    public function test_teacher_accompanying_multiple_students_gets_honor_once(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();

        // Guru ini mendampingi 3 murid
        $this->addParticipantWithTeacher($event, $teacher);
        $this->addParticipantWithTeacher($event, $teacher);
        $this->addParticipantWithTeacher($event, $teacher);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(1, $result['slips_updated']);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)->first();
        $this->assertEquals(250000, $slip->event_honor); // bukan 750000
    }

    // =========================================================
    // 8. Nominal dibaca dari PayrollConfig H_PENDAMPING
    // =========================================================

    public function test_honor_amount_read_from_payroll_config(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        // Override nominal via PayrollConfig
        PayrollConfig::factory()->create([
            'scenario_code'    => 'H_PENDAMPING',
            'formula_type'     => 'FIXED',
            'value_or_formula' => '300000',
            'is_active'        => true,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)->first();
        $this->assertEquals(300000, $slip->event_honor);
    }

    // =========================================================
    // 9. event_honor_note di-append jika sudah ada isi
    // =========================================================

    public function test_note_appended_when_existing_event_honor_note(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        HonorSlip::factory()->create([
            'teacher_id'       => $teacher->id,
            'month'            => 6,
            'year'             => 2026,
            'event_honor'      => 200000,
            'event_honor_note' => 'Pendamping Ujian Mei',
            'status'           => HonorSlip::STATUS_CALCULATED,
        ]);

        $this->service->processEventCompletion($event, $this->createdBy);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)->first();

        $this->assertStringContainsString('Pendamping Ujian Mei', $slip->event_honor_note);
        $this->assertStringContainsString('Konser KITA Juni 2026', $slip->event_honor_note);
        $this->assertStringContainsString(' | ', $slip->event_honor_note);
        $this->assertEquals(450000, $slip->event_honor); // 200000 + 250000
    }

    // =========================================================
    // 10. Tidak ada guru pendamping → slips_updated = 0
    // =========================================================

    public function test_no_accompanying_teachers_returns_zero_updated(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $student = Student::factory()->create();

        // Peserta tanpa guru pendamping
        EventParticipant::factory()->create([
            'event_id'                => $event->id,
            'student_id'              => $student->id,
            'accompanying_teacher_id' => null,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(0, $result['slips_updated']);
        $this->assertEquals(0, $result['slips_skipped']);
    }
}
```

- [ ] **Step 2: Jalankan test dan verifikasi semua FAIL**

```bash
php artisan test tests/Feature/EventHonorServiceTest.php
```

Expected: semua 10 test FAIL dengan `Class "App\Services\EventHonorService" not found` atau serupa.

- [ ] **Step 3: Commit test yang failing**

```bash
git add tests/Feature/EventHonorServiceTest.php
git commit -m "Tests: Tulis failing tests EventHonorService (TDD Step 1)"
```

---

## Task 3: Implementasi EventHonorService

**Files:**
- Create: `app/Services/EventHonorService.php`

- [ ] **Step 1: Buat file service**

Buat `app/Services/EventHonorService.php`:

```php
<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Holiday;
use App\Models\HonorSlip;
use App\Models\PayrollConfig;

class EventHonorService
{
    private const DEFAULT_PENDAMPING_HONOR = 250000;

    /**
     * Proses honor guru pendamping saat event di-mark COMPLETED.
     *
     * Melakukan dua hal:
     * 1. Cek keberadaan Holiday Internal di tanggal event (warning jika tidak ada)
     * 2. Inject honor H_PENDAMPING ke slip bulan event untuk setiap guru pendamping unik
     *
     * @return array{slips_updated: int, slips_skipped: int, holiday_warning: bool}
     */
    public function processEventCompletion(Event $event, int $createdBy): array
    {
        $result = [
            'slips_updated'  => 0,
            'slips_skipped'  => 0,
            'holiday_warning' => false,
        ];

        // Step 1: Cek apakah ada Holiday Internal di tanggal event
        $hasHoliday = Holiday::where('date', $event->event_date)
            ->where('type', 'Internal')
            ->exists();

        if (!$hasHoliday) {
            $result['holiday_warning'] = true;
        }

        // Step 2: Baca nominal honor dari PayrollConfig
        $config      = PayrollConfig::where('scenario_code', 'H_PENDAMPING')
            ->where('is_active', true)
            ->first();
        $honorAmount = (int) ($config?->value_or_formula ?? self::DEFAULT_PENDAMPING_HONOR);

        // Step 3: Ambil semua guru pendamping unik dari peserta event
        $teacherIds = $event->participants()
            ->whereNotNull('accompanying_teacher_id')
            ->pluck('accompanying_teacher_id')
            ->unique();

        if ($teacherIds->isEmpty()) {
            return $result;
        }

        $month = $event->event_date->month;
        $year  = $event->event_date->year;

        foreach ($teacherIds as $teacherId) {
            $slip = HonorSlip::where('teacher_id', $teacherId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (!$slip) {
                $slip = new HonorSlip([
                    'slip_number'     => $this->generateSlipNumber($year, $month),
                    'teacher_id'      => $teacherId,
                    'month'           => $month,
                    'year'            => $year,
                    'base_honor'      => 0,
                    'event_honor'     => 0,
                    'transport_honor' => 0,
                    'other_honor'     => 0,
                    'status'          => HonorSlip::STATUS_DRAFT,
                    'created_by'      => $createdBy,
                ]);
            }

            if ($slip->isLocked()) {
                $result['slips_skipped']++;
                continue;
            }

            $slip->event_honor = ($slip->event_honor ?? 0) + $honorAmount;
            $slip->event_honor_note = trim(
                ($slip->event_honor_note ? $slip->event_honor_note . ' | ' : '')
                . "Pendamping {$event->name}"
            );
            $slip->recalcTotal();
            $slip->save();

            $result['slips_updated']++;
        }

        return $result;
    }

    /**
     * Generate nomor slip format SLIP/YYYY/MM/NNNN (reset per bulan).
     * Duplikasi dari HonorCalculationService — disengaja agar dua service tidak saling bergantung.
     */
    private function generateSlipNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $latest = HonorSlip::where('slip_number', 'like', "SLIP/{$year}/{$monthStr}/%")
            ->orderBy('slip_number', 'desc')
            ->value('slip_number');

        $nextSeq = 1;
        if ($latest) {
            $parts   = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('SLIP/%d/%s/%04d', $year, $monthStr, $nextSeq);
    }
}
```

- [ ] **Step 2: Jalankan test suite dan verifikasi semua PASS**

```bash
php artisan test tests/Feature/EventHonorServiceTest.php
```

Expected: 10 tests PASS. Jika ada yang fail, investigasi dan perbaiki service — jangan ubah test.

- [ ] **Step 3: Commit**

```bash
git add app/Services/EventHonorService.php
git commit -m "M08: Implementasi EventHonorService — inject honor guru pendamping saat event COMPLETED"
```

---

## Task 4: Integrasi ke EventController

**Files:**
- Modify: `app/Http/Controllers/EventController.php`

- [ ] **Step 1: Tambahkan factory PayrollConfig jika belum ada**

Cek apakah `database/factories/PayrollConfigFactory.php` sudah ada:

```bash
ls database/factories/PayrollConfigFactory.php
```

Jika **belum ada**, buat file tersebut:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scenario_code'    => $this->faker->unique()->word(),
            'scenario_name'    => $this->faker->sentence(3),
            'formula_type'     => 'FIXED',
            'value_or_formula' => '250000',
            'description'      => $this->faker->sentence(),
            'is_active'        => true,
        ];
    }
}
```

- [ ] **Step 2: Tulis feature test untuk controller**

Tambahkan file `tests/Feature/EventCompleteControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\HonorSlip;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCompleteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ownerUser(): User
    {
        return User::factory()->create()->assignRole('Owner');
    }

    public function test_complete_event_injects_honor_and_shows_flash(): void
    {
        $owner   = $this->ownerUser();
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create();

        $event = Event::factory()->create([
            'status'     => Event::STATUS_DRAFT,
            'event_date' => '2026-06-15',
            'name'       => 'Konser KITA Juni 2026',
        ]);

        EventParticipant::factory()->create([
            'event_id'                => $event->id,
            'student_id'              => $student->id,
            'accompanying_teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($owner)
            ->post(route('events.complete', $event));

        $response->assertRedirect(route('events.show', $event));

        // Event status berubah ke COMPLETED
        $this->assertEquals(Event::STATUS_COMPLETED, $event->fresh()->status);

        // Slip honor dibuat
        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)->first();
        $this->assertNotNull($slip);
        $this->assertEquals(250000, $slip->event_honor);
    }

    public function test_complete_already_completed_event_returns_error(): void
    {
        $owner = $this->ownerUser();
        $event = Event::factory()->create(['status' => Event::STATUS_COMPLETED]);

        $response = $this->actingAs($owner)
            ->post(route('events.complete', $event));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
```

- [ ] **Step 3: Jalankan test dan verifikasi FAIL**

```bash
php artisan test tests/Feature/EventCompleteControllerTest.php
```

Expected: `test_complete_event_injects_honor_and_shows_flash` FAIL karena service belum diinjeksi.

- [ ] **Step 4: Modifikasi EventController**

Buka `app/Http/Controllers/EventController.php`. Tambahkan:

**a) Import di bagian `use`:**
```php
use App\Services\EventHonorService;
```

**b) Tambahkan property dan constructor injection** (cari constructor yang ada atau tambahkan baru):

```php
public function __construct(private EventHonorService $eventHonorService)
{
}
```

**c) Ganti method `complete()` (baris ~339-348) dengan:**

```php
public function complete(Event $event)
{
    if ($event->isCompleted()) {
        return back()->with('error', 'Event sudah selesai.');
    }

    $event->update(['status' => Event::STATUS_COMPLETED]);

    $result = $this->eventHonorService->processEventCompletion($event, auth()->id());

    // Susun flash message dari hasil proses
    $messages = [];

    if ($result['slips_updated'] > 0) {
        $messages[] = "{$result['slips_updated']} slip honor guru pendamping diperbarui.";
    } elseif ($result['slips_updated'] === 0 && $result['slips_skipped'] === 0) {
        $messages[] = 'Tidak ada guru pendamping yang terdaftar.';
    }

    if ($result['slips_skipped'] > 0) {
        $messages[] = "{$result['slips_skipped']} slip dilewati karena sudah berstatus PAID — perlu update manual.";
    }

    $successMsg = 'Event ditandai selesai. ' . implode(' ', $messages);

    if ($result['holiday_warning']) {
        return redirect()->route('events.show', $event)
            ->with('success', $successMsg)
            ->with('warning', 'Tidak ada Hari Libur Internal untuk tanggal ini. Pastikan sesi kelas sudah diatur via menu Hari Libur.');
    }

    return redirect()->route('events.show', $event)
        ->with('success', $successMsg);
}
```

- [ ] **Step 5: Jalankan semua test terkait**

```bash
php artisan test tests/Feature/EventHonorServiceTest.php tests/Feature/EventCompleteControllerTest.php
```

Expected: 12 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/EventController.php tests/Feature/EventCompleteControllerTest.php
git commit -m "M08: Integrasi EventHonorService ke EventController::complete()"
```

---

## Task 5: Update View Flash Message

**Files:**
- Modify: `resources/views/events/show.blade.php`

- [ ] **Step 1: Cek apakah flash `warning` sudah ditangani di layout**

Buka `resources/views/layouts/app.blade.php`. Cari bagian yang menampilkan `session('success')` atau `session('error')`.

Jika sudah ada handler untuk `session('warning')` → **lewati Step 2**, lanjut ke Step 3.

Jika belum ada handler `warning` → lanjut ke Step 2.

- [ ] **Step 2: Tambahkan handler flash warning di layout (jika belum ada)**

Di `resources/views/layouts/app.blade.php`, di bagian flash messages, tambahkan setelah blok flash `success`:

```blade
@if(session('warning'))
    <div class="bg-yellow-900/30 border border-yellow-600/40 text-yellow-300 px-4 py-3 rounded-lg mb-4 text-sm">
        {{ session('warning') }}
    </div>
@endif
```

- [ ] **Step 3: Verifikasi flash success sudah tampil di events/show.blade.php**

Buka `resources/views/events/show.blade.php`. Pastikan halaman ini merender flash messages dari layout (biasanya sudah otomatis via `@extends('layouts.app')`). Tidak perlu perubahan jika layout sudah menangani flash.

- [ ] **Step 4: Build assets**

```bash
npm run build
```

- [ ] **Step 5: Test manual**

1. Login sebagai Owner (`owner@musikkita.local` / `password`)
2. Buka halaman Events → buat event baru dengan `event_date` di masa lalu
3. Tambah peserta, assign guru pendamping
4. Klik tombol "Tandai Selesai"
5. Verifikasi:
   - Flash message sukses muncul
   - Jika tidak ada Holiday Internal: flash warning kuning muncul
   - Buka halaman Honor Slip bulan event → pastikan `event_honor` guru pendamping terisi Rp 250.000

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/views/events/show.blade.php
git commit -m "M08: Tampilkan flash message hasil inject honor guru pendamping"
```

---

## Task 6: Seed H_PENDAMPING ke Database

- [ ] **Step 1: Jalankan seeder di database utama**

> ⚠️ Jalankan hanya jika sistem sudah berjalan dengan data live, atau setelah `migrate:fresh --seed`.
> Seeder ini bersifat upsert — aman dijalankan berulang jika menggunakan `updateOrInsert`.

Cek dulu apakah `PayrollConfigSeeder` sudah menggunakan `updateOrInsert`. Jika ya:

```bash
php artisan db:seed --class=PayrollConfigSeeder
```

Jika menggunakan `insert` biasa (bisa duplikasi), tambahkan manual via Tinker:

```bash
php artisan tinker
```

```php
\App\Models\PayrollConfig::firstOrCreate(
    ['scenario_code' => 'H_PENDAMPING'],
    [
        'scenario_name'    => 'Honor Guru Pendamping Konser KITA',
        'formula_type'     => 'FIXED',
        'value_or_formula' => '250000',
        'description'      => 'Honor flat per event untuk guru yang mendampingi murid di Konser KITA.',
        'is_active'        => true,
    ]
);
```

- [ ] **Step 2: Verifikasi di halaman Payroll Config**

Login sebagai Owner → buka `/payroll-configs` → pastikan `H_PENDAMPING` muncul dengan nilai 250.000.

---

## Checklist Self-Review

- [x] `processEventCompletion` signature konsisten di test dan implementasi (Event $event, int $createdBy)
- [x] `HonorSlip::STATUS_DRAFT`, `STATUS_PAID`, `isLocked()`, `recalcTotal()` — semua sudah ada di model
- [x] `generateSlipNumber` format `SLIP/YYYY/MM/NNNN` konsisten dengan yang ada di codebase
- [x] `event_honor += honorAmount` bukan assignment langsung — guru bisa dapat honor dari 2 event sebulan
- [x] `unique()` pada `teacherIds` — guru dampingi banyak murid tetap dapat honor sekali
- [x] Fallback 250000 jika H_PENDAMPING belum ada di PayrollConfig
- [x] Flash warning `session('warning')` terpisah dari `session('success')` — keduanya bisa muncul bersamaan
