# Split Reschedule Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin bisa membagi satu sesi 30 menit (IZIN_RESCHEDULE) menjadi 2 bagian 15 menit di tanggal/jam berbeda, dengan honor dibagi rata per bagian.

**Architecture:** Tambah kolom `split_part` (tinyint nullable, 1 atau 2) ke `class_sessions`. Part 1 dibuat bersamaan dengan penandaan original sebagai IZIN_RESCHEDULE via endpoint baru `POST /absensi/{session}/split/{part}`. Part 2 dibuat dari baris Part 1 via tombol "Tambah Bagian 2". Conflict detection dan label sesi mengikuti pola reschedule normal.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL, Alpine.js, Blade, Tailwind CSS, PHPUnit (RefreshDatabase + SQLite in-memory)

---

## File Structure

| File | Aksi | Tanggung Jawab |
|---|---|---|
| `database/migrations/2026_05_24_XXXXXX_add_split_part_to_class_sessions.php` | Create | Tambah kolom `split_part` + index |
| `app/Models/ClassSession.php` | Modify | `split_part` di fillable/casts, update `getSessionLabel()` |
| `app/Services/RescheduleService.php` | Modify | Tambah `createSplitPart()` |
| `app/Http/Requests/StoreSplitPartRequest.php` | Create | Validasi input tanggal/jam/ruang untuk split |
| `app/Http/Controllers/AbsensiController.php` | Modify | Tambah `storeSplitPart()`, update `index()` |
| `routes/web.php` | Modify | Route `POST /absensi/{session}/split/{part}` |
| `resources/views/absensi/_row.blade.php` | Modify | Toggle split di modal, tombol + modal Part 2 |
| `CLAUDE.md` | Modify | Tambah `H_SPLIT` ke tabel honor code |
| `tests/Feature/Admin/SplitRescheduleTest.php` | Create | Unit + integration tests |

---

## Task 1: Migration — Tambah Kolom `split_part`

**Files:**
- Create: `database/migrations/2026_05_24_000002_add_split_part_to_class_sessions.php`

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
            // null = sesi normal, 1 = bagian pertama split, 2 = bagian kedua split
            $table->tinyInteger('split_part')->unsigned()->nullable()->after('origin_session_id');
            // Index untuk query "apakah Part 2 sudah ada untuk origin ini?"
            $table->index(['origin_session_id', 'split_part'], 'cs_origin_split_idx');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex('cs_origin_split_idx');
            $table->dropColumn('split_part');
        });
    }
};
```

- [ ] **Step 2: Jalankan migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_05_24_000002_add_split_part_to_class_sessions` → `Migrated`

- [ ] **Step 3: Verifikasi kolom ada**

```bash
php artisan tinker --execute="echo implode(', ', array_column(\DB::select('DESCRIBE class_sessions'), 'Field'));"
```

Expected: output mengandung `split_part`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_24_000002_add_split_part_to_class_sessions.php
git commit -m "DB: Migration tambah kolom split_part ke class_sessions"
```

---

## Task 2: ClassSession Model — fillable, casts, getSessionLabel

**Files:**
- Modify: `app/Models/ClassSession.php`

- [ ] **Step 1: Tulis tes yang gagal**

Buat file `tests/Feature/Admin/SplitRescheduleTest.php` dengan tes label split:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SplitRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner',  'guard_name' => 'web']);
    }

    private function makeOriginalSession(array $override = []): ClassSession
    {
        $teacher    = Teacher::factory()->create(['name' => 'Guru Test', 'is_active' => true]);
        $student    = Student::factory()->create();
        $package    = Package::factory()->create([
            'duration_min'      => 30,
            'price_per_month'   => 340000,
            'is_active'         => true,
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create(array_merge([
            'teacher_id'       => $teacher->id,
            'student_id'       => $student->id,
            'enrollment_id'    => $enrollment->id,
            'session_date'     => '2026-05-20',
            'start_time'       => '10:00:00',
            'end_time'         => '10:30:00',
            'status'           => ClassSession::STATUS_SCHEDULED,
            'session_sequence' => 3,
        ], $override));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    /** @test */
    public function session_label_split_part1(): void
    {
        $original = $this->makeOriginalSession();
        $part1 = ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'session_sequence'  => 3,
            'split_part'        => 1,
            'session_date'      => '2026-06-05',
        ]);
        $part1->load('originSession');

        $this->assertEquals(
            'Bagian 1/2 — Reschedule dari Sesi ke-3 Bulan Mei 2026',
            $part1->getSessionLabel()
        );
    }

    /** @test */
    public function session_label_split_part2(): void
    {
        $original = $this->makeOriginalSession();
        $part2 = ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'session_sequence'  => 3,
            'split_part'        => 2,
            'session_date'      => '2026-06-12',
        ]);
        $part2->load('originSession');

        $this->assertEquals(
            'Bagian 2/2 — Reschedule dari Sesi ke-3 Bulan Mei 2026',
            $part2->getSessionLabel()
        );
    }
}
```

