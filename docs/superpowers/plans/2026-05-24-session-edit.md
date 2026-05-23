# Session Edit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin/Owner dapat mengubah `start_time`, `end_time`, `teacher_id`, dan `room_id` pada sesi yang sudah ada — di semua status — dengan conflict detection guru dan ruang.

**Architecture:** PATCH `sessions/{session}` → `SessionController::update()` dengan conflict query langsung di controller. Modal Alpine.js per halaman (sessions/index dan students/show) — tidak ada route terpisah, form action di-set via Alpine reactive data dari row yang diklik.

**Tech Stack:** Laravel 11, PHPUnit (SQLite in-memory), Alpine.js v3, Blade, Tailwind CSS

---

## File Structure

| Action  | File                                                                     | Tanggung Jawab                         |
|---------|--------------------------------------------------------------------------|----------------------------------------|
| CREATE  | `app/Http/Requests/UpdateSessionRequest.php`                             | Validasi start_time, end_time, teacher_id, room_id |
| MODIFY  | `app/Http/Controllers/SessionController.php`                             | Tambah `update()` dengan conflict detection |
| MODIFY  | `routes/web.php:154`                                                     | PATCH route `sessions.update` di group Owner\|Admin |
| MODIFY  | `resources/views/sessions/index.blade.php:57`                            | Extend x-data + kolom Aksi + modal edit |
| MODIFY  | `resources/views/students/show.blade.php:966`                            | Kolom Aksi + modal di Sesi Mendatang   |
| CREATE  | `tests/Feature/SessionEditTest.php`                                      | 7 test cases — backend only            |

---

## Task 1: Backend — Form Request + Route + Controller

**Files:**
- Create: `app/Http/Requests/UpdateSessionRequest.php`
- Modify: `routes/web.php:154-155`
- Modify: `app/Http/Controllers/SessionController.php:99` (tambah setelah `generate()`)
- Test: `tests/Feature/SessionEditTest.php`

- [ ] **Step 1: Tulis failing tests**

