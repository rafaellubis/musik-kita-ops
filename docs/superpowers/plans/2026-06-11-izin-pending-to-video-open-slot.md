# IZIN_PENDING → IZIN_VIDEO + Menu Opsi Open Slot Board — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin dapat mengubah sesi `IZIN_PENDING` menjadi `IZIN_VIDEO` langsung dari Open Slot Board; semua aksi per baris digroup ke menu **Opsi ▾**.

**Architecture:** Logic bisnis di `AttendanceService::finalizePendingAsVideo()` yang reuse `recordAttendance()` (honor `H_VIDEO` otomatis). HTTP layer tipis di `AbsensiController`. UI Blade+Alpine refactor kolom aksi tanpa ubah schema DB.

**Tech Stack:** Laravel 11, PHP 8.3, Blade + Tailwind CSS 3 + Alpine.js, Spatie Permission v6, PHPUnit Feature Tests (`RefreshDatabase`).

**Spec:** `docs/superpowers/specs/2026-06-11-izin-pending-to-video-open-slot.md`

---

## File Map

| File | Aksi |
|---|---|
| `app/Services/AttendanceService.php` | Modify — tambah `finalizePendingAsVideo()` |
| `app/Http/Requests/ConvertPendingToVideoRequest.php` | Create — validasi `notes` opsional |
| `app/Http/Controllers/AbsensiController.php` | Modify — tambah `convertPendingToVideo()` |
| `routes/web.php` | Modify — route `POST absensi.open-slots.video` |
| `tests/Feature/IzinPendingTest.php` | Modify — 6+ test baru |
| `resources/views/absensi/open-slots.blade.php` | Modify — menu Opsi + aksi Video |

**Tidak disentuh:** migration, `RescheduleService`, `HonorCalculationService`, portal guru.

---

## Task 1: Service `finalizePendingAsVideo()` (TDD)

**Files:**
- Modify: `app/Services/AttendanceService.php` (setelah method `recordAttendance()`, sebelum `scheduleReplacement()`)
- Test: `tests/Feature/IzinPendingTest.php`

- [ ] **Step 1: Tulis test service yang gagal**

Tambahkan di `tests/Feature/IzinPendingTest.php`:

```php
/** @test */
public function finalize_pending_as_video_sets_h_video_honor(): void
{
    $session = $this->makeSession([
        'status'       => 'IZIN_PENDING',
        'honor_code'   => 'H_IZIN',
        'honor_amount' => 0,
        'notes'        => 'Murid izin, jadwal menyusul',
    ]);

    $service = app(AttendanceService::class);
    $result  = $service->finalizePendingAsVideo($session, 'Link video dikirim via WA');

    $this->assertEquals('IZIN_VIDEO', $result->status);
    $this->assertEquals('H_VIDEO', $result->honor_code);
    $this->assertEquals(50000, $result->honor_amount); // 400k * 50% / 4
    $this->assertStringContainsString('[VIDEO via Open Slot]', $result->notes);
    $this->assertStringContainsString('Link video dikirim via WA', $result->notes);
    $this->assertStringContainsString('Murid izin, jadwal menyusul', $result->notes);
}

/** @test */
public function finalize_pending_as_video_tanpa_catatan_pertahankan_notes_lama(): void
{
    $session = $this->makeSession([
        'status' => 'IZIN_PENDING',
        'notes'  => 'Catatan lama saja',
    ]);

    $result = app(AttendanceService::class)->finalizePendingAsVideo($session, null);

    $this->assertEquals('IZIN_VIDEO', $result->status);
    $this->assertEquals('Catatan lama saja', $result->notes);
}

/** @test */
public function finalize_pending_as_video_ditolak_jika_bukan_izin_pending(): void
{
    $session = $this->makeSession(['status' => 'SCHEDULED']);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Sesi bukan IZIN_PENDING.');

    app(AttendanceService::class)->finalizePendingAsVideo($session);
}

/** @test */
public function finalize_pending_as_video_ditolak_jika_sudah_punya_replacement(): void
{
    $session = $this->makeSession(['status' => 'IZIN_PENDING']);

    ClassSession::factory()->create([
        'origin_session_id' => $session->id,
        'status'            => 'SCHEDULED',
        'teacher_id'        => $session->teacher_id,
        'student_id'        => $session->student_id,
        'enrollment_id'     => $session->enrollment_id,
        'session_date'      => $session->session_date,
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('sudah punya sesi pengganti');

    app(AttendanceService::class)->finalizePendingAsVideo($session);
}
```

