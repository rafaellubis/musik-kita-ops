# Slip Honor Unifikasi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gabungkan honor event ke slip bulanan guru — hapus seluruh infrastruktur `EventHonorSlip` dan tambah kolom `event_honor` ke `teacher_honor_slips`.

**Architecture:** Tambah dua kolom (`event_honor`, `event_honor_note`) ke tabel `teacher_honor_slips`. Update model `HonorSlip` dan `HonorController`. Hapus model `EventHonorSlip`, controller, views, dan routes-nya. Drop tabel `event_honor_slips` di akhir setelah semua kode bersih.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL (Laragon), Blade + Tailwind CSS, PHPUnit (SQLite in-memory untuk test), Spatie Permission v6.

**Spec:** `docs/superpowers/specs/2026-05-20-slip-honor-unifikasi-design.md`

---

## File Map

| Aksi   | File                                                          |
|--------|---------------------------------------------------------------|
| BUAT   | `database/migrations/..._add_event_honor_to_teacher_honor_slips.php` |
| BUAT   | `database/migrations/..._drop_event_honor_slips_table.php`   |
| BUAT   | `tests/Unit/Models/HonorSlipTest.php`                        |
| BUAT   | `tests/Feature/HonorControllerEventHonorTest.php`            |
| EDIT   | `app/Models/HonorSlip.php`                                   |
| EDIT   | `app/Http/Controllers/HonorController.php`                   |
| EDIT   | `routes/web.php`                                             |
| EDIT   | `resources/views/honors/edit.blade.php`                      |
| EDIT   | `resources/views/honors/show.blade.php`                      |
| EDIT   | `resources/views/honors/print.blade.php`                     |
| EDIT   | `resources/views/events/show.blade.php`                      |
| HAPUS  | `app/Models/EventHonorSlip.php`                              |
| HAPUS  | `app/Http/Controllers/EventHonorSlipController.php`          |
| HAPUS  | `resources/views/event-honor-slips/edit.blade.php`           |
| HAPUS  | `resources/views/event-honor-slips/print.blade.php`          |

---

## Task 1: Cek Data Sebelum Mulai

**Files:** tidak ada perubahan file

- [ ] **Step 1: Cek apakah ada data di tabel event_honor_slips**

  Jalankan di MySQL (via Laragon phpMyAdmin atau terminal):
  ```sql
  SELECT COUNT(*) FROM event_honor_slips;
  ```

  - Jika hasilnya `0` → lanjut ke Task 2.
  - Jika hasilnya `> 0` → catat slip mana saja yang ada (nama guru, jumlah) sebelum lanjut.
    ```sql
    SELECT slip_number, teacher_id, total_honor, status FROM event_honor_slips;
    ```
    Data ini akan hilang saat Migration 2 dijalankan — catat manual jika perlu dipertahankan.

---

## Task 2: Migration 1 — Tambah Kolom event_honor

**Files:**
- Buat: `database/migrations/YYYY_MM_DD_HHMMSS_add_event_honor_to_teacher_honor_slips.php`

- [ ] **Step 1: Buat migration**

  ```bash
  php artisan make:migration add_event_honor_to_teacher_honor_slips
  ```

  Output: `Created Migration: database/migrations/YYYY_MM_DD_HHMMSS_add_event_honor_to_teacher_honor_slips.php`

- [ ] **Step 2: Edit file migration yang baru dibuat**

  Ganti seluruh isi dengan:

  ```php
  <?php

  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Support\Facades\Schema;
  use Illuminate\Database\Schema\Blueprint;

  return new class extends Migration
  {
      public function up(): void
      {
          Schema::table('teacher_honor_slips', function (Blueprint $table) {
              // Honor event diinput manual oleh Owner — tidak ada formula otomatis
              $table->unsignedInteger('event_honor')->default(0)->after('base_honor');
              $table->string('event_honor_note')->nullable()->after('event_honor');
          });
      }

      public function down(): void
      {
          Schema::table('teacher_honor_slips', function (Blueprint $table) {
              $table->dropColumn(['event_honor', 'event_honor_note']);
          });
      }
  };
  ```