- [ ] **Step 2: Jalankan tes — harus GAGAL**

```bash
php artisan test tests/Feature/Admin/SplitRescheduleTest.php --filter="session_label_split"
```

Expected: FAIL — `split_part` belum ada di fillable/casts, `getSessionLabel()` belum handle split.

- [ ] **Step 3: Update ClassSession model**

Di `app/Models/ClassSession.php`:

Tambah `'split_part'` ke `$fillable` (setelah `'origin_session_id'`):
```php
'session_sequence', 'origin_session_id', 'split_part',
```

Tambah ke `$casts` (setelah `'session_sequence'`):
```php
'split_part' => 'integer',
```

Update `getSessionLabel()` — tambah blok split SEBELUM blok `origin_session_id` yang sudah ada:
```php
public function getSessionLabel(): string
{
    // Sesi split (bagian 1 atau 2) — label lebih spesifik dari reschedule biasa
    if ($this->split_part && $this->origin_session_id && $this->originSession) {
        $bulan = Carbon::parse($this->originSession->session_date)
                       ->locale('id')->translatedFormat('F Y');
        $seq   = $this->session_sequence;
        return "Bagian {$this->split_part}/2 — Reschedule dari Sesi ke-{$seq} Bulan {$bulan}";
    }

    // Sesi pengganti / reschedule biasa — ada origin
    if ($this->origin_session_id && $this->originSession) {
        $bulan = Carbon::parse($this->originSession->session_date)
                       ->locale('id')->translatedFormat('F Y');
        $seq   = $this->session_sequence;
        return "Reschedule dari Sesi ke-{$seq} Bulan {$bulan}";
    }

    // Sesi biasa dengan sequence (SCHEDULED atau LIBUR tanpa replacement)
    if ($this->session_sequence) {
        $bulan = Carbon::parse($this->session_date)->locale('id')->translatedFormat('F Y');
        return "Sesi ke-{$this->session_sequence} Bulan {$bulan}";
    }

    // LIBUR yang punya replacement — sequence null
    return '—';
}
```

- [ ] **Step 4: Jalankan tes — harus LULUS**

```bash
php artisan test tests/Feature/Admin/SplitRescheduleTest.php --filter="session_label_split"
```

Expected: 2 PASS

- [ ] **Step 5: Pastikan full test suite masih hijau**

```bash
php artisan test
```

Expected: semua tes lulus, tidak ada regresi.

- [ ] **Step 6: Commit**

```bash
git add app/Models/ClassSession.php tests/Feature/Admin/SplitRescheduleTest.php
git commit -m "M04: ClassSession — split_part di fillable/casts, getSessionLabel handle split"
```

---

## Task 3: RescheduleService — Tambah `createSplitPart()`

**Files:**
- Modify: `app/Services/RescheduleService.php`

- [ ] **Step 1: Tulis tes yang gagal** — tambah ke `SplitRescheduleTest.php`

```php
/** @test */
public function createSplitPart_membuat_sesi_dengan_durasi_setengah(): void
{
    $original = $this->makeOriginalSession();
    // Paksa original ke IZIN_RESCHEDULE (bypass AttendanceService)
    $original->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);

    $service = app(\App\Services\RescheduleService::class);
    $part1   = $service->createSplitPart($original, '2026-06-05', '14:00', null, 1);

    $this->assertEquals(1, $part1->split_part);
    $this->assertEquals($original->id, $part1->origin_session_id);
    $this->assertEquals('14:00:00', $part1->start_time);
    $this->assertEquals('14:15:00', $part1->end_time); // 30 / 2 = 15 menit
    $this->assertEquals('H_SPLIT', $part1->honor_code);
    $this->assertEquals(ClassSession::STATUS_SCHEDULED, $part1->status);
}

/** @test */
public function createSplitPart_honor_setengah_dari_normal(): void
{
    $original = $this->makeOriginalSession(); // package price_per_month = 340000
    $original->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);

    $service = app(\App\Services\RescheduleService::class);
    $part1   = $service->createSplitPart($original, '2026-06-05', '14:00', null, 1);
    $part2   = $service->createSplitPart($original, '2026-06-12', '14:00', null, 2);

    // Honor normal = 340000 * 0.5 / 4 = 42500
    // Honor split  = 42500 / 2 = 21250
    $this->assertEquals(21250, $part1->honor_amount);
    $this->assertEquals(21250, $part2->honor_amount);
    $this->assertEquals(42500, $part1->honor_amount + $part2->honor_amount);
}

/** @test */
public function createSplitPart_konflik_guru_throw_exception(): void
{
    $original = $this->makeOriginalSession();
    $original->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);

    // Sesi lain dengan guru yang sama, waktu overlap
    ClassSession::factory()->create([
        'teacher_id'   => $original->teacher_id,
        'session_date' => '2026-06-05',
        'start_time'   => '14:00:00',
        'end_time'     => '14:15:00',
        'status'       => ClassSession::STATUS_SCHEDULED,
    ]);

    $service = app(\App\Services\RescheduleService::class);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/Guru/');
    $service->createSplitPart($original, '2026-06-05', '14:00', null, 1);
}
```