- [ ] **Step 2: Jalankan test — harus FAIL**

Run:

```bash
cd C:/laragon/www/musik-kita-ops
php artisan test --filter="finalize_pending_as_video"
```

Expected: FAIL — `Call to undefined method ... finalizePendingAsVideo()`

- [ ] **Step 3: Implementasi minimal di AttendanceService**

Tambahkan method setelah `recordAttendance()` (sekitar baris 122):

```php
/**
 * Selesaikan IZIN_PENDING menjadi IZIN_VIDEO dari Open Slot Board.
 * Murid tidak jadi reschedule fisik; guru tetap dapat honor penuh (H_VIDEO).
 *
 * @throws InvalidArgumentException Jika status bukan IZIN_PENDING atau sudah ada replacement
 */
public function finalizePendingAsVideo(ClassSession $session, ?string $notes = null): ClassSession
{
    if ($session->status !== ClassSession::STATUS_IZIN_PENDING) {
        throw new InvalidArgumentException('Sesi bukan IZIN_PENDING.');
    }

    $hasReplacement = ClassSession::where('origin_session_id', $session->id)
        ->whereNull('split_part')
        ->exists();

    if ($hasReplacement) {
        throw new InvalidArgumentException(
            'Sesi ini sudah punya sesi pengganti/isian slot dan tidak bisa diubah ke video.'
        );
    }

    $mergedNotes = $session->notes;

    if ($notes !== null && trim($notes) !== '') {
        $suffix = '[VIDEO via Open Slot] ' . trim($notes);
        $mergedNotes = $mergedNotes
            ? trim($mergedNotes) . "\n" . $suffix
            : $suffix;
    }

    return $this->recordAttendance($session, [
        'status' => ClassSession::STATUS_IZIN_VIDEO,
        'notes'  => $mergedNotes,
    ]);
}
```

Pastikan `use App\Models\ClassSession;` sudah ada di file (sudah).

- [ ] **Step 4: Jalankan test — harus PASS**

Run:

```bash
php artisan test --filter="finalize_pending_as_video"
```

Expected: 4 tests passed

- [ ] **Step 5: Commit**

```bash
git add app/Services/AttendanceService.php tests/Feature/IzinPendingTest.php
git commit -m "[M04] Service finalizePendingAsVideo untuk IZIN_PENDING → IZIN_VIDEO"
```

---

## Task 2: Form Request validasi

**Files:**
- Create: `app/Http/Requests/ConvertPendingToVideoRequest.php`

- [ ] **Step 1: Buat Form Request**

```bash
php artisan make:request ConvertPendingToVideoRequest
```

- [ ] **Step 2: Isi rules + pesan Bahasa Indonesia**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertPendingToVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware role:Owner|Admin sudah di route group
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max'    => 'Catatan maksimal 500 karakter.',
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/ConvertPendingToVideoRequest.php
git commit -m "[M04] Form Request validasi convert pending ke video"
```

---

## Task 3: Route + Controller HTTP endpoint

**Files:**
- Modify: `routes/web.php` (setelah `open-slots.cancel`, baris ~197)
- Modify: `app/Http/Controllers/AbsensiController.php`
- Test: `tests/Feature/IzinPendingTest.php`

- [ ] **Step 1: Tulis test HTTP happy path yang gagal**

```php
/** @test */
public function admin_dapat_convert_izin_pending_ke_video_dari_open_slot_board(): void
{
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $admin   = User::factory()->create();
    $admin->assignRole('Admin');
    $session = $this->makeSession([
        'status'       => 'IZIN_PENDING',
        'honor_code'   => 'H_IZIN',
        'honor_amount' => 0,
    ]);

    $response = $this->actingAs($admin)->postJson(
        route('absensi.open-slots.video', $session),
        ['notes' => 'Murid minta video pengganti']
    );

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseHas('class_sessions', [
        'id'           => $session->id,
        'status'       => 'IZIN_VIDEO',
        'honor_code'   => 'H_VIDEO',
        'honor_amount' => 50000,
    ]);
}
```

- [ ] **Step 2: Jalankan test — harus FAIL (route missing)**

Run:

```bash
php artisan test --filter="admin_dapat_convert_izin_pending_ke_video"
```

Expected: FAIL — route `absensi.open-slots.video` not defined

- [ ] **Step 3: Tambah route**

Di `routes/web.php`, setelah block `open-slots.cancel`:

```php
Route::post('/absensi/open-slots/{session}/video',
    [AbsensiController::class, 'convertPendingToVideo']
)->name('absensi.open-slots.video');
```

- [ ] **Step 4: Tambah import + method controller**

Import di bagian atas `AbsensiController.php`:

```php
use App\Http\Requests\ConvertPendingToVideoRequest;
```

Tambahkan method setelah `cancelPending()`:

```php
/**
 * Ubah IZIN_PENDING menjadi IZIN_VIDEO — murid pakai video pengganti, tanpa sesi fisik baru.
 */