- [ ] **Step 3: Jalankan migration**

  ```bash
  php artisan migrate
  ```

  Output yang diharapkan:
  ```
  Running migrations.
  ... add_event_honor_to_teacher_honor_slips ... DONE
  ```

- [ ] **Step 4: Verifikasi kolom berhasil ditambahkan**

  ```bash
  php artisan tinker --execute="echo implode(', ', array_column(\Illuminate\Support\Facades\Schema::getColumns('teacher_honor_slips'), 'name'));"
  ```

  Pastikan `event_honor` dan `event_honor_note` muncul di output.

- [ ] **Step 5: Commit**

  ```bash
  git add database/migrations/
  git commit -m "DB: Migration tambah kolom event_honor ke teacher_honor_slips"
  ```

---

## Task 3: Update Model HonorSlip + Unit Test

**Files:**
- Edit: `app/Models/HonorSlip.php`
- Buat: `tests/Unit/Models/HonorSlipTest.php`

- [ ] **Step 1: Tulis unit test yang akan gagal dulu**

  Buat file `tests/Unit/Models/HonorSlipTest.php`:

  ```php
  <?php

  namespace Tests\Unit\Models;

  use App\Models\HonorSlip;
  use Tests\TestCase;

  class HonorSlipTest extends TestCase
  {
      public function test_recalc_total_menyertakan_event_honor(): void
      {
          $slip = new HonorSlip();
          $slip->base_honor      = 3_200_000;
          $slip->event_honor     = 250_000;
          $slip->transport_honor = 100_000;
          $slip->other_honor     = 0;

          $slip->recalcTotal();

          $this->assertEquals(3_550_000, $slip->total_honor);
      }

      public function test_recalc_total_tanpa_event_honor_tetap_benar(): void
      {
          $slip = new HonorSlip();
          $slip->base_honor      = 2_000_000;
          $slip->event_honor     = 0;
          $slip->transport_honor = 50_000;
          $slip->other_honor     = 0;

          $slip->recalcTotal();

          $this->assertEquals(2_050_000, $slip->total_honor);
      }

      public function test_has_event_honor_true_jika_event_honor_lebih_dari_nol(): void
      {
          $slip = new HonorSlip();
          $slip->event_honor = 250_000;

          $this->assertTrue($slip->hasEventHonor());
      }

      public function test_has_event_honor_false_jika_event_honor_nol(): void
      {
          $slip = new HonorSlip();
          $slip->event_honor = 0;

          $this->assertFalse($slip->hasEventHonor());
      }

      public function test_has_event_honor_false_jika_event_honor_null(): void
      {
          $slip = new HonorSlip();
          $slip->event_honor = null;

          $this->assertFalse($slip->hasEventHonor());
      }
  }
  ```

- [ ] **Step 2: Jalankan test — pastikan GAGAL**

  ```bash
  php artisan test tests/Unit/Models/HonorSlipTest.php
  ```

  Expected: FAIL — `recalcTotal` belum menyertakan `event_honor`, `hasEventHonor` belum ada.

- [ ] **Step 3: Update `app/Models/HonorSlip.php`**

  Tambah `'event_honor'` dan `'event_honor_note'` ke `$fillable`:

  ```php
  protected $fillable = [
      'slip_number', 'teacher_id',
      'month', 'year',
      'base_honor',
      'event_honor', 'event_honor_note',   // ← TAMBAH
      'transport_honor', 'other_honor', 'other_honor_note',
      'total_honor',
      'status', 'paid_at', 'paid_by', 'created_by',
  ];
  ```

  Tambah cast untuk `event_honor` di `$casts`:

  ```php
  protected $casts = [
      'month'           => 'integer',
      'year'            => 'integer',
      'base_honor'      => 'integer',
      'event_honor'     => 'integer',   // ← TAMBAH
      'transport_honor' => 'integer',
      'other_honor'     => 'integer',
      'total_honor'     => 'integer',
      'paid_at'         => 'datetime',
  ];
  ```

  Ganti method `recalcTotal()` yang lama:

  ```php
  public function recalcTotal(): void
  {
      $this->total_honor = ($this->base_honor ?? 0)
          + ($this->event_honor ?? 0)
          + ($this->transport_honor ?? 0)
          + ($this->other_honor ?? 0);
  }
  ```

  Tambah helper baru setelah `recalcTotal()`:

  ```php
  public function hasEventHonor(): bool
  {
      return ($this->event_honor ?? 0) > 0;
  }
  ```

