# Design Spec: Unifikasi Slip Honor Guru & Event

**Tanggal:** 2026-05-20
**Status:** Approved
**Modul:** M06 (Honor Guru) + M08 (Event)

---

## Latar Belakang

Sistem saat ini memiliki dua model slip honor yang terpisah:
- `HonorSlip` (M06) — slip bulanan per guru, berbasis sesi mengajar
- `EventHonorSlip` (M08) — slip per event (Mini Concert / Ujian), berbasis event

**Masalah yang diidentifikasi:**
- Owner ingin melihat semua honor guru dalam satu tempat (Goal A)
- Owner ingin membayar honor bulanan + honor event sekaligus dalam satu slip (Goal B)
- Dua model/controller/view membuat kode lebih kompleks dari yang diperlukan (Goal D)
- Event hanya terjadi 2x/tahun — tidak perlu infrastruktur terpisah

## Keputusan Desain

**Gabungkan `event_honor` ke dalam slip bulanan `teacher_honor_slips`.**

Honor event diinput manual oleh Owner ke slip bulanan bulan yang sama dengan event berlangsung. Tidak ada FK eksplisit ke tabel `events` (Pendekatan 1) — cukup dengan field keterangan teks.

Seluruh infrastruktur `EventHonorSlip` (model, controller, views, tabel) dihapus.

---

## Section 1: Perubahan Schema & Model

### Migration 1 — Tambah kolom ke `teacher_honor_slips`

```php
// add_event_honor_to_teacher_honor_slips
Schema::table('teacher_honor_slips', function (Blueprint $table) {
    $table->unsignedInteger('event_honor')->default(0)->after('base_honor');
    $table->string('event_honor_note')->nullable()->after('event_honor');
});
```

### Migration 2 — Hapus tabel `event_honor_slips`

```php
// drop_event_honor_slips_table
Schema::dropIfExists('event_honor_slips');
```

> **Catatan:** Jalankan Migration 2 hanya setelah memastikan tidak ada data event_honor_slips
> yang perlu dipertahankan. Cek dengan: `SELECT COUNT(*) FROM event_honor_slips;`

### Model `HonorSlip` — perubahan

```php
// fillable — tambah:
'event_honor', 'event_honor_note'

// casts — tambah:
'event_honor' => 'integer'

// recalcTotal() — update:
$this->total_honor = ($this->base_honor ?? 0)
    + ($this->event_honor ?? 0)       // ← BARU
    + ($this->transport_honor ?? 0)
    + ($this->other_honor ?? 0);

// Helper baru:
public function hasEventHonor(): bool
{
    return $this->event_honor > 0;
}
```

### Model `EventHonorSlip` → **DIHAPUS**

File `app/Models/EventHonorSlip.php` dihapus setelah migration dijalankan.

---

## Section 2: Controller & Validasi

### `HonorController::update()` — tambah validasi event honor

```php
$data = $request->validate([
    'transport_honor'  => 'required|integer|min:0|max:99999999',
    'event_honor'      => 'required|integer|min:0|max:99999999',   // ← BARU
    'event_honor_note' => 'nullable|string|max:255',               // ← BARU
    'other_honor'      => 'required|integer|min:0|max:99999999',
    'other_honor_note' => 'nullable|string|max:255',
]);

// Validasi manual: keterangan wajib jika event_honor > 0
if ((int) $data['event_honor'] > 0 && empty(trim($data['event_honor_note'] ?? ''))) {
    return back()->withErrors([
        'event_honor_note' => 'Keterangan event wajib diisi jika ada honor event.'
    ])->withInput();
}

// Saat save — tambah:
$honor->event_honor      = $data['event_honor'];
$honor->event_honor_note = $data['event_honor_note'] ?? null;
```

### `EventHonorSlipController` → **DIHAPUS**

File `app/Http/Controllers/EventHonorSlipController.php` dihapus.

### Routes yang dihapus dari `web.php`

```php
// Hapus seluruh resource group ini:
Route::resource('event-honor-slips', EventHonorSlipController::class)->except(['index', 'create']);
Route::post('event-honor-slips/{eventHonorSlip}/mark-paid', ...);
Route::get('event-honor-slips/{eventHonorSlip}/print', ...);
```

---

## Section 3: Views

### `honors/edit.blade.php` — tambah field event honor

Sisipkan dua field baru **di antara** info honor pokok dan field transport:

```html
{{-- Honor Event --}}
<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700">
        Honor Event (Rp)
        <span class="text-gray-400 font-normal text-xs ml-1">— isi 0 jika tidak ada event bulan ini</span>
    </label>
    <input type="number" name="event_honor" min="0" max="99999999" required
           value="{{ old('event_honor', $honor->event_honor) }}"
           class="mt-1 block w-full border-gray-300 rounded">
    @error('event_honor') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

<div class="mb-4" id="event_note_wrapper">
    <label class="block text-sm font-medium text-gray-700">
        Keterangan Event <span class="text-red-500">*</span>
        <span class="text-gray-400 font-normal text-xs ml-1">— wajib diisi jika ada honor event</span>
    </label>
    <input type="text" name="event_honor_note" maxlength="255"
           value="{{ old('event_honor_note', $honor->event_honor_note) }}"
           placeholder="Contoh: Mini Concert Mei 2026, Ujian Grade Semester 1"
           class="mt-1 block w-full border-gray-300 rounded">
    @error('event_honor_note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>
```