- [ ] **Step 2: Jalankan tes — harus GAGAL**

```bash
php artisan test tests/Feature/Admin/SplitRescheduleTest.php --filter="createSplitPart"
```

Expected: FAIL — `createSplitPart` method not found.

- [ ] **Step 3: Tambah method `createSplitPart` ke RescheduleService**

Tambah setelah closing brace method `createReplacement()`:

```php
/**
 * Buat satu bagian dari split reschedule (½ durasi paket).
 *
 * Dipanggil oleh AbsensiController::storeSplitPart().
 * Original harus sudah IZIN_RESCHEDULE sebelum method ini dipanggil.
 *
 * @param  ClassSession  $original  Sesi asli (status IZIN_RESCHEDULE)
 * @param  string        $date      Format Y-m-d
 * @param  string        $startTime Format H:i
 * @param  int|null      $roomId
 * @param  int           $part      1 atau 2
 *
 * @throws InvalidArgumentException Jika ada konflik guru atau ruangan
 */
public function createSplitPart(
    ClassSession $original,
    string $date,
    string $startTime,
    ?int $roomId,
    int $part
): ClassSession {
    $original->loadMissing(['enrollment.package', 'teacher']);

    // Durasi split = setengah durasi paket (30 menit → 15 menit)
    $durationMin   = (int) ceil($original->enrollment->package->duration_min / 2);
    $endTime       = Carbon::createFromFormat('H:i', $startTime)->addMinutes($durationMin)->format('H:i:s');
    $startTimeFull = $startTime . ':00';

    // Cek konflik guru — sama dengan createReplacement()
    $teacherConflict = ClassSession::where('teacher_id', $original->teacher_id)
        ->whereDate('session_date', $date)
        ->where('start_time', '<', $endTime)
        ->where('end_time', '>', $startTimeFull)
        ->where('status', '!=', ClassSession::STATUS_CANCELLED)
        ->where('id', '!=', $original->id)
        ->first();

    if ($teacherConflict) {
        $namaGuru   = $original->teacher->name;
        $jamMulai   = substr($teacherConflict->start_time, 0, 5);
        $jamSelesai = substr($teacherConflict->end_time, 0, 5);
        throw new InvalidArgumentException(
            "Guru {$namaGuru} sudah ada sesi lain pada {$date} {$jamMulai}–{$jamSelesai}"
        );
    }

    // Cek konflik ruangan — skip jika tidak dipilih
    if ($roomId !== null) {
        $roomConflict = ClassSession::where('room_id', $roomId)
            ->whereDate('session_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTimeFull)
            ->where('status', '!=', ClassSession::STATUS_CANCELLED)
            ->where('id', '!=', $original->id)
            ->first();

        if ($roomConflict) {
            $room       = Room::find($roomId);
            $jamMulai   = substr($roomConflict->start_time, 0, 5);
            $jamSelesai = substr($roomConflict->end_time, 0, 5);
            throw new InvalidArgumentException(
                "Ruangan {$room->code} sudah dipakai pada {$date} {$jamMulai}–{$jamSelesai}"
            );
        }
    }

    // Honor = setengah honor normal satu sesi
    $honorAmount = (int) round($original->enrollment->package->price_per_month * 0.5 / 4 / 2);

    return ClassSession::create([
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
        'honor_code'            => 'H_SPLIT',
        'honor_amount'          => $honorAmount,
        'notes'                 => "Split bagian {$part}/2 dari sesi " . Carbon::parse($original->session_date)->format('d/m/Y'),
        'session_sequence'      => $original->session_sequence,
        'origin_session_id'     => $original->id,
        'split_part'            => $part,
    ]);
}
```

