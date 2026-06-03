# Schedule & Absensi DIGANTI — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki 7 bug di Schedule CRUD (multi-enrollment) dan Absensi DIGANTI (two-phase confirmation) agar konsisten dan tidak saling tabrakan.

**Architecture:** Cluster A memperbaiki ScheduleController + StudentController + view show.blade.php agar multi-enrollment dikelola per-enrollment. Cluster B menambahkan two-phase DIGANTI (assignment → konfirmasi) di AttendanceService + AbsensiController + _row.blade.php.

**Tech Stack:** Laravel 11, PHP 8.3, Blade + Alpine.js + Tailwind, Spatie Permission. Test via `php artisan test --filter=<TestClass>` (SQLite in-memory, aman).

**Spec:** `docs/superpowers/specs/2026-06-03-schedule-absensi-redesign.md`

---

## Task 1 — A3: Fix `$bookedSchedules` (konflik palsu di edit modal)

**Files:**
- Modify: `app/Http/Controllers/StudentController.php` ~line 117

**Root cause:** `get()` tanpa kolom `id` → Alpine.js `s.id` = undefined → exclusion check gagal → ruangan selalu tampak penuh saat edit.

- [ ] **Step 1: Tambah kolom `id` ke query `$bookedSchedules`**

Di `StudentController::show()`, cari baris:
```php
$bookedSchedules = Schedule::active()
    ->whereNotNull('room_id')
    ->get(['room_id', 'day_of_week', 'start_time', 'end_time']);
```
Ganti menjadi:
```php
$bookedSchedules = Schedule::active()
    ->whereNotNull('room_id')
    ->get(['id', 'room_id', 'day_of_week', 'start_time', 'end_time']);
```

- [ ] **Step 2: Verifikasi manual**

Buka halaman murid yang punya jadwal dengan ruangan, klik Edit jadwal, pilih hari + jam yang sama → ruangan seharusnya TIDAK tampak penuh (tidak ada konflik merah sendiri).

- [ ] **Step 3: Commit**
```
git add app/Http/Controllers/StudentController.php
git commit -m "M03: Fix bookedSchedules sertakan id — hilangkan konflik palsu saat edit jadwal"
```

---