public function convertPendingToVideo(
    ConvertPendingToVideoRequest $request,
    ClassSession $session,
): JsonResponse {
    abort_if($session->status !== ClassSession::STATUS_IZIN_PENDING, 422, 'Sesi bukan IZIN_PENDING.');

    $hasReplacement = ClassSession::where('origin_session_id', $session->id)
        ->whereNull('split_part')
        ->exists();

    if ($hasReplacement) {
        return response()->json([
            'success' => false,
            'message' => 'Sesi ini sudah punya sesi pengganti/isian slot dan tidak bisa diubah ke video dari sini.',
        ], 422);
    }

    try {
        $this->attendanceService->finalizePendingAsVideo(
            $session,
            $request->validated('notes'),
        );
    } catch (\InvalidArgumentException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }

    $session->loadMissing('student');

    return response()->json([
        'success' => true,
        'message' => 'Sesi ' . ($session->student->full_name ?? '') . ' dicatat sebagai Izin Video.',
    ]);
}
```

- [ ] **Step 5: Jalankan test — harus PASS**

Run:

```bash
php artisan test --filter="admin_dapat_convert_izin_pending_ke_video"
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/AbsensiController.php tests/Feature/IzinPendingTest.php
git commit -m "[M04] Endpoint POST open-slots video untuk convert IZIN_PENDING"
```

---

## Task 4: Feature tests guard & regression

**Files:**
- Test: `tests/Feature/IzinPendingTest.php`

- [ ] **Step 1: Tambah test guard status + replacement + role**

```php
/** @test */
public function convert_video_ditolak_jika_bukan_izin_pending(): void
{
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $admin   = User::factory()->create();
    $admin->assignRole('Admin');
    $session = $this->makeSession(['status' => 'SCHEDULED']);

    $response = $this->actingAs($admin)->postJson(
        route('absensi.open-slots.video', $session)
    );

    $response->assertStatus(422);
}

/** @test */
public function convert_video_ditolak_jika_sudah_punya_replacement(): void
{
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $admin   = User::factory()->create();
    $admin->assignRole('Admin');
    $session = $this->makeSession(['status' => 'IZIN_PENDING']);

    ClassSession::factory()->create([
        'origin_session_id' => $session->id,
        'status'            => 'SCHEDULED',
        'teacher_id'        => $session->teacher_id,
        'student_id'        => $session->student_id,
        'enrollment_id'     => $session->enrollment_id,
        'session_date'      => $session->session_date,
    ]);

    $response = $this->actingAs($admin)->postJson(
        route('absensi.open-slots.video', $session)
    );

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
}