- [ ] **Step 4: Jalankan test — pastikan LULUS**

  ```bash
  php artisan test tests/Unit/Models/HonorSlipTest.php
  ```

  Expected:
  ```
  PASS  Tests\Unit\Models\HonorSlipTest
  ✓ test_recalc_total_menyertakan_event_honor
  ✓ test_recalc_total_tanpa_event_honor_tetap_benar
  ✓ test_has_event_honor_true_jika_event_honor_lebih_dari_nol
  ✓ test_has_event_honor_false_jika_event_honor_nol
  ✓ test_has_event_honor_false_jika_event_honor_null
  ```

- [ ] **Step 5: Commit**

  ```bash
  git add app/Models/HonorSlip.php tests/Unit/Models/HonorSlipTest.php
  git commit -m "M06: Update HonorSlip model — tambah event_honor + hasEventHonor()"
  ```

---

## Task 4: Update HonorController + Feature Test

**Files:**
- Edit: `app/Http/Controllers/HonorController.php`
- Buat: `tests/Feature/HonorControllerEventHonorTest.php`

- [ ] **Step 1: Tulis feature test yang akan gagal dulu**

  Buat file `tests/Feature/HonorControllerEventHonorTest.php`:

  ```php
  <?php

  namespace Tests\Feature;

  use App\Models\HonorSlip;
  use App\Models\Teacher;
  use App\Models\User;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Spatie\Permission\Models\Role;
  use Tests\TestCase;

  class HonorControllerEventHonorTest extends TestCase
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

      private function buatSlip(): HonorSlip
      {
          $teacher = Teacher::create([
              'name'      => 'Thomas',
              'is_active' => true,
          ]);

          return HonorSlip::create([
              'slip_number'     => 'SLIP/2026/05/0001',
              'teacher_id'      => $teacher->id,
              'month'           => 5,
              'year'            => 2026,
              'base_honor'      => 3_200_000,
              'event_honor'     => 0,
              'transport_honor' => 0,
              'other_honor'     => 0,
              'total_honor'     => 3_200_000,
              'status'          => HonorSlip::STATUS_CALCULATED,
              'created_by'      => null,
          ]);
      }

      public function test_owner_dapat_simpan_event_honor_ke_slip(): void
      {
          $owner = $this->ownerUser();
          $slip  = $this->buatSlip();

          $response = $this->actingAs($owner)->patch(route('honors.update', $slip), [
              'event_honor'      => 250_000,
              'event_honor_note' => 'Mini Concert Mei 2026',
              'transport_honor'  => 100_000,
              'other_honor'      => 0,
              'other_honor_note' => null,
          ]);

          $response->assertRedirect(route('honors.show', $slip));
          $response->assertSessionHas('success');

          $slip->refresh();
          $this->assertEquals(250_000,   $slip->event_honor);
          $this->assertEquals('Mini Concert Mei 2026', $slip->event_honor_note);
          $this->assertEquals(3_550_000, $slip->total_honor); // 3.2jt + 250k + 100k
      }

      public function test_event_honor_lebih_dari_nol_tanpa_keterangan_ditolak(): void
      {
          $owner = $this->ownerUser();
          $slip  = $this->buatSlip();

          $response = $this->actingAs($owner)->patch(route('honors.update', $slip), [
              'event_honor'      => 250_000,
              'event_honor_note' => '',   // ← kosong, harus ditolak
              'transport_honor'  => 0,
              'other_honor'      => 0,
              'other_honor_note' => null,
          ]);

          $response->assertSessionHasErrors('event_honor_note');
      }

      public function test_event_honor_nol_tanpa_keterangan_diterima(): void
      {
          $owner = $this->ownerUser();
          $slip  = $this->buatSlip();

          $response = $this->actingAs($owner)->patch(route('honors.update', $slip), [
              'event_honor'      => 0,
              'event_honor_note' => '',   // ← boleh kosong jika event_honor = 0
              'transport_honor'  => 0,
              'other_honor'      => 0,
              'other_honor_note' => null,
          ]);

          $response->assertRedirect(route('honors.show', $slip));
          $response->assertSessionHas('success');
      }

      public function test_slip_paid_tidak_bisa_diupdate(): void
      {
          $owner = $this->ownerUser();
          $slip  = $this->buatSlip();
          $slip->update(['status' => HonorSlip::STATUS_PAID]);

          $response = $this->actingAs($owner)->patch(route('honors.update', $slip), [
              'event_honor'      => 250_000,
              'event_honor_note' => 'Mini Concert',
              'transport_honor'  => 0,
              'other_honor'      => 0,
              'other_honor_note' => null,
          ]);

          $response->assertRedirect(route('honors.show', $slip));
          $response->assertSessionHas('error');
      }
  }
  ```

