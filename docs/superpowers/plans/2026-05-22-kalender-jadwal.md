# Kalender Jadwal Mingguan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman read-only yang menampilkan seluruh sesi konkret minggu ini sebagai grid hari × jam, lengkap dengan filter guru/ruangan, navigasi minggu, warna status, dan popup detail Alpine.

**Architecture:** `KalenderController` query `ClassSession` per minggu, group per `day_of_week` + `start_time`, render server-side Blade. Navigasi minggu = full page reload via `?week=YYYY-MM-DD`. Alpine hanya untuk popup detail dan auto-submit filter.

**Tech Stack:** Laravel 11, Blade, Alpine.js, Tailwind CSS, Carbon, Spatie Permission

**Spec:** `docs/superpowers/specs/2026-05-22-kalender-jadwal-design.md`

---

> **Catatan teknis penting:** `ClassSession` menyimpan `start_time`, `end_time`, `room_id`, `teacher_id`, `student_id` **langsung di tabel `class_sessions`** (bukan hanya via `schedules`). Ini menyederhanakan query — tidak perlu join ke `schedules` untuk mendapatkan jam. Spec menyebut "skip sesi tanpa schedule", tapi karena `start_time` ada langsung di `ClassSession`, semua sesi bisa ditampilkan.

---

## File Map

| File | Status | Tanggung Jawab |
|---|---|---|
| `app/Http/Controllers/KalenderController.php` | **CREATE** | Query, grouping, pass ke view |
| `resources/views/kalender/index.blade.php` | **CREATE** | Layout grid, navigator, filter, popup |
| `routes/web.php` | **MODIFY** | Daftarkan route GET /kalender |
| `resources/views/layouts/navigation.blade.php` | **MODIFY** | Tambah menu Kalender di sidebar |
| `tests/Feature/KalenderControllerTest.php` | **CREATE** | Akses kontrol + integrasi grid |

---

## Task 1: Route + Controller Skeleton + Test Akses Kontrol

**Files:**
- Create: `app/Http/Controllers/KalenderController.php`
- Modify: `routes/web.php` (dalam group `role:Owner|Admin|Auditor`, sekitar baris 314)
- Create: `resources/views/kalender/index.blade.php` (placeholder minimal)
- Create: `tests/Feature/KalenderControllerTest.php`

- [ ] **Step 1: Buat test akses kontrol (akan FAIL dulu)**

Buat file `tests/Feature/KalenderControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KalenderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    private function auditorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Auditor');
        return $user;
    }

    public function test_owner_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_admin_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_auditor_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->auditorUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_tamu_tidak_dapat_akses_kalender(): void
    {
        $response = $this->get(route('kalender.index'));
        $response->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Jalankan test — verifikasi FAIL**

```bash
php artisan test tests/Feature/KalenderControllerTest.php
```

Expected: FAIL — `Route [kalender.index] not defined`

- [ ] **Step 3: Buat KalenderController skeleton**

Buat file `app/Http/Controllers/KalenderController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kalender Jadwal Mingguan — tampilan read-only grid sesi per minggu (Senin-Sabtu).
 */
class KalenderController extends Controller
{
    public function index(Request $request): View
    {
        return view('kalender.index');
    }
}
```

- [ ] **Step 4: Daftarkan route di `routes/web.php`**

Cari blok komentar `// ===== M04: Absensi` (sekitar baris 305) dan tambahkan route baru DI BAWAHNYA, masih dalam group `role:Owner|Admin|Auditor`:

```php
        // ===== Kalender Jadwal Mingguan (read-only, semua role) =====
        Route::get('/kalender',
            [\App\Http\Controllers\KalenderController::class, 'index']
        )->name('kalender.index');
```

Tambahkan juga `use App\Http\Controllers\KalenderController;` di bagian `use` atas file routes/web.php bersama controller lainnya.

- [ ] **Step 5: Buat view placeholder minimal**

