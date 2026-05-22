# Spec: Redesign Slip Honor Guru — Rincian Per Murid

**Tanggal:** 2026-05-23
**Status:** Disetujui — siap implementasi

---

## Latar Belakang

Slip honor guru saat ini menampilkan rincian honor berdasarkan **kode honor** (H_REG, H_KIDS, dll).
Permintaan: ganti dengan rincian **per murid** — guru dapat melihat nama murid, instrumen, jumlah sesi, dan nominal yang dihasilkan dari tiap murid.

---

## Keputusan Desain

| # | Keputusan | Pilihan yang ditolak |
|---|-----------|----------------------|
| 1 | Tabel per-murid **menggantikan** tabel per-kategori (bukan ditambah di atasnya) | Tampilkan dua tabel sekaligus |
| 2 | Tabel per-murid di atas, komponen honor + total di bawah | Total di atas |
| 3 | Total honor tanpa box — baris bold biasa, font sama dengan baris di atasnya | Navy blue box |
| 4 | Info bank (nama bank, nomor rekening, nama pemilik) pindah ke header, italic kecil | Bank tetap di bawah sebelum tanda tangan |
| 5 | Kids Class: **tiap murid terdaftar = 1 baris**, honor = sesi terlaksana × Rp 42.500 (bukan per kehadiran) | Kids Class digabung 1 baris |
| 6 | Pimpinan Studio hardcode: **"Charly Nurjaya, S.MG"** | Ambil dari config/user |

---

## Perubahan Database

### Migration baru: `add_bank_account_holder_to_teachers`

```php
$table->string('bank_account_holder', 100)->nullable()->after('bank_account');
```

Kolom baru ini menyimpan nama pemilik rekening bank guru (contoh: "Daniel Kurniawan").
Nullable — guru lama yang belum diisi tetap valid.

---

## Perubahan File

### 1. Migration baru
**File:** `database/migrations/YYYY_MM_DD_add_bank_account_holder_to_teachers.php`
- Tambah kolom `bank_account_holder` (string 100, nullable) ke tabel `teachers`

### 2. `app/Models/Teacher.php`
- Tambah `'bank_account_holder'` ke array `$fillable`

### 3. `resources/views/teachers/_form.blade.php`
- Tambah field input `bank_account_holder` di seksi informasi bank (setelah `bank_account`)
- Label: "Nama Pemilik Rekening"
- `maxlength="100"`, nullable

### 4. `app/Services/HonorCalculationService.php`
- Tambah method baru: `getStudentBreakdown(HonorSlip $slip): Collection`
- Method ini mengolah hasil `getSessionBreakdown()` menjadi ringkasan per murid

**Logika `getStudentBreakdown()`:**

```
Untuk sesi PRIVAT (H_REG, H_TRIAL, H_VIDEO, H_HANGUS, H_LIBUR, H_PENG):
  - Group by student_id
  - Setiap murid: full_name, instrumen (dari enrollment.package.instrument.name), 
    jumlah sesi (count), total honor (sum honor_amount)

Untuk sesi KIDS CLASS (H_KIDS):
  - Ambil semua class_session Kids Class guru ini bulan ini
  - Dari enrollment_id pada tiap sesi, ambil semua enrollment aktif dalam grup yang sama
    (enrollment dengan schedule yang sama / teacher yang sama / bulan yang sama)
  - Jumlah sesi Kids Class = count distinct session_date dalam bulan ini
  - Tiap murid terdaftar mendapat: sesi_terlaksana × Rp 42.500
  - BUKAN berdasarkan kehadiran murid
```

**Struktur output per baris:**
```php
[
    'student_id'   => int,
    'student_name' => string,        // full_name
    'instrument'   => string,        // nama instrumen atau 'Kids Class'
    'session_count'=> int,           // jumlah sesi
    'total_amount' => int,           // total honor dari murid ini
    'is_kids'      => bool,          // untuk catatan kaki di view
]
```

**Urutan:** Privat terlebih dahulu (urut nama), lalu Kids Class (urut nama).

### 5. `app/Http/Controllers/HonorController.php` — method `print()`

Ganti pemanggilan dari:
```php
$breakdown = $sessions->groupBy('honor_code')->map(...)
```
Menjadi:
```php
$studentBreakdown = $this->service->getStudentBreakdown($honor);
$hasKids = $studentBreakdown->where('is_kids', true)->isNotEmpty();
```