- [ ] **Step 2: Jalankan test — pastikan GAGAL**

  ```bash
  php artisan test tests/Feature/HonorControllerEventHonorTest.php
  ```

  Expected: FAIL — `event_honor` belum ada di validasi controller.

- [ ] **Step 3: Update `app/Http/Controllers/HonorController.php` — method `update()`**

  Ganti seluruh method `update()` dengan:

  ```php
  public function update(Request $request, HonorSlip $honor)
  {
      if ($honor->isLocked()) {
          return redirect()->route('honors.show', $honor)
              ->with('error', 'Slip sudah berstatus PAID dan tidak bisa diubah.');
      }

      $data = $request->validate([
          'transport_honor'  => 'required|integer|min:0|max:99999999',
          'event_honor'      => 'required|integer|min:0|max:99999999',
          'event_honor_note' => 'nullable|string|max:255',
          'other_honor'      => 'required|integer|min:0|max:99999999',
          'other_honor_note' => 'nullable|string|max:255',
      ], [
          'transport_honor.required' => 'Honor transport wajib diisi (isi 0 jika tidak ada).',
          'transport_honor.min'      => 'Honor transport tidak boleh negatif.',
          'event_honor.required'     => 'Honor event wajib diisi (isi 0 jika tidak ada event).',
          'event_honor.min'          => 'Honor event tidak boleh negatif.',
          'other_honor.required'     => 'Honor lain-lain wajib diisi (isi 0 jika tidak ada).',
          'other_honor.min'          => 'Honor lain-lain tidak boleh negatif.',
      ]);

      // Keterangan wajib jika event_honor > 0
      if ((int) $data['event_honor'] > 0 && empty(trim($data['event_honor_note'] ?? ''))) {
          return back()
              ->withErrors(['event_honor_note' => 'Keterangan event wajib diisi jika ada honor event.'])
              ->withInput();
      }

      // Keterangan wajib jika other_honor > 0
      if ((int) $data['other_honor'] > 0 && empty(trim($data['other_honor_note'] ?? ''))) {
          return back()
              ->withErrors(['other_honor_note' => 'Keterangan lain-lain wajib diisi jika ada honor lain-lain.'])
              ->withInput();
      }

      $honor->transport_honor  = $data['transport_honor'];
      $honor->event_honor      = $data['event_honor'];
      $honor->event_honor_note = $data['event_honor_note'] ?? null;
      $honor->other_honor      = $data['other_honor'];
      $honor->other_honor_note = $data['other_honor_note'] ?? null;
      $honor->recalcTotal();
      $honor->save();

      return redirect()->route('honors.show', $honor)
          ->with('success', 'Komponen honor berhasil disimpan.');
  }
  ```