Update JS preview agar `event_honor` ikut dihitung:

```javascript
const eventHonor = parseInt(document.querySelector('[name=event_honor]')?.value) || 0;
const total = baseHonor + eventHonor + transport + other;
```

### `honors/show.blade.php` — tambah kartu event honor

Tambah kartu di grid komponen (grid menjadi 5 kolom di md, 2 kolom di mobile).
Kartu hanya muncul jika `$honor->hasEventHonor()`:

```html
@if($honor->hasEventHonor())
<div class="bg-gray-50 rounded p-3">
    <div class="text-xs text-gray-500">Honor Event (Manual)</div>
    <div class="text-lg font-bold mt-1">
        Rp {{ number_format($honor->event_honor, 0, ',', '.') }}
    </div>
    @if($honor->event_honor_note)
        <div class="text-xs text-gray-500 mt-1 italic">{{ $honor->event_honor_note }}</div>
    @endif
</div>
@endif
```

### `honors/print.blade.php` — tambah baris event honor

Tambah baris di tabel komponen, setelah baris "Honor Pokok", hanya jika `> 0`:

```html
@if($honor->event_honor > 0)
<tr>
    <td>Honor Event</td>
    <td>{{ $honor->event_honor_note ?: 'Input manual' }}</td>
    <td class="text-right">{{ number_format($honor->event_honor, 0, ',', '.') }}</td>
</tr>
@endif
```

### `events/show.blade.php` — ganti section slip honor

Hapus seluruh section "Slip Honor Guru" (form buat slip + tabel slip).
Ganti dengan info statis + link ke halaman honor bulanan:

```html
{{-- Honor guru event ini masuk ke slip bulanan M06 --}}
<div class="bg-white shadow-sm sm:rounded-lg p-4">
    <h3 class="font-semibold text-sm mb-2">Honor Guru</h3>
    <p class="text-sm text-gray-600">
        Honor guru untuk event ini dimasukkan manual ke slip honor bulanan
        masing-masing guru di bulan yang sama dengan event berlangsung.
    </p>
    <a href="{{ route('honors.index', [
                'year'  => $event->event_date->year,
                'month' => $event->event_date->month,
               ]) }}"
       class="inline-block mt-3 text-sm text-indigo-600 hover:underline">
        → Lihat Slip Honor {{ $event->event_date->format('F Y') }}
    </a>
</div>
```

### `honors/index.blade.php` — tidak ada perubahan kolom

Kolom tabel tetap: No. Slip | Guru | Honor Pokok | Transport | Lain-lain | **Total** | Status | Aksi.
Honor event sudah tercakup di nilai Total — tidak perlu kolom terpisah di index.

### Views yang dihapus

```
resources/views/event-honor-slips/edit.blade.php   → HAPUS
resources/views/event-honor-slips/print.blade.php  → HAPUS
```

---

## Section 4: Urutan Eksekusi yang Aman

1. Cek apakah ada data di `event_honor_slips`: `SELECT COUNT(*) FROM event_honor_slips`
2. Jika ada data → catat manual ke catatan Owner sebelum hapus tabel
3. Jalankan Migration 1 (add columns) — aman, tidak merusak data existing
4. Update HonorSlip model
5. Update HonorController
6. Update semua views
7. Hapus EventHonorSlipController, EventHonorSlip model, routes
8. Hapus views event-honor-slips/
9. Jalankan Migration 2 (drop table) — terakhir setelah semua kode bersih
10. `npm run build` — karena ada perubahan Blade

---

## Ringkasan File yang Berubah

| Aksi    | File                                              |
|---------|---------------------------------------------------|
| EDIT    | `database/migrations/...add_event_honor...`       |
| EDIT    | `database/migrations/...drop_event_honor_slips...`|
| EDIT    | `app/Models/HonorSlip.php`                        |
| EDIT    | `app/Http/Controllers/HonorController.php`        |
| EDIT    | `routes/web.php`                                  |
| EDIT    | `resources/views/honors/edit.blade.php`           |
| EDIT    | `resources/views/honors/show.blade.php`           |
| EDIT    | `resources/views/honors/print.blade.php`          |
| EDIT    | `resources/views/events/show.blade.php`           |
| HAPUS   | `app/Models/EventHonorSlip.php`                   |
| HAPUS   | `app/Http/Controllers/EventHonorSlipController.php`|
| HAPUS   | `resources/views/event-honor-slips/edit.blade.php`|
| HAPUS   | `resources/views/event-honor-slips/print.blade.php`|