## Task 2 — A1+A2+A4: Multi-enrollment tab Jadwal + fix store() + instrumen per-enrollment

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php`
- Modify: `app/Http/Controllers/StudentController.php`
- Modify: `resources/views/students/show.blade.php`

### Step 1 — Tulis failing test untuk store() dengan enrollment_id

Buat file baru `tests/Feature/ScheduleMultiEnrollmentTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleMultiEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function makeStudentWithTwoEnrollments(): array
    {
        $piano = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $gitar = Instrument::create(['name' => 'Gitar', 'code' => 'GITAR', 'is_active' => true, 'sort_order' => 2]);

        $pkgPiano = Package::create([
            'code' => 'REG-PIANO-L1', 'instrument_id' => $piano->id,
            'class_type' => 'REGULER', 'grade' => 'Level 1', 'duration_min' => 30,
            'price_per_month' => 370000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $pkgGitar = Package::create([
            'code' => 'HOBBY-GITAR', 'instrument_id' => $gitar->id,
            'class_type' => 'HOBBY', 'grade' => null, 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 2,
        ]);

        $teacher = Teacher::factory()->create(['is_active' => true]);
        $student = Student::factory()->create(['status' => 'Aktif']);

        $enrollPiano = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $pkgPiano->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'is_primary' => true, 'effective_date' => now()->toDateString(),
        ]);
        $enrollGitar = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $pkgGitar->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'is_primary' => false, 'effective_date' => now()->toDateString(),
        ]);

        return [$student, $enrollPiano, $enrollGitar, $teacher];
    }

    /** store() harus pakai enrollment_id dari form, bukan latest() */
    public function test_store_attaches_schedule_to_correct_enrollment(): void
    {
        [$student, $enrollPiano, $enrollGitar] = $this->makeStudentWithTwoEnrollments();

        $this->actingAs($this->admin)
            ->post(route('schedules.store', $student->id), [
                'enrollment_id' => $enrollPiano->id,
                'day_of_week'   => 1,
                'start_time'    => '10:00',
                'end_time'      => '10:30',
                'room_id'       => null,
            ])
            ->assertRedirect();

        // Jadwal harus attach ke Piano (bukan Gitar)
        $this->assertDatabaseHas('schedules', [
            'enrollment_id' => $enrollPiano->id,
            'day_of_week'   => 1,
        ]);
        $this->assertDatabaseMissing('schedules', [
            'enrollment_id' => $enrollGitar->id,
        ]);
    }

    /** store() harus tolak enrollment_id yang bukan milik student ini */
    public function test_store_rejects_enrollment_not_belonging_to_student(): void
    {
        [$student, $enrollPiano] = $this->makeStudentWithTwoEnrollments();

        // Enrollment milik student lain
        $otherStudent  = Student::factory()->create(['status' => 'Aktif']);
        $teacher2      = Teacher::factory()->create(['is_active' => true]);
        $piano2 = \App\Models\Instrument::first();
        $pkg2   = Package::first();
        $otherEnroll = Enrollment::create([
            'student_id' => $otherStudent->id, 'package_id' => $pkg2->id,
            'teacher_id' => $teacher2->id, 'status' => 'ACTIVE',
            'is_primary' => true, 'effective_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('schedules.store', $student->id), [
                'enrollment_id' => $otherEnroll->id,
                'day_of_week'   => 2,
                'start_time'    => '11:00',
                'end_time'      => '11:30',
            ]);

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**
```
php artisan test --filter=ScheduleMultiEnrollmentTest
```
Expected: 2 FAIL (enrollment_id tidak divalidasi di store())

- [ ] **Step 3: Update `ScheduleController::store()` — pakai enrollment_id dari request**

Ganti method `store()` seluruhnya:

```php
public function store(Request $request, Student $student)
{
    $data = $request->validate([
        'enrollment_id' => 'required|exists:enrollments,id',
        'day_of_week'   => 'required|integer|min:0|max:6',
        'start_time'    => 'required|date_format:H:i',
        'end_time'      => 'required|date_format:H:i|after:start_time',
        'room_id'       => 'nullable|exists:rooms,id',
        'notes'         => 'nullable|string|max:500',
    ], [
        'enrollment_id.required' => 'Enrollment wajib dipilih.',
        'enrollment_id.exists'   => 'Enrollment tidak ditemukan.',
        'day_of_week.required'   => 'Hari wajib dipilih.',
        'start_time.required'    => 'Jam mulai wajib diisi.',
        'end_time.after'         => 'Jam selesai harus setelah jam mulai.',
    ]);

    // Verifikasi enrollment milik student ini dan berstatus ACTIVE
    $enrollment = $student->enrollments()
        ->where('id', $data['enrollment_id'])
        ->where('status', 'ACTIVE')
        ->first();

    if (!$enrollment) {
        abort(403, 'Enrollment tidak ditemukan atau tidak aktif untuk murid ini.');
    }

    $package = $enrollment->package;
    $isDuo   = $package?->isDuo() ?? false;

    // Validasi konflik guru
    $teacherClashes = $this->conflictDetector->findTeacherConflicts(
        teacherId: $enrollment->teacher_id,
        dayOfWeek: $data['day_of_week'],
        startTime: $data['start_time'],
        endTime:   $data['end_time'],
    );

    if ($isDuo) {
        $nonDuoClashes = $teacherClashes->filter(
            fn ($s) => $s->enrollment?->package?->class_type !== 'DUO'
        );
        $duoClashes = $teacherClashes->filter(
            fn ($s) => $s->enrollment?->package?->class_type === 'DUO'
        );

        if ($nonDuoClashes->isNotEmpty()) {
            $names = $nonDuoClashes->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                                   ->implode(', ');
            return back()->withInput()->with('error',
                "Bentrok jadwal guru di slot tsb. Sudah dipakai untuk: {$names}.");
        }
        if ($duoClashes->count() >= 2) {
            return back()->withInput()->with('error',
                'Slot DUO sudah penuh (maksimal 2 murid per slot).');
        }
    } else {
        if ($teacherClashes->isNotEmpty()) {
            $names = $teacherClashes->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                                    ->implode(', ');
            return back()->withInput()->with('error',
                "Bentrok jadwal guru di slot tsb. Sudah dipakai untuk: {$names}.");
        }
    }

    // Validasi kapasitas + instrumen ruangan
    if (!empty($data['room_id'])) {
        if ($isDuo) {
            $roomConflicts = $this->conflictDetector->findRoomConflicts(
                roomId:    (int) $data['room_id'],
                dayOfWeek: $data['day_of_week'],
                startTime: $data['start_time'],
                endTime:   $data['end_time'],
            );
            $nonDuoRoom = $roomConflicts->filter(
                fn ($s) => $s->enrollment?->package?->class_type !== 'DUO'
            );
            $duoRoom = $roomConflicts->filter(
                fn ($s) => $s->enrollment?->package?->class_type === 'DUO'
            );

            if ($nonDuoRoom->isNotEmpty() || $duoRoom->count() >= 2) {
                return back()->withInput()->with('error',
                    'Ruangan tidak tersedia di slot ini untuk DUO.');
            }
        } else {
            $isFull = $this->conflictDetector->isRoomFull(
                roomId:    (int) $data['room_id'],
                dayOfWeek: $data['day_of_week'],
                startTime: $data['start_time'],
                endTime:   $data['end_time'],
            );
            if ($isFull) {
                return back()->withInput()->with('error',
                    'Kapasitas ruangan sudah penuh di slot ini.');
            }
        }

        $room           = Room::findOrFail($data['room_id']);
        $instrumentName = $enrollment->package?->instrument?->name;
        if ($instrumentName && !$room->supportsInstrument($instrumentName)) {
            return back()->withInput()->with('error',
                "Ruangan [{$room->code}] {$room->name} tidak mendukung instrumen {$instrumentName}.");
        }
    }

    Schedule::create([
        'enrollment_id' => $enrollment->id,
        'day_of_week'   => $data['day_of_week'],
        'start_time'    => $data['start_time'],
        'end_time'      => $data['end_time'],
        'room_id'       => $data['room_id'] ?? null,
        'notes'         => $data['notes'] ?? null,
        'is_active'     => true,
    ]);

    return back()->with('success', 'Jadwal mingguan berhasil ditambahkan.');
}
```

- [ ] **Step 4: Jalankan test — pastikan PASS**
```
php artisan test --filter=ScheduleMultiEnrollmentTest
```
Expected: 2 PASS

- [ ] **Step 5: Update tab Jadwal di `students/show.blade.php`**

Cari blok:
```blade
{{-- Jadwal Mingguan --}}
@if($activeEnrollment)
```

Ganti seluruh blok "Jadwal Mingguan" (dari `{{-- Jadwal Mingguan --}}` hingga `@endif` penutup blok `@if($activeEnrollment)` yang menutup div ruang jadwal) dengan struktur baru:

```blade
{{-- Jadwal Mingguan per Enrollment --}}
@if($activeEnrollments->isNotEmpty())
    @foreach($activeEnrollments as $enrollment)
    @php
        $enrollInstrument = $enrollment->package?->instrument?->name;
        $enrollmentOpenKey = 'create-' . $enrollment->id;
    @endphp
    <div class="bg-mk-card rounded-xl border border-mk-borderLight shadow-sm p-5"
         x-data="{ openCreate: false }">
        {{-- Header enrollment --}}
        <div class="flex justify-between items-center mb-3">
            <div>
                <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#5DB890">
                    Jadwal Mingguan — {{ $enrollment->package?->instrument?->name ?? '?' }}
                </div>
                <div class="text-xs text-mk-dim mt-0.5">
                    {{ $enrollment->package?->code ?? '?' }}
                    · {{ $enrollment->teacher?->name ?? '?' }}
                    @if($enrollment->is_primary)
                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold"
                              style="background:rgba(93,184,144,0.12);color:#5DB890">Utama</span>
                    @endif
                </div>
            </div>
            <button type="button"
                    @click="openCreate = !openCreate"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                    style="background:rgba(93,184,144,0.15);color:#5DB890">
                + Tambah Jadwal
            </button>
        </div>

        {{-- Form tambah jadwal untuk enrollment ini --}}
        <div x-show="openCreate" x-cloak
             x-data="{
                 selectedDay: '',
                 startTime: '',
                 endTime: '',
                 rooms: {{ Js::from($roomsForFilter) }},
                 booked: {{ Js::from($bookedSchedules) }},
                 instrument: {{ Js::from($enrollInstrument) }},
                 get availableRooms() {
                     return this.rooms.filter(room => {
                         if (this.instrument && !room.supported_instruments.includes(this.instrument)) {
                             return false;
                         }
                         if (!this.selectedDay || !this.startTime || !this.endTime) {
                             return true;
                         }
                         const occupants = this.booked.filter(s =>
                             s.room_id === room.id &&
                             s.day_of_week === parseInt(this.selectedDay) &&
                             s.start_time.slice(0,5) < this.endTime &&
                             s.end_time.slice(0,5) > this.startTime
                         ).length;
                         return occupants < room.capacity;
                     });
                 }
             }"
             class="mb-4 rounded-xl p-4"
             style="background:rgba(93,184,144,0.06);border:1px solid rgba(93,184,144,0.2)">
            <form method="POST" action="{{ route('schedules.store', $student->id) }}">
                @csrf
                <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                    <div>
                        <label class="block text-xs text-mk-dim mb-1">Hari <span class="text-red-400">*</span></label>
                        <select name="day_of_week" x-model="selectedDay" required class="block w-full rounded-lg text-sm px-2 py-1.5">
                            <option value="">—</option>
                            @foreach(\App\Models\Schedule::DAY_NAMES as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-mk-dim mb-1">Mulai <span class="text-red-400">*</span></label>
                        <input type="time" name="start_time" x-model="startTime" required class="block w-full rounded-lg text-sm px-2 py-1.5">
                    </div>
                    <div>
                        <label class="block text-xs text-mk-dim mb-1">Selesai <span class="text-red-400">*</span></label>
                        <input type="time" name="end_time" x-model="endTime" required class="block w-full rounded-lg text-sm px-2 py-1.5">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-mk-dim mb-1">Ruangan</label>
                        <select name="room_id" class="block w-full rounded-lg text-sm px-2 py-1.5">
                            <option value="">— Pilih —</option>
                            <template x-for="r in availableRooms" :key="r.id">
                                <option :value="r.id"
                                        x-text="`[${r.code}] ${r.name} (kap. ${r.capacity})`">
                                </option>
                            </template>
                        </select>
                        <p class="text-xs mt-1" style="color:#F87171"
                           x-show="instrument && availableRooms.length === 0 && (selectedDay || startTime)">
                            Tidak ada ruangan tersedia untuk slot &amp; instrumen ini.
                        </p>
                    </div>
                    <div class="col-span-2 md:col-span-5">
                        <label class="block text-xs text-mk-dim mb-1">Catatan</label>
                        <input type="text" name="notes" maxlength="500" class="block w-full rounded-lg text-sm px-2 py-1.5">
                    </div>
                </div>
                <button type="submit" class="mt-2 px-3 py-1.5 rounded-lg text-xs font-semibold"
                        style="background:rgba(93,184,144,0.2);color:#5DB890">
                    Simpan Jadwal
                </button>
            </form>
        </div>

        {{-- Tabel jadwal enrollment ini --}}
        @if($enrollment->schedules->isEmpty())
        <p class="text-sm text-mk-dim">Belum ada jadwal. Klik "+ Tambah Jadwal" di atas.</p>
        @else
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-mk-borderLight">
                    <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-mk-dim font-semibold">Hari</th>
                    <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-mk-dim font-semibold">Jam</th>
                    <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-mk-dim font-semibold">Ruang</th>
                    <th class="pb-2 text-center text-[10px] uppercase tracking-wide text-mk-dim font-semibold">Status</th>
                    <th class="pb-2 text-right text-[10px] uppercase tracking-wide text-mk-dim font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($enrollment->schedules as $sch)
                <tr class="border-b border-mk-borderLight {{ $sch->is_active ? '' : 'opacity-50' }}">
                    <td class="py-2 text-mk-muted">{{ $sch->day_name }}</td>
                    <td class="py-2 font-mono text-mk-muted">
                        {{ \Carbon\Carbon::parse($sch->start_time)->format('H:i') }} -
                        {{ \Carbon\Carbon::parse($sch->end_time)->format('H:i') }}
                    </td>
                    <td class="py-2 text-mk-dim">{{ $sch->room ? '[' . $sch->room->code . '] ' . $sch->room->name : '—' }}</td>
                    <td class="py-2 text-center">
                        @if($sch->is_active)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                              style="background:rgba(52,211,153,0.12);color:#34D399">Aktif</span>
                        @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                              style="background:rgba(139,146,168,0.12);color:#8B92A8">Nonaktif</span>
                        @endif
                    </td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <button type="button"
                                @click="editSchedule = {
                                    id: {{ $sch->id }},
                                    url: '{{ route('schedules.update', [$student->id, $sch->id]) }}',
                                    day_of_week: {{ $sch->day_of_week }},
                                    start_time: '{{ substr($sch->start_time, 0, 5) }}',
                                    end_time: '{{ substr($sch->end_time, 0, 5) }}',
                                    room_id: {{ $sch->room_id ?? 'null' }},
                                    notes: @js($sch->notes ?? ''),
                                    instrument: @js($enrollInstrument ?? '')
                                }"
                                class="text-xs hover:underline" style="color:#5DB890">Edit</button>
                        <form method="POST" action="{{ route('schedules.toggle-active', [$student->id, $sch->id]) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs hover:underline" style="color:#FBBF24">
                                {{ $sch->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('schedules.destroy', [$student->id, $sch->id]) }}" class="inline"
                              onsubmit="return confirm('Hapus jadwal ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs hover:underline" style="color:#F87171">Hapus</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <p class="text-xs text-mk-dim mt-2">
            Nonaktifkan menghentikan generator sesi baru. Hapus hanya bisa jika belum ada sesi ter-generate.
        </p>
        @endif
    </div>
    @endforeach
@else
<div class="bg-mk-card rounded-xl border border-mk-borderLight shadow-sm p-5">
    <p class="text-sm text-mk-dim">Belum ada enrollment aktif. Ubah status murid ke Aktif lewat panel Lifecycle di atas.</p>
</div>
@endif
```

- [ ] **Step 6: Update modal Edit Jadwal — pakai `editSchedule.instrument` (bukan global `$studentInstrument`)**

Cari di modal edit (sekitar baris 922–946 di show.blade.php):
```js
instrument: {{ Js::from($studentInstrument) }},
```
Ganti menjadi:
```js
get instrument() { return editSchedule ? editSchedule.instrument : '' },
```

Dan hapus `rooms` + `booked` dari x-data modal edit (sudah ada di parent scope). Jika tidak ada di parent scope, tambahkan. Cek apakah `rooms` dan `booked` sudah di-define di outer `x-data` atau perlu dipertahankan.

> **Catatan:** Modal edit tetap satu (tidak diloop), dikontrol `editSchedule` Alpine state dari outer div. `editSchedule.instrument` diisi saat klik tombol Edit di baris masing-masing enrollment.

- [ ] **Step 7: Hapus variabel lama dari `show.blade.php` @php block**

Di blok `@php` awal halaman (sekitar baris 44–51), hapus baris:
```php
$activeEnrollment = $student->enrollments->firstWhere('status', 'ACTIVE');
$studentInstrument = $activeEnrollment?->package?->instrument?->name;
```

Pastikan tidak ada referensi `$activeEnrollment` yang tertinggal di tab Jadwal (referensi di tab Info tetap OK karena `$student->primaryEnrollment`).

- [ ] **Step 8: Jalankan test suite**
```
php artisan test --filter=ScheduleMultiEnrollmentTest
php artisan test --filter=ScheduleInstrumentCheckTest
```
Expected: semua PASS

- [ ] **Step 9: Commit**
```
git add app/Http/Controllers/ScheduleController.php
git add resources/views/students/show.blade.php
git add tests/Feature/ScheduleMultiEnrollmentTest.php
git commit -m "M03: Multi-enrollment tab Jadwal — form per-enrollment, store() pakai enrollment_id, instrumen per-enrollment"
```

---

## Task 3 — A5: Route nesting + ownership validation

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/ScheduleController.php`

- [ ] **Step 1: Update routes ke nested pattern**

Di `routes/web.php`, cari blok `===== M03: Schedule mingguan tetap =====`, ganti:
```php
// Sebelum
Route::patch('schedules/{schedule}',
    [ScheduleController::class, 'update']
)->name('schedules.update');
Route::delete('schedules/{schedule}',
    [ScheduleController::class, 'destroy']
)->name('schedules.destroy');
Route::post('schedules/{schedule}/toggle-active',
    [ScheduleController::class, 'toggleActive']
)->name('schedules.toggle-active');
```

Ganti menjadi:
```php
Route::patch('students/{student}/schedules/{schedule}',
    [ScheduleController::class, 'update']
)->name('schedules.update');
Route::delete('students/{student}/schedules/{schedule}',
    [ScheduleController::class, 'destroy']
)->name('schedules.destroy');
Route::post('students/{student}/schedules/{schedule}/toggle-active',
    [ScheduleController::class, 'toggleActive']
)->name('schedules.toggle-active');
```

- [ ] **Step 2: Update controller — tambah `$student` parameter + ownership guard ke semua method**

`update()` — ubah signature dan tambah guard di baris pertama method body:
```php
public function update(Request $request, Student $student, Schedule $schedule)
{
    abort_unless(
        $schedule->enrollment->student_id === $student->id,
        403,
        'Jadwal tidak ditemukan untuk murid ini.'
    );

    // ... sisa kode update() tidak berubah ...
}
```

`destroy()` — ubah signature dan tambah guard:
```php
public function destroy(Student $student, Schedule $schedule)
{
    abort_unless(
        $schedule->enrollment->student_id === $student->id,
        403,
        'Jadwal tidak ditemukan untuk murid ini.'
    );

    // ... sisa kode destroy() tidak berubah ...
}
```

`toggleActive()` — ubah signature dan tambah guard:
```php
public function toggleActive(Student $student, Schedule $schedule)
{
    abort_unless(
        $schedule->enrollment->student_id === $student->id,
        403,
        'Jadwal tidak ditemukan untuk murid ini.'
    );

    // ... sisa kode toggleActive() tidak berubah ...
}
```

- [ ] **Step 3: Pastikan test suite masih passing**
```
php artisan test --filter=ScheduleMultiEnrollmentTest
php artisan test --filter=ScheduleInstrumentCheckTest
```
Expected: PASS (route names tidak berubah, hanya parameter berubah)

- [ ] **Step 4: Commit**
```
git add routes/web.php app/Http/Controllers/ScheduleController.php
git commit -m "M03: Nested routes schedules/{student}/{schedule} + ownership validation"
```

---

## Task 4 — B1: Expand modal DIGANTI (jam + ruang) + AttendanceService honor pending

**Files:**
- Modify: `app/Http/Requests/UpdateAbsensiRequest.php`
- Modify: `app/Services/AttendanceService.php`
- Modify: `resources/views/absensi/_row.blade.php`

### Step 1 — Tulis failing test untuk DIGANTI honor pending

Buka `tests/Feature/Admin/AbsensiControllerTest.php`, tambahkan test baru di akhir class:

```php
/** DIGANTI harus set honor_code = null (pending konfirmasi), bukan H_PENG langsung */
public function test_diganti_sets_honor_pending(): void
{
    $session = $this->createTestSession(['status' => 'SCHEDULED']);
    $sub     = Teacher::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->patchJson(route('absensi.update', $session), [
            'status'                => 'DIGANTI',
            'substitute_teacher_id' => $sub->id,
        ])
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('class_sessions', [
        'id'                    => $session->id,
        'status'                => 'DIGANTI',
        'substitute_teacher_id' => $sub->id,
        'honor_code'            => null,   // pending — belum dikonfirmasi
        'honor_amount'          => 0,
    ]);
}

/** DIGANTI dengan jam pengganti harus update start_time/end_time sesi */
public function test_diganti_updates_time_when_substitute_time_provided(): void
{
    $session = $this->createTestSession(['status' => 'SCHEDULED', 'start_time' => '10:00:00', 'end_time' => '10:30:00']);
    $sub     = Teacher::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->patchJson(route('absensi.update', $session), [
            'status'                 => 'DIGANTI',
            'substitute_teacher_id'  => $sub->id,
            'substitute_start_time'  => '14:00',
            'substitute_end_time'    => '14:30',
        ])
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('class_sessions', [
        'id'         => $session->id,
        'start_time' => '14:00:00',
        'end_time'   => '14:30:00',
    ]);
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**
```
php artisan test --filter="AbsensiControllerTest::test_diganti_sets_honor_pending"
php artisan test --filter="AbsensiControllerTest::test_diganti_updates_time_when_substitute_time_provided"
```
Expected: FAIL

- [ ] **Step 3: Update `UpdateAbsensiRequest` — tambah field opsional jam/ruang pengganti**

Tambahkan di `rules()`:
```php
// Jam pengganti untuk DIGANTI (opsional)
'substitute_start_time' => [
    'nullable',
    'date_format:H:i',
    'required_with:substitute_end_time',
],
'substitute_end_time' => [
    'nullable',
    'date_format:H:i',
    'after:substitute_start_time',
    'required_with:substitute_start_time',
],
// Ruangan pengganti untuk DIGANTI (opsional)
'substitute_room_id' => [
    'nullable',
    'exists:rooms,id',
],
```

Tambahkan di `messages()`:
```php
'substitute_start_time.date_format'    => 'Format jam mulai pengganti harus HH:MM.',
'substitute_start_time.required_with'  => 'Jam mulai pengganti wajib jika jam selesai diisi.',
'substitute_end_time.date_format'      => 'Format jam selesai pengganti harus HH:MM.',
'substitute_end_time.after'            => 'Jam selesai pengganti harus setelah jam mulai.',
'substitute_end_time.required_with'    => 'Jam selesai pengganti wajib jika jam mulai diisi.',
'substitute_room_id.exists'            => 'Ruangan pengganti tidak ditemukan.',
```

- [ ] **Step 4: Update `AttendanceService::recordAttendance()` — DIGANTI honor pending + update jam/ruang**

Di method `recordAttendance()`, ganti blok `$update = [...]`:

```php
$update = [
    'status'      => $status,
    'late_minutes' => $status === 'HADIR_TERLAMBAT'
        ? ($data['late_minutes'] ?? null)
        : null,
    'substitute_teacher_id' => $status === 'DIGANTI'
        ? $data['substitute_teacher_id']
        : null,
    'notes' => $data['notes'] ?? $session->notes,
];

// Untuk DIGANTI: update jam/ruang jika pengganti masuk di waktu/tempat berbeda
if ($status === 'DIGANTI') {
    if (!empty($data['substitute_start_time']) && !empty($data['substitute_end_time'])) {
        $update['start_time'] = $data['substitute_start_time'] . ':00';
        $update['end_time']   = $data['substitute_end_time'] . ':00';
    }
    if (array_key_exists('substitute_room_id', $data) && $data['substitute_room_id'] !== null) {
        $update['room_id'] = $data['substitute_room_id'];
    }
}
```

Di method `calculateHonor()`, di bagian Reguler/Hobby match expression, ubah:
```php
// Sebelum
'DIGANTI' => 'H_PENG',

// Sesudah
'DIGANTI' => null,   // honor pending — akan di-set saat confirmSubstitute()
```

Dan di bagian DUO match expression, ubah juga:
```php
// Sebelum
'DIGANTI' => 'H_PENG',

// Sesudah
'DIGANTI' => null,
```

Untuk kasus DUO honor_amount saat DIGANTI (sebelumnya mengembalikan $honorPerMurid untuk H_PENG):
```php
return ['code' => $code, 'amount' => $code ? $honorPerMurid : 0];
```
Karena $code sekarang null untuk DIGANTI, ini otomatis return amount = 0. Tidak perlu ubah.

- [ ] **Step 5: Update `AbsensiController::update()` — sertakan field baru ke AttendanceService**

Di `recordAttendance()` call dalam `AbsensiController::update()`:
```php
$this->attendanceService->recordAttendance($classSession, [
    'status'                 => $request->status,
    'late_minutes'           => $request->late_minutes,
    'substitute_teacher_id'  => $request->substitute_teacher_id,
    'substitute_start_time'  => $request->substitute_start_time,
    'substitute_end_time'    => $request->substitute_end_time,
    'substitute_room_id'     => $request->substitute_room_id,
    'notes'                  => $request->notes,
    '__session'              => $classSession,
]);
```

- [ ] **Step 6: Jalankan test — pastikan PASS**
```
php artisan test --filter="AbsensiControllerTest::test_diganti_sets_honor_pending"
php artisan test --filter="AbsensiControllerTest::test_diganti_updates_time_when_substitute_time_provided"
```
Expected: PASS

- [ ] **Step 7: Update modal DIGANTI di `absensi/_row.blade.php` — tambah jam + ruang**

Di Alpine.js state (awal `x-data="{...}"`), tambahkan variabel baru setelah `substituteId`:
```js
substituteStartTime: '',
substituteEndTime: '',
substituteRoomId: null,
```

Di fungsi `save('DIGANTI', ...)` yang ada di tombol Simpan modal DIGANTI, ganti call menjadi:
```js
@click="if(substituteId) save('DIGANTI', {
    substitute_teacher_id: substituteId,
    substitute_start_time: substituteStartTime || null,
    substitute_end_time: substituteEndTime || null,
    substitute_room_id: substituteRoomId || null,
})"
```

Di HTML modal DIGANTI, setelah select guru pengganti dan sebelum tombol Simpan, tambahkan:
```html
<div class="grid grid-cols-2 gap-2 mb-3 mt-2">
    <div>
        <label class="block text-mk-dim text-xs mb-1">Jam Mulai Pengganti <span class="text-mk-dim">(opsional)</span></label>
        <input type="time" x-model="substituteStartTime"
            class="w-full border border-mk-border text-mk-muted rounded px-3 py-1.5 text-sm">
    </div>
    <div>
        <label class="block text-mk-dim text-xs mb-1">Jam Selesai Pengganti</label>
        <input type="time" x-model="substituteEndTime"
            class="w-full border border-mk-border text-mk-muted rounded px-3 py-1.5 text-sm">
    </div>
</div>
<div class="mb-3">
    <label class="block text-mk-dim text-xs mb-1">Ruangan Pengganti <span class="text-mk-dim">(opsional)</span></label>
    <select x-model="substituteRoomId"
        class="w-full border border-mk-border text-mk-muted rounded px-3 py-1.5 text-sm">
        <option value="">— Sama dengan ruangan asli —</option>
        @foreach($rooms as $room)
            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
        @endforeach
    </select>
</div>
<p class="text-mk-dim text-xs mb-3">Honor otomatis ke guru pengganti setelah dikonfirmasi hadir.</p>
```

- [ ] **Step 8: Jalankan semua test terkait**
```
php artisan test --filter=AbsensiControllerTest
php artisan test --filter=RescheduleTest
```
Expected: PASS

- [ ] **Step 9: Commit**
```
git add app/Http/Requests/UpdateAbsensiRequest.php
git add app/Services/AttendanceService.php
git add app/Http/Controllers/AbsensiController.php
git add resources/views/absensi/_row.blade.php
git commit -m "M04: DIGANTI two-phase — honor pending sampai dikonfirmasi, modal + jam/ruang pengganti"
```

---

## Task 5 — B2: Endpoint `confirmSubstitute` + UI tombol konfirmasi

**Files:**
- Modify: `app/Http/Controllers/AbsensiController.php`
- Modify: `app/Services/AttendanceService.php`
- Modify: `resources/views/absensi/_row.blade.php`
- Modify: `routes/web.php`

### Step 1 — Tulis failing test untuk confirmSubstitute

Tambahkan ke `tests/Feature/Admin/AbsensiControllerTest.php`:

```php
/** confirmSubstitute hadir: set honor_code H_PENG + honor_amount */
public function test_confirm_substitute_hadir_sets_honor(): void
{
    $session = $this->createTestSession(['status' => 'DIGANTI', 'honor_code' => null, 'honor_amount' => 0]);
    $sub     = Teacher::factory()->create(['is_active' => true]);
    $session->update(['substitute_teacher_id' => $sub->id]);

    $this->actingAs($this->admin)
        ->postJson(route('absensi.confirm-substitute', $session), ['action' => 'hadir'])
        ->assertJson(['success' => true, 'action' => 'hadir']);

    $session->refresh();
    $this->assertNotNull($session->honor_code);
    $this->assertSame('H_PENG', $session->honor_code);
    $this->assertGreaterThan(0, $session->honor_amount);
}

/** confirmSubstitute batal: reset ke SCHEDULED, restore jam/ruang dari schedule */
public function test_confirm_substitute_batal_resets_session(): void
{
    // Buat schedule dulu
    $teacher  = Teacher::factory()->create(['is_active' => true]);
    $student  = Student::factory()->create(['status' => 'Aktif']);
    $instr    = \App\Models\Instrument::create(['name' => 'Piano', 'code' => 'P', 'is_active' => true, 'sort_order' => 1]);
    $pkg      = \App\Models\Package::create([
        'code' => 'REG-P-B', 'instrument_id' => $instr->id,
        'class_type' => 'REGULER', 'grade' => 'Basic', 'duration_min' => 30,
        'price_per_month' => 340000, 'is_active' => true, 'sort_order' => 1,
    ]);
    $enrollment = Enrollment::create([
        'student_id' => $student->id, 'package_id' => $pkg->id,
        'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
        'is_primary' => true, 'effective_date' => now()->toDateString(),
    ]);
    $schedule = Schedule::create([
        'enrollment_id' => $enrollment->id, 'day_of_week' => 1,
        'start_time' => '10:00:00', 'end_time' => '10:30:00',
        'room_id' => null, 'is_active' => true,
    ]);
    $sub = Teacher::factory()->create(['is_active' => true]);
    $session = ClassSession::create([
        'schedule_id' => $schedule->id, 'enrollment_id' => $enrollment->id,
        'student_id' => $student->id, 'teacher_id' => $teacher->id,
        'substitute_teacher_id' => $sub->id,
        'session_date' => now()->toDateString(),
        'start_time' => '14:00:00', 'end_time' => '14:30:00', // sudah diubah pengganti
        'status' => 'DIGANTI', 'honor_code' => null, 'honor_amount' => 0,
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('absensi.confirm-substitute', $session), ['action' => 'batal'])
        ->assertJson(['success' => true, 'action' => 'batal']);

    $session->refresh();
    $this->assertSame('SCHEDULED', $session->status);
    $this->assertNull($session->substitute_teacher_id);
    $this->assertNull($session->honor_code);
    // Jam harus dikembalikan ke jadwal asli
    $this->assertSame('10:00:00', $session->start_time);
    $this->assertSame('10:30:00', $session->end_time);
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**
```
php artisan test --filter="AbsensiControllerTest::test_confirm_substitute_hadir_sets_honor"
php artisan test --filter="AbsensiControllerTest::test_confirm_substitute_batal_resets_session"
```
Expected: FAIL (route belum ada)

- [ ] **Step 3: Tambah route baru di `routes/web.php`**

Setelah route `absensi.split`, tambahkan:
```php
// ===== M04: Konfirmasi kehadiran guru pengganti (DIGANTI two-phase) =====
Route::post('/absensi/{classSession}/confirm-substitute',
    [AbsensiController::class, 'confirmSubstitute']
)->name('absensi.confirm-substitute');
```

- [ ] **Step 4: Tambah method `calculateSubstituteHonor()` ke `AttendanceService`**

Tambahkan method public baru di `AttendanceService` (setelah method `recordAttendance()`):

```php
/**
 * Hitung honor untuk guru pengganti (H_PENG).
 * Dipanggil saat confirmSubstitute(action='hadir').
 *
 * @return array{code: string, amount: int}
 */
public function calculateSubstituteHonor(ClassSession $session): array
{
    $package = $session->enrollment?->package;

    if (!$package) {
        return ['code' => 'H_PENG', 'amount' => 0];
    }

    // Kids Class: flat per murid
    if ($package->isKidsClass()) {
        return ['code' => 'H_KIDS', 'amount' => self::KIDS_HONOR_PER_STUDENT];
    }

    // DUO: honor dari PayrollConfig H_DUO
    if ($package->isDuo()) {
        $honorPerMurid = (int) (PayrollConfig::where('scenario_code', 'H_DUO')
            ->value('value_or_formula') ?? 40000);
        return ['code' => 'H_PENG', 'amount' => $honorPerMurid];
    }

    // Reguler/Hobby: formula price_per_month * 50% / 4
    $amount = (int) round($package->price_per_month * 0.5 / 4);
    return ['code' => 'H_PENG', 'amount' => $amount];
}
```

- [ ] **Step 5: Tambah method `confirmSubstitute()` ke `AbsensiController`**

Tambahkan method baru setelah `storeSplitPart()`:

```php
/**
 * Konfirmasi kehadiran guru pengganti (DIGANTI two-phase).
 *
 * action = hadir : hitung honor H_PENG ke pengganti (final)
 * action = batal : reset ke SCHEDULED, restore jam/ruang dari jadwal asli
 */
public function confirmSubstitute(Request $request, ClassSession $classSession): JsonResponse
{
    $request->validate([
        'action' => ['required', \Illuminate\Validation\Rule::in(['hadir', 'batal'])],
    ], [
        'action.required' => 'Aksi konfirmasi wajib diisi.',
        'action.in'       => 'Aksi tidak valid. Pilih hadir atau batal.',
    ]);

    // Guard: hanya berlaku untuk sesi DIGANTI yang belum dikonfirmasi (honor_code = null)
    if ($classSession->status !== ClassSession::STATUS_DIGANTI) {
        return response()->json([
            'success' => false,
            'message' => 'Sesi bukan berstatus DIGANTI.',
        ], 422);
    }

    if ($classSession->honor_code !== null) {
        return response()->json([
            'success' => false,
            'message' => 'Sesi sudah dikonfirmasi sebelumnya.',
        ], 422);
    }

    if ($request->action === 'hadir') {
        $classSession->loadMissing(['enrollment.package']);
        $honor = $this->attendanceService->calculateSubstituteHonor($classSession);

        $classSession->update([
            'honor_code'   => $honor['code'],
            'honor_amount' => $honor['amount'],
        ]);

        // Update last_session_at murid
        $classSession->student?->update([
            'last_session_at' => \Carbon\Carbon::parse($classSession->session_date)
                ->setTimeFromTimeString($classSession->start_time),
        ]);

        return response()->json([
            'success'       => true,
            'action'        => 'hadir',
            'honor_code'    => $honor['code'],
            'honor_amount'  => $honor['amount'],
        ]);
    }

    // action = batal: reset ke SCHEDULED, restore jam/ruang dari jadwal mingguan asli
    $schedule = $classSession->schedule;

    $classSession->update([
        'status'                => ClassSession::STATUS_SCHEDULED,
        'substitute_teacher_id' => null,
        'honor_code'            => null,
        'honor_amount'          => null,
        'start_time'            => $schedule?->start_time ?? $classSession->start_time,
        'end_time'              => $schedule?->end_time   ?? $classSession->end_time,
        'room_id'               => $schedule?->room_id    ?? $classSession->room_id,
    ]);

    return response()->json([
        'success' => true,
        'action'  => 'batal',
    ]);
}
```

- [ ] **Step 6: Jalankan test — pastikan PASS**
```
php artisan test --filter="AbsensiControllerTest::test_confirm_substitute_hadir_sets_honor"
php artisan test --filter="AbsensiControllerTest::test_confirm_substitute_batal_resets_session"
```
Expected: PASS

- [ ] **Step 7: Update `absensi/_row.blade.php` — tambah state + tombol konfirmasi**

Di Alpine.js `x-data="{...}"`, tambahkan variabel baru setelah `substituteId` dan `substituteLabel`:
```js
honorConfirmed: {{ ($session->status === 'DIGANTI' && $session->honor_code !== null) ? 'true' : 'false' }},
```

Tambahkan fungsi `confirmSubstitute` di dalam Alpine.js `x-data`:
```js
async confirmSubstitute(action) {
    this.loading = true;
    this.errorMsg = '';
    try {
        const res = await fetch('{{ route('absensi.confirm-substitute', $session) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ action }),
        });
        const data = await res.json();
        if (data.success) {
            if (action === 'hadir') {
                this.honorConfirmed = true;
                this.$el.dataset.status = 'DIGANTI';
            } else {
                // batal: kembali ke SCHEDULED
                this.status = 'SCHEDULED';
                this.substituteId = null;
                this.substituteLabel = '';
                this.honorConfirmed = false;
                this.$el.dataset.status = 'SCHEDULED';
            }
        } else {
            this.errorMsg = data.message || 'Gagal mengkonfirmasi.';
        }
    } finally {
        this.loading = false;
    }
},
```

Di bagian badge status DIGANTI (di dalam `x-text`), ubah:
```js
// Sebelum
status === 'DIGANTI' ? '↔ ' + substituteLabel :

// Sesudah
status === 'DIGANTI' ? '↔ ' + substituteLabel + (honorConfirmed ? ' ✓' : ' ⏳') :
```

Di bagian badge setelah status DIGANTI, tambahkan tombol konfirmasi tepat setelah badge (dalam elemen `div x-show="status !== 'SCHEDULED'"`):

```html
{{-- Tombol konfirmasi kehadiran pengganti — hanya tampil jika DIGANTI dan belum dikonfirmasi --}}
<div x-show="status === 'DIGANTI' && !honorConfirmed"
     class="flex items-center gap-1 mt-1">
    <button @click="confirmSubstitute('hadir')"
            :disabled="loading"
            class="rounded px-2 py-1 text-xs font-semibold bg-green-100 text-green-700 hover:bg-green-200 disabled:opacity-40">
        ✓ Hadir
    </button>
    <button @click="confirmSubstitute('batal')"
            :disabled="loading"
            class="rounded px-2 py-1 text-xs bg-red-50 text-red-500 hover:bg-red-100 disabled:opacity-40">
        ✗ Batalkan
    </button>
</div>
```

- [ ] **Step 8: Jalankan full test suite**
```
php artisan test --filter=AbsensiControllerTest
php artisan test --filter=RescheduleTest
php artisan test --filter=SplitRescheduleTest
```
Expected: semua PASS

- [ ] **Step 9: Commit**
```
git add routes/web.php
git add app/Http/Controllers/AbsensiController.php
git add app/Services/AttendanceService.php
git add resources/views/absensi/_row.blade.php
git commit -m "M04: confirmSubstitute endpoint — konfirmasi kehadiran guru pengganti DIGANTI"
```

---

## Task 6 — B3: Helper text + final smoke test

**Files:**
- Modify: `resources/views/absensi/_row.blade.php`
- Modify: `resources/views/sessions/index.blade.php`

- [ ] **Step 1: Tambah helper text di modal DIGANTI**

Di dalam modal DIGANTI di `_row.blade.php`, tambahkan note di bawah header:
```html
<p class="text-mk-dim text-xs mb-3">
    Hari H: guru asli berhalangan. Tugaskan pengganti + opsional jam/ruang baru.
    Konfirmasi kehadiran setelah sesi berlangsung.
</p>
```

- [ ] **Step 2: Tambah note di halaman sesi (`sessions/index.blade.php`)**

Di tombol/section Edit sesi (cari form atau modal edit sesi), tambahkan note:
```html
<p class="text-xs text-mk-dim mt-1">
    Edit ini untuk koreksi data sesi sebelum berlangsung (guru, jam, ruang).
    Untuk penggantian guru hari H, gunakan "DIGANTI" di halaman Absensi.
</p>
```

- [ ] **Step 3: Jalankan full test suite akhir**
```
php artisan test
```
Expected: semua PASS, tidak ada regression

- [ ] **Step 4: Final commit**
```
git add resources/views/absensi/_row.blade.php
git add resources/views/sessions/index.blade.php
git commit -m "M04: Helper text klarifikasi DIGANTI vs Session Edit"
```

---

## Self-Review Checklist

- [x] A1: `store()` pakai `enrollment_id` dari request — Task 2 Step 3
- [x] A2: Tab Jadwal loop semua `$activeEnrollments` — Task 2 Step 5
- [x] A3: `$bookedSchedules` include `id` — Task 1 Step 1
- [x] A4: Instrumen per-enrollment di form — Task 2 Step 5 (x-data per enrollment)
- [x] A5: Nested routes + ownership guard — Task 3
- [x] B1: Modal DIGANTI + jam/ruang opsional + AttendanceService honor pending — Task 4
- [x] B2: `confirmSubstitute` endpoint + UI tombol — Task 5
- [x] B3: Helper text — Task 6
- [x] Test coverage: ScheduleMultiEnrollmentTest, AbsensiControllerTest DIGANTI tests
- [x] Tidak ada schema DB baru