Buat folder `resources/views/kalender/` dan file `resources/views/kalender/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Kalender Jadwal</h2>
    </x-slot>
    <div class="py-6 px-4 lg:px-8">
        <p class="text-gray-500">Kalender sedang dibangun.</p>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Jalankan test — verifikasi PASS**

```bash
php artisan test tests/Feature/KalenderControllerTest.php
```

Expected: 4 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/KalenderController.php \
        resources/views/kalender/index.blade.php \
        routes/web.php \
        tests/Feature/KalenderControllerTest.php
git commit -m "Kalender: route + controller skeleton + akses kontrol test"
```

---

## Task 2: Controller — Week Parsing, Query, Grouping

**Files:**
- Modify: `app/Http/Controllers/KalenderController.php`
- Modify: `tests/Feature/KalenderControllerTest.php` (tambah test grid)

- [ ] **Step 1: Tambah test week parsing dan struktur $grid**

Tambahkan method berikut ke `KalenderControllerTest`:

```php
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;

// ... (di dalam class, setelah method auditorUser)

public function test_default_week_adalah_senin_minggu_ini(): void
{
    $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
    $response->assertStatus(200);
    $response->assertViewHas('weekStart', function ($weekStart) {
        return $weekStart->isMonday() && $weekStart->isSameWeek(now());
    });
}

public function test_week_param_menentukan_minggu_yang_ditampilkan(): void
{
    $response = $this->actingAs($this->ownerUser())
        ->get(route('kalender.index') . '?week=2026-05-18');
    $response->assertStatus(200);
    $response->assertViewHas('weekStart', function ($weekStart) {
        return $weekStart->format('Y-m-d') === '2026-05-18';
    });
}

public function test_grid_berisi_sesi_minggu_ini(): void
{
    // Buat data minimal
    $teacher    = Teacher::factory()->create(['name' => 'ADI', 'is_active' => true]);
    $room       = Room::factory()->create(['code' => 'R2', 'is_active' => true]);
    $instrument = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
    $package    = Package::factory()->create(['instrument_id' => $instrument->id]);
    $student    = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'teacher_id' => $teacher->id,
        'package_id' => $package->id,
        'status'     => 'ACTIVE',
    ]);

    $senin = Carbon::now()->startOfWeek(Carbon::MONDAY);
    ClassSession::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id'    => $student->id,
        'teacher_id'    => $teacher->id,
        'room_id'       => $room->id,
        'session_date'  => $senin,
        'start_time'    => '09:00:00',
        'end_time'      => '09:30:00',
        'status'        => 'SCHEDULED',
    ]);

    $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
    $response->assertStatus(200);
    $response->assertViewHas('grid', function ($grid) use ($senin) {
        $dow = $senin->dayOfWeek; // 1 = Monday
        return isset($grid[$dow]['09:00:00']) && count($grid[$dow]['09:00:00']) === 1;
    });
}

public function test_filter_teacher_id_hanya_tampilkan_sesi_guru_itu(): void
{
    $teacher1   = Teacher::factory()->create(['name' => 'ADI',    'is_active' => true]);
    $teacher2   = Teacher::factory()->create(['name' => 'THOMAS', 'is_active' => true]);
    $room       = Room::factory()->create(['code' => 'R2', 'is_active' => true]);
    $instrument = Instrument::create(['name' => 'Piano', 'code' => 'PIANO2', 'is_active' => true, 'sort_order' => 2]);
    $package    = Package::factory()->create(['instrument_id' => $instrument->id]);
    $student    = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'teacher_id' => $teacher1->id,
        'package_id' => $package->id,
        'status'     => 'ACTIVE',
    ]);

    $senin = Carbon::now()->startOfWeek(Carbon::MONDAY);

    ClassSession::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id'    => $student->id,
        'teacher_id'    => $teacher1->id,
        'room_id'       => $room->id,
        'session_date'  => $senin,
        'start_time'    => '09:00:00',
        'end_time'      => '09:30:00',
        'status'        => 'SCHEDULED',
    ]);
    ClassSession::factory()->create([
        'enrollment_id' => $enrollment->id,
        'student_id'    => $student->id,
        'teacher_id'    => $teacher2->id,
        'room_id'       => $room->id,
        'session_date'  => $senin,
        'start_time'    => '10:00:00',
        'end_time'      => '10:30:00',
        'status'        => 'SCHEDULED',
    ]);

    $response = $this->actingAs($this->ownerUser())
        ->get(route('kalender.index') . '?teacher_id=' . $teacher1->id);

    $response->assertViewHas('grid', function ($grid) {
        $totalSesi = 0;
        foreach ($grid as $daySlots) {
            foreach ($daySlots as $sessions) {
                $totalSesi += count($sessions);
            }
        }
        return $totalSesi === 1;
    });
}
```