- [ ] **Step 4: Jalankan tes — harus LULUS**

```bash
php artisan test tests/Feature/Admin/SplitRescheduleTest.php --filter="createSplitPart"
```

Expected: 3 PASS

- [ ] **Step 5: Full test suite**

```bash
php artisan test
```

Expected: semua lulus.

- [ ] **Step 6: Commit**

```bash
git add app/Services/RescheduleService.php tests/Feature/Admin/SplitRescheduleTest.php
git commit -m "M04: RescheduleService — tambah createSplitPart() untuk split reschedule"
```

---

## Task 4: Form Request — `StoreSplitPartRequest`

**Files:**
- Create: `app/Http/Requests/StoreSplitPartRequest.php`

- [ ] **Step 1: Buat file**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi input untuk endpoint POST /absensi/{session}/split/{part}.
 * Dipakai untuk membuat Part 1 dan Part 2 dari split reschedule.
 */
class StoreSplitPartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Role check sudah di route middleware
    }

    public function rules(): array
    {
        return [
            'replacement_date'    => ['required', 'date', 'date_format:Y-m-d'],
            'replacement_time'    => ['required', 'date_format:H:i'],
            'replacement_room_id' => ['nullable', 'exists:rooms,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'replacement_date.required'    => 'Tanggal sesi wajib diisi.',
            'replacement_date.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'replacement_time.required'    => 'Jam mulai wajib diisi.',
            'replacement_time.date_format' => 'Format jam harus HH:MM.',
            'replacement_room_id.exists'   => 'Ruangan tidak ditemukan.',
        ];
    }
}
```

- [ ] **Step 2: Verifikasi file ada**

```bash
php artisan route:list 2>&1 | head -5
```

Expected: no error (file valid PHP).

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/StoreSplitPartRequest.php
git commit -m "M04: Tambah StoreSplitPartRequest untuk validasi input split reschedule"
```

---

## Task 5: Route — Tambah `absensi.split`

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Tambah route** di sebelah route `absensi.update` (sekitar line 167–169):

```php
Route::patch('/absensi/{classSession}',
    [AbsensiController::class, 'update']
)->name('absensi.update');

// Split reschedule: POST /absensi/{classSession}/split/1 atau /split/2
Route::post('/absensi/{classSession}/split/{part}',
    [AbsensiController::class, 'storeSplitPart']
)->name('absensi.split')->where('part', '[12]');
```

- [ ] **Step 2: Verifikasi route terdaftar**

```bash
php artisan route:list --name=absensi
```

Expected: `absensi.split` muncul dengan method POST dan URI `/absensi/{classSession}/split/{part}`

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "M04: Tambah route absensi.split untuk split reschedule"
```

---

## Task 6: AbsensiController — `storeSplitPart` + update `index()`

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`

- [ ] **Step 1: Tulis tes endpoint** — tambah ke `SplitRescheduleTest.php`