- [ ] **Step 4: Jalankan test — pastikan LULUS**

  ```bash
  php artisan test tests/Feature/HonorControllerEventHonorTest.php
  ```

  Expected:
  ```
  PASS  Tests\Feature\HonorControllerEventHonorTest
  ✓ test_owner_dapat_simpan_event_honor_ke_slip
  ✓ test_event_honor_lebih_dari_nol_tanpa_keterangan_ditolak
  ✓ test_event_honor_nol_tanpa_keterangan_diterima
  ✓ test_slip_paid_tidak_bisa_diupdate
  ```

- [ ] **Step 5: Jalankan seluruh test suite — pastikan tidak ada yang rusak**

  ```bash
  php artisan test
  ```

  Expected: semua test PASS (tidak ada regresi).

- [ ] **Step 6: Commit**

  ```bash
  git add app/Http/Controllers/HonorController.php tests/Feature/HonorControllerEventHonorTest.php
  git commit -m "M06: HonorController update() — tambah validasi event_honor"
  ```

---

## Task 5: Update View — honors/edit.blade.php

**Files:**
- Edit: `resources/views/honors/edit.blade.php`

- [ ] **Step 1: Tambah field event_honor di antara info honor pokok dan field transport**

  Di `resources/views/honors/edit.blade.php`, cari baris:
  ```html
  <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700">
          Honor Transport (Rp)
  ```

  Sisipkan blok berikut **tepat sebelum** div tersebut:

  ```html
  {{-- Honor Event (isi 0 jika bulan ini tidak ada event) --}}
  <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700">
          Honor Event (Rp)
          <span class="text-gray-400 font-normal text-xs ml-1">— isi 0 jika tidak ada event bulan ini</span>
      </label>
      <input type="number"
             name="event_honor"
             value="{{ old('event_honor', $honor->event_honor) }}"
             min="0" max="99999999" required
             class="mt-1 block w-full border-gray-300 rounded @error('event_honor') border-red-500 @enderror">
      @error('event_honor')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
      @enderror
  </div>

  <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700">
          Keterangan Event
          <span class="text-red-500">*</span>
          <span class="text-gray-400 font-normal text-xs ml-1">— wajib diisi jika ada honor event</span>
      </label>
      <input type="text"
             name="event_honor_note"
             value="{{ old('event_honor_note', $honor->event_honor_note) }}"
             maxlength="255"
             placeholder="Contoh: Mini Concert Mei 2026, Ujian Grade Semester 1"
             class="mt-1 block w-full border-gray-300 rounded @error('event_honor_note') border-red-500 @enderror">
      @error('event_honor_note')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
      @enderror
  </div>
  ```

- [ ] **Step 2: Update preview total di section preview**

  Cari baris:
  ```html
  Rp {{ number_format($honor->base_honor, 0, ',', '.') }}
  + <span id="preview_transport">
  ```

  Ganti seluruh baris preview menjadi:
  ```html
  Rp {{ number_format($honor->base_honor, 0, ',', '.') }}
  + <span id="preview_event">Rp {{ number_format($honor->event_honor, 0, ',', '.') }}</span>
  + <span id="preview_transport">Rp {{ number_format($honor->transport_honor, 0, ',', '.') }}</span>
  + <span id="preview_other">Rp {{ number_format($honor->other_honor, 0, ',', '.') }}</span>
  = <span id="preview_total" class="text-lg">Rp {{ number_format($honor->total_honor, 0, ',', '.') }}</span>
  ```