/** @test */
public function convert_video_ditolak_untuk_role_guru(): void
{
    Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
    $guru    = User::factory()->create();
    $guru->assignRole('Guru');
    $session = $this->makeSession(['status' => 'IZIN_PENDING']);

    $response = $this->actingAs($guru)->postJson(
        route('absensi.open-slots.video', $session)
    );

    $response->assertForbidden();
}
```

- [ ] **Step 2: Tambah test sesi hilang dari board + last_session_at**

```php
/** @test */
public function sesi_izin_video_tidak_muncul_di_open_slot_board(): void
{
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $admin   = User::factory()->create();
    $admin->assignRole('Admin');
    $session = $this->makeSession([
        'status'       => 'IZIN_PENDING',
        'honor_code'   => 'H_IZIN',
        'honor_amount' => 0,
    ]);

    $this->actingAs($admin)->postJson(
        route('absensi.open-slots.video', $session)
    )->assertOk();

    $response = $this->actingAs($admin)->getJson(route('absensi.open-slots'));

    $response->assertOk();
    $ids = collect($response->json('slots'))->pluck('id');
    $this->assertFalse($ids->contains($session->id));
}

/** @test */
public function convert_video_update_last_session_at_murid(): void
{
    $session = $this->makeSession([
        'status'       => 'IZIN_PENDING',
        'session_date' => '2026-06-01',
        'start_time'   => '10:00:00',
    ]);
    $student = $session->student;
    $student->update(['last_session_at' => null]);

    app(AttendanceService::class)->finalizePendingAsVideo($session);

    $student->refresh();
    $this->assertNotNull($student->last_session_at);
    $this->assertEquals('2026-06-01', $student->last_session_at->toDateString());
}
```

- [ ] **Step 3: Jalankan seluruh IzinPendingTest**

Run:

```bash
php artisan test tests/Feature/IzinPendingTest.php
```

Expected: ALL PASS (termasuk test lama assign/schedule/cancel)

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/IzinPendingTest.php
git commit -m "[M04] Test guard dan regression convert pending ke video"
```

---

## Task 5: UI — refactor menu Opsi (3 aksi existing)

**Files:**
- Modify: `resources/views/absensi/open-slots.blade.php`

- [ ] **Step 1: Tambah state Alpine `menuOpen`**

Di block `x-data` tbody (sekitar baris 51), tambahkan:

```javascript
menuOpen: false,
openMenu() {
    this.menuOpen = !this.menuOpen;
},
closeMenu() {
    this.menuOpen = false;
},
pickAction(action) {
    this.menuOpen = false;
    this.errorMsg = '';
    if (action === 'isi' || action === 'jadwal') {
        this.showAction = (this.showAction === action) ? null : action;
    }
},
```

- [ ] **Step 2: Ganti kolom Aksi (baris ~195–215) dengan dropdown**

Ganti tiga `<button>` inline dengan:

```html
<td class="px-4 py-3 text-right whitespace-nowrap">
    <div class="relative inline-block text-left" @click.outside="menuOpen = false">
        <button type="button"
            @click="openMenu()"
            :aria-expanded="menuOpen"
            aria-haspopup="menu"
            :disabled="loading"
            class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded border border-mk-border
                   text-mk-muted hover:text-mk-text hover:bg-mk-surface disabled:opacity-50 transition-colors">
            Opsi
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="menuOpen"
             x-cloak
             role="menu"
             class="absolute right-0 z-10 mt-1 w-48 origin-top-right rounded-md bg-white shadow-lg
                    border border-gray-200 py-1 text-sm">
            <button type="button" role="menuitem"
                @click="pickAction('isi')"
                class="block w-full text-left px-4 py-2 text-mk-text hover:bg-mk-surface">
                Isi Slot
            </button>
            <button type="button" role="menuitem"
                @click="pickAction('jadwal')"
                class="block w-full text-left px-4 py-2 text-mk-text hover:bg-mk-surface">
                Jadwalkan Pengganti
            </button>
            <button type="button" role="menuitem"
                @click="closeMenu(); submitBatal()"
                class="block w-full text-left px-4 py-2 text-red-600 hover:bg-red-50">
                Batalkan Pending
            </button>
        </div>
    </div>
</td>
```

- [ ] **Step 3: Smoke test manual**

1. Login Admin → buka `/absensi/open-slots`
2. Klik **Opsi** → menu muncul
3. **Isi Slot** → form expand
4. **Jadwalkan Pengganti** → form jadwal expand
5. **Batalkan Pending** → confirm + POST (jika ada data test)

- [ ] **Step 4: Commit**