```php
/** @test */
public function part1_berhasil_dibuat_dan_original_jadi_izin_reschedule(): void
{
    $original = $this->makeOriginalSession(); // status = SCHEDULED

    $response = $this->actingAs($this->adminUser())->postJson(
        route('absensi.split', ['classSession' => $original, 'part' => 1]),
        ['replacement_date' => '2026-06-05', 'replacement_time' => '14:00']
    );

    $response->assertOk()->assertJson(['success' => true]);

    // Original harus jadi IZIN_RESCHEDULE
    $this->assertDatabaseHas('class_sessions', [
        'id'     => $original->id,
        'status' => 'IZIN_RESCHEDULE',
    ]);

    // Part 1 harus terbuat
    $this->assertDatabaseHas('class_sessions', [
        'origin_session_id' => $original->id,
        'split_part'        => 1,
        'session_date'      => '2026-06-05',
        'start_time'        => '14:00:00',
        'end_time'          => '14:15:00',
        'honor_code'        => 'H_SPLIT',
        'status'            => 'SCHEDULED',
    ]);
}

/** @test */
public function part2_berhasil_dibuat_setelah_part1(): void
{
    $original = $this->makeOriginalSession();
    $original->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);
    // Buat Part 1 manual
    ClassSession::factory()->create([
        'origin_session_id' => $original->id,
        'split_part'        => 1,
        'status'            => ClassSession::STATUS_SCHEDULED,
    ]);

    $response = $this->actingAs($this->adminUser())->postJson(
        route('absensi.split', ['classSession' => $original, 'part' => 2]),
        ['replacement_date' => '2026-06-12', 'replacement_time' => '10:00']
    );

    $response->assertOk()->assertJson(['success' => true]);
    $this->assertDatabaseHas('class_sessions', [
        'origin_session_id' => $original->id,
        'split_part'        => 2,
        'session_date'      => '2026-06-12',
        'honor_code'        => 'H_SPLIT',
    ]);
}

/** @test */
public function gagal_part1_jika_original_bukan_scheduled(): void
{
    $original = $this->makeOriginalSession([
        'status' => ClassSession::STATUS_HADIR, // sudah diisi absen
    ]);

    $response = $this->actingAs($this->adminUser())->postJson(
        route('absensi.split', ['classSession' => $original, 'part' => 1]),
        ['replacement_date' => '2026-06-05', 'replacement_time' => '14:00']
    );

    $response->assertStatus(422);
}

/** @test */
public function gagal_part1_jika_sudah_ada_part1(): void
{
    $original = $this->makeOriginalSession();
    $original->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);
    ClassSession::factory()->create([
        'origin_session_id' => $original->id,
        'split_part'        => 1,
    ]);

    // Coba buat Part 1 lagi
    $response = $this->actingAs($this->adminUser())->postJson(
        route('absensi.split', ['classSession' => $original, 'part' => 1]),
        ['replacement_date' => '2026-06-05', 'replacement_time' => '14:00']
    );

    $response->assertStatus(422);
    $this->assertStringContainsString('Bagian 1', $response->json('message'));
}

/** @test */
public function gagal_part2_jika_part1_belum_ada(): void
{
    $original = $this->makeOriginalSession(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);

    $response = $this->actingAs($this->adminUser())->postJson(
        route('absensi.split', ['classSession' => $original, 'part' => 2]),
        ['replacement_date' => '2026-06-12', 'replacement_time' => '10:00']
    );

    $response->assertStatus(422);
    $this->assertStringContainsString('Bagian 1', $response->json('message'));
}

/** @test */
public function gagal_part2_jika_sudah_ada_part2(): void
{
    $original = $this->makeOriginalSession(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);
    ClassSession::factory()->create(['origin_session_id' => $original->id, 'split_part' => 1]);
    ClassSession::factory()->create(['origin_session_id' => $original->id, 'split_part' => 2]);

    $response = $this->actingAs($this->adminUser())->postJson(
        route('absensi.split', ['classSession' => $original, 'part' => 2]),
        ['replacement_date' => '2026-06-19', 'replacement_time' => '10:00']
    );

    $response->assertStatus(422);
    $this->assertStringContainsString('Bagian 2', $response->json('message'));
}

/** @test */
public function konflik_guru_return_422(): void
{
    $original = $this->makeOriginalSession(); // SCHEDULED

    ClassSession::factory()->create([
        'teacher_id'   => $original->teacher_id,
        'session_date' => '2026-06-05',
        'start_time'   => '14:00:00',
        'end_time'     => '14:15:00',
        'status'       => ClassSession::STATUS_SCHEDULED,
    ]);

    $response = $this->actingAs($this->adminUser())->postJson(
        route('absensi.split', ['classSession' => $original, 'part' => 1]),
        ['replacement_date' => '2026-06-05', 'replacement_time' => '14:00']
    );

    $response->assertStatus(422);
    $this->assertStringContainsString('Guru', $response->json('message'));
}
```

- [ ] **Step 2: Jalankan tes — harus GAGAL**

```bash
php artisan test tests/Feature/Admin/SplitRescheduleTest.php --filter="part1_berhasil|part2_berhasil|gagal_part|konflik_guru_return"
```

Expected: FAIL — method `storeSplitPart` not found.

- [ ] **Step 3: Update `AbsensiController`**

Tambah `use` statement (setelah yang sudah ada):
```php
use App\Http\Requests\StoreSplitPartRequest;
```

Update method `index()` — tambah eager load `enrollment.package` dan hitung `$part2ExistsForOriginIds`:

```php
public function index(Request $request): View
{
    $tanggal = $request->date
        ? Carbon::parse($request->date)->toDateString()
        : today()->toDateString();

    $sessions = ClassSession::with([
            'student', 'teacher', 'substituteTeacher', 'room', 'originSession', 'enrollment.package',
        ])
        ->whereDate('session_date', $tanggal)
        ->orderBy('start_time')
        ->get();

    $teachers = Teacher::where('is_active', true)->orderBy('name')->get();
    $rooms    = Room::where('is_active', true)->orderBy('code')->get();

    // Cari Part 1 yang tampil hari ini — cek apakah Part 2-nya sudah dijadwalkan
    $part1OriginIds = $sessions->where('split_part', 1)->pluck('origin_session_id')->filter()->all();
    $part2ExistsForOriginIds = $part1OriginIds
        ? ClassSession::whereIn('origin_session_id', $part1OriginIds)
              ->where('split_part', 2)
              ->pluck('origin_session_id')
              ->all()
        : [];

    return view('absensi.index', [
        'sessions'                 => $sessions,
        'teachers'                 => $teachers,
        'rooms'                    => $rooms,
        'tanggal'                  => $tanggal,
        'tanggalObj'               => Carbon::parse($tanggal),
        'part2ExistsForOriginIds'  => $part2ExistsForOriginIds,
    ]);
}
```