- [ ] **Step 3: Update JavaScript preview agar event_honor ikut dihitung**

  Ganti seluruh blok `<script>` di bagian bawah file dengan:

  ```html
  <script>
      const baseHonor = {{ $honor->base_honor }};

      document.addEventListener('input', function () {
          const event     = parseInt(document.querySelector('[name=event_honor]')?.value) || 0;
          const transport = parseInt(document.querySelector('[name=transport_honor]')?.value) || 0;
          const other     = parseInt(document.querySelector('[name=other_honor]')?.value) || 0;
          const total     = baseHonor + event + transport + other;

          const fmt = (n) => 'Rp ' + n.toLocaleString('id-ID');
          const el  = (id) => document.getElementById(id);

          if (el('preview_event'))     el('preview_event').textContent     = fmt(event);
          if (el('preview_transport')) el('preview_transport').textContent = fmt(transport);
          if (el('preview_other'))     el('preview_other').textContent     = fmt(other);
          if (el('preview_total'))     el('preview_total').textContent     = fmt(total);
      });
  </script>
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add resources/views/honors/edit.blade.php
  git commit -m "M06: honors/edit — tambah field event_honor + update preview JS"
  ```

---

## Task 6: Update View — honors/show.blade.php

**Files:**
- Edit: `resources/views/honors/show.blade.php`

- [ ] **Step 1: Tambah kartu event honor di grid komponen**

  Cari baris yang berisi:
  ```html
  <div class="bg-gray-50 rounded p-3">
      <div class="text-xs text-gray-500">Transport (Manual)</div>
  ```

  Sisipkan blok berikut **tepat sebelum** div tersebut:

  ```html
  @if($honor->hasEventHonor())
  <div class="bg-gray-50 rounded p-3">
      <div class="text-xs text-gray-500">Honor Event (Manual)</div>
      <div class="text-lg font-bold mt-1">
          Rp {{ number_format($honor->event_honor, 0, ',', '.') }}
      </div>
      @if($honor->event_honor_note)
          <div class="text-xs text-gray-500 mt-1 italic">
              {{ $honor->event_honor_note }}
          </div>
      @endif
  </div>
  @endif
  ```

- [ ] **Step 2: Commit**

  ```bash
  git add resources/views/honors/show.blade.php
  git commit -m "M06: honors/show — tambah kartu event honor (kondisional)"
  ```

---

## Task 7: Update View — honors/print.blade.php

**Files:**
- Edit: `resources/views/honors/print.blade.php`

- [ ] **Step 1: Tambah baris event honor di tabel komponen**

  Cari baris:
  ```html
  <tr>
      <td>Honor Transport</td>
  ```

  Sisipkan blok berikut **tepat sebelum** baris tersebut:

  ```html
  @if($honor->event_honor > 0)
  <tr>
      <td>Honor Event</td>
      <td>{{ $honor->event_honor_note ?: 'Input manual' }}</td>
      <td class="text-right">{{ number_format($honor->event_honor, 0, ',', '.') }}</td>
  </tr>
  @endif
  ```

- [ ] **Step 2: Commit**

  ```bash
  git add resources/views/honors/print.blade.php
  git commit -m "M06: honors/print — tambah baris event honor di slip cetak"
  ```

---

## Task 8: Update View — events/show.blade.php

**Files:**
- Edit: `resources/views/events/show.blade.php`