- [ ] **Step 2: Jalankan test baru — verifikasi FAIL**

```bash
php artisan test tests/Feature/KalenderControllerTest.php
```

Expected: test_default_week, test_week_param, test_grid, test_filter FAIL.

- [ ] **Step 3: Implementasi penuh KalenderController**

Ganti seluruh isi `app/Http/Controllers/KalenderController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Room;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kalender Jadwal Mingguan — tampilan read-only grid sesi per minggu (Senin-Sabtu).
 * Data dari class_sessions (sesi konkret), bukan jadwal template.
 */
class KalenderController extends Controller
{
    public function index(Request $request): View
    {
        // --- 1. Resolve minggu yang ditampilkan ---
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->input('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(5); // Sabtu

        // --- 2. Query sesi minggu ini dengan eager load ---
        $query = ClassSession::whereBetween('session_date', [$weekStart, $weekEnd])
            ->with([
                'student',                        // nama + kode murid
                'teacher',                        // nama guru
                'room',                           // kode + nama ruangan
                'enrollment.package.instrument',  // nama instrumen
            ]);

        // --- 3. Apply filter opsional ---
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', (int) $request->input('teacher_id'));
        }
        if ($request->filled('room_id')) {
            $query->where('room_id', (int) $request->input('room_id'));
        }

        $sessions = $query
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        // --- 4. Bangun $grid[day_of_week][start_time] = [ClassSession, ...] ---
        // day_of_week: 1=Senin ... 6=Sabtu (Carbon: 1=Monday)
        $grid = [];
        foreach ($sessions as $session) {
            $dow  = $session->session_date->dayOfWeek; // Carbon: 1=Mon...6=Sat
            $time = $session->start_time;
            $grid[$dow][$time][] = $session;
        }

        // --- 5. Daftar slot jam unik yang tampil (baris grid) ---
        $timeSlots = $sessions->pluck('start_time')->unique()->sort()->values();

        // --- 6. Kolom hari: Carbon objects Senin-Sabtu ---
        $days = [];
        for ($i = 0; $i <= 5; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $days[$date->dayOfWeek] = $date;
        }

        // --- 7. Data dropdown filter ---
        $teachers = Teacher::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $rooms    = Room::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        // --- 8. URL untuk navigator minggu (filter tetap disertakan) ---
        $filterParams = array_filter([
            'teacher_id' => $request->input('teacher_id'),
            'room_id'    => $request->input('room_id'),
        ]);
        $prevWeek    = array_merge($filterParams, ['week' => $weekStart->copy()->subWeek()->format('Y-m-d')]);
        $nextWeek    = array_merge($filterParams, ['week' => $weekStart->copy()->addWeek()->format('Y-m-d')]);
        $currentWeek = array_merge($filterParams, ['week' => Carbon::now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d')]);

        return view('kalender.index', compact(
            'weekStart', 'weekEnd',
            'grid', 'timeSlots', 'days',
            'teachers', 'rooms',
            'prevWeek', 'nextWeek', 'currentWeek',
        ));
    }
}
```

