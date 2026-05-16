# Ruangan Fleksibel & Auto-Suggest Jadwal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti fasilitas ruangan hardcoded (3 boolean) dengan `supported_instruments` JSON, lalu hubungkan ke form jadwal murid via Alpine.js auto-suggest dan hard block server-side.

**Architecture:** Schema change → model/seeder update → RoomController refactor → form UI update → ScheduleController instrument check → Alpine.js client-side filter. Setiap task independent dan bisa di-commit sendiri.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL, Blade + Alpine.js + Tailwind CSS, Spatie Permission, PHPUnit (RefreshDatabase + SQLite in-memory).

**Spec:** `docs/superpowers/specs/2026-05-16-ruangan-schedule-design.md`

---

## File Map

| File | Action |
|---|---|
| `database/migrations/XXXX_add_supported_instruments_to_rooms_table.php` | CREATE |
| `database/seeders/RoomSeeder.php` | MODIFY |
| `app/Models/Room.php` | MODIFY |
| `tests/Unit/Models/RoomTest.php` | CREATE |
| `app/Http/Controllers/Admin/RoomController.php` | CREATE (pindah dari root) |
| `app/Http/Controllers/RoomController.php` | DELETE |
| `routes/web.php` | MODIFY (update `use` statement) |
| `resources/views/rooms/_form.blade.php` | MODIFY |
| `resources/views/rooms/create.blade.php` | MODIFY |
| `resources/views/rooms/edit.blade.php` | MODIFY |
| `tests/Feature/RoomControllerTest.php` | CREATE |
| `app/Http/Controllers/ScheduleController.php` | MODIFY |
| `tests/Feature/ScheduleInstrumentCheckTest.php` | CREATE |
| `app/Http/Controllers/StudentController.php` | MODIFY |
| `resources/views/students/show.blade.php` | MODIFY |

---

## Task 1: Migration — Drop Boolean Columns, Tambah `supported_instruments`

**Files:**
- Create: `database/migrations/XXXX_add_supported_instruments_to_rooms_table.php`

- [ ] **Step 1: Buat file migration**

```bash
php artisan make:migration add_supported_instruments_to_rooms_table
```

- [ ] **Step 2: Isi migration**

Buka file migration yang baru dibuat, ganti isinya:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Tambah kolom baru setelah capacity
            $table->json('supported_instruments')->nullable()->after('capacity');

            // Hapus 3 boolean lama
            $table->dropColumn(['has_piano', 'has_drum', 'has_amplifier']);
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('supported_instruments');
            $table->boolean('has_piano')->default(false);
            $table->boolean('has_drum')->default(false);
            $table->boolean('has_amplifier')->default(false);
        });
    }
};
```

- [ ] **Step 3: Jalankan migration**

```bash
php artisan migrate
```

Expected output:
```
INFO  Running migrations.
XXXX_add_supported_instruments_to_rooms_table .............. DONE
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "DB: Migration ganti has_piano/drum/amplifier ke supported_instruments JSON di tabel rooms"
```

---

## Task 2: Room Model + Unit Test `supportsInstrument()`

**Files:**
- Modify: `app/Models/Room.php`
- Create: `tests/Unit/Models/RoomTest.php`

- [ ] **Step 1: Tulis unit test yang akan gagal**

Buat file `tests/Unit/Models/RoomTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Room;
use PHPUnit\Framework\TestCase;

class RoomTest extends TestCase
{
    public function test_supports_instrument_returns_true_when_in_list(): void
    {
        $room = new Room();
        $room->supported_instruments = ['Piano', 'Gitar'];

        $this->assertTrue($room->supportsInstrument('Piano'));
        $this->assertTrue($room->supportsInstrument('Gitar'));
    }

    public function test_supports_instrument_returns_false_when_not_in_list(): void
    {
        $room = new Room();
        $room->supported_instruments = ['Piano'];

        $this->assertFalse($room->supportsInstrument('Drum'));
    }

    public function test_supports_instrument_returns_false_when_list_empty(): void
    {
        $room = new Room();
        $room->supported_instruments = [];

        $this->assertFalse($room->supportsInstrument('Piano'));
    }

    public function test_supports_instrument_returns_false_when_null(): void
    {
        $room = new Room();
        $room->supported_instruments = null;

        $this->assertFalse($room->supportsInstrument('Piano'));
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan gagal**

```bash
php artisan test tests/Unit/Models/RoomTest.php
```

Expected: FAIL — `Call to undefined method App\Models\Room::supportsInstrument()`

- [ ] **Step 3: Update Room model**

Buka `app/Models/Room.php`, ganti seluruh isinya:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'capacity',
        'supported_instruments',
        'notes', 'is_active',
    ];