- [ ] **Step 1: Ganti section slip honor guru dengan info statis**

  Di `resources/views/events/show.blade.php`, cari seluruh blok yang diawali:
  ```html
  {{-- ===== SLIP HONOR GURU ===== --}}
  <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
      <div class="px-4 py-3 border-b bg-gray-50 flex justify-between items-center">
          <h3 class="font-semibold text-sm">Slip Honor Guru ({{ $event->honorSlips->count() }})</h3>
      </div>
  ```

  Hapus seluruh blok div tersebut hingga tag `</div>` penutupnya (yang mencakup tabel slip + form buat slip).

  Ganti dengan:

  ```html
  {{-- Honor guru masuk ke slip bulanan M06, bukan slip terpisah --}}
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

- [ ] **Step 2: Hapus variabel `$teachers` dari EventController jika hanya dipakai di section honor**

  Buka `app/Http/Controllers/EventController.php`, method `show()`.
  Cari apakah ada baris seperti:
  ```php
  $teachers = Teacher::where('is_active', true)->orderBy('name')->get();
  ```
  Jika variabel `$teachers` hanya dipakai untuk form buat slip honor (yang sudah dihapus), hapus baris tersebut dan hapus dari `compact()`.

- [ ] **Step 3: Commit**

  ```bash
  git add resources/views/events/show.blade.php app/Http/Controllers/EventController.php
  git commit -m "M08: events/show — ganti section slip honor dengan link ke honor bulanan"
  ```

---

## Task 9: Cleanup — Hapus Infrastruktur EventHonorSlip

**Files:**
- Hapus: `app/Models/EventHonorSlip.php`
- Hapus: `app/Http/Controllers/EventHonorSlipController.php`
- Hapus: `resources/views/event-honor-slips/edit.blade.php`
- Hapus: `resources/views/event-honor-slips/print.blade.php`
- Edit: `routes/web.php`

- [ ] **Step 1: Hapus routes event-honor-slips dari `routes/web.php`**

  Cari dan hapus seluruh blok routes berikut (ada di dua tempat — group Owner dan group Auditor):

  ```php
  // Di group Owner — hapus seluruh block ini:
  Route::post('events/{event}/honor-slips',
      [EventHonorSlipController::class, 'store']
  )->name('event-honor-slips.store');
  Route::get('event-honor-slips/{eventHonorSlip}/edit',
      [EventHonorSlipController::class, 'edit']
  )->name('event-honor-slips.edit');
  Route::patch('event-honor-slips/{eventHonorSlip}',
      [EventHonorSlipController::class, 'update']
  )->name('event-honor-slips.update');
  Route::post('event-honor-slips/{eventHonorSlip}/mark-paid',
      [EventHonorSlipController::class, 'markPaid']
  )->name('event-honor-slips.mark-paid');
  Route::delete('event-honor-slips/{eventHonorSlip}',
      [EventHonorSlipController::class, 'destroy']
  )->name('event-honor-slips.destroy');

  // Di group Auditor/semua role — hapus ini juga:
  Route::get('event-honor-slips/{eventHonorSlip}/print',
      [EventHonorSlipController::class, 'print']
  )->name('event-honor-slips.print');
  ```

  Hapus juga baris `use` import di bagian atas `web.php` jika ada:
  ```php
  use App\Http\Controllers\EventHonorSlipController;
  ```

- [ ] **Step 2: Hapus file model, controller, dan views**

  ```bash
  rm app/Models/EventHonorSlip.php
  rm app/Http/Controllers/EventHonorSlipController.php
  rm resources/views/event-honor-slips/edit.blade.php
  rm resources/views/event-honor-slips/print.blade.php
  rmdir resources/views/event-honor-slips
  ```

- [ ] **Step 3: Clear cache dan pastikan tidak ada error**

  ```bash
  php artisan route:list | grep event-honor
  ```

  Expected: tidak ada output (route sudah terhapus).

  ```bash
  php artisan config:clear && php artisan cache:clear && php artisan view:clear
  ```

- [ ] **Step 4: Jalankan seluruh test suite**

  ```bash
  php artisan test
  ```

  Expected: semua test PASS.

- [ ] **Step 5: Commit**

  ```bash
  git add routes/web.php
  git rm app/Models/EventHonorSlip.php
  git rm app/Http/Controllers/EventHonorSlipController.php
  git rm resources/views/event-honor-slips/edit.blade.php
  git rm resources/views/event-honor-slips/print.blade.php
  git commit -m "Cleanup: hapus EventHonorSlip model, controller, views, dan routes"
  ```

---

## Task 10: Migration 2 — Drop Tabel event_honor_slips

**Files:**
- Buat: `database/migrations/YYYY_MM_DD_HHMMSS_drop_event_honor_slips_table.php`

- [ ] **Step 1: Buat migration**

  ```bash
  php artisan make:migration drop_event_honor_slips_table
  ```

- [ ] **Step 2: Edit file migration**

  ```php
  <?php

  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Support\Facades\Schema;

  return new class extends Migration
  {
      public function up(): void
      {
          // Tabel ini digantikan oleh kolom event_honor di teacher_honor_slips
          Schema::dropIfExists('event_honor_slips');
      }

      public function down(): void
      {
          // Recreate minimal — hanya untuk rollback darurat
          // Data yang sudah terhapus tidak bisa dikembalikan
          Schema::create('event_honor_slips', function (\Illuminate\Database\Schema\Blueprint $table) {
              $table->id();
              $table->string('slip_number', 30)->unique();
              $table->unsignedBigInteger('event_id');
              $table->unsignedBigInteger('teacher_id');
              $table->string('role', 100)->nullable();
              $table->integer('base_honor')->default(250000);
              $table->integer('transport_honor')->default(0);
              $table->integer('other_honor')->default(0);
              $table->string('other_honor_note', 255)->nullable();
              $table->integer('total_honor')->default(250000);
              $table->enum('status', ['DRAFT', 'PAID'])->default('DRAFT');
              $table->timestamp('paid_at')->nullable();
              $table->unsignedBigInteger('paid_by')->nullable();
              $table->unsignedBigInteger('created_by');
              $table->timestamps();
          });
      }
  };
  ```

- [ ] **Step 3: Jalankan migration**

  ```bash
  php artisan migrate
  ```

  Output yang diharapkan:
  ```
  Running migrations.
  ... drop_event_honor_slips_table ... DONE
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add database/migrations/
  git commit -m "DB: Drop tabel event_honor_slips — digantikan kolom event_honor di slip bulanan"
  ```

---

## Task 11: Build Assets + Verifikasi Akhir

**Files:** tidak ada perubahan kode

- [ ] **Step 1: Build Tailwind CSS**

  ```bash
  npm run build
  ```

  Expected: build sukses tanpa error.

- [ ] **Step 2: Jalankan seluruh test suite satu kali lagi**

  ```bash
  php artisan test
  ```

  Expected: semua test PASS.

- [ ] **Step 3: Verifikasi manual di browser**

  Buka sistem di browser, cek:
  1. `/honors` — halaman index slip honor tampil normal
  2. `/honors/{id}/edit` — form edit muncul field "Honor Event" dan "Keterangan Event"
  3. Isi `event_honor = 250000`, kosongkan keterangan → harus muncul error validasi
  4. Isi keterangan "Mini Concert" → simpan → total berubah benar
  5. `/honors/{id}` — kartu "Honor Event" muncul di komponen
  6. `/honors/{id}/print` — baris "Honor Event" muncul di slip cetak
  7. `/events/{id}` — section slip honor sudah diganti info + link ke honor bulanan
  8. Link "Lihat Slip Honor" mengarah ke bulan yang benar

- [ ] **Step 4: Commit final jika ada perubahan yang tersisa**

  ```bash
  git status
  # Jika bersih, tidak perlu commit
  # Jika ada file yang belum di-commit, tambahkan sekarang
  ```

- [ ] **Step 5: Push ke GitHub**

  ```bash
  git push
  ```

---

## Ringkasan Commit yang Dihasilkan

```
DB: Migration tambah kolom event_honor ke teacher_honor_slips
M06: Update HonorSlip model — tambah event_honor + hasEventHonor()
M06: HonorController update() — tambah validasi event_honor
M06: honors/edit — tambah field event_honor + update preview JS
M06: honors/show — tambah kartu event honor (kondisional)
M06: honors/print — tambah baris event honor di slip cetak
M08: events/show — ganti section slip honor dengan link ke honor bulanan
Cleanup: hapus EventHonorSlip model, controller, views, dan routes
DB: Drop tabel event_honor_slips — digantikan kolom event_honor di slip bulanan
```
