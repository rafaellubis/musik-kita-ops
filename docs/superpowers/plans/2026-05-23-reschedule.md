# Reschedule Lengkap (Fase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memungkinkan admin input tanggal + jam + ruangan sesi pengganti langsung dari halaman absensi — sistem otomatis buat ClassSession baru dengan konflik-check.

**Architecture:** RescheduleService baru menangani konflik-check + pembuatan ClassSession pengganti (schedule_id=null). AbsensiController memanggil service saat status=IZIN_RESCHEDULE. Alpine.js di _row.blade.php menampilkan mini-modal dengan 3 field tambahan (date, time, room).

**Tech Stack:** Laravel 11, Alpine.js, MySQL, PHPUnit Feature Tests (RefreshDatabase + Spatie roles)

---

## File Map

| Status | File | Tanggung Jawab |
|--------|------|----------------|
| **Baru** | `app/Services/RescheduleService.php` | Konflik-check + buat ClassSession pengganti |
| **Baru** | `tests/Feature/Admin/RescheduleTest.php` | 8 test cases feature test |
| **Ubah** | `app/Http/Requests/UpdateAbsensiRequest.php` | Tambah rules replacement_date/time/room_id |
| **Ubah** | `app/Http/Controllers/AbsensiController.php` | Inject service, handle IZIN_RESCHEDULE, pass $rooms ke view |
| **Ubah** | `resources/views/absensi/_row.blade.php` | Alpine mini-modal reschedule + 3 field + badge update |
| **Ubah** | `resources/views/absensi/index.blade.php` | Pass $rooms ke @include _row |

---

## Task 1: RescheduleService — Tulis Test Dulu (TDD)

**Files:**
- Create: `tests/Feature/Admin/RescheduleTest.php`
- Create: `app/Services/RescheduleService.php`

### Step 1.1: Buat direktori test dan file test

- [ ] Buat file `tests/Feature/Admin/RescheduleTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pastikan role Admin tersedia untuk actingAs
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    }

    /** Helper: buat ClassSession dengan relasi enrollment+package siap pakai. */
    private function makeSession(array $override = []): ClassSession
    {
        $teacher    = Teacher::factory()->create(['name' => 'Guru Test', 'is_active' => true]);
        $student    = Student::factory()->create();
        $package    = Package::factory()->create(['duration_min' => 30, 'is_active' => true]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create(array_merge([
            'teacher_id'    => $teacher->id,
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'session_date'  => '2026-05-20',
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => ClassSession::STATUS_IZIN_RESCHEDULE,
        ], $override));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    /** @test */
    public function happy_path_buat_sesi_pengganti_berhasil(): void
    {
        $session = $this->makeSession();

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-05',
                'replacement_time' => '14:00',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('class_sessions', [
            'student_id'    => $session->student_id,
            'enrollment_id' => $session->enrollment_id,
            'teacher_id'    => $session->teacher_id,
            'session_date'  => '2026-06-05',
            'start_time'    => '14:00:00',
            'end_time'      => '14:30:00',
            'schedule_id'   => null,
            'status'        => 'SCHEDULED',
        ]);
    }

    /** @test */
    public function konflik_guru_return_422_dengan_pesan_guru(): void
    {
        $session = $this->makeSession();

        // Sesi lain di waktu yang sama dengan guru yang sama
        ClassSession::factory()->create([
            'teacher_id'   => $session->teacher_id,
            'session_date' => '2026-06-05',
            'start_time'   => '14:00:00',
            'end_time'     => '14:30:00',
            'status'       => ClassSession::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-05',
                'replacement_time' => '14:00',
            ]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Guru', $response->json('message'));
    }

    /** @test */
    public function konflik_ruangan_return_422_dengan_pesan_ruangan(): void
    {
        $session = $this->makeSession();
        $room    = Room::factory()->create(['code' => 'R1', 'is_active' => true]);

        ClassSession::factory()->create([
            'room_id'      => $room->id,
            'session_date' => '2026-06-05',
            'start_time'   => '14:10:00',  // overlap sebagian
            'end_time'     => '14:40:00',
            'status'       => ClassSession::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'              => 'IZIN_RESCHEDULE',
                'replacement_date'    => '2026-06-05',
                'replacement_time'    => '14:00',
                'replacement_room_id' => $room->id,
            ]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Ruangan', $response->json('message'));
    }

    /** @test */
    public function ruangan_null_tidak_cek_konflik_ruangan_berhasil(): void
    {
        $session = $this->makeSession();

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-05',
                'replacement_time' => '14:00',
                // replacement_room_id tidak dikirim → null
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    /** @test */
    public function tanggal_bulan_depan_berhasil(): void
    {
        $session = $this->makeSession();

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-07-15',
                'replacement_time' => '09:00',
            ]
        );

        $response->assertOk();
        $this->assertDatabaseHas('class_sessions', [
            'session_date' => '2026-07-15',
            'status'       => 'SCHEDULED',
        ]);
    }

    /** @test */
    public function replacement_date_dan_time_wajib_saat_izin_reschedule(): void
    {
        $session = $this->makeSession();

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            ['status' => 'IZIN_RESCHEDULE']
            // tidak ada replacement_date dan replacement_time
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['replacement_date', 'replacement_time']);
    }

    /** @test */
    public function sesi_pengganti_schedule_id_null_dan_status_scheduled(): void
    {
        $session = $this->makeSession();

        $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-10',
                'replacement_time' => '11:00',
            ]
        );

        $replacement = ClassSession::where('session_date', '2026-06-10')
            ->where('student_id', $session->student_id)
            ->first();

        $this->assertNotNull($replacement);
        $this->assertNull($replacement->schedule_id);
        $this->assertEquals('SCHEDULED', $replacement->status);
    }

    /** @test */
    public function notes_sesi_asli_terupdate_dengan_referensi_replacement(): void
    {
        $session = $this->makeSession();

        $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-10',
                'replacement_time' => '11:00',
            ]
        );

        $session->refresh();
        $this->assertStringContainsString('2026-06-10', $session->notes);
        $this->assertStringContainsString('11:00', $session->notes);
    }
}
```

