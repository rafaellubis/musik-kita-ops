# Ruangan Fleksibel & Auto-Suggest Jadwal — Design Spec

> **For agentic workers:** Gunakan `superpowers:writing-plans` untuk membuat implementation plan dari spec ini.

**Goal:** Memperbaiki fitur ruangan agar (1) fasilitas merepresentasikan instrumen nyata yang didukung, bukan 3 boolean hardcoded, (2) form jadwal murid otomatis menyaring ruangan yang cocok berdasarkan instrumen dan ketersediaan slot, dan (3) ada hard block saat admin mencoba assign ruangan yang tidak cocok.

**Konteks:** `ScheduleConflictDetector` dan validasi konflik guru/ruangan sudah ada dan berjalan. `SessionGeneratorService` sudah copy `room_id` dari schedule ke sesi. Yang belum ada: konsep "ruangan mendukung instrumen apa" dan auto-suggest berbasis data itu.

---

## Scope Perubahan

| Komponen | Jenis Perubahan |
|---|---|
| Migration `rooms` | Drop 3 boolean, tambah `supported_instruments` JSON |
| Seeder `RoomsSeeder` | Reset dan isi ulang dari data CLAUDE.md |
| `Room` model | Update fillable, casts, tambah `supportsInstrument()` |
| `RoomController` | Pindah ke namespace Admin, update store/update/warning |
| Routes | Update use statement dan namespace |
| `rooms/_form.blade.php` | Ganti 3 checkbox → multi-checkbox instrumen dari DB |
| `StudentsController@show` | Pass `$roomsForFilter`, `$bookedSchedules`, `$studentInstrument` |
| `students/show.blade.php` | Alpine.js auto-suggest di form jadwal |
| `ScheduleController` | Tambah instrument compatibility check |
| `SessionGeneratorService` | Tidak diubah |

---

## Section 1 — Schema & Model

### Migration

**Buat migration baru** (jangan modify migration lama):

```php
// database/migrations/XXXX_add_supported_instruments_to_rooms_table.php
Schema::table('rooms', function (Blueprint $table) {
    $table->json('supported_instruments')->nullable()->after('capacity');
    $table->dropColumn(['has_piano', 'has_drum', 'has_amplifier']);
});
```

### Seeder — Data Ruangan dari CLAUDE.md

Reset dan isi ulang `rooms` dengan data aktual studio:

```php
$rooms = [
    ['code' => 'R1', 'name' => 'Studio 1', 'capacity' => 4,
     'supported_instruments' => ['Vocal', 'Kids Class', 'Gitar']],
    ['code' => 'R2', 'name' => 'Studio 2', 'capacity' => 1,
     'supported_instruments' => ['Piano', 'Vocal', 'Gitar']],
    ['code' => 'R3', 'name' => 'Studio 3', 'capacity' => 1,
     'supported_instruments' => ['Piano']],
    ['code' => 'R4', 'name' => 'Studio 4', 'capacity' => 1,
     'supported_instruments' => ['Piano', 'Gitar']],
    ['code' => 'R5', 'name' => 'Studio 5', 'capacity' => 1,
     'supported_instruments' => ['Bass', 'Gitar']],
    ['code' => 'R6', 'name' => 'Studio 6', 'capacity' => 1,
     'supported_instruments' => ['Violin']],
    ['code' => 'R7', 'name' => 'Studio 7', 'capacity' => 1,
     'supported_instruments' => ['Piano', 'Vocal']],
    ['code' => 'R8', 'name' => 'Studio 8', 'capacity' => 1,
     'supported_instruments' => ['Drum']],
    ['code' => 'R9', 'name' => 'Studio 9', 'capacity' => 1,
     'supported_instruments' => ['Drum']],
];
```

Seeder menggunakan `updateOrCreate(['code' => $r['code']], $r)` — aman dijalankan ulang tanpa duplikat.

### Room Model

```php
protected $fillable = [
    'code', 'name', 'capacity',
    'supported_instruments',   // menggantikan has_piano, has_drum, has_amplifier
    'notes', 'is_active',
];

protected $casts = [
    'supported_instruments' => 'array',
    'capacity'              => 'integer',
    'is_active'             => 'boolean',
];

/**
 * Cek apakah ruangan mendukung instrumen tertentu.
 * Dipakai oleh ScheduleController untuk hard block.
 */
public function supportsInstrument(string $instrumentName): bool
{
    return in_array($instrumentName, $this->supported_instruments ?? []);
}
```