Buat `tests/Feature/SessionEditTest.php`:

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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ClassSession $session;
    private Teacher $teacher;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->admin   = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->teacher = Teacher::factory()->create();
        $this->room    = Room::factory()->create();

        $student  = Student::factory()->create(['status' => 'Aktif']);
        $package  = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $this->teacher->id,
            'status'     => Enrollment::STATUS_ACTIVE,
            'is_primary' => true,
        ]);

        $this->session = ClassSession::factory()->create([
            'student_id'    => $student->id,
            'teacher_id'    => $this->teacher->id,
            'enrollment_id' => $enrollment->id,
            'room_id'       => $this->room->id,
            'session_date'  => now()->toDateString(),
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => 'SCHEDULED',
        ]);
    }

    /** Admin bisa ubah jam sesi */
    public function test_admin_bisa_edit_jam_sesi(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '11:00',
                'end_time'   => '11:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $this->room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'id'         => $this->session->id,
            'start_time' => '11:00:00',
            'end_time'   => '11:30:00',
        ]);
    }

    /** Admin bisa ganti guru sesi */
    public function test_admin_bisa_ganti_guru(): void
    {
        $guru2 = Teacher::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $guru2->id,
                'room_id'    => $this->room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'id'         => $this->session->id,
            'teacher_id' => $guru2->id,
        ]);
    }

    /** Admin bisa ganti ruang sesi */
    public function test_admin_bisa_ganti_ruang(): void
    {
        $ruang2 = Room::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $ruang2->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'id'      => $this->session->id,
            'room_id' => $ruang2->id,
        ]);
    }

    /** Guru yang sudah punya sesi di jam sama → konflik ditolak */
    public function test_konflik_guru_ditolak(): void
    {
        $guru2   = Teacher::factory()->create();
        $student2 = Student::factory()->create(['status' => 'Aktif']);

        // Sesi lain dengan guru2 di jam 10:00–10:30
        ClassSession::factory()->create([
            'teacher_id'   => $guru2->id,
            'student_id'   => $student2->id,
            'session_date' => $this->session->session_date,
            'start_time'   => '10:00:00',
            'end_time'     => '10:30:00',
            'status'       => 'SCHEDULED',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $guru2->id,
                'room_id'    => $this->room->id,
            ])
            ->assertSessionHasErrors(['teacher_id']);

        // Sesi tidak berubah
        $this->assertDatabaseHas('class_sessions', [
            'id'         => $this->session->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    /** Ruang yang sudah dipakai di jam sama → konflik ditolak */
    public function test_konflik_ruang_ditolak(): void
    {
        $ruang2  = Room::factory()->create();
        $guru2   = Teacher::factory()->create();
        $student2 = Student::factory()->create(['status' => 'Aktif']);

        ClassSession::factory()->create([
            'teacher_id'   => $guru2->id,
            'student_id'   => $student2->id,
            'room_id'      => $ruang2->id,
            'session_date' => $this->session->session_date,
            'start_time'   => '10:00:00',
            'end_time'     => '10:30:00',
            'status'       => 'SCHEDULED',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $ruang2->id,
            ])
            ->assertSessionHasErrors(['room_id']);
    }

    /** Sesi CANCELLED tidak dihitung dalam conflict detection */
    public function test_sesi_cancelled_tidak_conflict(): void
    {
        $guru2    = Teacher::factory()->create();
        $student2 = Student::factory()->create(['status' => 'Aktif']);

        ClassSession::factory()->create([
            'teacher_id'   => $guru2->id,
            'student_id'   => $student2->id,
            'session_date' => $this->session->session_date,
            'start_time'   => '10:00:00',
            'end_time'     => '10:30:00',
            'status'       => 'CANCELLED',  // diabaikan
        ]);

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $guru2->id,
                'room_id'    => $this->room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    /** Sesi status HADIR tetap bisa diedit */
    public function test_edit_sesi_status_hadir_diizinkan(): void
    {
        $this->session->update(['status' => 'HADIR']);

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '09:00',
                'end_time'   => '09:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $this->room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'id'         => $this->session->id,
            'start_time' => '09:00:00',
        ]);
    }

    /** Auditor tidak boleh edit sesi → 403 */
    public function test_auditor_tidak_boleh_edit(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('Auditor');

        $this->actingAs($auditor)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '11:00',
                'end_time'   => '11:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $this->room->id,
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan tests — pastikan FAIL**

```
php artisan test tests/Feature/SessionEditTest.php
```

Expected: FAIL — "Route [sessions.update] not defined."

- [ ] **Step 3: Buat Form Request**

Buat `app/Http/Requests/UpdateSessionRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'teacher_id' => ['required', 'exists:teachers,id'],
            'room_id'    => ['nullable', 'exists:rooms,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.required'    => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Format jam mulai tidak valid (HH:MM).',
            'end_time.required'      => 'Jam selesai wajib diisi.',
            'end_time.date_format'   => 'Format jam selesai tidak valid (HH:MM).',
            'end_time.after'         => 'Jam selesai harus setelah jam mulai.',
            'teacher_id.required'    => 'Guru wajib dipilih.',
            'teacher_id.exists'      => 'Guru tidak ditemukan.',
            'room_id.exists'         => 'Ruang tidak ditemukan.',
        ];
    }
}
```

- [ ] **Step 4: Tambah PATCH route di `routes/web.php`**

Setelah baris 154 (`sessions.generate`), tambahkan:

```php
        Route::patch('sessions/{classSession}',
            [SessionController::class, 'update']
        )->name('sessions.update');
```

Pastikan `use App\Http\Controllers\SessionController;` sudah ada di atas file (sudah ada).

- [ ] **Step 5: Tambah `update()` di `SessionController.php`**

Tambahkan setelah method `generate()` (setelah baris 98), sebelum penutup class:

```php
    /**
     * Update jam, guru, atau ruang satu sesi. Owner+Admin only.
     * Conflict detection: guru dan ruang tidak boleh double-booked di jam yang sama.
     */
    public function update(
        \App\Http\Requests\UpdateSessionRequest $request,
        ClassSession $session
    ): \Illuminate\Http\RedirectResponse {
        $data      = $request->validated();
        $startTime = $data['start_time'] . ':00';
        $endTime   = $data['end_time'] . ':00';

        // Deteksi konflik guru (abaikan CANCELLED dan sesi itu sendiri)
        $teacherConflict = ClassSession::where('session_date', $session->session_date)
            ->where('teacher_id', $data['teacher_id'])
            ->where('status', '!=', 'CANCELLED')
            ->where('id', '!=', $session->id)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($teacherConflict) {
            return back()
                ->withErrors(['teacher_id' => 'Guru sudah punya sesi lain di jam yang sama.'])
                ->withInput();
        }

        // Deteksi konflik ruang (hanya jika ruang dipilih)
        if (!empty($data['room_id'])) {
            $roomConflict = ClassSession::where('session_date', $session->session_date)
                ->where('room_id', $data['room_id'])
                ->where('status', '!=', 'CANCELLED')
                ->where('id', '!=', $session->id)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if ($roomConflict) {
                return back()
                    ->withErrors(['room_id' => 'Ruang sudah dipakai sesi lain di jam yang sama.'])
                    ->withInput();
            }
        }

        $session->update([
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'teacher_id' => $data['teacher_id'],
            'room_id'    => $data['room_id'] ?? null,
        ]);

        return back()->with('success', 'Sesi berhasil diperbarui.');
    }
```

- [ ] **Step 6: Jalankan tests — pastikan PASS**

```
php artisan test tests/Feature/SessionEditTest.php
```

Expected: 7 PASS. Jika ada factory error (`ClassSession::factory()` tidak ada kolom), periksa factory di `database/factories/ClassSessionFactory.php`.

- [ ] **Step 7: Jalankan full suite — tidak ada regresi**

```
php artisan test
```

Expected: semua test PASS.

- [ ] **Step 8: Commit**

```
git add app/Http/Requests/UpdateSessionRequest.php app/Http/Controllers/SessionController.php routes/web.php tests/Feature/SessionEditTest.php
git commit -m "M03: Edit sesi — PATCH sessions.update dengan conflict detection guru+ruang"
```

---

## Task 2: UI — Edit modal di sessions/index.blade.php

**Files:**
- Modify: `resources/views/sessions/index.blade.php`

- [ ] **Step 1: Extend x-data di baris 57**

Ganti:
```html
<div class="bg-white shadow-sm sm:rounded-lg p-5"
     x-data="{ showGenerate: false }">
```

Jadi:
```html
<div class="bg-white shadow-sm sm:rounded-lg p-5"
     x-data="{ showGenerate: false, editSession: null }">
```

- [ ] **Step 2: Tambah kolom header "Aksi" ke thead**

Di `sessions/index.blade.php` baris 197–203, tambahkan kolom header setelah kolom Honor:

```html
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Tanggal</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Jam</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Murid</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Guru</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Ruang</th>
                            <th class="px-2 py-1.5 text-center text-xs uppercase font-medium">Status</th>
                            <th class="px-2 py-1.5 text-right text-xs uppercase font-medium">Honor</th>
                            @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
                            <th class="px-2 py-1.5 text-center text-xs uppercase font-medium">Aksi</th>
                            @endif
```

- [ ] **Step 3: Tambah tombol Edit di setiap row**

Di dalam `@forelse($sessions as $s)` loop (sekitar baris 207–253), tambahkan kolom Aksi setelah kolom Honor (setelah `</td>` penutup kolom honor):

```html
                            @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
                            <td class="px-2 py-1.5 text-center">
                                <button type="button"
                                        @click="editSession = {
                                            id: {{ $s->id }},
                                            action: '{{ route('sessions.update', $s->id) }}',
                                            sessionDate: '{{ \Carbon\Carbon::parse($s->session_date)->format('D, d M Y') }}',
                                            startTime: '{{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}',
                                            endTime: '{{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}',
                                            teacherId: {{ $s->teacher_id ?? 'null' }},
                                            roomId: {{ $s->room_id ?? 'null' }}
                                        }"
                                        class="text-xs text-indigo-600 hover:underline px-1">
                                    Edit
                                </button>
                            </td>
                            @endif
```

- [ ] **Step 4: Tambah modal edit sebelum penutup `</div>` container (setelah pagination)**

Tambahkan setelah `{{ $sessions->links() }}` dan `</div>` penutup overflow-x-auto (baris 270–273), masih di dalam div x-data container:

```html
            {{-- ===== MODAL EDIT SESI ===== --}}
            @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
            <div x-show="editSession !== null" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center"
                 style="background: rgba(0,0,0,0.6);"
                 @click.self="editSession = null">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">
                            Edit Sesi — <span x-text="editSession?.sessionDate" class="font-mono"></span>
                        </h3>
                        <button @click="editSession = null" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                    </div>

                    @if($errors->any())
                    <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                        @foreach($errors->all() as $e)
                            <p>{{ $e }}</p>
                        @endforeach
                    </div>
                    @endif

                    <form :action="editSession?.action" method="POST" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Jam Mulai</label>
                                <input type="time" name="start_time"
                                       :value="editSession?.startTime"
                                       required
                                       class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Jam Selesai</label>
                                <input type="time" name="end_time"
                                       :value="editSession?.endTime"
                                       required
                                       class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Guru</label>
                            <select name="teacher_id" required
                                    class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih Guru —</option>
                                @foreach($teachers as $t)
                                <option value="{{ $t->id }}"
                                        :selected="editSession?.teacherId == {{ $t->id }}">
                                    {{ $t->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Ruang <span class="text-gray-400">(opsional)</span></label>
                            <select name="room_id"
                                    class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                <option value="">— Tidak Ditentukan —</option>
                                @foreach($rooms as $r)
                                <option value="{{ $r->id }}"
                                        :selected="editSession?.roomId == {{ $r->id }}">
                                    {{ $r->code }} — {{ $r->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="editSession = null"
                                    class="px-4 py-2 text-xs bg-gray-100 rounded-lg hover:bg-gray-200">
                                Batal
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-xs font-bold rounded-lg btn-mk-primary">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
```

- [ ] **Step 5: Commit**

```
git add resources/views/sessions/index.blade.php
git commit -m "M03: Tambah tombol Edit sesi di halaman /sessions"
```

---

## Task 3: UI — Edit modal di students/show.blade.php (Sesi Mendatang)

**Files:**
- Modify: `resources/views/students/show.blade.php:966-1016`

- [ ] **Step 1: Tambah `editSession: null` ke x-data Sesi Mendatang**

Baris 966 saat ini:
```html
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
```

Ganti menjadi (tambah x-data):
```html
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5"
                     x-data="{ editSession: null }">
```

- [ ] **Step 2: Tambah kolom header "Aksi" di thead**

Di baris 980–985, setelah `<th>Status</th>`:

```html
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Tanggal</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Jam</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Ruang</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Guru</th>
                                <th class="pb-2 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Status</th>
                                @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
                                <th class="pb-2 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Aksi</th>
                                @endif
```

- [ ] **Step 3: Tambah tombol Edit di setiap row**

Di dalam `@foreach($upcomingSessions as $sess)` (setelah penutup `</td>` kolom Status, baris ~1010), tambahkan kolom Aksi:

```html
                                @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
                                <td class="py-2 text-center">
                                    <button type="button"
                                            @click="editSession = {
                                                id: {{ $sess->id }},
                                                action: '{{ route('sessions.update', $sess->id) }}',
                                                sessionDate: '{{ \Carbon\Carbon::parse($sess->session_date)->format('D, d M Y') }}',
                                                startTime: '{{ \Carbon\Carbon::parse($sess->start_time)->format('H:i') }}',
                                                endTime: '{{ \Carbon\Carbon::parse($sess->end_time)->format('H:i') }}',
                                                teacherId: {{ $sess->teacher_id ?? 'null' }},
                                                roomId: {{ $sess->room_id ?? 'null' }}
                                            }"
                                            class="text-[10px] text-indigo-600 hover:underline">
                                        Edit
                                    </button>
                                </td>
                                @endif
```

- [ ] **Step 4: Tambah modal edit sebelum `</div>` penutup section Sesi Mendatang (baris 1016)**

Tambahkan setelah `@endif` (penutup `@if($upcomingSessions->isEmpty())`) dan sebelum `</div>` penutup section:

```html
                    {{-- Modal edit sesi dari halaman detail murid --}}
                    @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
                    <div x-show="editSession !== null" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center"
                         style="background: rgba(0,0,0,0.6);"
                         @click.self="editSession = null">
                        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-sm font-semibold text-gray-800">
                                    Edit Sesi — <span x-text="editSession?.sessionDate" class="font-mono"></span>
                                </h3>
                                <button @click="editSession = null" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                            </div>

                            @if($errors->any())
                            <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                @foreach($errors->all() as $e)
                                    <p>{{ $e }}</p>
                                @endforeach
                            </div>
                            @endif

                            <form :action="editSession?.action" method="POST" class="space-y-4">
                                @csrf
                                @method('PATCH')

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Jam Mulai</label>
                                        <input type="time" name="start_time"
                                               :value="editSession?.startTime"
                                               required
                                               class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Jam Selesai</label>
                                        <input type="time" name="end_time"
                                               :value="editSession?.endTime"
                                               required
                                               class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Guru</label>
                                    <select name="teacher_id" required
                                            class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                        <option value="">— Pilih Guru —</option>
                                        @foreach($teachers as $t)
                                        <option value="{{ $t->id }}"
                                                :selected="editSession?.teacherId == {{ $t->id }}">
                                            {{ $t->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Ruang <span class="text-gray-400">(opsional)</span></label>
                                    <select name="room_id"
                                            class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                        <option value="">— Tidak Ditentukan —</option>
                                        @foreach($rooms as $r)
                                        <option value="{{ $r->id }}"
                                                :selected="editSession?.roomId == {{ $r->id }}">
                                            {{ $r->code }} — {{ $r->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="editSession = null"
                                            class="px-4 py-2 text-xs bg-gray-100 rounded-lg hover:bg-gray-200">
                                        Batal
                                    </button>
                                    <button type="submit"
                                            class="px-4 py-2 text-xs font-bold rounded-lg btn-mk-primary">
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif
```

**Catatan:** `$teachers` dan `$rooms` sudah di-load di `StudentController::show()` (lines 102–106). Tidak perlu modifikasi controller.

- [ ] **Step 5: Commit**

```
git add resources/views/students/show.blade.php
git commit -m "M03: Tambah tombol Edit sesi di halaman detail murid"
```

---

## Task 4: Verify + Push

- [ ] **Step 1: Jalankan full test suite**

```
php artisan test
```

Expected: semua PASS, tidak ada regresi.

- [ ] **Step 2: Build assets**

```
npm run build
```

Expected: selesai tanpa error.

- [ ] **Step 3: Push ke GitHub**

```
git push
```

- [ ] **Step 4: Verifikasi manual di browser**

1. Buka `/sessions` → pastikan kolom "Aksi" muncul → klik "Edit" di satu sesi → modal terbuka dengan data pre-filled.
2. Ubah jam → Simpan → pastikan berhasil (flash success, data terupdate).
3. Ubah guru ke guru yang konflik jam → pastikan error muncul di flash/modal.
4. Buka `/students/{id}` → tab Info → scroll ke "Sesi Mendatang" → klik "Edit" → modal terbuka.

---

## Self-Review

**Spec coverage:**
- ✅ Edit start_time, end_time → Task 1 + Task 2 + Task 3
- ✅ Edit teacher_id → Task 1 + Task 2 + Task 3
- ✅ Edit room_id → Task 1 + Task 2 + Task 3
- ✅ Conflict detection guru → Task 1 (controller + test)
- ✅ Conflict detection ruang → Task 1 (controller + test)
- ✅ Semua status diizinkan → test `test_edit_sesi_status_hadir_diizinkan`
- ✅ UI di /sessions → Task 2
- ✅ UI di /students/{id} → Task 3
- ✅ Auditor tidak bisa edit → test `test_auditor_tidak_boleh_edit`

**Catatan penting untuk implementor:**
- Modal pakai `:value` bukan `x-model` untuk time input — karena Alpine reactive pre-fill lebih reliable dengan `:value` + `@change` daripada `x-model` di `<input type="time">`.
- `editSession` di students/show scoped ke div Sesi Mendatang sendiri (`x-data`), bukan ke div tabs utama di line 634 — ini menghindari collision dengan `activeTab` dan `openSchedule`.
- Conflict check excludes `status = CANCELLED` (sesuai pattern RescheduleService lines 44–80).
- `start_time` di DB disimpan sebagai `H:i:s` (dengan detik) — input browser `type="time"` mengirim `H:i` tanpa detik, makanya controller append `:00`.