### Step 1.2: Jalankan test — pastikan FAIL

- [ ] Jalankan: `php artisan test tests/Feature/Admin/RescheduleTest.php`
- Expected: semua gagal dengan error (RescheduleService belum ada, validation belum ada)

### Step 1.3: Buat `app/Services/RescheduleService.php`

- [ ] Buat file:

```php
<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Room;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Menangani pembuatan sesi pengganti (reschedule) beserta validasi konflik.
 *
 * Dipanggil oleh AbsensiController saat status IZIN_RESCHEDULE.
 * Tidak menyentuh AttendanceService atau HonorCalculationService.
 */
class RescheduleService
{
    /**
     * Buat sesi pengganti untuk sesi yang di-reschedule.
     *
     * @param  ClassSession  $original   Sesi asli (status sudah IZIN_RESCHEDULE)
     * @param  string        $date       Format Y-m-d (tanggal pengganti)
     * @param  string        $startTime  Format H:i (jam mulai pengganti)
     * @param  int|null      $roomId     ID ruangan pengganti, null = tanpa ruangan
     *
     * @throws InvalidArgumentException Jika ada konflik guru atau ruangan
     */
    public function createReplacement(
        ClassSession $original,
        string $date,
        string $startTime,
        ?int $roomId
    ): ClassSession {
        // Hitung jam selesai berdasarkan durasi paket enrollment
        $original->loadMissing(['enrollment.package', 'teacher']);

        $durationMin = $original->enrollment->package->duration_min;
        $endTime = Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($durationMin)
            ->format('H:i:s');

        $startTimeFull = $startTime . ':00';

        // Cek konflik guru — satu guru tidak boleh dua sesi overlap waktu
        $teacherConflict = ClassSession::where('teacher_id', $original->teacher_id)
            ->whereDate('session_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTimeFull)
            ->where('status', '!=', ClassSession::STATUS_CANCELLED)
            ->where('id', '!=', $original->id)
            ->first();

        if ($teacherConflict) {
            $namaGuru    = $original->teacher->name;
            $jamMulai    = substr($teacherConflict->start_time, 0, 5);
            $jamSelesai  = substr($teacherConflict->end_time, 0, 5);
            throw new InvalidArgumentException(
                "Guru {$namaGuru} sudah ada sesi lain pada {$date} {$jamMulai}–{$jamSelesai}"
            );
        }

        // Cek konflik ruangan — skip jika ruangan tidak dipilih
        if ($roomId !== null) {
            $roomConflict = ClassSession::where('room_id', $roomId)
                ->whereDate('session_date', $date)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTimeFull)
                ->where('status', '!=', ClassSession::STATUS_CANCELLED)
                ->first();

            if ($roomConflict) {
                $room        = Room::find($roomId);
                $jamMulai    = substr($roomConflict->start_time, 0, 5);
                $jamSelesai  = substr($roomConflict->end_time, 0, 5);
                throw new InvalidArgumentException(
                    "Ruangan {$room->code} sudah dipakai pada {$date} {$jamMulai}–{$jamSelesai}"
                );
            }
        }

        // Buat sesi pengganti (ad-hoc — schedule_id null)
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
            'notes'                 => "Sesi pengganti dari {$original->session_date->format('d/m/Y')}",
        ]);

        // Update notes sesi asli dengan referensi tanggal pengganti
        $original->update([
            'notes' => "Sesi pengganti: {$date} " . substr($startTime, 0, 5),
        ]);

        return $replacement;
    }
}
```