Pass ke view: `$studentBreakdown`, `$hasKids`

### 6. `resources/views/honors/print.blade.php` — desain ulang penuh

#### Struktur layout (atas ke bawah):

```
[HEADER]
  Kiri:  Logo "🎵 MUSIK KITA"
         "Slip Honor Guru" (subtitle kecil)
         [Bank info italic kecil]
           {bank_name} · {bank_account}
           a.n. {bank_account_holder}   ← tampil jika ada
  Kanan: "No. Slip" + slip_number
         badge status

[INFO GURU]
  Grid 2 kolom: Nama Guru | Periode | Instrumen | Tanggal Cetak

[RINCIAN SESI PER MURID]
  Heading: "Rincian Sesi per Murid"
  Tabel kolom: Nama Murid | Instrumen | Sesi | Jumlah (Rp)
  Baris privat: normal
  Baris Kids Class: background #fffbf0 (kuning muda)
  Footer tabel: "Subtotal Honor Pokok" | total sesi | total amount
  Catatan kaki (tampil jika $hasKids):
    "* Kids Class: honor per murid = jumlah sesi × Rp 42.500
       (dihitung dari murid terdaftar, bukan kehadiran)"

[KOMPONEN HONOR]
  Heading: "Komponen Honor"
  Tabel: Honor Pokok | [jumlah sesi] | nominal
         Honor Transport | "Input manual" | nominal
         Honor Event | catatan | nominal      ← tampil jika > 0
         Honor Lain-lain | catatan | nominal
  Baris total (border-top 2px, bold, font sama 11px/12px):
    "TOTAL HONOR YANG DITERIMA" | Rp {total_honor}

[TANDA TANGAN]
  Kiri:  "Penerima Honor" + garis + {teacher.name}
  Kanan: "Pimpinan Studio" + garis + "Charly Nurjaya, S.MG"

[FOOTER]
  Jika PAID: "Dibayarkan: {paid_at} — {paidBy.name}"
```

#### Catatan tambahan view:
- Tabel per-kategori (H_REG, H_KIDS, dll) **dihapus sepenuhnya** dari print view
- Variabel `$breakdown` tidak lagi dipakai di print view (tetap dipakai di `show.blade.php`)
- `show.blade.php` tidak berubah (tetap tampilkan breakdown per kategori untuk internal admin)

---

## Yang TIDAK Berubah

- `show.blade.php` — halaman detail internal tetap tampilkan breakdown per honor_code
- `HonorCalculationService::getSessionBreakdown()` — method existing tidak diubah, method baru `getStudentBreakdown()` memanggil ini
- Logika kalkulasi honor (`calculateForTeacher`) — tidak berubah
- `base_honor` di database — tidak berubah, masih agregat total

---

## Test Cases

| # | Skenario | Ekspektasi |
|---|----------|------------|
| T1 | Guru privat dengan 15 murid, masing-masing 4 sesi | 15 baris di tabel, total sesi = 60 |
| T2 | Guru Kids Class dengan 4 murid, 4 sesi terlaksana | 4 baris, tiap baris sesi=4, jumlah=170.000 |
| T3 | Murid Kids Class yang absen 1 sesi | Tetap sesi=4, jumlah=170.000 (honor dari murid terdaftar) |
| T4 | Guru campuran privat + Kids Class | Privat dulu (urut nama) lalu Kids Class, catatan kaki muncul |
| T5 | Guru tanpa `bank_account_holder` | Info bank hanya tampil bank_name + bank_account, baris a.n. tidak muncul |
| T6 | Guru dengan `bank_account_holder` diisi | Tampil lengkap 3 baris info bank di header |
| T7 | Total: Honor Pokok + Transport + Lain-lain | Semua font 11px, baris total hanya bold + border-top tebal |

---

## Catatan Implementasi

- **Urutan baris Kids Class**: tiap murid dapat satu baris, meski honor tidak bergantung kehadiran individual. Ini untuk transparansi — guru tahu siapa saja murid yang membentuk honornya.
- **Jika guru tidak punya data bank sama sekali**: blok info bank di header tidak ditampilkan.
- **`show.blade.php` tidak diubah**: breakdown per-kategori tetap berguna untuk admin saat review sebelum tandai dibayar.
