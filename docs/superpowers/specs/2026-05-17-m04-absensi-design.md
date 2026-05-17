# Spesifikasi M04 — Absensi Harian

> Status: Siap implementasi
> Tanggal: 2026-05-17
> Sumber: Brainstorming session 2026-05-17 (3 section)

---

## 1. Konteks & Masalah

Sistem saat ini sudah generate ~1.200 sesi/bulan (40 sesi/hari) via `GenerateMonthlySessions`, tetapi **tidak ada UI untuk input status sesi**. Admin masih input manual di luar sistem.

M04 mengisi gap ini: admin buka halaman "Absensi Hari Ini" dan input status setiap sesi yang sudah berlangsung.

---

## 2. Scope M04

### Fase 1 (yang diimplementasikan sekarang)
- Input dan edit status sesi harian
- 6 status yang bisa diinput: `HADIR`, `HADIR_TERLAMBAT`, `HANGUS`, `IZIN_RESCHEDULE`, `IZIN_VIDEO`, `DIGANTI`
- `LIBUR` read-only (di-set oleh generator, admin tidak bisa override)
- Edit ulang status yang sudah diinput (tidak ada hard lock kecuali LIBUR)

### Fase 2 (defer)
- Buat sesi pengganti setelah status `IZIN_RESCHEDULE` diinput
- Rapel ke bulan berikutnya
- Validasi BR-4.4 (info ≥5 jam + izin pertama bulan ini)

> **Catatan operasional:** Reschedule terjadi sangat jarang (<10x/bulan). Di Fase 1, admin cukup isi field `notes` dengan tanggal/jam sesi pengganti yang sudah disepakati secara lisan. Field `notes` sudah tersedia di tabel `sessions` — tidak butuh schema tambahan.

---

## 3. Business Rules yang Relevan

| Rule | Implementasi Fase 1 |
|------|---------------------|
| BR-4.4: Izin berhak reschedule jika info ≥5 jam + izin pertama bulan ini | Validasi defer ke Fase 2; Fase 1 cukup simpan status `IZIN_RESCHEDULE` |
| BR-4.6: Izin ke-2+ = video pengganti | Admin memilih `IZIN_VIDEO` secara manual; sistem tidak auto-enforce |
| BR-4.7: Info <5 jam atau tanpa info = HANGUS | Admin memilih `HANGUS` secara manual |
| BR-4.9: Diganti guru lain → honor ke guru pengganti | Simpan `substitute_teacher_id`; HonorCalculationService menggunakan ini |
| BR-4.10: Libur nasional → honor guru tetap dibayar penuh | Status `LIBUR` sudah di-set generator; tidak ada aksi tambahan |

---

## 4. Model Data

Tabel utama: `sessions` (ClassSession model)

```
sessions:
  id
  schedule_id
  enrollment_id
  student_id
  teacher_id
  session_date
  status (SCHEDULED|HADIR|HADIR_TERLAMBAT|IZIN_RESCHEDULE|IZIN_VIDEO|HANGUS|LIBUR|DIGANTI)
  substitute_teacher_id (nullable) — wajib diisi jika status DIGANTI
  late_minutes (nullable) — wajib diisi jika status HADIR_TERLAMBAT
  notes (nullable)
  honor_code
  honor_amount
  timestamps
```

Status awal sesi dari generator: `SCHEDULED` (atau `LIBUR` jika hari libur nasional).

---

## 5. Arsitektur

### Controller
`App\Http\Controllers\Admin\AbsensiController`

Method:
- `index(Request $request)` — tampilkan halaman absensi hari ini (default: tanggal hari ini)
- `update(Request $request, ClassSession $session)` — update status satu sesi via AJAX

### Routes
```php
// routes/web.php (middleware: auth, role:Owner|Admin)
Route::get('/admin/absensi', [AbsensiController::class, 'index'])->name('admin.absensi.index');
Route::patch('/admin/absensi/{session}', [AbsensiController::class, 'update'])->name('admin.absensi.update');
```

### Data Flow (AJAX)
```
Admin klik tombol status
  → JS fetch PATCH /admin/absensi/{session}
  → AbsensiController@update validasi + update DB
  → Return JSON { success, session_id, status, badge_html }
  → JS update baris tabel (swap tombol → badge)
```

Tidak ada full page reload. Setiap klik langsung tersimpan.

---