### Step 1.4: Jalankan test Task 1 saja — pastikan masih FAIL (controller belum diubah)

- [ ] `php artisan test tests/Feature/Admin/RescheduleTest.php`
- Expected: masih fail karena validation belum ada

### Step 1.5: Commit service

```bash
git add app/Services/RescheduleService.php tests/Feature/Admin/RescheduleTest.php
git commit -m "M04: Tambah RescheduleService + test skeleton (TDD step 1)"
```

---

## Task 2: UpdateAbsensiRequest — Tambah Rules Reschedule

**Files:**
- Modify: `app/Http/Requests/UpdateAbsensiRequest.php`

### Step 2.1: Tambah rules ke `rules()`

- [ ] Edit `app/Http/Requests/UpdateAbsensiRequest.php`, tambah ke array `rules()` setelah `'notes'`:

```php
// Tanggal pengganti — wajib jika status IZIN_RESCHEDULE
'replacement_date' => [
    'required_if:status,' . ClassSession::STATUS_IZIN_RESCHEDULE,
    'nullable', 'date', 'date_format:Y-m-d',
],
// Jam mulai pengganti — wajib jika status IZIN_RESCHEDULE
'replacement_time' => [
    'required_if:status,' . ClassSession::STATUS_IZIN_RESCHEDULE,
    'nullable', 'date_format:H:i',
],
// Ruangan pengganti — opsional
'replacement_room_id' => [
    'nullable', 'exists:rooms,id',
],
```

### Step 2.2: Tambah messages

- [ ] Tambah ke array `messages()`:

```php
'replacement_date.required_if' => 'Tanggal pengganti wajib diisi.',
'replacement_date.date_format'  => 'Format tanggal harus YYYY-MM-DD.',
'replacement_time.required_if'  => 'Jam mulai pengganti wajib diisi.',
'replacement_time.date_format'  => 'Format jam harus HH:MM.',
'replacement_room_id.exists'    => 'Ruangan tidak ditemukan.',
```

### Step 2.3: Jalankan test — pastikan test 6 (validation) sudah hijau

- [ ] `php artisan test tests/Feature/Admin/RescheduleTest.php --filter replacement_date_dan_time_wajib`
- Expected: PASS

### Step 2.4: Commit

```bash
git add app/Http/Requests/UpdateAbsensiRequest.php
git commit -m "M04: Tambah validasi replacement_date/time/room_id di UpdateAbsensiRequest"
```

---

