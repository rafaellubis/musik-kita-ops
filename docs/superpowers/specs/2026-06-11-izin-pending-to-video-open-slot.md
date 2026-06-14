# Spec: IZIN_PENDING → IZIN_VIDEO dari Open Slot Board + Menu Aksi Tergrup

**Tanggal:** 2026-06-11  
**Status:** Approved  
**Modul terkait:** M04 Absensi, M06 Honor Guru  
**Spec induk:** `docs/superpowers/specs/2026-05-28-izin-pending-open-slot.md`

---

## Latar Belakang

Open Slot Board sudah menangani tiga aksi untuk sesi `IZIN_PENDING`:

1. **Isi Slot** — murid lain pakai slot waktu yang sama (sesi asli tetap pending)
2. **Jadwalkan Pengganti** — buat sesi pengganti → asli jadi `IZIN_RESCHEDULE`
3. **Batalkan Pending** — reset ke `SCHEDULED`

Kondisi lapangan: murid yang awalnya minta reschedule (pending) akhirnya memilih **video pengganti** — tidak jadi datang fisik dan tidak perlu sesi pengganti baru. Admin saat ini harus keluar ke halaman absensi harian untuk set `IZIN_VIDEO`, padahal keputusan sering diambil saat mengelola Open Slot Board.

Kebutuhan:
1. Admin bisa menutup `IZIN_PENDING` langsung menjadi `IZIN_VIDEO` dari Open Slot Board
2. Semua tombol aksi per baris digroup ke menu **Opsi** agar kolom aksi tidak penuh

---

## Keputusan Desain

| Topik | Keputusan |
|---|---|
| Kapan boleh convert | **Kapan saja** dari `IZIN_PENDING` — keputusan Admin (tidak enforce izin ke-2+ / ≥5 jam) |
| Pendekatan backend | Method baru `AttendanceService::finalizePendingAsVideo()` — reuse `recordAttendance()` |
| UI aksi | Satu tombol **Opsi ▾** → dropdown 4 item |
| Migration / enum | **Tidak ada** — `IZIN_VIDEO` sudah ada di enum |
| Portal guru | **Tidak berubah** |
| Combo video + isi slot | **Out of scope** Fase 1 |

---

## Section 1 — State Machine

### Transisi baru

```
IZIN_PENDING  →  IZIN_VIDEO     Admin: murid pakai video pengganti, tidak jadi reschedule fisik
```

### Nilai kolom setelah convert

| Kolom | Nilai |
|---|---|
| `status` | `IZIN_VIDEO` |
| `honor_code` | `H_VIDEO` (Reguler/Hobby), `H_DUO` (DUO), `H_KIDS` (Kids), `H_TRIAL` (Trial hadir-equivalent) |
| `honor_amount` | Nominal penuh sesuai paket (sama skenario hadir/video existing) |
| `origin_session_id` | `NULL` (tidak ada sesi pengganti) |
| `notes` | Catatan existing dipertahankan; jika Admin isi catatan baru, **append** dengan prefix `[VIDEO via Open Slot]` |
| `students.last_session_at` | Ter-update (terminal status — sudah di `recordAttendance()`) |

### Perbandingan aksi Open Slot Board

| Aksi | Status murid asli | Sesi pengganti | Honor sesi asli | Hilang dari board |
|---|---|---|---|---|
| Isi Slot | `IZIN_PENDING` | Ya (murid lain) | Rp 0 (`H_IZIN`) | Ya* |
| Jadwalkan Pengganti | `IZIN_RESCHEDULE` | Ya (murid asli) | Rp 0 | Ya |
| **Video (baru)** | **`IZIN_VIDEO`** | **Tidak** | **Penuh (`H_VIDEO` dll.)** | **Ya** |
| Batalkan Pending | `SCHEDULED` | Tidak | NULL | Ya |

\* Baris hilang dari board karena filter board mengecualikan sesi yang sudah punya child `origin_session_id`.