Tambah method `storeSplitPart()` setelah method `update()`:

```php
/**
 * Buat bagian split dari sesi IZIN_RESCHEDULE.
 *
 * POST /absensi/{classSession}/split/1 — tandai original sebagai IZIN_RESCHEDULE + buat Part 1
 * POST /absensi/{classSession}/split/2 — buat Part 2 (original sudah IZIN_RESCHEDULE)
 */
public function storeSplitPart(StoreSplitPartRequest $request, ClassSession $classSession, int $part): JsonResponse
{
    // Guard Part 1: original harus SCHEDULED
    if ($part === 1 && $classSession->status !== ClassSession::STATUS_SCHEDULED) {
        return response()->json([
            'success' => false,
            'message' => 'Sesi harus dalam status Terjadwal untuk memulai split.',
        ], 422);
    }

    // Guard Part 2: original harus IZIN_RESCHEDULE
    if ($part === 2 && $classSession->status !== ClassSession::STATUS_IZIN_RESCHEDULE) {
        return response()->json([
            'success' => false,
            'message' => 'Bagian 1 harus dijadwalkan terlebih dahulu.',
        ], 422);
    }

    // Guard duplikasi Part 1
    $part1Exists = ClassSession::where('origin_session_id', $classSession->id)
        ->where('split_part', 1)->exists();

    if ($part === 1 && $part1Exists) {
        return response()->json([
            'success' => false,
            'message' => 'Bagian 1 sudah terjadwal untuk sesi ini.',
        ], 422);
    }

    // Guard Part 2 tanpa Part 1, atau Part 2 duplikat
    if ($part === 2) {
        if (!$part1Exists) {
            return response()->json([
                'success' => false,
                'message' => 'Bagian 1 belum dijadwalkan.',
            ], 422);
        }
        if (ClassSession::where('origin_session_id', $classSession->id)->where('split_part', 2)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bagian 2 sudah terjadwal.',
            ], 422);
        }
    }

    try {
        $splitSession = DB::transaction(function () use ($request, $classSession, $part) {
            // Part 1: tandai original sebagai IZIN_RESCHEDULE + honor H_IZIN = 0
            if ($part === 1) {
                $this->attendanceService->recordAttendance($classSession, [
                    'status'                => ClassSession::STATUS_IZIN_RESCHEDULE,
                    'late_minutes'          => null,
                    'substitute_teacher_id' => null,
                    'notes'                 => null,
                    '__session'             => $classSession,
                ]);
            }

            return $this->rescheduleService->createSplitPart(
                $classSession,
                $request->replacement_date,
                $request->replacement_time,
                $request->replacement_room_id,
                $part,
            );
        });
    } catch (\InvalidArgumentException $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $splitSession->load('originSession');

    return response()->json([
        'success'    => true,
        'session_id' => $splitSession->id,
        'label'      => $splitSession->getSessionLabel(),
    ]);
}
```

- [ ] **Step 4: Jalankan tes — harus LULUS**

```bash
php artisan test tests/Feature/Admin/SplitRescheduleTest.php
```

Expected: semua tes PASS.

- [ ] **Step 5: Full test suite**

```bash
php artisan test
```

Expected: semua lulus.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AbsensiController.php tests/Feature/Admin/SplitRescheduleTest.php
git commit -m "M04: AbsensiController — storeSplitPart endpoint + index eager load enrollment.package"
```

---

## Task 7: Views — Toggle Split + Tombol & Modal Part 2

**Files:**
- Modify: `resources/views/absensi/_row.blade.php`

Perubahan ini pada file yang panjang (280 baris). Lakukan perubahan berurutan.

- [ ] **Step 1: Tambah PHP vars di blok `@php` atas file**

Temukan blok `@php` di baris 1–10, tambah setelah `$replacementLabel`:

```php
// Split state untuk baris ini
$isSplitPart1            = $session->split_part === 1;
$hasPart2AlreadyScheduled = $isSplitPart1
    && in_array($session->origin_session_id, $part2ExistsForOriginIds ?? []);