## Task 3: AbsensiController — Integrasi RescheduleService

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`

### Step 3.1: Update AbsensiController

- [ ] Edit `app/Http/Controllers/AbsensiController.php` — ganti seluruh isi file dengan:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAbsensiRequest;
use App\Models\ClassSession;
use App\Models\Room;
use App\Models\Teacher;
use App\Services\RescheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Absensi Harian (M04) — tampilan per-hari.
 *
 * Menampilkan SEMUA sesi pada satu tanggal sekaligus
 * agar Admin bisa input absensi dalam satu layar (M04 daily view).
 *
 * Dua endpoint:
 *   GET  /absensi              -> daftar sesi hari ini (filter by tanggal)
 *   PATCH /absensi/{session}   -> update satu sesi via AJAX inline
 */
class AbsensiController extends Controller
{
    public function __construct(private RescheduleService $rescheduleService) {}

    /**
     * Tampilkan halaman absensi harian.
     * Default: hari ini. Bisa difilter via query ?date=YYYY-MM-DD
     */
    public function index(Request $request): View
    {
        $tanggal = $request->date
            ? Carbon::parse($request->date)->toDateString()
            : today()->toDateString();

        $sessions = ClassSession::with(['student', 'teacher', 'substituteTeacher', 'room'])
            ->whereDate('session_date', $tanggal)
            ->orderBy('start_time')
            ->get();

        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();
        $rooms    = Room::where('is_active', true)->orderBy('code')->get();

        return view('absensi.index', [
            'sessions'   => $sessions,
            'teachers'   => $teachers,
            'rooms'      => $rooms,
            'tanggal'    => $tanggal,
            'tanggalObj' => Carbon::parse($tanggal),
        ]);
    }

    /**
     * Update status absensi satu sesi (AJAX inline).
     *
     * Business rules:
     * - LIBUR tidak bisa diubah (BR-4.10)
     * - IZIN_RESCHEDULE: buat sesi pengganti via RescheduleService
     *   Jika ada konflik guru/ruangan → rollback via DB transaction, return 422
     */
    public function update(UpdateAbsensiRequest $request, ClassSession $classSession): JsonResponse
    {
        if ($classSession->status === ClassSession::STATUS_LIBUR) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi libur nasional tidak bisa diubah.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($request, $classSession) {
                $classSession->update([
                    'status'                => $request->status,
                    'late_minutes'          => $request->status === ClassSession::STATUS_HADIR_TERLAMBAT
                                                ? $request->late_minutes : null,
                    'substitute_teacher_id' => $request->status === ClassSession::STATUS_DIGANTI
                                                ? $request->substitute_teacher_id : null,
                    // Notes untuk IZIN_RESCHEDULE di-set otomatis oleh service
                    'notes'                 => $request->status !== ClassSession::STATUS_IZIN_RESCHEDULE
                                                ? $request->notes : null,
                ]);

                if ($request->status === ClassSession::STATUS_IZIN_RESCHEDULE) {
                    $this->rescheduleService->createReplacement(
                        $classSession,
                        $request->replacement_date,
                        $request->replacement_time,
                        $request->replacement_room_id,
                    );
                }
            });
        } catch (\InvalidArgumentException $e) {
            // Konflik guru atau ruangan — transaction di-rollback otomatis
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $classSession->refresh()->load('substituteTeacher');

        return response()->json([
            'success'                 => true,
            'session_id'              => $classSession->id,
            'status'                  => $classSession->status,
            'late_minutes'            => $classSession->late_minutes,
            'substitute_teacher_name' => $classSession->substituteTeacher?->name,
            // notes berisi "Sesi pengganti: 2026-06-05 14:00" untuk IZIN_RESCHEDULE
            'replacement_label'       => $classSession->status === ClassSession::STATUS_IZIN_RESCHEDULE
                                            ? str_replace('Sesi pengganti: ', '', $classSession->notes ?? '')
                                            : null,
        ]);
    }
}
```

### Step 3.2: Jalankan seluruh test reschedule

- [ ] `php artisan test tests/Feature/Admin/RescheduleTest.php`
- Expected: semua 8 test PASS

### Step 3.3: Jalankan full test suite — pastikan tidak ada regresi

- [ ] `php artisan test`
- Expected: semua test PASS

### Step 3.4: Commit

```bash
git add app/Http/Controllers/AbsensiController.php
git commit -m "M04: AbsensiController — integrasi RescheduleService, tambah rooms ke view"
```

---

## Task 4: Blade UI — Mini-modal Reschedule di _row.blade.php

**Files:**
- Modify: `resources/views/absensi/_row.blade.php`
- Modify: `resources/views/absensi/index.blade.php`

### Step 4.1: Update `index.blade.php` — pass $rooms ke @include

- [ ] Di `resources/views/absensi/index.blade.php`, cari baris:
```blade
@include('absensi._row', ['session' => $session, 'teachers' => $teachers])
```
Ganti dengan:
```blade
@include('absensi._row', ['session' => $session, 'teachers' => $teachers, 'rooms' => $rooms])
```

### Step 4.2: Update `_row.blade.php` — Alpine state + mini-modal reschedule