```bash
git add resources/views/absensi/open-slots.blade.php
git commit -m "[M04] Open Slot: group aksi ke menu Opsi"
```

---

## Task 6: UI — item menu Video + banner

**Files:**
- Modify: `resources/views/absensi/open-slots.blade.php`

- [ ] **Step 1: Tambah state + method Alpine**

Di `x-data` tbody, tambahkan:

```javascript
videoNotes: '',
submitVideo() {
    const nama = @js($slot->student->full_name);
    const tgl  = @js(\Carbon\Carbon::parse($slot->session_date)->translatedFormat('d M Y'));
    const jam  = @js(substr($slot->start_time, 0, 5) . '–' . substr($slot->end_time, 0, 5));
    const catatan = this.videoNotes.trim();
    let msg = `Ubah izin pending ${nama} (${tgl}, ${jam}) menjadi Izin Video?\n\nSesi dianggap masuk via video pengganti. Guru mendapat honor penuh. Tidak ada sesi pengganti fisik.`;
    if (catatan) {
        msg += `\n\nCatatan: ${catatan}`;
    }
    if (!confirm(msg)) return;
    this.postAction(
        '{{ route('absensi.open-slots.video', $slot) }}',
        { notes: catatan || null }
    );
},
```

- [ ] **Step 2: Tambah item menu Video**

Di dalam `<div role="menu">`, **sebelum** item Batalkan Pending:

```html
<button type="button" role="menuitem"
    @click="closeMenu(); submitVideo()"
    class="block w-full text-left px-4 py-2 text-mk-text hover:bg-mk-surface">
    Video
</button>
```

- [ ] **Step 3: Update banner penjelasan (baris ~11–15)**

Tambah kalimat:

```html
Atau pilih <span class="font-semibold">Video</span> jika murid tidak jadi reschedule dan guru memberi video pengganti.
```

- [ ] **Step 4: Smoke test end-to-end**

1. Buat sesi `IZIN_PENDING` (via absensi harian)
2. Open Slot Board → **Opsi → Video** → confirm
3. Baris hilang, flash sukses
4. Cek DB: status `IZIN_VIDEO`, `honor_code` = `H_VIDEO`

- [ ] **Step 5: Commit**

```bash
git add resources/views/absensi/open-slots.blade.php
git commit -m "[M04] Open Slot: aksi Video dari menu Opsi"
```

---

## Task 7: Regression & verifikasi akhir

**Files:** (none — verifikasi saja)

- [ ] **Step 1: Jalankan full test file**

```bash
php artisan test tests/Feature/IzinPendingTest.php
```

Expected: semua test PASS

- [ ] **Step 2: Cek route terdaftar**

```bash
php artisan route:list --name=absensi.open-slots
```

Expected: muncul `absensi.open-slots.video` method POST

- [ ] **Step 3: Build assets (hanya jika ubah di luar Tailwind utility standar)**

```bash
npm run build
```

Skip jika hanya pakai class Tailwind existing (`bg-white`, `shadow-lg`, dll.) — sudah di-override `.dark-content`.

- [ ] **Step 4: Commit final (jika ada tweak kecil dari smoke test)**

```bash
git status
# jika ada fix kecil:
git add -A
git commit -m "[M04] Polish Open Slot video action"
```

---

## Spec Coverage Checklist

| Spec requirement | Task |
|---|---|
| Transisi IZIN_PENDING → IZIN_VIDEO | Task 1, 3 |
| Honor H_VIDEO via recordAttendance | Task 1 |
| Guard replacement exists | Task 1, 3, 4 |
| Admin discretion (no BR-4.6 enforce) | — (by omission) |
| Route POST video | Task 3 |
| Form Request notes max 500 | Task 2 |
| Menu Opsi grouped | Task 5 |
| Item Video + confirm | Task 6 |
| Banner copy update | Task 6 |
| Tests T1–T7 | Task 1, 3, 4 |
| No migration | — |
| No portal guru change | — |

---

## Out of Scope (jangan implement di plan ini)

- Audit log entry
- Combo Video + Isi Slot satu klik
- Enforce izin ke-2+ sebelum Video
- Entry point di halaman absensi harian (service sudah reusable untuk follow-up)