---

## Section 2 — RoomController & Form

### 2a. Pindah ke Namespace Admin

- File lama: `app/Http/Controllers/RoomController.php`
- File baru: `app/Http/Controllers/Admin/RoomController.php`
- Update `namespace` dan `use` statement di dalam file
- Update `routes/web.php`: ganti `use App\Http\Controllers\RoomController` → `use App\Http\Controllers\Admin\RoomController`

### 2b. Form Ruangan — Multi-Checkbox Instrumen

`rooms/_form.blade.php` — ganti 3 checkbox lama dengan loop instrumen dari DB:

```blade
{{-- Di controller: $instruments = Instrument::orderBy('name')->get() --}}
<div class="md:col-span-2">
    <label class="block text-sm font-medium mb-2">
        Instrumen yang Didukung
    </label>
    <div class="grid grid-cols-3 gap-2">
        @foreach($instruments as $instrument)
        <label class="inline-flex items-center gap-2">
            <input type="checkbox"
                   name="supported_instruments[]"
                   value="{{ $instrument->name }}"
                   {{ in_array($instrument->name, old('supported_instruments', $room->supported_instruments ?? [])) ? 'checked' : '' }}
                   class="rounded border-gray-300">
            <span class="text-sm">{{ $instrument->name }}</span>
        </label>
        @endforeach
    </div>
</div>
```

Controller `create()` dan `edit()` pass `$instruments`:
```php
$instruments = Instrument::orderBy('name')->get();
return view('rooms.create', compact('instruments'));
// dan edit:
return view('rooms.edit', compact('room', 'instruments'));
```

### 2c. RoomController store/update — Validasi & Simpan JSON

```php
$validated = $request->validate([
    'code'                    => 'required|string|max:10|unique:rooms,code|regex:/^[A-Z0-9]+$/',
    'name'                    => 'required|string|max:50',
    'capacity'                => 'required|integer|min:1|max:20',
    'supported_instruments'   => 'nullable|array',
    'supported_instruments.*' => 'string|exists:instruments,name',
    'notes'                   => 'nullable|string',
]);
$validated['supported_instruments'] = $request->input('supported_instruments', []);
$validated['is_active'] = $request->boolean('is_active');
```

### 2d. Warning saat Edit Fasilitas — Jadwal Terdampak

Saat `update()`, sebelum simpan, cek apakah ada schedule aktif yang pakai ruangan ini dengan instrumen yang akan dihapus dari `supported_instruments`:

```php
public function update(Request $request, Room $room)
{
    // ... validasi ...

    $instrumenDihapus = array_diff(
        $room->supported_instruments ?? [],
        $validated['supported_instruments']
    );

    $warning = null;
    if (!empty($instrumenDihapus)) {
        // Cari schedule aktif yang terdampak
        $terdampak = Schedule::active()
            ->where('room_id', $room->id)
            ->whereHas('enrollment.package.instrument', function ($q) use ($instrumenDihapus) {
                $q->whereIn('name', $instrumenDihapus);
            })
            ->with('enrollment.student')
            ->get();

        if ($terdampak->isNotEmpty()) {
            $namaMusid = $terdampak
                ->map(fn ($s) => $s->enrollment->student->full_name ?? '?')
                ->implode(', ');
            $warning = "Perhatian: {$terdampak->count()} jadwal aktif terdampak perubahan fasilitas ini: {$namaMusid}. Perbarui jadwal mereka secara manual.";
        }
    }

    $room->update($validated);

    return redirect()
        ->route('rooms.index')
        ->with('success', 'Ruangan diperbarui.')
        ->with('warning', $warning); // null = tidak ditampilkan
}
```

Warning ditampilkan di view `rooms/index` sebagai alert kuning (pasif, tidak memblokir save).

---

## Section 3 — Auto-Suggest di Form Jadwal

### 3a. Data dari StudentsController@show

Tambah 3 variabel ke data yang di-pass ke view `students/show`:

```php
// Semua ruangan aktif + supported_instruments untuk filter Alpine.js
$roomsForFilter = Room::active()
    ->get(['id', 'code', 'name', 'capacity', 'supported_instruments']);

// Semua schedule aktif yang sudah ada room_id — untuk cek konflik client-side
$bookedSchedules = Schedule::active()
    ->whereNotNull('room_id')
    ->get(['room_id', 'day_of_week', 'start_time', 'end_time']);

// Instrumen murid dari enrollment aktif → package → instrument
$studentInstrument = $activeEnrollment?->package?->instrument?->name;
```

