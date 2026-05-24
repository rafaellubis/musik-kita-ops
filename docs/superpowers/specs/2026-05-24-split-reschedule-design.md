# Split Reschedule — Design Spec

**Tanggal:** 2026-05-24
**Status:** Approved

## Konteks

Saat murid izin reschedule tapi tidak ada slot 30 menit penuh yang tersedia, admin perlu opsi untuk membagi sesi menjadi dua bagian lebih pendek — 15 menit + 15 menit di tanggal/jam berbeda.

**Fondasi yang sudah ada:**
- `class_sessions.origin_session_id` (FK self-ref, nullOnDelete)
- `class_sessions.session_sequence` (nomor slot mingguan 1–4)
- `RescheduleService::createReplacement()` — buat sesi pengganti tunggal
- Mini-modal reschedule di halaman absensi (input tanggal, jam, ruang)
- Conflict detection guru & ruang

---

## Section 1: Schema & Data Model

### Kolom Baru

Satu kolom tambahan di tabel `class_sessions`:

```sql
split_part TINYINT UNSIGNED NULL
```

| Nilai | Makna |
|---|---|
| `null` | Sesi normal (tidak split) |
| `1` | Bagian pertama dari split |
| `2` | Bagian kedua dari split |

Index tambahan: `(origin_session_id, split_part)` untuk query cepat "apakah Part 2 sudah ada?".

### Relasi Data

```
Sesi IZIN_RESCHEDULE (origin — sudah ada sebelumnya)
  session_sequence: 3
  split_part:       null

  ← Part 1 (dibuat di langkah 1)
      origin_session_id: origin.id
      session_sequence:  3          ← diwarisi dari origin
      split_part:        1
      honor_code:        H_SPLIT
      honor_amount:      package.price_per_month × 0.5 / 4 / 2
      status:            SCHEDULED

  ← Part 2 (dibuat di langkah 2)
      origin_session_id: origin.id
      session_sequence:  3          ← diwarisi dari origin
      split_part:        2
      honor_code:        H_SPLIT
      honor_amount:      package.price_per_month × 0.5 / 4 / 2
      status:            SCHEDULED
```

**Durasi:** Tidak disimpan sebagai kolom. Dihitung dinamis: `package.duration_min / 2` ketika `split_part` tidak null. Untuk paket 30 menit → 15 menit per bagian.

**Honor code baru:** `H_SPLIT` ditambahkan ke daftar honor code di CLAUDE.md. Nilai = setengah dari honor normal satu sesi.

---

## Section 2: Alur UI & Service Layer

### Alur Admin (2 Langkah)

**Langkah 1 — Jadwalkan Bagian 1:**

1. Admin di halaman Absensi, melihat baris sesi `IZIN_RESCHEDULE`
2. Admin klik tombol "Reschedule" → mini-modal terbuka (form yang sudah ada)
3. Di modal ada tambahan toggle: **"Bagi menjadi 2 bagian"** (default: off)
4. Admin aktifkan toggle → modal tampilkan info: *"Durasi akan dibagi: 15 menit + 15 menit"*
5. Admin pilih tanggal, jam mulai, ruang untuk Bagian 1
6. Submit → sistem buat Part 1 (`split_part=1`, `honor_amount=½`)

**Langkah 2 — Jadwalkan Bagian 2:**

1. Baris Part 1 muncul di halaman Absensi/Sessions dengan tombol **"Tambah Bagian 2"**
2. Label baris Part 1: *"Bagian 1/2 — Reschedule dari Sesi ke-3 Bulan Mei 2026"*
3. Admin klik "Tambah Bagian 2" → mini-modal kedua terbuka (tanggal, jam, ruang saja)
4. Submit → sistem buat Part 2 (`split_part=2`, `honor_amount=½`)
5. Tombol "Tambah Bagian 2" hilang dari baris Part 1 (Part 2 sudah ada)

### Service Layer

**Method baru:** `RescheduleService::createSplitPart(ClassSession $original, Carbon $date, string $startTime, ?int $roomId, int $part): ClassSession`

- Method ini TERPISAH dari `createReplacement()` yang sudah berjalan — tidak ada perubahan pada alur reschedule normal
- Kalkulasi honor: `HonorCalculationService` (atau inline) → `package.price_per_month * 0.5 / 4 / 2`
- Set `session_sequence` dari `$original->session_sequence`
- Set `origin_session_id` dari `$original->id`
- Jalankan conflict detection guru & ruang (sama dengan reschedule normal)

### Routes Baru

```
POST /reschedule/{session}/split/part-1   → AbsensiController@storeSplitPart1
POST /reschedule/{session}/split/part-2   → AbsensiController@storeSplitPart2
```