### Guard (precondition)

Convert **Video** hanya jika:

1. `session.status === IZIN_PENDING`
2. **Belum** ada record `class_sessions` dengan `origin_session_id = session.id` dan `split_part IS NULL` (guard sama `cancelPending` dan filter `openSlotBoard`)
3. User role **Owner | Admin**

Tidak ada validasi:
- Urutan izin ke-1 / ke-2+ di bulan (BR-4.6 tidak di-enforce di Fase 1)
- Info ≥5 jam sebelum sesi
- Catatan wajib

---

## Section 2 — UI Open Slot Board

### Refactor kolom Aksi: menu tergrup

**Sebelum:** empat tombol inline per baris.

**Sesudah:** satu tombol **Opsi ▾** membuka dropdown vertikal:

```
[ Opsi ▾ ]
  ├─ Isi Slot
  ├─ Jadwalkan Pengganti
  ├─ Video
  └─ Batalkan Pending
```

### Perilaku per item menu

| Item | Aksi UI | Backend |
|---|---|---|
| Isi Slot | Tutup menu → expand row form isi slot (existing) | `POST absensi.open-slots.assign` |
| Jadwalkan Pengganti | Tutup menu → expand row form jadwal (existing) | `POST absensi.open-slots.schedule` |
| Video | Tutup menu → confirm dialog → optional textarea catatan → POST | `POST absensi.open-slots.video` **(baru)** |
| Batalkan Pending | Tutup menu → confirm dialog (existing) → POST | `POST absensi.open-slots.cancel` |

### Confirm dialog — Video

Teks (Bahasa Indonesia):

> Ubah izin pending **{nama murid}** ({tanggal}, {jam}) menjadi **Izin Video**?  
> Sesi dianggap masuk via video pengganti. Guru mendapat honor penuh. Tidak ada sesi pengganti fisik.

- Textarea **Catatan** (opsional, max 500 karakter)
- Tombol: **Ya, Ubah ke Video** / **Batal**

### Styling

- Tombol **Opsi**: border default, align kanan kolom
- Dropdown: `bg-white`, shadow, `z-10`, align kanan agar tidak overflow tabel horizontal
- Item **Batalkan Pending**: `text-red-600`
- Item lain: teks default theme (dark/light via class Tailwind existing)

### Alpine.js state per `<tbody>`

Tambah / pertahankan:

| State | Fungsi |
|---|---|
| `menuOpen` | Toggle dropdown Opsi |
| `showAction` | `'isi' \| 'jadwal' \| null` — expand form row |
| `videoNotes` | Catatan opsional sebelum POST video |
| `loading`, `errorMsg`, `postAction()` | Existing — reuse |

Flow:
1. Klik **Opsi** → `menuOpen = !menuOpen`; tutup menu lain di baris lain via `@click.outside` atau tutup saat buka menu baris berbeda (pilih salah satu, konsisten)
2. Pilih expand action → `menuOpen = false`, set `showAction`
3. Pilih Video → `menuOpen = false` → `confirm()` → `postAction(url, { notes })`
4. Sukses → baris di-remove dari DOM (existing pattern)

### Banner penjelasan (update copy)

Tambah kalimat di blok info atas halaman:

> Atau pilih **Video** jika murid tidak jadi reschedule dan guru memberi video pengganti.

### Aksesibilitas

- Tombol Opsi: `aria-haspopup="menu"`, `:aria-expanded="menuOpen"`
- Container menu: `role="menu"`
- Item: `role="menuitem"`, fokus keyboard Escape tutup menu

---

## Section 3 — Backend

### Route baru

Registrasi di `routes/web.php` **sebelum** wildcard `/absensi/{classSession}` (sama pola route open-slots existing):

```php
Route::post('/absensi/open-slots/{session}/video',
    [AbsensiController::class, 'convertPendingToVideo']
)->name('absensi.open-slots.video');
```