## 6. UI: Halaman Absensi Harian

### Header Halaman
```
Absensi Hari Ini — [Nama Hari], [Tanggal]
[Date picker untuk ganti tanggal]
[Badge: X belum diinput] [Badge: Y sudah ✓]
```

### Filter Bar
- Dropdown: Semua Guru / pilih guru tertentu
- Dropdown: Semua Jam / range jam (pagi/siang/sore)
- Dropdown: Semua Status / Belum Diinput / Sudah Diinput
- Input: Cari nama murid

### Tabel Sesi

Kolom: **Jam | Murid | Guru | Ruang | Aksi**

**Baris belum diinput** (status = SCHEDULED):
- Jam: warna gold (#D4A853), bold
- Murid, Guru, Ruang: warna normal
- Aksi: tombol HADIR + HANGUS + IZIN + VIDEO + `···`

**Baris sudah diinput** (status ≠ SCHEDULED dan ≠ LIBUR):
- Seluruh baris: opacity 70%, background sesuai status
- Aksi: badge berwarna + link "ubah" kecil

**Baris LIBUR** (status = LIBUR):
- Seluruh baris: opacity 50%, semua teks abu-abu
- Aksi: badge abu-abu `🗓 LIBUR` — tidak ada tombol

---

## 7. Pengelompokan Tombol Status

### Tombol Langsung (1 klik, langsung simpan)

| Tombol | Warna | Status DB | Kondisi |
|--------|-------|-----------|---------|
| **HADIR** | Hijau solid | `HADIR` | Murid hadir tepat waktu |
| **HANGUS** | Merah outline | `HANGUS` | No-show / tanpa kabar |
| **IZIN** | Kuning outline | `IZIN_RESCHEDULE` | Izin (reschedule defer Fase 2) |
| **VIDEO** | Biru outline | `IZIN_VIDEO` | Izin ke-2+, video pengganti |

### Tombol `···` (buka dropdown mini-modal)

| Pilihan | Status DB | Input Tambahan |
|---------|-----------|----------------|
| Terlambat | `HADIR_TERLAMBAT` | Input: berapa menit (min 1) |
| Diganti | `DIGANTI` | Select: guru pengganti (wajib pilih) |

### Status LIBUR
Set otomatis oleh generator sesi. Tidak bisa diubah oleh admin.

---

## 8. Mini-Modal: TERLAMBAT

Muncul sebagai dropdown/popover di bawah tombol `···`:

```
Andi Pratama · ADI · R2 · 10:00
─────────────────────────────────
Terlambat berapa menit?
[ 15 ] menit
[  Simpan TERLAMBAT  ] [Batal]
```

Validasi:
- Wajib diisi
- Minimal 1 menit
- Maksimal 60 menit (asumsi durasi sesi 30 menit, lebih dari itu = no-show)

---

## 9. Mini-Modal: DIGANTI

```
Andi Pratama · ADI · R2 · 10:00
─────────────────────────────────
Guru pengganti
[ — Pilih guru pengganti — ▾ ]
Honor otomatis diarahkan ke guru pengganti.
[  Simpan DIGANTI  ] [Batal]
```

Validasi:
- Wajib pilih guru pengganti (tidak boleh submit kosong)
- Dropdown berisi semua guru aktif (is_active = 1)

Efek ke honor: `substitute_teacher_id` di-set → `HonorCalculationService` otomatis pakai nilai ini saat kalkulasi honor (sudah sesuai BR-4.9).

---

## 10. Edit Ulang Status

Jika admin perlu ubah status yang sudah diinput:

- Badge menampilkan link kecil "ubah" di sebelahnya
- Klik "ubah" → baris kembali ke mode tombol (swap badge → tombol aksi)
- Admin pilih status baru → simpan ulang

Tidak ada konfirmasi popup untuk edit biasa. Konfirmasi hanya jika mengubah dari `DIGANTI` (untuk clear `substitute_teacher_id`).

**Lock rule:** Hanya status `LIBUR` yang tidak bisa diubah.

---

## 11. Warna Badge Status

| Status | Warna Badge |
|--------|-------------|
| HADIR | Hijau (#34d399) |
| HADIR_TERLAMBAT | Oranye (#fb923c) — tampilkan "+X mnt" |
| HANGUS | Merah (#f87171) |
| IZIN_RESCHEDULE | Kuning (#FBBF24) |
| IZIN_VIDEO | Biru (#60a5fa) |
| DIGANTI | Ungu (#a78bfa) — tampilkan nama guru pengganti |
| LIBUR | Abu-abu (#6b7280) |
| SCHEDULED | — (belum diinput, tampilkan tombol) |

---

## 12. Validasi Server-side

`App\Http\Requests\UpdateAbsensiRequest`

```php
rules:
  status       => required, in:HADIR,HADIR_TERLAMBAT,HANGUS,IZIN_RESCHEDULE,IZIN_VIDEO,DIGANTI
  late_minutes => required_if:status,HADIR_TERLAMBAT | integer | min:1 | max:60
  substitute_teacher_id => required_if:status,DIGANTI | exists:teachers,id
```

Validasi tambahan di controller:
- Cek `session->status !== 'LIBUR'` sebelum update (jika LIBUR, return 403)

---

## 13. Integrasi dengan Modul Lain

### M05 (Keuangan Murid)
- Status `HANGUS`, `IZIN_RESCHEDULE`, `IZIN_VIDEO` → murid tetap dihitung hadir untuk keperluan SPP
- Status `LIBUR` → sesi tidak mempengaruhi tagihan

### M06 (Honor Guru)
- `HonorCalculationService` membaca `sessions.status` dan `sessions.substitute_teacher_id`
- `DIGANTI` → honor ke `substitute_teacher_id`, bukan `teacher_id`
- `LIBUR` → honor guru utama tetap dibayar penuh (BR-4.10)
- `SCHEDULED` (belum diinput saat cut-off H-2) → dianggap HADIR untuk honor

---

## 14. Edge Cases

| Skenario | Penanganan |
|----------|-----------|
| Admin input status sesi hari ini sebelum jam sesi berlangsung | Diizinkan (tidak ada blokir berdasarkan jam) |
| Admin buka absensi hari kemarin | Bisa via date picker, semua rule sama |
| Sesi LIBUR di-klik ubah | Return 403, pesan error "Sesi libur nasional tidak bisa diubah" |
| DIGANTI tapi guru pengganti tidak dipilih | Validasi client-side dan server-side, tidak bisa submit |
| HADIR_TERLAMBAT dengan menit 0 | Validasi min:1, error "Minimal 1 menit" |
| Guru pengganti sama dengan guru utama | Diizinkan secara teknis (edge case tidak diblokir) |
| Session belum di-generate untuk hari ini | Tampilkan pesan "Belum ada sesi terjadwal hari ini" |
| Filter guru aktif di dropdown DIGANTI | Hanya tampilkan guru is_active = 1 |
| Sesi masa depan (minggu depan) | Tetap bisa diakses dan diinput via date picker |

---

## 15. Test Cases (9 Test)

### Feature Test: `tests/Feature/Admin/AbsensiControllerTest.php`

1. **test_halaman_absensi_tampil_sesi_hari_ini** — GET /admin/absensi mengembalikan daftar sesi hari ini
2. **test_input_status_hadir_berhasil** — PATCH /absensi/{session} dengan status HADIR mengubah DB
3. **test_input_status_hangus_berhasil** — status HANGUS tersimpan
4. **test_input_hadir_terlambat_butuh_late_minutes** — validasi error jika late_minutes kosong
5. **test_input_hadir_terlambat_min_1_menit** — validasi error jika late_minutes = 0
6. **test_input_diganti_butuh_substitute_teacher_id** — validasi error jika guru pengganti kosong
7. **test_status_libur_tidak_bisa_diubah** — PATCH ke sesi LIBUR return 403
8. **test_edit_ulang_status_yang_sudah_diinput** — update status dari HADIR ke HANGUS berhasil
9. **test_absensi_filter_per_tanggal** — GET /admin/absensi?date=2026-05-16 mengembalikan sesi tanggal tsb

---

## 16. File yang Perlu Dibuat/Diubah

```
app/Http/Controllers/Admin/AbsensiController.php   (baru)
app/Http/Requests/UpdateAbsensiRequest.php          (baru)
resources/views/admin/absensi/index.blade.php       (baru)
tests/Feature/Admin/AbsensiControllerTest.php       (baru)
routes/web.php                                      (tambah 2 route)
```

Tidak ada migration baru — kolom yang dibutuhkan (`status`, `late_minutes`, `substitute_teacher_id`) sudah ada di tabel `sessions`.