    protected $casts = [
        'supported_instruments' => 'array',
        'capacity'              => 'integer',
        'is_active'             => 'boolean',
    ];

    /**
     * Cek apakah ruangan mendukung instrumen tertentu.
     * Dipakai oleh ScheduleController untuk hard block saat assign jadwal.
     */
    public function supportsInstrument(string $instrumentName): bool
    {
        return in_array($instrumentName, $this->supported_instruments ?? []);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 4: Jalankan test — pastikan lulus**

```bash
php artisan test tests/Unit/Models/RoomTest.php
```

Expected:
```
PASS  Tests\Unit\Models\RoomTest
✓ supports instrument returns true when in list
✓ supports instrument returns false when not in list
✓ supports instrument returns false when list empty
✓ supports instrument returns false when null
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/Room.php tests/Unit/Models/RoomTest.php
git commit -m "M01: Update Room model — ganti 3 boolean ke supported_instruments JSON + method supportsInstrument()"
```

---

## Task 3: RoomSeeder — Reset Data dari CLAUDE.md

**Files:**
- Modify: `database/seeders/RoomSeeder.php`

- [ ] **Step 1: Ganti isi RoomSeeder.php**

```php
<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // Data ruangan aktual studio — sumber: CLAUDE.md
        $rooms = [
            [
                'code' => 'R1', 'name' => 'Studio 1', 'capacity' => 4,
                'supported_instruments' => ['Vocal', 'Kids Class', 'Gitar'],
                'notes' => 'Ruang Kids Class — kapasitas 4 anak',
            ],
            [
                'code' => 'R2', 'name' => 'Studio 2', 'capacity' => 1,
                'supported_instruments' => ['Piano', 'Vocal', 'Gitar'],
                'notes' => null,
            ],
            [
                'code' => 'R3', 'name' => 'Studio 3', 'capacity' => 1,
                'supported_instruments' => ['Piano'],
                'notes' => null,
            ],
            [
                'code' => 'R4', 'name' => 'Studio 4', 'capacity' => 1,
                'supported_instruments' => ['Piano', 'Gitar'],
                'notes' => null,
            ],
            [
                'code' => 'R5', 'name' => 'Studio 5', 'capacity' => 1,
                'supported_instruments' => ['Bass', 'Gitar'],
                'notes' => null,
            ],
            [
                'code' => 'R6', 'name' => 'Studio 6', 'capacity' => 1,
                'supported_instruments' => ['Violin'],
                'notes' => null,
            ],
            [
                'code' => 'R7', 'name' => 'Studio 7', 'capacity' => 1,
                'supported_instruments' => ['Piano', 'Vocal'],
                'notes' => null,
            ],
            [
                'code' => 'R8', 'name' => 'Studio 8', 'capacity' => 1,
                'supported_instruments' => ['Drum'],
                'notes' => null,
            ],
            [
                'code' => 'R9', 'name' => 'Studio 9', 'capacity' => 1,
                'supported_instruments' => ['Drum'],
                'notes' => null,
            ],
        ];

        foreach ($rooms as $data) {
            // updateOrCreate: aman dijalankan ulang tanpa duplikat
            Room::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
```

- [ ] **Step 2: Jalankan seeder**

```bash
php artisan db:seed --class=RoomSeeder
```

- [ ] **Step 3: Verifikasi data di DB**

```bash
php artisan tinker --execute="App\Models\Room::all(['code','name','supported_instruments'])->each(fn(\$r) => dump(\$r->code, \$r->supported_instruments));"
```

Expected: 9 ruangan dengan `supported_instruments` array sesuai CLAUDE.md.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/RoomSeeder.php
git commit -m "M01: Reset RoomSeeder — 9 ruangan dengan supported_instruments dari CLAUDE.md"
```

---

## Task 4: RoomController Pindah ke Namespace Admin + Update Validasi

**Files:**
- Create: `app/Http/Controllers/Admin/RoomController.php`
- Delete: `app/Http/Controllers/RoomController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Buat file baru di namespace Admin**

Buat `app/Http/Controllers/Admin/RoomController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::orderBy('code')->get();
        return view('rooms.index', compact('rooms'));
    }

    public function create()
    {
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();
        return view('rooms.create', compact('instruments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                    => 'required|string|max:10|unique:rooms,code|regex:/^[A-Z0-9]+$/',
            'name'                    => 'required|string|max:50',
            'capacity'                => 'required|integer|min:1|max:20',
            'supported_instruments'   => 'nullable|array',
            'supported_instruments.*' => 'string|exists:instruments,name',
            'notes'                   => 'nullable|string',
        ], [
            'code.regex'   => 'Kode hanya boleh huruf besar dan angka.',
            'code.unique'  => 'Kode ruangan sudah dipakai.',
            'name.required' => 'Nama ruangan wajib diisi.',
            'capacity.min' => 'Kapasitas minimal 1.',
        ]);

        $validated['supported_instruments'] = $request->input('supported_instruments', []);
        $validated['is_active'] = $request->boolean('is_active', true);

        Room::create($validated);

        return redirect()->route('rooms.index')->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Room $room)
    {
        $instruments = Instrument::where('is_active', true)->orderBy('sort_order')->get();
        return view('rooms.edit', compact('room', 'instruments'));
    }

    public function update(Request $request, Room $room)
    {
        $validated = $request->validate([
            'code'                    => 'required|string|max:10|unique:rooms,code,' . $room->id . '|regex:/^[A-Z0-9]+$/',
            'name'                    => 'required|string|max:50',
            'capacity'                => 'required|integer|min:1|max:20',
            'supported_instruments'   => 'nullable|array',
            'supported_instruments.*' => 'string|exists:instruments,name',
            'notes'                   => 'nullable|string',
        ], [
            'code.regex'  => 'Kode hanya boleh huruf besar dan angka.',
            'code.unique' => 'Kode ruangan sudah dipakai.',
        ]);

        $instrumenBaru = $request->input('supported_instruments', []);
        $instrumenDihapus = array_diff($room->supported_instruments ?? [], $instrumenBaru);

        // Cek jadwal aktif yang terdampak perubahan fasilitas
        $warning = null;
        if (!empty($instrumenDihapus)) {
            $terdampak = Schedule::active()
                ->where('room_id', $room->id)
                ->whereHas('enrollment.package.instrument', function ($q) use ($instrumenDihapus) {
                    $q->whereIn('name', array_values($instrumenDihapus));
                })
                ->with('enrollment.student')
                ->get();

            if ($terdampak->isNotEmpty()) {
                $namaMurid = $terdampak
                    ->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                    ->unique()
                    ->implode(', ');
                $warning = "Perhatian: {$terdampak->count()} jadwal aktif terdampak perubahan fasilitas ini: {$namaMurid}. Perbarui jadwal mereka secara manual.";
            }
        }

        $validated['supported_instruments'] = $instrumenBaru;
        $validated['is_active'] = $request->boolean('is_active');

        $room->update($validated);

        return redirect()
            ->route('rooms.index')
            ->with('success', 'Ruangan berhasil diperbarui.')
            ->with('warning', $warning);
    }

    public function destroy(Room $room)
    {
        // Tolak hapus jika masih ada schedule aktif
        if ($room->schedules()->active()->exists()) {
            return back()->with('error',
                "Ruangan [{$room->code}] masih dipakai oleh jadwal aktif. Nonaktifkan ruangan atau pindahkan jadwal dulu.");
        }

        $room->delete();
        return redirect()->route('rooms.index')->with('success', 'Ruangan berhasil dihapus.');
    }
}
```

- [ ] **Step 2: Update routes/web.php — ganti use statement**

Cari baris:
```php
use App\Http\Controllers\RoomController;
```

Ganti dengan:
```php
use App\Http\Controllers\Admin\RoomController;
```

- [ ] **Step 3: Hapus file RoomController lama**

```bash
rm app/Http/Controllers/RoomController.php
```

- [ ] **Step 4: Verifikasi routes masih benar**

```bash
php artisan route:list --name=rooms
```

Expected: 6 rooms routes masih ada, controller sekarang `Admin\RoomController`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/RoomController.php routes/web.php
git rm app/Http/Controllers/RoomController.php
git commit -m "M01: Pindah RoomController ke namespace Admin + update validasi supported_instruments + warning jadwal terdampak"
```

---

## Task 5: Room Form — Multi-Checkbox Instrumen

**Files:**
- Modify: `resources/views/rooms/_form.blade.php`
- Modify: `resources/views/rooms/create.blade.php`
- Modify: `resources/views/rooms/edit.blade.php`

- [ ] **Step 1: Update `rooms/_form.blade.php`**

Ganti seluruh isi file dengan versi baru. Perbedaan utama: hapus 3 checkbox lama `has_piano/drum/amplifier`, ganti dengan loop `$instruments`:

```blade
@php $room = $room ?? null; @endphp

@if($errors->any())
<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">
    <ul class="text-sm text-red-700 list-disc pl-5">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium">Kode Ruangan <span class="text-red-500">*</span></label>
        <input type="text" name="code" required maxlength="10"
               value="{{ old('code', $room->code ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md font-mono uppercase"
               placeholder="R1 / R10"
               oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
        <p class="text-xs text-gray-500 mt-1">Hanya huruf besar dan angka. Contoh: R1, R10</p>
    </div>
    <div>
        <label class="block text-sm font-medium">Nama Ruangan <span class="text-red-500">*</span></label>
        <input type="text" name="name" required maxlength="50"
               value="{{ old('name', $room->name ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="Studio 1">
    </div>
    <div>
        <label class="block text-sm font-medium">Kapasitas <span class="text-red-500">*</span></label>
        <input type="number" name="capacity" required min="1" max="20"
               value="{{ old('capacity', $room->capacity ?? 1) }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
        <p class="text-xs text-gray-500 mt-1">Isi 4 untuk ruang Kids Class, 1 untuk ruang privat.</p>
    </div>
    <div></div>

    {{-- Multi-checkbox instrumen yang didukung --}}
    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-2">
            Instrumen yang Didukung
        </label>
        <div class="grid grid-cols-3 md:grid-cols-4 gap-2">
            @foreach($instruments as $instrument)
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox"
                       name="supported_instruments[]"
                       value="{{ $instrument->name }}"
                       {{ in_array($instrument->name, old('supported_instruments', $room->supported_instruments ?? [])) ? 'checked' : '' }}
                       class="rounded border-gray-300">
                <span class="text-sm">{{ $instrument->name }}</span>
            </label>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mt-1">Pilih instrumen apa saja yang bisa diajarkan di ruangan ini.</p>
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium">Catatan</label>
        <textarea name="notes" rows="2"
                  class="mt-1 block w-full border-gray-300 rounded-md"
                  placeholder="Catatan khusus tentang ruangan">{{ old('notes', $room->notes ?? '') }}</textarea>
    </div>

    <div class="flex items-end">
        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $room->is_active ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300">
            <span class="ml-2 text-sm">Ruangan Aktif</span>
        </label>
    </div>
</div>
```

- [ ] **Step 2: Cek `rooms/create.blade.php` — pastikan `$instruments` tersedia**

Buka file. Jika ada baris seperti `return view('rooms.create');` atau `@include('rooms._form')`, cek apakah `$instruments` sudah di-pass. Jika belum (seharusnya sudah dari Task 4 controller), verifikasi ulang controller.

- [ ] **Step 3: Cek `rooms/edit.blade.php` — pastikan `$instruments` dan `$room` tersedia**

Sama seperti Step 2 — cek `@include('rooms._form')` dan pastikan controller sudah pass `compact('room', 'instruments')`.

- [ ] **Step 4: Tampilkan warning di `rooms/index.blade.php`**

Cari blok flash `session('success')` di `rooms/index.blade.php`, tambahkan blok warning di bawahnya:

```blade
@if(session('warning'))
<div class="mb-5 p-3 rounded-lg text-sm"
     style="background:rgba(251,191,36,0.1);color:#FBBF24;border:1px solid rgba(251,191,36,0.2)">
    ⚠️ {{ session('warning') }}
</div>
@endif
```

- [ ] **Step 5: Test manual di browser**

1. Buka `/rooms/create` → form harus tampilkan daftar instrumen sebagai checkbox (bukan 3 boolean lama)
2. Buat ruangan baru, centang Piano + Gitar → simpan → ruangan muncul di index
3. Edit ruangan tersebut → checkbox Piano dan Gitar harus sudah tercentang

- [ ] **Step 6: Commit**

```bash
git add resources/views/rooms/
git commit -m "M01: Update form ruangan — ganti 3 checkbox hardcoded ke multi-checkbox instrumen dinamis dari DB"
```

---

## Task 6: Feature Test RoomController

**Files:**
- Create: `tests/Feature/RoomControllerTest.php`

- [ ] **Step 1: Tulis feature test**

Buat `tests/Feature/RoomControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    public function test_owner_dapat_buat_ruangan_dengan_supported_instruments(): void
    {
        $owner = $this->ownerUser();
        Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        Instrument::create(['name' => 'Gitar', 'code' => 'GITAR', 'is_active' => true, 'sort_order' => 2]);

        $response = $this->actingAs($owner)->post(route('rooms.store'), [
            'code'                  => 'TEST',
            'name'                  => 'Studio Test',
            'capacity'              => 1,
            'supported_instruments' => ['Piano', 'Gitar'],
            'is_active'             => '1',
        ]);

        $response->assertRedirect(route('rooms.index'));
        $room = Room::where('code', 'TEST')->first();
        $this->assertNotNull($room);
        $this->assertContains('Piano', $room->supported_instruments);
        $this->assertContains('Gitar', $room->supported_instruments);
    }

    public function test_supported_instruments_tersimpan_sebagai_array_bukan_string(): void
    {
        $owner = $this->ownerUser();
        Instrument::create(['name' => 'Drum', 'code' => 'DRUM', 'is_active' => true, 'sort_order' => 3]);

        $this->actingAs($owner)->post(route('rooms.store'), [
            'code'                  => 'R99',
            'name'                  => 'Studio Drum',
            'capacity'              => 1,
            'supported_instruments' => ['Drum'],
        ]);

        $room = Room::where('code', 'R99')->first();
        $this->assertIsArray($room->supported_instruments);
    }

    public function test_update_ruangan_hapus_instrumen_tidak_trigger_warning_tanpa_jadwal(): void
    {
        $owner = $this->ownerUser();
        Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);

        $room = Room::create([
            'code'                  => 'R2',
            'name'                  => 'Studio 2',
            'capacity'              => 1,
            'supported_instruments' => ['Piano'],
            'is_active'             => true,
        ]);

        // Hapus Piano dari fasilitas — tidak ada jadwal aktif, tidak ada warning
        $response = $this->actingAs($owner)->put(route('rooms.update', $room), [
            'code'                  => 'R2',
            'name'                  => 'Studio 2',
            'capacity'              => 1,
            'supported_instruments' => [],
        ]);

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionMissing('warning');
    }