Middleware group: `role:Owner|Admin` (sama route open-slots lain).

### Form Request (disarankan)

`ConvertPendingToVideoRequest` atau validasi inline di controller:

| Field | Rule |
|---|---|
| `notes` | `nullable`, `string`, `max:500` |

Pesan validasi Bahasa Indonesia.

### `AbsensiController::convertPendingToVideo()`

1. Validasi request
2. `abort_if($session->status !== IZIN_PENDING, 422)`
3. Guard replacement exists → JSON 422, pesan sama semantik `cancelPending`:
   > Sesi ini sudah punya sesi pengganti/isian slot dan tidak bisa diubah ke video dari sini.
4. Panggil `$this->attendanceService->finalizePendingAsVideo($session, $request->notes)`
5. Return JSON `{ success: true, message: '...' }`

Pesan sukses:

> Sesi {nama murid} dicatat sebagai Izin Video.

### `AttendanceService::finalizePendingAsVideo()`

Signature:

```php
public function finalizePendingAsVideo(ClassSession $session, ?string $notes = null): ClassSession
```

Logic:
1. Validasi internal: status `IZIN_PENDING`, belum ada replacement non-split
2. Merge notes:
   - Jika `$notes` tidak kosong: `{existing_notes}\n[VIDEO via Open Slot] {notes}` (trim, skip double newline berlebihan)
   - Jika kosong: pertahankan `existing_notes` apa adanya
3. Return `$this->recordAttendance($session, ['status' => IZIN_VIDEO, 'notes' => $mergedNotes])`

Honor & side effect lain **tidak** di-duplicate — semua lewat `recordAttendance()` + `calculateHonor()`.

### Yang tidak berubah

- `RescheduleService`
- `HonorCalculationService` exclude list (`IZIN_VIDEO` sudah masuk slip; `IZIN_PENDING` tetap excluded)
- Enum / migration database
- Portal guru, absensi harian (dropdown status existing sudah punya `IZIN_VIDEO` — tidak wajib ditambah entry point baru di Fase 1)

---

## Section 4 — Testing

Extend `tests/Feature/IzinPendingTest.php` (atau file sibling jika sudah terlalu panjang).

| # | Skenario | Ekspektasi |
|---|---|---|
| T1 | Admin POST video pada sesi IZIN_PENDING valid | 200 JSON success; status `IZIN_VIDEO`; `honor_code` = `H_VIDEO`; honor > 0 |
| T2 | Sesi hilang dari GET `absensi.open-slots` | Tidak muncul di collection slots |
| T3 | Status bukan IZIN_PENDING | 422 |
| T4 | Sudah ada replacement (`origin_session_id`) | 422, pesan guard |
| T5 | Role Guru / Auditor POST | 403 |
| T6 | Notes opsional tersimpan dengan prefix | `notes` contains `[VIDEO via Open Slot]` |
| T7 | `last_session_at` murid ter-update | Sesuai `session_date` |

Regression: test existing assign / schedule / cancel tetap hijau.

---

## Edge Cases

| Kasus | Penanganan |
|---|---|
| Admin salah klik Video | Koreksi manual via halaman absensi harian (ubah status) — tidak ada undo khusus |
| Sesi pending >14 hari | Tetap boleh convert |
| Paket DUO / Kids / Trial | Honor via `calculateHonor()` existing |
| Slip honor bulan ini sudah CALCULATED | Honor masuk agregasi sesuai `session_date` asli — tidak ada perubahan logic M06 |
| Dua baris buka menu Opsi bersamaan | Tutup menu baris lain saat buka menu baru (satu menu terbuka per tabel) |

---

## Task Implementasi (urutan disarankan)

Setiap task = satu commit logical (`[M04] ...`). Jalankan `php artisan test --filter=IzinPending` setelah task backend selesai.

### Task 1 — Service: `finalizePendingAsVideo`

**File:** `app/Services/AttendanceService.php`

