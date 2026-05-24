# Session Numbering — Design Spec
**Tanggal:** 2026-05-24
**Status:** Approved
**Scope:** Fitur 1 dari 2 (Fitur 2: Split Reschedule didesain terpisah)

---

## Latar Belakang

Admin kesulitan melacak urutan sesi per murid dalam sebulan, terutama saat reschedule. Saat ini tidak ada cara cepat untuk tahu "ini sesi ke berapa Budi bulan Mei" atau "sesi reschedule ini menggantikan sesi yang mana."

---

## Tujuan

- Setiap sesi punya label urutan: **"Sesi ke-2 Bulan Mei"**
- Sesi reschedule/pengganti punya referensi ke sesi asal: **"Reschedule dari Sesi ke-2 Bulan Mei"**
- Label tampil di 3 halaman: Absensi harian, Detail Murid, Sessions list

---

## Data Model

### Dua kolom baru di tabel `class_sessions`

```sql
session_sequence   TINYINT UNSIGNED NULL
    -- Nomor urut sesi dalam bulan untuk murid ini (1–4).
    -- NULL untuk sesi LIBUR yang punya replacement_date.

origin_session_id  BIGINT UNSIGNED NULL
    -- FK → class_sessions.id, nullOnDelete.
    -- Diisi untuk sesi pengganti holiday dan sesi reschedule.
    -- NULL untuk sesi reguler biasa.
```

### Relasi di Model `ClassSession`

```php
public function originSession(): BelongsTo
{
    return $this->belongsTo(ClassSession::class, 'origin_session_id');
}

public function replacementSessions(): HasMany
{
    return $this->hasMany(ClassSession::class, 'origin_session_id');
}
```

---

## Aturan Pengisian `session_sequence`

| Tipe Sesi | session_sequence | origin_session_id |
|-----------|-----------------|-------------------|
| SCHEDULED reguler | 1–4 dari generator (urutan slot mingguan) | NULL |
| LIBUR tanpa `replacement_date` | 1–4 dari generator (honor dibayar, SPP jalan) | NULL |
| LIBUR dengan `replacement_date` | **NULL** — slot "diserahkan" ke sesi pengganti | NULL |
| Sesi pengganti holiday | Copy dari slot LIBUR asal | ID sesi LIBUR asal |
| Reschedule bulan sama | Copy dari sesi IZIN_RESCHEDULE asal | ID sesi asal |
| Reschedule rapel bulan depan | Copy dari sesi IZIN_RESCHEDULE asal | ID sesi asal |

### Contoh Konkret — Budi, jadwal Senin, Mei 2026

Senin 11 Mei adalah hari libur nasional **dengan** replacement_date (28 Mei):

```
ID 101 | Senin 4 Mei   | SCHEDULED | sequence=1 | origin=null
ID 102 | Senin 11 Mei  | LIBUR     | sequence=null | origin=null  ← ada replacement
ID 103 | Senin 18 Mei  | SCHEDULED | sequence=3 | origin=null
ID 104 | Senin 25 Mei  | SCHEDULED | sequence=4 | origin=null
ID 105 | Kamis 28 Mei  | SCHEDULED | sequence=2 | origin=102   ← pengganti, mewarisi slot 2
```

**Prinsip:** sequence mengikuti nomor slot mingguan (bukan urutan tanggal aktual). Sesi pengganti mewarisi nomor slot dari LIBUR yang digantikan.

---

## Logika Generator (`SessionGeneratorService`)

Perubahan di `generateForSchedule()`:

```
Untuk setiap jadwal mingguan (schedule):
  slotCounter = 0

  Untuk setiap occurrence dalam bulan (minggu 1–4):
    slotCounter += 1

    Jika LIBUR dengan replacement_date:
      Buat sesi LIBUR → session_sequence = null
      Simpan slotCounter sebagai reserved_slot
      Buat sesi pengganti di replacement_date:
        session_sequence  = reserved_slot
        origin_session_id = ID sesi LIBUR

    Jika LIBUR tanpa replacement_date:
      Buat sesi LIBUR → session_sequence = slotCounter

    Jika hari biasa:
      Buat sesi SCHEDULED → session_sequence = slotCounter
```

Generator sudah idempotent — tidak ada perubahan logika skip/duplicate check, hanya tambah pengisian dua kolom baru.

---

## Logika Reschedule (`RescheduleService`)

Saat `createReplacement()` membuat sesi pengganti:

```php
$replacement->session_sequence   = $originalSession->session_sequence;
$replacement->origin_session_id  = $originalSession->id;
```

Berlaku untuk reschedule bulan sama maupun rapel bulan depan — tidak ada perlakuan khusus berdasarkan bulan.

Field `notes` tetap diisi seperti sekarang (backward compatible).

---

## Label Helper

Method di model `ClassSession`:

```php
public function getSessionLabel(): string
{
    // Sesi pengganti / reschedule — ada origin
    if ($this->origin_session_id && $this->originSession) {
        $bulan = Carbon::parse($this->originSession->session_date)
                       ->translatedFormat('F Y');
        $seq   = $this->originSession->session_sequence;
        return "Reschedule dari Sesi ke-{$seq} Bulan {$bulan}";
    }

    // Sesi biasa dengan sequence (SCHEDULED atau LIBUR tanpa replacement)
    if ($this->session_sequence) {
        $bulan = Carbon::parse($this->session_date)->translatedFormat('F Y');
        return "Sesi ke-{$this->session_sequence} Bulan {$bulan}";
    }

    // LIBUR yang punya replacement — sequence null
    return '—';
}
```

Eager-load yang dibutuhkan: `->with('originSession')` di controller yang menampilkan label.

---

## UI di 3 Halaman

### 1. Halaman Absensi Harian (`/absensi`)

Label tampil **di bawah nama murid** (bukan kolom baru) — tidak mengubah lebar tabel:

```html
<td>
    <div>{{ $session->student->full_name }}</div>
    <div class="text-xs mt-0.5 ...">{{ $session->getSessionLabel() }}</div>
</td>
```

Warna label:
- Sesi reguler → gold (`text-mk-accent`)
- Sesi replacement/reschedule → biru (`text-blue-400` dalam dark, disesuaikan light mode)
- LIBUR tanpa sequence → tidak ditampilkan (atau `—` redup)

### 2. Halaman Detail Murid — Tab Sesi

Tambah kolom **"Label Sesi"** di tabel sesi per murid.

Warna badge sama dengan absensi. Sesi LIBUR dengan sequence=null tampilkan baris redup tanpa badge.

### 3. Halaman Sessions (`/sessions`)

Tambah kolom **"Label Sesi"** di tabel daftar sesi.

Controller perlu tambah `->with('originSession')` pada query yang dipakai di view ini.

---

## Edge Cases

- **Sesi CANCELLED** (murid cuti) — tidak mendapat `session_sequence`. Generator tidak membuat sesi untuk enrollment ON_LEAVE, jadi ini tidak terjadi dari generator. Jika sesi di-cancel manual, `session_sequence` tetap sesuai nilai awal saat sesi dibuat.
- **Sesi DIGANTI** (guru diganti) — status berubah via AbsensiController, bukan membuat sesi baru. `session_sequence` tetap dari nilai awal saat sesi dibuat oleh generator.
- **Minggu ke-5 reguler** (bukan replacement) — generator tidak membuat sesi minggu ke-5 kecuali sebagai replacement. Jadi tidak ada slot ke-5 yang perlu ditangani.

---

## Out of Scope

- **Split Reschedule** (1 sesi dipecah jadi 2 bagian di waktu berbeda) → fitur terpisah, akan didesain di brainstorming berikutnya. Kolom `origin_session_id` yang didesain di sini sudah menjadi fondasi untuk fitur tersebut.
- Penomoran sesi trial → tidak relevan (trial hanya 1 sesi, tidak ada urutan)
- Filter/search berdasarkan session_sequence → bisa ditambah di masa mendatang

---

## File yang Akan Diubah

| File | Perubahan |
|------|-----------|
| `database/migrations/2026_05_24_000001_add_session_sequence_to_class_sessions.php` | Migration baru: 2 kolom baru |
| `app/Models/ClassSession.php` | Relasi `originSession()`, method `getSessionLabel()` |
| `app/Services/SessionGeneratorService.php` | Isi `session_sequence` saat generate |
| `app/Services/RescheduleService.php` | Copy sequence + set `origin_session_id` |
| `app/Http/Controllers/AbsensiController.php` | Tambah `->with('originSession')` |
| `app/Http/Controllers/SessionController.php` | Tambah `->with('originSession')` |
| `resources/views/absensi/index.blade.php` | Label di bawah nama murid |
| `resources/views/sessions/index.blade.php` | Kolom Label Sesi |
| `resources/views/students/partials/tab-kelas.blade.php` | Kolom Label Sesi |