### 3b. Alpine.js di Form Tambah Jadwal

Form jadwal di tab "Jadwal & Sesi" dalam `students/show.blade.php`. State jadwal diletakkan sebagai **nested `x-data` langsung di form div** — bukan digabung ke parent component (yang sudah punya banyak state tab/lifecycle). Alpine.js v3 mendukung nested component: child tetap bisa baca `openSchedule` dari parent via scope chain.

**Form div** (yang sekarang hanya punya `x-show` dan `x-cloak`) ditambah `x-data`:

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
                 // Filter 1: ruangan harus support instrumen murid
                 if (this.instrument &&
                     !room.supported_instruments.includes(this.instrument)) {
                     return false;
                 }
                 // Filter 2: ruangan belum penuh di slot yang dipilih
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

**Update input hari & jam — tambah `x-model`:**
```html
<select name="day_of_week" x-model="selectedDay" required ...>
<input type="time" name="start_time" x-model="startTime" required ...>
<input type="time" name="end_time" x-model="endTime" required ...>
```

**Ganti dropdown ruangan — dari `@foreach` statis ke Alpine loop:**
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
    {{-- Hint instrumen --}}
    <p class="text-xs mt-1"
       x-show="instrument && availableRooms.length === 0 && (selectedDay || startTime)"
       style="color:#F87171">
        Tidak ada ruangan tersedia untuk slot & instrumen ini.
    </p>
    <p class="text-xs mt-1 text-gray-400"
       x-show="instrument"
       x-text="`Menampilkan ruangan yang support ${instrument}`">
    </p>
    <p class="text-xs mt-1" style="color:#FBBF24"
       x-show="!instrument">
        Murid belum punya paket aktif — semua ruangan ditampilkan.
    </p>
</div>
```

### 3c. ScheduleController — Instrument Compatibility Check (Hard Block)

Tambah setelah blok `isRoomFull` yang sudah ada, di `store()` dan `update()`:

```php
// Validasi: ruangan harus support instrumen murid (jika room dipilih)
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

Urutan validasi lengkap di `store()` dan `update()` setelah perubahan ini:
1. Validasi field Laravel (sudah ada)
2. Konflik guru — `findTeacherConflicts()` (sudah ada)
3. Kapasitas ruangan — `isRoomFull()` (sudah ada)
4. Instrumen tidak cocok — `supportsInstrument()` **(baru)**
5. `Schedule::create()` / `$schedule->update()`

---

## Tidak Diubah

- `SessionGeneratorService` — generator hanya copy `room_id` dari schedule. Conflict dicegah di hulu (saat buat schedule), bukan di hilir. Aman.
- `ScheduleConflictDetector` — logika overlap waktu tidak berubah.
- Logika reschedule — out of scope Fase 1.

---

## Alur Lengkap Setelah Implementasi

```
Admin buka halaman detail murid → tab Jadwal
    ↓
Alpine.js load: 9 ruangan + instrumen, semua schedule aktif yang ada room
    ↓
Admin pilih Hari + Jam Mulai + Jam Selesai
    ↓ (reaktif, tanpa request ke server)
Dropdown ruangan auto-filter:
    ✓ Support instrumen murid (dari package enrollment aktif)
    ✓ Belum penuh di slot itu (cek overlap client-side)
    ↓
Admin pilih ruangan → klik Simpan
    ↓
ScheduleController validasi ulang (server-side):
    1. Field wajib valid
    2. Konflik guru → hard block
    3. Kapasitas ruangan → hard block
    4. Instrumen tidak cocok → hard block (BARU)
    ↓
Schedule tersimpan → SessionGenerator bulan depan
copy room_id ke setiap sesi → tidak ada konflik
```

---

## Edge Case

| Kondisi | Handling |
|---|---|
| Murid belum punya enrollment aktif | `$studentInstrument = null` → semua ruangan ditampilkan, tidak ada filter instrumen |
| Ruangan tidak dipilih (nullable) | Instrument check di-skip — ruangan opsional tetap boleh kosong |
| Admin edit `supported_instruments` ruangan, ada jadwal terdampak | Warning kuning di index, tidak memblokir save |
| Konflik reschedule (sesi individual pindah slot) | Out of scope Fase 1 — ditangani di logika reschedule Fase 2 |

---

*Spec dibuat: 2026-05-16*
*Berkaitan dengan: M03 Penjadwalan, M01 Master Data Ruangan*