- [ ] **Step 4: Jalankan semua test — verifikasi PASS**

```bash
php artisan test tests/Feature/KalenderControllerTest.php
```

Expected: semua PASS (termasuk 4 test akses kontrol dari Task 1).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/KalenderController.php \
        tests/Feature/KalenderControllerTest.php
git commit -m "Kalender: implementasi controller — week parsing, query, grouping, filter"
```

---

## Task 3: View — Week Navigator + Filter Bar

**Files:**
- Modify: `resources/views/kalender/index.blade.php`

- [ ] **Step 1: Ganti view dengan struktur lengkap + navigator + filter**

Ganti seluruh isi `resources/views/kalender/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Kalender Jadwal</h2>
        <div class="text-xs text-mk-muted mt-0.5">
            Jadwal sesi minggu ini — read-only
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 space-y-4">

        {{-- ===== WEEK NAVIGATOR ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">

                {{-- Navigasi prev / label / next --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('kalender.index', $prevWeek) }}"
                       class="px-3 py-1.5 rounded text-sm border border-gray-200 hover:bg-gray-50 transition-colors">
                        ← Minggu Lalu
                    </a>
                    <span class="px-4 py-1.5 text-sm font-semibold text-gray-800 whitespace-nowrap">
                        {{ $weekStart->translatedFormat('d M') }}
                        –
                        {{ $weekEnd->translatedFormat('d M Y') }}
                    </span>
                    <a href="{{ route('kalender.index', $nextWeek) }}"
                       class="px-3 py-1.5 rounded text-sm border border-gray-200 hover:bg-gray-50 transition-colors">
                        Minggu Depan →
                    </a>
                </div>

                {{-- Tombol Minggu Ini --}}
                <a href="{{ route('kalender.index', $currentWeek) }}"
                   class="px-3 py-1.5 rounded text-sm border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
                    Minggu Ini
                </a>

            </div>
        </div>

        {{-- ===== FILTER BAR ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4"
             x-data>
            <form method="GET" action="{{ route('kalender.index') }}" id="filter-form">
                {{-- Pertahankan week aktif --}}
                <input type="hidden" name="week" value="{{ $weekStart->format('Y-m-d') }}">

                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Guru</label>
                        <select name="teacher_id"
                                class="border-gray-300 rounded text-sm"
                                @change="$el.form.submit()">
                            <option value="">Semua Guru</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}"
                                    {{ request('teacher_id') == $t->id ? 'selected' : '' }}>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ruangan</label>
                        <select name="room_id"
                                class="border-gray-300 rounded text-sm"
                                @change="$el.form.submit()">
                            <option value="">Semua Ruangan</option>
                            @foreach($rooms as $r)
                                <option value="{{ $r->id }}"
                                    {{ request('room_id') == $r->id ? 'selected' : '' }}>
                                    {{ $r->code }} — {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(request('teacher_id') || request('room_id'))
                        <a href="{{ route('kalender.index', ['week' => $weekStart->format('Y-m-d')]) }}"
                           class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded transition-colors">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Grid diisi di Task 4 --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <p class="text-sm text-gray-500">Grid sedang dibangun (Task 4).</p>
        </div>

    </div>
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
```

- [ ] **Step 2: Test manual di browser**

Buka `http://localhost/kalender`. Verifikasi:
- Navigator minggu tampil dengan label tanggal benar
- Tombol ← Minggu Lalu dan Minggu Depan → berfungsi (URL berubah)
- Tombol Minggu Ini kembali ke minggu berjalan
- Dropdown Guru dan Ruangan terisi data
- Pilih guru → halaman reload dengan filter aktif
- "Reset Filter" muncul saat filter aktif

- [ ] **Step 3: Commit**

```bash
git add resources/views/kalender/index.blade.php
git commit -m "Kalender: week navigator + filter bar"
```

---

## Task 4: View — Grid + Event Cells + Warna Status

**Files:**
- Modify: `resources/views/kalender/index.blade.php`

- [ ] **Step 1: Ganti blok "Grid sedang dibangun" dengan grid lengkap**

Cari baris `{{-- Grid diisi di Task 4 --}}` hingga `</div>` penutupnya, ganti dengan:

```blade
        {{-- ===== GRID KALENDER ===== --}}
        @php
            // Warna background per status sesi
            $statusColors = [
                'SCHEDULED'        => 'bg-gray-100 text-gray-600',
                'HADIR'            => 'bg-green-100 text-green-700',
                'HADIR_TERLAMBAT'  => 'bg-green-100 text-green-700',
                'IZIN_RESCHEDULE'  => 'bg-yellow-100 text-yellow-700',
                'IZIN_VIDEO'       => 'bg-yellow-100 text-yellow-700',
                'HANGUS'           => 'bg-red-100 text-red-700',
                'LIBUR'            => 'bg-gray-50 text-gray-400',
                'DIGANTI'          => 'bg-gray-50 text-gray-400',
                'CANCELLED'        => 'bg-gray-50 text-gray-400',
            ];
            $statusLabels = [
                'SCHEDULED'        => 'Terjadwal',
                'HADIR'            => 'Hadir',
                'HADIR_TERLAMBAT'  => 'Hadir (Terlambat)',
                'IZIN_RESCHEDULE'  => 'Izin – Reschedule',
                'IZIN_VIDEO'       => 'Izin – Video',
                'HANGUS'           => 'Hangus',
                'LIBUR'            => 'Libur',
                'DIGANTI'          => 'Diganti',
                'CANCELLED'        => 'Dibatalkan',
            ];
            $statusCoretan = ['LIBUR', 'DIGANTI', 'CANCELLED'];
        @endphp

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
             x-data="{ open: false, sesi: {} }">

            {{-- Pesan minggu kosong --}}
            @if($timeSlots->isEmpty())
                <div class="p-6 text-center">
                    @if(request('teacher_id') || request('room_id'))
                        <p class="text-gray-500 text-sm">Tidak ada sesi untuk filter ini minggu ini.</p>
                    @else
                        <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-700 text-sm inline-block">
                            Sesi belum di-generate untuk minggu ini.
                            Generator otomatis berjalan tanggal 25 setiap bulan.
                        </div>
                    @endif
                </div>
            @else

            {{-- Tabel grid --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    {{-- Header: Jam + Senin–Sabtu --}}
                    <thead>
                        <tr class="border-b bg-gray-50">
                            <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 w-16">Jam</th>
                            @foreach($days as $dow => $date)
                                <th class="py-2 px-2 text-center text-xs font-semibold
                                    {{ $date->isToday() ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600' }}">
                                    <div>{{ $date->translatedFormat('D') }}</div>
                                    <div class="font-normal text-gray-400">{{ $date->format('d/m') }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timeSlots as $time)
                            <tr class="border-b hover:bg-gray-50/50">
                                {{-- Label jam --}}
                                <td class="py-2 px-3 text-xs text-gray-400 align-top whitespace-nowrap">
                                    {{ substr($time, 0, 5) }}
                                </td>
                                {{-- Sel per hari --}}
                                @foreach($days as $dow => $date)
                                    <td class="py-1.5 px-1.5 align-top min-w-[110px]">
                                        @foreach($grid[$dow][$time] ?? [] as $session)
                                            @php
                                                $colorClass = $statusColors[$session->status] ?? 'bg-gray-100 text-gray-600';
                                                $isCoretan  = in_array($session->status, $statusCoretan);
                                                $instrumen  = $session->enrollment->package->instrument->name ?? '?';
                                                $guruNama   = $session->teacher->name ?? '?';
                                                $roomCode   = $session->room->code ?? '?';

                                                // Data untuk popup Alpine
                                                $popupData = [
                                                    'studentName'  => $session->student->full_name ?? '?',
                                                    'studentCode'  => $session->student->student_code ?? '?',
                                                    'studentId'    => $session->student_id,
                                                    'teacherName'  => $guruNama,
                                                    'roomCode'     => $roomCode,
                                                    'roomName'     => $session->room->name ?? '?',
                                                    'startTime'    => substr($session->start_time, 0, 5),
                                                    'endTime'      => substr($session->end_time ?? '', 0, 5),
                                                    'status'       => $session->status,
                                                    'statusLabel'  => $statusLabels[$session->status] ?? $session->status,
                                                    'instrumen'    => $instrumen,
                                                    'isScheduled'  => $session->status === 'SCHEDULED',
                                                    'detailUrl'    => route('students.show', $session->student_id),
                                                    'absensiUrl'   => route('sessions.index'),
                                                ];
                                            @endphp
                                            <button type="button"
                                                    @click="sesi = {{ Js::from($popupData) }}; open = true"
                                                    class="w-full text-left rounded px-1.5 py-1 mb-1 text-xs
                                                           {{ $colorClass }} hover:opacity-80 transition-opacity">
                                                <div class="{{ $isCoretan ? 'line-through' : '' }} font-medium truncate">
                                                    {{ $instrumen }} · {{ $guruNama }}
                                                </div>
                                                <div class="text-xs opacity-70 truncate">
                                                    {{ $roomCode }} · {{ substr($session->start_time, 0, 5) }}
                                                </div>
                                            </button>
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Popup detail diisi di Task 5 --}}

        </div>
```

- [ ] **Step 2: Test manual di browser**

Buka `http://localhost/kalender`. Verifikasi (butuh ada data sesi di database):
- Grid tampil dengan kolom Senin–Sabtu dan baris per slot jam
- Hari ini di-highlight (bg-indigo-50)
- Event cell tampil dengan warna berbeda per status
- Sesi LIBUR/DIGANTI memiliki teks dicoret
- Overflow-x-auto aktif di layar sempit

- [ ] **Step 3: Commit**

```bash
git add resources/views/kalender/index.blade.php
git commit -m "Kalender: grid tabel + event cells + warna status"
```

---

## Task 5: View — Popup Detail Alpine

**Files:**
- Modify: `resources/views/kalender/index.blade.php`

- [ ] **Step 1: Tambahkan popup sebelum penutup `</div>` dari x-data container**

Cari komentar `{{-- Popup detail diisi di Task 5 --}}` dan ganti dengan:

```blade
            {{-- ===== POPUP DETAIL SESI (Alpine) ===== --}}
            <div x-show="open"
                 x-cloak
                 @click.self="open = false"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5"
                     @click.stop>

                    {{-- Header popup --}}
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-800" x-text="sesi.studentName"></h3>
                            <div class="text-xs text-gray-400 font-mono" x-text="sesi.studentCode"></div>
                        </div>
                        <button type="button"
                                @click="open = false"
                                class="text-gray-400 hover:text-gray-600 text-lg leading-none p-1">
                            ×
                        </button>
                    </div>

                    {{-- Detail sesi --}}
                    <div class="space-y-1.5 text-sm text-gray-600 border-t pt-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Instrumen</span>
                            <span class="font-medium" x-text="sesi.instrumen"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Guru</span>
                            <span x-text="sesi.teacherName"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Ruangan</span>
                            <span x-text="sesi.roomCode + ' – ' + sesi.roomName"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Jam</span>
                            <span x-text="sesi.startTime + (sesi.endTime ? ' – ' + sesi.endTime : '')"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Status</span>
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700"
                                  x-text="sesi.statusLabel"></span>
                        </div>
                    </div>

                    {{-- Tombol aksi --}}
                    <div class="mt-4 flex gap-2">
                        <a :href="sesi.detailUrl"
                           class="flex-1 text-center px-3 py-2 rounded text-sm border border-gray-200
                                  text-gray-600 hover:bg-gray-50 transition-colors">
                            Detail Murid
                        </a>
                        <a x-show="sesi.isScheduled"
                           :href="sesi.absensiUrl"
                           class="flex-1 text-center px-3 py-2 rounded text-sm font-medium
                                  bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                            Catat Absensi
                        </a>
                    </div>

                </div>
            </div>
```

- [ ] **Step 2: Test manual di browser**

Klik salah satu event di grid. Verifikasi:
- Popup muncul dengan data murid, guru, ruangan, jam, status
- Status badge tampil dengan teks yang benar
- Tombol "Detail Murid" menuju halaman detail murid
- Tombol "Catat Absensi" hanya muncul jika status = SCHEDULED
- Klik di luar popup (overlay gelap) menutup popup
- Tombol × menutup popup

- [ ] **Step 3: Commit**

```bash
git add resources/views/kalender/index.blade.php
git commit -m "Kalender: popup detail Alpine — nama murid, guru, ruangan, jam, status, shortcut link"
```

---

## Task 6: Sidebar Nav Item

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`

- [ ] **Step 1: Tambah menu Kalender di sidebar**

Buka `resources/views/layouts/navigation.blade.php`.
Cari baris berikut:

```blade
    <x-sidebar-item route="sessions.index" icon="🎵" label="Sesi"
        :active="request()->routeIs('sessions.*')" />
```

Tambahkan menu Kalender SETELAH baris tersebut:

```blade
    <x-sidebar-item route="kalender.index" icon="📅" label="Kalender"
        :active="request()->routeIs('kalender.*')" />
```

- [ ] **Step 2: Test manual di browser**

Reload halaman manapun. Verifikasi:
- Menu "📅 Kalender" muncul di sidebar grup Utama
- Klik menu → buka halaman kalender
- State active (highlight) aktif saat di halaman kalender

- [ ] **Step 3: Jalankan semua test**

```bash
php artisan test tests/Feature/KalenderControllerTest.php
```

Expected: semua PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/navigation.blade.php
git commit -m "Kalender: tambah menu sidebar Kalender Jadwal"
```

---

## Task 7: Build Assets + Final Smoke Test

**Files:**
- Tidak ada file baru

- [ ] **Step 1: Build Tailwind/Vite**

```bash
npm run build
```

Expected: build berhasil tanpa error. Pastikan class-class baru seperti `bg-green-100`, `bg-yellow-100`, `bg-red-100`, `bg-indigo-50` masuk ke output CSS.

- [ ] **Step 2: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua test PASS (tidak ada regresi).

- [ ] **Step 3: Smoke test manual end-to-end**

Buka `http://localhost/kalender` dan lakukan:
1. Navigasi minggu ← dan → — label tanggal berubah benar
2. Tombol "Minggu Ini" kembali ke minggu berjalan
3. Filter Guru → grid hanya tampil sesi guru itu
4. Filter Ruangan → grid hanya tampil sesi ruangan itu
5. Reset Filter → semua sesi kembali tampil
6. Klik event → popup detail muncul dengan data lengkap
7. Popup: tombol "Detail Murid" buka halaman murid
8. Popup: klik luar → popup tutup
9. Navigasi ke minggu tanpa sesi → banner "Sesi belum di-generate" tampil
10. Login sebagai Auditor → kalender tetap bisa diakses (read-only)

- [ ] **Step 4: Commit final**

```bash
git add -A
git commit -m "Kalender: build assets + semua task selesai"
```

---

## Ringkasan Perubahan

| Task | Deliverable |
|---|---|
| 1 | Route + controller skeleton + 4 test akses kontrol |
| 2 | Controller penuh: week parsing, query, grouping, filter |
| 3 | Week navigator + filter bar dengan auto-submit |
| 4 | Grid tabel dengan event cells berwarna per status |
| 5 | Popup detail Alpine dengan shortcut links |
| 6 | Menu sidebar + smoke test |
| 7 | Build assets + full test suite |