```

- [ ] **Step 2: Tambah Alpine state vars baru** di x-data (dalam string JS di baris 17–63)

Cari baris `rescheduleRoomId: null,` — tambah setelah baris itu (sebelum `showModal: null`):

```js
isSplit: false,
splitPart2Date: '',
splitPart2Time: '',
splitPart2RoomId: null,
splitPart2ErrorMsg: '',
hasPart2: {{ $hasPart2AlreadyScheduled ? 'true' : 'false' }},
```

- [ ] **Step 3: Tambah method `saveSplitPart1` dan `saveSplitPart2` + update `saveReschedule`**

Ganti method `saveReschedule()` yang sudah ada:

```js
saveReschedule() {
    if (!this.rescheduleDate || !this.rescheduleTime) return;
    if (this.isSplit) {
        this.saveSplitPart1();
    } else {
        this.save('IZIN_RESCHEDULE', {
            replacement_date:    this.rescheduleDate,
            replacement_time:    this.rescheduleTime,
            replacement_room_id: this.rescheduleRoomId || null,
        });
    }
},
async saveSplitPart1() {
    this.loading = true;
    this.errorMsg = '';
    try {
        const res = await fetch('{{ route('absensi.split', ['classSession' => $session->id, 'part' => 1]) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                replacement_date:    this.rescheduleDate,
                replacement_time:    this.rescheduleTime,
                replacement_room_id: this.rescheduleRoomId || null,
            })
        });
        const data = await res.json();
        if (data.success) {
            this.status = 'IZIN_RESCHEDULE';
            this.replacementLabel = data.label || 'Bagian 1/2';
            this.showModal = null;
            this.$el.dataset.status = 'IZIN_RESCHEDULE';
        } else {
            this.errorMsg = data.message || 'Gagal menjadwalkan Bagian 1.';
        }
    } finally { this.loading = false; }
},
async saveSplitPart2() {
    if (!this.splitPart2Date || !this.splitPart2Time) return;
    this.loading = true;
    this.splitPart2ErrorMsg = '';
    try {
        const res = await fetch(this.$el.dataset.splitPart2Url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                replacement_date:    this.splitPart2Date,
                replacement_time:    this.splitPart2Time,
                replacement_room_id: this.splitPart2RoomId || null,
            })
        });
        const data = await res.json();
        if (data.success) {
            this.hasPart2 = true;
            this.showModal = null;
        } else {
            this.splitPart2ErrorMsg = data.message || 'Gagal menjadwalkan Bagian 2.';
        }
    } finally { this.loading = false; }
},
```

- [ ] **Step 4: Tambah `data-split-part2-url` ke `<tr>`**

Pada tag `<tr>` (baris 12–15 area), tambah data attribute:

```html
<tr class="hover:bg-gray-50 transition-colors"
    data-teacher-id="{{ $session->teacher_id }}"
    data-status="{{ $session->status }}"
    data-murid="{{ $session->student->full_name }}"
    @if($isSplitPart1)
    data-split-part2-url="{{ route('absensi.split', ['classSession' => $session->origin_session_id, 'part' => 2]) }}"
    @endif
```

- [ ] **Step 5: Tambah toggle "Bagi menjadi 2 bagian" ke reschedule modal**

Di dalam modal reschedule (sekitar baris 232–275), setelah `<p x-show="errorMsg" ...>`, tambah sebelum label "Tanggal Pengganti":

```html
{{-- Toggle split — hanya untuk sesi yang belum pernah di-split --}}
@if($session->split_part === null)
<div class="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100">
    <button type="button" @click="isSplit = !isSplit"
        :class="isSplit ? 'bg-yellow-500' : 'bg-gray-300'"
        class="relative inline-flex h-5 w-9 flex-shrink-0 rounded-full transition-colors duration-200 ease-in-out focus:outline-none">
        <span :class="isSplit ? 'translate-x-4' : 'translate-x-0'"
            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
    </button>
    <span class="text-gray-600 text-xs">Bagi menjadi 2 bagian</span>
</div>
<p x-show="isSplit" class="bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs rounded px-3 py-2 mb-3">
    Durasi akan dibagi rata — masing-masing ½ durasi normal
    @if($session->enrollment?->package)
        ({{ (int)($session->enrollment->package->duration_min / 2) }} menit per bagian)
    @endif
</p>
@endif
```

Ubah teks tombol submit reschedule agar dinamis:

```html
<button @click="saveReschedule()"
    :disabled="!rescheduleDate || !rescheduleTime"
    class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded btn-mk-primary">
    <span x-text="isSplit ? 'Jadwalkan Bagian 1' : 'Buat Sesi Pengganti'"></span>