- [ ] Ganti seluruh isi `resources/views/absensi/_row.blade.php` dengan:

```blade
@php
    $isLibur = $session->status === 'LIBUR';

    // Ekstrak label replacement dari notes sesi asli (jika sudah pernah di-reschedule)
    $replacementLabel = '';
    if ($session->status === 'IZIN_RESCHEDULE' && $session->notes
        && str_starts_with($session->notes, 'Sesi pengganti: ')) {
        $replacementLabel = substr($session->notes, strlen('Sesi pengganti: '));
    }
@endphp

<tr class="hover:bg-gray-50 transition-colors"
    data-teacher-id="{{ $session->teacher_id }}"
    data-status="{{ $session->status }}"
    data-murid="{{ $session->student->full_name }}"
    @if(! $isLibur)
    x-data="{
        status: '{{ $session->status }}',
        lateMinutes: {{ $session->late_minutes ?? 15 }},
        substituteId: {{ $session->substitute_teacher_id ?? 'null' }},
        substituteLabel: @js($session->substituteTeacher?->name ?? ''),
        replacementLabel: @js($replacementLabel),
        rescheduleDate: '',
        rescheduleTime: '',
        rescheduleRoomId: null,
        showModal: null,
        loading: false,
        errorMsg: '',
        async save(newStatus, extra = {}) {
            this.loading  = true;
            this.errorMsg = '';
            try {
                const res = await fetch('{{ route('absensi.update', $session) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: newStatus, ...extra })
                });
                const data = await res.json();
                if (data.success) {
                    this.status = data.status;
                    if (data.late_minutes)            this.lateMinutes     = data.late_minutes;
                    if (data.substitute_teacher_name) this.substituteLabel = data.substitute_teacher_name;
                    if (data.replacement_label)       this.replacementLabel = data.replacement_label;
                    this.showModal = null;
                    this.$el.dataset.status = data.status;
                } else {
                    this.errorMsg = data.message || 'Gagal menyimpan.';
                }
            } finally { this.loading = false; }
        },
        saveReschedule() {
            if (!this.rescheduleDate || !this.rescheduleTime) return;
            this.save('IZIN_RESCHEDULE', {
                replacement_date:    this.rescheduleDate,
                replacement_time:    this.rescheduleTime,
                replacement_room_id: this.rescheduleRoomId || null,
            });
        }
    }"
    :class="status !== 'SCHEDULED' ? 'opacity-60' : ''"
    @endif
>
    {{-- Jam --}}
    <td class="px-4 py-2.5 font-bold text-sm"
        @if(! $isLibur)
        :style="status === 'SCHEDULED' ? 'color:#D4A853;font-weight:700' : ''"
        :class="status !== 'SCHEDULED' ? 'text-gray-500' : ''"
        @else
        class="text-gray-500"
        @endif>
        {{ substr($session->start_time, 0, 5) }}
    </td>

    {{-- Murid --}}
    <td class="px-3 py-2.5 text-sm"
        @if(! $isLibur)
        :class="status === 'SCHEDULED' ? 'text-gray-800 font-medium' : 'text-gray-500'"
        @else
        class="text-gray-500"
        @endif>
        {{ $session->student->full_name }}
    </td>

    {{-- Guru --}}
    <td class="px-3 py-2.5 text-xs text-gray-500">{{ $session->teacher->name }}</td>

    {{-- Ruang --}}
    <td class="px-3 py-2.5 text-xs text-gray-500">{{ $session->room?->code ?? '—' }}</td>

    {{-- Aksi --}}
    <td class="px-4 py-2.5 text-right">

        @if($isLibur)
            <span class="bg-gray-100 text-gray-500 border border-gray-200 rounded px-3 py-1 text-xs">
                🗓 LIBUR
            </span>

        @else
            {{-- Badge setelah status diinput --}}
            <div x-show="status !== 'SCHEDULED'" class="flex items-center justify-end gap-2">
                <span class="rounded px-3 py-1 text-xs border"
                    :class="{
                        'bg-green-100 text-green-700 border-green-200':   status === 'HADIR',
                        'bg-orange-100 text-orange-700 border-orange-200': status === 'HADIR_TERLAMBAT',
                        'bg-red-100 text-red-700 border-red-200':          status === 'HANGUS',
                        'bg-yellow-100 text-yellow-700 border-yellow-200': status === 'IZIN_RESCHEDULE',
                        'bg-blue-100 text-blue-700 border-blue-200':       status === 'IZIN_VIDEO',
                        'bg-purple-100 text-purple-700 border-purple-200': status === 'DIGANTI',
                    }"
                    x-text="
                        status === 'HADIR'            ? '✓ HADIR' :
                        status === 'HADIR_TERLAMBAT'  ? '⏱ +' + lateMinutes + ' mnt' :
                        status === 'HANGUS'            ? '✕ HANGUS' :
                        status === 'IZIN_RESCHEDULE'   ? '📅 ' + (replacementLabel || 'IZIN') :
                        status === 'IZIN_VIDEO'        ? '📹 VIDEO' :
                        status === 'DIGANTI'           ? '↔ ' + substituteLabel : status
                    ">
                </span>
                <button @click="status = 'SCHEDULED'; errorMsg = ''"
                    class="text-gray-400 hover:text-gray-600 text-xs underline">ubah</button>
            </div>

            {{-- Tombol aksi (status belum diinput) --}}
            <div x-show="status === 'SCHEDULED'"
                class="flex items-center justify-end gap-1.5"
                :class="loading ? 'opacity-50 pointer-events-none' : ''">

                <button @click="save('HADIR')"
                    class="rounded px-3 py-1.5 text-xs font-semibold btn-mk-primary">
                    HADIR
                </button>
                <button @click="save('HANGUS')"
                    class="border border-red-300 text-red-600 hover:bg-red-50 rounded px-3 py-1.5 text-xs">
                    HANGUS
                </button>
                {{-- IZIN → buka mini-modal (bukan langsung save) --}}
                <button @click="showModal = 'reschedule'"
                    class="border border-yellow-300 text-yellow-700 hover:bg-yellow-50 rounded px-3 py-1.5 text-xs">
                    IZIN
                </button>
                <button @click="save('IZIN_VIDEO')"
                    class="border border-blue-300 text-blue-600 hover:bg-blue-50 rounded px-3 py-1.5 text-xs">
                    VIDEO
                </button>

                {{-- Tombol ··· dengan dropdown --}}
                <div class="relative">
                    <button @click="showModal = showModal === 'menu' ? null : 'menu'"
                        class="border border-gray-300 text-gray-600 hover:bg-gray-100 rounded px-2.5 py-1.5 text-xs">
                        ···
                    </button>

                    {{-- Dropdown menu --}}
                    <div x-show="showModal === 'menu'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-36 py-1">
                        <button @click="showModal = 'terlambat'"
                            class="w-full text-left px-4 py-2 text-orange-600 text-xs hover:bg-gray-50">
                            Terlambat
                        </button>
                        <button @click="showModal = 'diganti'"
                            class="w-full text-left px-4 py-2 text-purple-600 text-xs hover:bg-gray-50">
                            Diganti
                        </button>
                    </div>

                    {{-- Mini-modal: TERLAMBAT --}}
                    <div x-show="showModal === 'terlambat'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-56 p-4">
                        <p class="text-gray-500 text-xs mb-3 truncate">
                            {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        </p>
                        <label class="block text-gray-500 text-xs mb-1">Terlambat berapa menit?</label>
                        <div class="flex items-center gap-2 mb-4">
                            <input type="number" x-model.number="lateMinutes" min="1" max="60"
                                class="border border-gray-300 text-gray-700 rounded px-3 py-1.5 w-20 text-center text-sm">
                            <span class="text-gray-500 text-xs">menit</span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="save('HADIR_TERLAMBAT', { late_minutes: lateMinutes })"
                                class="flex-1 font-semibold text-xs py-2 rounded btn-mk-primary">
                                Simpan
                            </button>
                            <button @click="showModal = null"
                                class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                                Batal
                            </button>
                        </div>
                    </div>

                    {{-- Mini-modal: DIGANTI --}}
                    <div x-show="showModal === 'diganti'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-64 p-4">
                        <p class="text-gray-500 text-xs mb-3 truncate">
                            {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        </p>
                        <label class="block text-gray-500 text-xs mb-1">Guru pengganti</label>
                        <select x-model="substituteId"
                            class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-1">
                            <option value="">— Pilih guru pengganti —</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-gray-400 text-xs mb-3">Honor otomatis ke guru pengganti.</p>
                        <div class="flex gap-2">
                            <button @click="if(substituteId) save('DIGANTI', { substitute_teacher_id: substituteId })"
                                :disabled="!substituteId"
                                class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded btn-mk-primary">
                                Simpan
                            </button>
                            <button @click="showModal = null"
                                class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                                Batal
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Mini-modal: RESCHEDULE (di luar tombol ···, karena dipanggil dari tombol IZIN) --}}
            <div x-show="showModal === 'reschedule'" @click.outside="showModal = null"
                class="fixed inset-0 z-40 flex items-center justify-center"
                style="display: none;">
                <div class="bg-white border border-gray-200 rounded-lg shadow-xl w-80 p-5">
                    <p class="text-gray-700 text-sm font-medium mb-1">Jadwalkan Sesi Pengganti</p>
                    <p class="text-gray-400 text-xs mb-4 truncate">
                        {{ $session->student->full_name }} · {{ $session->teacher->name }}
                    </p>

                    {{-- Error message dari server (konflik guru/ruangan) --}}
                    <p x-show="errorMsg" x-text="errorMsg"
                        class="bg-red-50 border border-red-200 text-red-600 text-xs rounded px-3 py-2 mb-3">
                    </p>

                    <label class="block text-gray-500 text-xs mb-1">Tanggal Pengganti</label>
                    <input type="date" x-model="rescheduleDate"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-3">

                    <label class="block text-gray-500 text-xs mb-1">Jam Mulai</label>
                    <input type="time" x-model="rescheduleTime"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-3">

                    <label class="block text-gray-500 text-xs mb-1">Ruangan <span class="text-gray-400">(opsional)</span></label>
                    <select x-model="rescheduleRoomId"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-4">
                        <option value="">— Tanpa ruangan —</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>

                    <div class="flex gap-2">
                        <button @click="saveReschedule()"
                            :disabled="!rescheduleDate || !rescheduleTime"
                            class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded btn-mk-primary">
                            Buat Sesi Pengganti
                        </button>
                        <button @click="showModal = null; errorMsg = ''"
                            class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                            Batal
                        </button>
                    </div>
                </div>
            </div>

        @endif
    </td>
</tr>
```