Atau alternatif single route dengan parameter:
```
POST /reschedule/{session}/split/{part}   → AbsensiController@storeSplitPart
```

Kedua opsi valid — pilih saat implementasi berdasarkan kode yang lebih bersih.

### Label Sesi (`getSessionLabel()`)

Tambahan kondisi di method yang sudah ada di `ClassSession`:

```php
if ($this->split_part && $this->origin_session_id && $this->originSession) {
    $bulan = Carbon::parse($this->originSession->session_date)
                   ->locale('id')->translatedFormat('F Y');
    $seq   = $this->session_sequence;
    return "Bagian {$this->split_part}/2 — Reschedule dari Sesi ke-{$seq} Bulan {$bulan}";
}
```

Kondisi ini diperiksa SEBELUM kondisi `origin_session_id` yang sudah ada (agar split label lebih spesifik dari label reschedule biasa).

---

## Section 3: Validasi, Error Handling & Testing

### Validasi & Guard

| Kondisi | Respons |
|---|---|
| Split diajukan pada sesi bukan `IZIN_RESCHEDULE` | 422 — "Hanya sesi Izin Reschedule yang bisa dibagi" |
| Buat Part 1 tapi origin sudah punya Part 1 (ada sesi lain dengan `origin_session_id = origin.id` dan `split_part = 1`) | 422 — "Bagian 1 sudah terjadwal untuk sesi ini" |
| Tambah Part 2 tapi Part 1 belum ada | 422 — "Bagian 1 belum dijadwalkan" |
| Tambah Part 2 tapi Part 2 sudah ada | 422 — "Bagian 2 sudah terjadwal" |
| Konflik guru di waktu yang dipilih | 422 — tampilkan pesan konflik (sama dengan reschedule normal) |
| Konflik ruang di waktu yang dipilih | 422 — tampilkan pesan konflik |

### Edge Cases

- **Absensi Part 1 dan Part 2 independen** — masing-masing bisa HADIR/HANGUS/dll secara terpisah, tidak ada dependency
- **Honor cut-off H-2** — tidak memblokir pembuatan sesi split; honor slip yang sudah PAID tidak diubah
- **Conflict detection** — menggunakan logika yang sama dengan `RescheduleService::createReplacement()` (cek `ClassSession` konkret, bukan jadwal mingguan)

### Testing

**Unit tests (`SplitRescheduleTest`):**
- Membuat Part 1 menghasilkan sesi dengan `split_part=1`, `honor_amount` = ½ normal
- Membuat Part 2 menghasilkan sesi dengan `split_part=2`, `honor_amount` = ½ normal
- Total honor Part 1 + Part 2 = honor normal satu sesi
- Guard: tidak bisa split sesi non-IZIN_RESCHEDULE
- Guard: tidak bisa split sesi yang sudah punya `split_part`
- Guard: Part 2 gagal jika Part 1 belum ada
- Guard: Part 2 gagal jika Part 2 sudah ada

**Integration tests:**
- Konflik guru terdeteksi saat buat Part 1
- Konflik ruang terdeteksi saat buat Part 2
- Label `getSessionLabel()` untuk `split_part=1` dan `split_part=2`

### Yang Tidak Berubah

- Alur reschedule normal (`createReplacement()`) tidak tersentuh
- `SessionGeneratorService` tidak diubah — split hanya terjadi manual via admin
- Struktur invoice & slip honor tidak berubah — `honor_amount` sudah tersimpan per sesi

---

## Files yang Akan Diubah

| File | Aksi |
|---|---|
| `database/migrations/2026_05_24_XXXXXX_add_split_part_to_class_sessions.php` | Create |
| `app/Models/ClassSession.php` | Modify — tambah `split_part` ke `$fillable`, `$casts`, dan logika `getSessionLabel()` |
| `app/Services/RescheduleService.php` | Modify — tambah `createSplitPart()` |
| `app/Http/Controllers/AbsensiController.php` | Modify — tambah action storeSplitPart |
| `app/Http/Requests/StoreSplitPartRequest.php` | Create |
| `resources/views/absensi/_reschedule_modal.blade.php` | Modify — tambah toggle split |
| `resources/views/absensi/_row.blade.php` | Modify — tambah tombol "Tambah Bagian 2" |
| `resources/views/absensi/_split_part2_modal.blade.php` | Create — modal khusus Part 2 |
| `routes/web.php` | Modify — tambah route split |
| `tests/Feature/SplitRescheduleTest.php` | Create |
| `CLAUDE.md` | Modify — tambah `H_SPLIT` ke daftar honor code |