</button>
```

- [ ] **Step 6: Tambah tombol "Tambah Bagian 2" dan modal Part 2 untuk Part 1 rows**

Di dalam `<td class="px-4 py-2.5 text-right">` (kolom Aksi), setelah blok `@if($isLibur)...@else...@endif` (setelah baris 277), tambah SEBELUM `</td>`:

```html
{{-- Tombol & modal Bagian 2 — hanya untuk baris Part 1 --}}
@if($isSplitPart1)
<div x-show="!hasPart2" class="mt-1.5">
    <button @click="showModal = 'splitPart2'"
        class="border border-purple-300 text-purple-600 hover:bg-purple-50 rounded px-3 py-1.5 text-xs">
        + Bagian 2
    </button>
</div>

{{-- Mini-modal Part 2 --}}
<div x-show="showModal === 'splitPart2'" @click.outside="showModal = null"
    class="fixed inset-0 z-40 flex items-center justify-center"
    style="display: none;">
    <div class="bg-white border border-gray-200 rounded-lg shadow-xl w-80 p-5">
        <p class="text-gray-700 text-sm font-medium mb-1">Jadwalkan Bagian 2</p>
        <p class="text-gray-400 text-xs mb-4 truncate">
            {{ $session->student->full_name }} · {{ $session->teacher->name }}
        </p>
        <p x-show="splitPart2ErrorMsg" x-text="splitPart2ErrorMsg"
            class="bg-red-50 border border-red-200 text-red-600 text-xs rounded px-3 py-2 mb-3">
        </p>
        <label class="block text-gray-500 text-xs mb-1">Tanggal</label>
        <input type="date" x-model="splitPart2Date"
            class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-3">
        <label class="block text-gray-500 text-xs mb-1">Jam Mulai</label>
        <input type="time" x-model="splitPart2Time"
            class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-3">
        <label class="block text-gray-500 text-xs mb-1">Ruangan <span class="text-gray-400">(opsional)</span></label>
        <select x-model="splitPart2RoomId"
            class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-4">
            <option value="">— Tanpa ruangan —</option>
            @foreach($rooms as $room)
                <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button @click="saveSplitPart2()"
                :disabled="!splitPart2Date || !splitPart2Time || loading"
                class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded btn-mk-primary">
                Jadwalkan Bagian 2
            </button>
            <button @click="showModal = null; splitPart2ErrorMsg = ''"
                class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                Batal
            </button>
        </div>
    </div>
</div>
@endif
```

- [ ] **Step 7: Build assets**

```bash
npm run build
```

Expected: selesai tanpa error.

- [ ] **Step 8: Full test suite**

```bash
php artisan test
```

Expected: semua lulus.

- [ ] **Step 9: Commit**

```bash
git add resources/views/absensi/_row.blade.php
git commit -m "M04: Absensi view — toggle split reschedule + tombol & modal Bagian 2"
```

---

## Task 8: CLAUDE.md — Tambah `H_SPLIT`

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Tambah H_SPLIT ke tabel honor code**

Cari tabel "10 Skenario" honor guru. Tambah baris setelah `H_IZIN`:

```markdown
H_SPLIT   | Sesi split reschedule (bagian 1 atau 2)  | package.price_per_month × 0.5 / 4 / 2
```

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "Docs: Tambah H_SPLIT ke tabel honor code di CLAUDE.md"
```

---

## Task 9: Verifikasi Manual di Browser

- [ ] **Step 1: Jalankan server lokal**

```bash
php artisan serve
```

- [ ] **Step 2: Login sebagai Admin** di `http://localhost:8000` dengan `admin@musikkita.local / password`

- [ ] **Step 3: Buka halaman Absensi**, navigasi ke tanggal yang ada sesi SCHEDULED

- [ ] **Step 4: Test alur split**
  1. Klik tombol IZIN pada satu sesi SCHEDULED
  2. Aktifkan toggle "Bagi menjadi 2 bagian" — pastikan muncul info durasi
  3. Isi tanggal + jam → klik "Jadwalkan Bagian 1"
  4. Verifikasi: baris sesi asli berubah badge ke "IZIN_RESCHEDULE", label "Bagian 1/2..."
  5. Navigasi ke tanggal Part 1 — verifikasi label "Bagian 1/2 — Reschedule dari Sesi ke-X Bulan..."
  6. Klik "+ Bagian 2" → isi tanggal + jam → klik "Jadwalkan Bagian 2"
  7. Verifikasi: tombol "+ Bagian 2" hilang

- [ ] **Step 5: Final test suite**

```bash
php artisan test
```

Expected: semua lulus.