    public function test_kolom_boolean_lama_tidak_ada_lagi(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('rooms', 'has_piano'),
            'Kolom has_piano seharusnya sudah dihapus oleh migration.'
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('rooms', 'has_drum'),
            'Kolom has_drum seharusnya sudah dihapus oleh migration.'
        );
    }
}
```

- [ ] **Step 2: Jalankan test**

```bash
php artisan test tests/Feature/RoomControllerTest.php
```

Expected: semua test PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/RoomControllerTest.php
git commit -m "M01: Feature test RoomController — supported_instruments, schema check, warning"
```

---

## Task 7: ScheduleController — Instrument Compatibility Hard Block

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php`
- Create: `tests/Feature/ScheduleInstrumentCheckTest.php`

- [ ] **Step 1: Tulis feature test yang akan gagal**

Buat `tests/Feature/ScheduleInstrumentCheckTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleInstrumentCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    /**
     * Buat setup murid Piano dengan enrollment aktif.
     * Return [$student, $pianoRoom, $drumRoom]
     */
    private function setupPianoStudent(): array
    {
        $piano = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        Instrument::create(['name' => 'Drum', 'code' => 'DRUM', 'is_active' => true, 'sort_order' => 2]);

        $pianoRoom = Room::create([
            'code' => 'R2', 'name' => 'Studio 2', 'capacity' => 1,
            'supported_instruments' => ['Piano', 'Gitar'],
            'is_active' => true,
        ]);
        $drumRoom = Room::create([
            'code' => 'R8', 'name' => 'Studio 8', 'capacity' => 1,
            'supported_instruments' => ['Drum'],
            'is_active' => true,
        ]);

        $package = Package::create([
            'code' => 'REG-PIANO-BASIC', 'instrument_id' => $piano->id,
            'class_type' => 'REGULER', 'grade' => 'Basic',
            'duration_min' => 30, 'price_per_month' => 340000,
            'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = Teacher::create([
            'code' => 'TCH-001', 'name' => 'Adi',
            'phone' => '08123456789', 'is_active' => true,
        ]);
        $student = Student::create([
            'student_code' => 'M-2026-0001', 'full_name' => 'Budi Santoso',
            'gender' => 'L', 'status' => 'Aktif',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'effective_date' => now()->toDateString(), 'status' => 'ACTIVE',
        ]);

        return [$student, $pianoRoom, $drumRoom];
    }

    public function test_tidak_bisa_buat_jadwal_dengan_ruangan_yang_tidak_support_instrumen(): void
    {
        $owner = $this->ownerUser();
        [$student, $pianoRoom, $drumRoom] = $this->setupPianoStudent();

        // Coba assign R8 (Drum) untuk murid Piano — harus ditolak
        $response = $this->actingAs($owner)->post(route('schedules.store', $student), [
            'day_of_week' => 1,
            'start_time'  => '15:00',
            'end_time'    => '15:30',
            'room_id'     => $drumRoom->id,
        ]);

        $response->assertRedirect();
        $this->assertTrue($response->isRedirect());
        $this->assertNotNull(session('error'));
        $this->assertDatabaseMissing('schedules', ['room_id' => $drumRoom->id]);
    }

    public function test_bisa_buat_jadwal_dengan_ruangan_yang_support_instrumen(): void
    {
        $owner = $this->ownerUser();
        [$student, $pianoRoom, $drumRoom] = $this->setupPianoStudent();

        // Assign R2 (Piano, Gitar) untuk murid Piano — harus sukses
        $response = $this->actingAs($owner)->post(route('schedules.store', $student), [
            'day_of_week' => 1,
            'start_time'  => '15:00',
            'end_time'    => '15:30',
            'room_id'     => $pianoRoom->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('schedules', [
            'room_id'     => $pianoRoom->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_bisa_buat_jadwal_tanpa_ruangan(): void
    {
        // room_id opsional — tanpa ruangan tetap valid
        $owner = $this->ownerUser();
        [$student] = $this->setupPianoStudent();

        $response = $this->actingAs($owner)->post(route('schedules.store', $student), [
            'day_of_week' => 2,
            'start_time'  => '10:00',
            'end_time'    => '10:30',
            'room_id'     => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('schedules', ['room_id' => null, 'day_of_week' => 2]);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan `test_tidak_bisa_buat_jadwal` gagal**

```bash
php artisan test tests/Feature/ScheduleInstrumentCheckTest.php --filter=tidak_bisa
```

Expected: FAIL — schedule berhasil dibuat padahal seharusnya ditolak.

- [ ] **Step 3: Tambah instrument check di `ScheduleController`**

Buka `app/Http/Controllers/ScheduleController.php`.

Tambah `use App\Models\Room;` di blok use statements (jika belum ada).

Di method `store()`, cari blok `isRoomFull` yang sudah ada:
```php
if (!empty($data['room_id'])) {
    $isFull = $this->conflictDetector->isRoomFull(...);
    if ($isFull) {
        return back()->withInput()->with('error', 'Kapasitas ruangan sudah penuh di slot ini.');
    }
}
```

Tambahkan instrument check **setelah** blok `isRoomFull` tersebut:

```php
        // Validasi: ruangan harus support instrumen murid
        if (!empty($data['room_id'])) {
            $room = Room::findOrFail($data['room_id']);
            $instrumentName = $enrollment->package?->instrument?->name;

            if ($instrumentName && !$room->supportsInstrument($instrumentName)) {
                return back()->withInput()->with('error',
                    "Ruangan [{$room->code}] {$room->name} tidak mendukung instrumen {$instrumentName}. " .
                    "Pilih ruangan lain atau kosongkan field ruangan."
                );
            }
        }
```

Lakukan hal yang sama di method `update()` — tambahkan blok identik setelah blok `isRoomFull` di update(). Bedanya: gunakan `$schedule->enrollment` bukan `$enrollment`:

```php
        // Validasi: ruangan harus support instrumen murid (saat update)
        if (!empty($data['room_id'])) {
            $room = Room::findOrFail($data['room_id']);
            $instrumentName = $schedule->enrollment?->package?->instrument?->name;

            if ($instrumentName && !$room->supportsInstrument($instrumentName)) {
                return back()->withInput()->with('error',
                    "Ruangan [{$room->code}] {$room->name} tidak mendukung instrumen {$instrumentName}. " .
                    "Pilih ruangan lain atau kosongkan field ruangan."
                );
            }
        }
```

- [ ] **Step 4: Jalankan semua test**

```bash
php artisan test tests/Feature/ScheduleInstrumentCheckTest.php
```

Expected: semua 3 test PASS.

- [ ] **Step 5: Pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php tests/Feature/ScheduleInstrumentCheckTest.php
git commit -m "M03: ScheduleController — hard block instrument incompatibility saat assign ruangan ke jadwal"
```

---

## Task 8: StudentController — Pass Data Alpine.js ke View

**Files:**
- Modify: `app/Http/Controllers/StudentController.php`

- [ ] **Step 1: Update method `show()` di StudentController**

Buka `app/Http/Controllers/StudentController.php`.

Tambah `use App\Models\Schedule;` di blok use statements (jika belum ada).

Di method `show()`, cari baris:
```php
$rooms = Room::where('is_active', true)->orderBy('code')->get();
```

Tambahkan 2 variabel baru **setelah** baris tersebut:

```php
        // Data untuk Alpine.js auto-suggest ruangan di form jadwal
        // $roomsForFilter: berisi supported_instruments untuk filter client-side
        $roomsForFilter = Room::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'capacity', 'supported_instruments']);

        // $bookedSchedules: jadwal aktif yang sudah ada room_id — untuk cek konflik client-side
        $bookedSchedules = Schedule::active()
            ->whereNotNull('room_id')
            ->get(['room_id', 'day_of_week', 'start_time', 'end_time']);
```

Tambah `enrollments.package.instrument` ke eager loading `$student` (cari blok `with([...])`):

```php
        $student = Student::with([
            'package.instrument',
            'assignedTeacher',
            'assignedRoom',
            'histories.changedBy',
            // M03: enrollment ACTIVE + schedules + room
            'enrollments' => fn ($q) => $q->latest('effective_date'),
            'enrollments.package',
            'enrollments.package.instrument',   // ← tambah ini
            'enrollments.teacher',
            'enrollments.schedules.room',
        ])->findOrFail($id);
```

Update `return view(...)` — tambahkan `$roomsForFilter` dan `$bookedSchedules`:

```php
        return view('students.show', compact(
            'student', 'packages', 'teachers', 'rooms',
            'roomsForFilter', 'bookedSchedules',   // ← tambah ini
            'upcomingSessions',
            'recentInvoices', 'outstandingBalance', 'unpaidCount',
        ));
```

- [ ] **Step 2: Verifikasi tidak ada error di halaman detail murid**

Buka browser, akses halaman detail salah satu murid aktif. Halaman harus tampil normal tanpa error.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/StudentController.php
git commit -m "M03: StudentController@show — pass roomsForFilter & bookedSchedules untuk Alpine.js auto-suggest"
```

---

## Task 9: Alpine.js Auto-Suggest di Form Jadwal

**Files:**
- Modify: `resources/views/students/show.blade.php`

- [ ] **Step 1: Tambah `$studentInstrument` di blok `@php` view**

Buka `resources/views/students/show.blade.php`. Cari blok `@php` di dekat awal (sekitar baris 45-47) yang berisi:
```php
$activeEnrollment = $student->enrollments->firstWhere('status', 'ACTIVE');
```

Tambahkan satu baris setelah itu:
```php
$studentInstrument = $activeEnrollment?->package?->instrument?->name;
```

- [ ] **Step 2: Tambah nested `x-data` ke form div jadwal**

Cari div form tambah jadwal (sekitar baris 727):
```html
<div x-show="openSchedule === 'create'" x-cloak
     class="mb-4 rounded-xl p-4"
     style="background:rgba(212,168,83,0.06);border:1px solid rgba(212,168,83,0.2)">
```

Ganti dengan (tambah `x-data` nested):
```html
<div x-show="openSchedule === 'create'" x-cloak
     x-data="{
         selectedDay: '',
         startTime: '',
         endTime: '',
         rooms: {{ Js::from($roomsForFilter) }},
         booked: {{ Js::from($bookedSchedules) }},
         instrument: {{ Js::from($studentInstrument) }},
         get availableRooms() {
             return this.rooms.filter(room => {
                 if (this.instrument &&
                     !room.supported_instruments.includes(this.instrument)) {
                     return false;
                 }
                 if (!this.selectedDay || !this.startTime || !this.endTime) {
                     return true;
                 }
                 const occupants = this.booked.filter(s =>
                     s.room_id === room.id &&
                     s.day_of_week === parseInt(this.selectedDay) &&
                     s.start_time < this.endTime &&
                     s.end_time > this.startTime
                 ).length;
                 return occupants < room.capacity;
             });
         }
     }"
     class="mb-4 rounded-xl p-4"
     style="background:rgba(212,168,83,0.06);border:1px solid rgba(212,168,83,0.2)">
```

- [ ] **Step 3: Tambah `x-model` ke input Hari, Jam Mulai, Jam Selesai**

Cari select `day_of_week`:
```html
<select name="day_of_week" required class="block w-full rounded-lg text-sm px-2 py-1.5">
```
Ganti dengan:
```html
<select name="day_of_week" x-model="selectedDay" required class="block w-full rounded-lg text-sm px-2 py-1.5">
```

Cari input `start_time`:
```html
<input type="time" name="start_time" required class="block w-full rounded-lg text-sm px-2 py-1.5">
```
Ganti dengan:
```html
<input type="time" name="start_time" x-model="startTime" required class="block w-full rounded-lg text-sm px-2 py-1.5">
```

Cari input `end_time`:
```html
<input type="time" name="end_time" required class="block w-full rounded-lg text-sm px-2 py-1.5">
```
Ganti dengan:
```html
<input type="time" name="end_time" x-model="endTime" required class="block w-full rounded-lg text-sm px-2 py-1.5">
```

- [ ] **Step 4: Ganti dropdown ruangan statis dengan Alpine template**

Cari blok dropdown ruangan di form jadwal (sekitar baris 750-757):
```html
<div class="col-span-2">
    <label class="block text-xs text-gray-500 mb-1">Ruangan</label>
    <select name="room_id" class="block w-full rounded-lg text-sm px-2 py-1.5">
        <option value="">— Pilih —</option>
        @foreach($rooms as $r)
        <option value="{{ $r->id }}">[{{ $r->code }}] {{ $r->name }} (kap. {{ $r->capacity }})</option>
        @endforeach
    </select>
</div>
```

Ganti dengan:
```html
<div class="col-span-2">
    <label class="block text-xs text-gray-500 mb-1">Ruangan</label>
    <select name="room_id" class="block w-full rounded-lg text-sm px-2 py-1.5">
        <option value="">— Pilih —</option>
        <template x-for="r in availableRooms" :key="r.id">
            <option :value="r.id"
                    x-text="`[${r.code}] ${r.name} (kap. ${r.capacity})`">
            </option>
        </template>
    </select>
    <p class="text-xs mt-1"
       x-show="instrument && availableRooms.length === 0 && (selectedDay || startTime)"
       style="color:#F87171">
        Tidak ada ruangan tersedia untuk slot &amp; instrumen ini.
    </p>
    <p class="text-xs mt-1 text-gray-400"
       x-show="instrument && availableRooms.length > 0"
       x-text="`Menampilkan ruangan yang support ${instrument}`">
    </p>
    <p class="text-xs mt-1" style="color:#FBBF24"
       x-show="!instrument">
        Murid belum punya paket aktif — semua ruangan ditampilkan.
    </p>
</div>
```

- [ ] **Step 5: Test manual di browser**

1. Buka detail murid yang punya instrumen Piano dan enrollment aktif
2. Klik tab Jadwal → klik "+ Tambah Jadwal"
3. Sebelum pilih hari/jam: dropdown harus menampilkan ruangan yang support Piano saja (R2, R3, R4, R7)
4. Pilih Senin + jam tertentu → ruangan yang sudah penuh di slot itu otomatis hilang dari dropdown
5. Coba murid tanpa enrollment aktif → dropdown tampil semua ruangan + hint kuning

- [ ] **Step 6: Commit**

```bash
git add resources/views/students/show.blade.php
git commit -m "M03: Alpine.js auto-suggest ruangan di form jadwal — filter by instrumen & slot availability"
```

---

## Verifikasi Akhir

- [ ] **Jalankan semua test**

```bash
php artisan test
```

Expected: semua PASS, tidak ada regresi.

- [ ] **Smoke test manual**

1. Buka `/rooms` → tambah ruangan baru dengan multi-checkbox instrumen → simpan
2. Edit ruangan → centang/hapus instrumen → simpan
3. Buka detail murid → tab Jadwal → dropdown ruangan auto-filter sesuai instrumen murid
4. Coba simpan jadwal dengan ruangan yang tidak support instrumen (via devtools edit form) → server harus menolak dengan pesan error
5. Simpan jadwal dengan ruangan yang benar → sukses

- [ ] **Final commit jika ada perubahan minor**

```bash
git add -A
git commit -m "M01/M03: Final polish ruangan fleksibel & auto-suggest jadwal"
```

---

*Plan dibuat: 2026-05-16 | Berdasarkan spec: `docs/superpowers/specs/2026-05-16-ruangan-schedule-design.md`*