- [ ] Tambah method `finalizePendingAsVideo()` dengan guard + merge notes
- [ ] Panggil `recordAttendance()` status `IZIN_VIDEO`
- [ ] Unit/feature test minimal di `IzinPendingTest` (happy path service via HTTP endpoint stub — bisa ditunda ke Task 3)

**Done when:** Method ada, PHPDoc Bahasa Indonesia, throw `InvalidArgumentException` jika guard gagal.

---

### Task 2 — Route + Controller action

**File:** `routes/web.php`, `app/Http/Controllers/AbsensiController.php`, optional `app/Http/Requests/ConvertPendingToVideoRequest.php`

- [ ] Route `POST absensi/open-slots/{session}/video` di group Owner|Admin
- [ ] Method `convertPendingToVideo()` dengan validasi + guard replacement
- [ ] Inject/panggil `AttendanceService::finalizePendingAsVideo()`
- [ ] Response JSON konsisten `{ success, message }` / 422

**Done when:** POST via `curl` atau test feature T1–T5 lulus (test bisa ditulis di Task 5).

---

### Task 3 — Feature tests backend

**File:** `tests/Feature/IzinPendingTest.php`

- [ ] T1–T7 dari Section 4
- [ ] Pastikan test existing tidak break

**Done when:** `php artisan test tests/Feature/IzinPendingTest.php` hijau.

---

### Task 4 — UI: refactor menu Opsi (tanpa Video dulu, optional split)

**File:** `resources/views/absensi/open-slots.blade.php`

- [ ] Ganti 3 tombol inline menjadi dropdown **Opsi ▾**
- [ ] Item: Isi Slot, Jadwalkan Pengganti, Batalkan Pending — behavior existing unchanged
- [ ] State Alpine: `menuOpen`, click-outside tutup menu
- [ ] Smoke manual: ketiga aksi masih jalan

**Done when:** UI tergrup, regression manual/t test existing lulus.

---

### Task 5 — UI: item menu Video + confirm

**File:** `resources/views/absensi/open-slots.blade.php`

- [ ] Item menu **Video** + confirm + optional notes
- [ ] `submitVideo()` → POST `route('absensi.open-slots.video')`
- [ ] Update banner penjelasan atas halaman
- [ ] Styling item destruktif vs normal

**Done when:** End-to-end Admin convert pending → video dari browser; baris hilang; flash sukses.

---

### Task 6 — Polish & dokumentasi

- [ ] Update edge case table di spec induk (`2026-05-28-...`) baris "Admin salah pilih" jika perlu cross-ref (opsional)
- [ ] `npm run build` jika ada perubahan class CSS baru di luar Tailwind utility standar
- [ ] Review aksesibilitas keyboard menu

**Done when:** Siap merge; spec ini status tetap Approved.

---

## Referensi Domain & Kode

| Sumber | Lokasi |
|---|---|
| Flow reschedule + Open Slot Board | `.understand-anything/domain-graph.json` → `flow:scheduling-attendance:reschedule-sesi` |
| Business rule izin video (BR-4.6) | `.cursor/rules/musik-kita-business-rules.mdc` — **tidak di-enforce** Fase 1 |
| Open Slot Board existing | `AbsensiController::openSlotBoard`, `assignOpenSlot`, `scheduleReplacement`, `cancelPending` |
| View existing | `resources/views/absensi/open-slots.blade.php` |
| Honor IZIN_VIDEO | `AttendanceService::calculateHonor()` → `H_VIDEO` |
| Test existing | `tests/Feature/IzinPendingTest.php` |

---

## Out of Scope (Fase 2+)

- Enforce izin ke-2+ sebelum allow Video
- Combo **Video + Isi Slot** satu klik
- Entry point convert pending→video di halaman absensi harian (service sudah reusable)
- Audit log (aksi open-slots existing belum konsisten pakai audit — harmonisasi terpisah)