### Step 4.3: Jalankan full test suite

- [ ] `php artisan test`
- Expected: semua test PASS (tidak ada regresi)

### Step 4.4: Verifikasi manual di browser

- [ ] Buka halaman `/absensi` di browser
- [ ] Klik tombol **IZIN** pada salah satu sesi → modal muncul dengan 3 field (tanggal, jam, ruangan)
- [ ] Isi tanggal + jam → klik **Buat Sesi Pengganti** → badge berubah ke `📅 2026-XX-XX HH:MM`
- [ ] Coba isi tanggal/jam yang konflik dengan guru → pastikan muncul pesan error di dalam modal

### Step 4.5: Commit final

```bash
git add resources/views/absensi/_row.blade.php resources/views/absensi/index.blade.php
git commit -m "M04: UI reschedule — mini-modal date/time/room + badge update"
git push
```

---

## Checklist Self-Review vs Spec

| Requirement Spec | Task | Status |
|------------------|------|--------|
| 3 field tambahan (date, time, room) muncul saat IZIN_RESCHEDULE | Task 4 | ✓ |
| Konflik guru → block + pesan nama guru + jam | Task 1 step 1.3 | ✓ |
| Konflik ruangan → block + pesan kode ruangan + jam | Task 1 step 1.3 | ✓ |
| Ruangan null → skip cek ruangan | Task 1 test 4 | ✓ |
| schedule_id = null di sesi pengganti | Task 1 test 7 | ✓ |
| status = SCHEDULED di sesi pengganti | Task 1 test 7 | ✓ |
| Notes sesi asli terupdate dengan tanggal pengganti | Task 1 test 8 | ✓ |
| Notes sesi pengganti = "Sesi pengganti dari {tanggal asli}" | Task 1 step 1.3 | ✓ |
| Tanggal bulan depan diizinkan (BR-4.5) | Task 1 test 5 | ✓ |
| Honor sesi asli = 0 (sudah AttendanceService, tidak diubah) | Tidak diubah | ✓ |
| DB transaction — rollback jika konflik | Task 3 step 3.1 | ✓ |
| Semua 8 test cases dari spec | Task 1 | ✓ |
