# Design Spec: Dashboard Admin — Simplifikasi & Daftar Absensi Hari Ini

**Tanggal:** 2026-05-26
**Scope:** Role Admin saja (Owner dan Auditor tidak berubah)
**Status:** Disetujui

---

## 1. Latar Belakang

Dashboard saat ini menampilkan widget keuangan (Saldo Kas, Aging Piutang, Slip Honor) yang
relevan untuk Owner tetapi tidak untuk Admin. Admin adalah operator harian yang fokus pada
absensi dan tagihan murid — bukan laporan keuangan internal studio. Penyederhanaan ini
membantu Admin langsung melihat informasi yang paling dibutuhkan saat membuka sistem.

---

## 2. Perubahan yang Diusulkan

### 2.1 Widget yang Dihapus (Admin only)

| Widget | Lokasi Saat Ini | Alasan Dihapus |
|--------|-----------------|----------------|
| KPI Saldo Kas | Baris 1 | Informasi keuangan kas — domain Owner |
| KPI Murid Aktif | Baris 1 | Dipindah konteksnya; tidak perlu di KPI row |
| Aging Piutang | Baris 3 (kanan) | Agregat keuangan — domain Owner |
| Slip Honor Belum Dibayarkan | Baris 5 | Payroll — domain Owner |

Karena kedua KPI Baris 1 dihapus, **Baris 1 tidak dirender sama sekali untuk Admin.**

### 2.2 Bar Chart Absensi — Perubahan Layout

Baris 3 saat ini: `[Bar Chart Absensi] [Aging Piutang]` (2 kolom)

Setelah perubahan untuk Admin: Bar Chart Absensi menjadi **full width** (1 kolom penuh)
karena pasangannya (Aging Piutang) dihapus.

### 2.3 Statistik Murid → Daftar Absensi Hari Ini

Widget "Statistik Murid" (Baris 4, kolom kiri) diganti dengan "Daftar Absensi Hari Ini".

**Spesifikasi widget baru:**
- **Judul:** Daftar Absensi Hari Ini
- **Link header:** `→ Halaman Absensi` (route `absensi.index`)
- **Data:** `ClassSession` dengan kondisi:
  - `session_date = today()` (format string: `now()->toDateString()`)
  - `status = 'SCHEDULED'`
- **Relasi yang di-eager load:** `student`, `teacher`, `schedule.room`
- **Sorting:** `schedules.start_time` ASC (join ke tabel `schedules`)
- **Kolom tabel:**

| Kolom | Sumber Data | Format |
|-------|-------------|--------|
| Jam | `schedules.start_time` | `H:i` (contoh: `09:00`) |
| Murid | `students.full_name` | Link ke `students.show` |
| Guru | `teachers.name` | Teks biasa |
| Ruangan | `rooms.code` atau `rooms.name` | Teks biasa |
| Status | Hardcoded | Badge "Belum Diabsen" (warna abu/kuning) |

- **Empty state:** "Tidak ada sesi yang perlu diabsen hari ini." + ikon kalender
- **Batas baris:** Tidak dibatasi (tampil semua SCHEDULED hari ini)

### 2.4 Hapus Kolom "Sisa" dari Tagihan Belum Lunas

Tabel "Tagihan Belum Lunas" (Baris 4, kolom kanan) saat ini memiliki 3 kolom:
`Murid | Sisa | Jatuh Tempo`

Setelah perubahan (Admin only): kolom `Sisa` dihapus.
Kolom tersisa: `Murid | Jatuh Tempo`

Data query dan batas 10 baris tidak berubah.

---

## 3. Layout Admin Setelah Perubahan

```
┌─────────────────────────────────────────────────────────┐
│  Baris 1: (tidak ada — KPI row dihapus untuk Admin)     │
├─────────────────────────────────────────────────────────┤
│  Baris 2: (Owner only — tidak berubah)                  │
├─────────────────────────────────────────────────────────┤
│  Baris 3: Bar Chart Absensi Bulan Ini (full width)      │
├────────────────────────────┬────────────────────────────┤
│  Baris 4 kiri:             │  Baris 4 kanan:            │
│  Daftar Absensi Hari Ini   │  Tagihan Belum Lunas       │
│  (Jam·Murid·Guru·Ruangan·  │  (Murid | Jatuh Tempo)     │
│   Status)                  │  tanpa kolom Sisa          │
├─────────────────────────────────────────────────────────┤
│  Baris 5: (tidak ada — Honor row dihapus untuk Admin)   │
└─────────────────────────────────────────────────────────┘
```

---

## 4. Perubahan Kode

### 4.1 DashboardController.php

Tambah variabel:
```php
$isAdmin = auth()->user()->hasRole('Admin');
```

Tambah query untuk widget baru:
```php
$absensiHariIni = [];
if ($isAdmin) {
    $absensiHariIni = ClassSession::with(['student', 'teacher', 'schedule.room'])
        ->join('schedules', 'class_sessions.schedule_id', '=', 'schedules.id')
        ->where('class_sessions.session_date', now()->toDateString())
        ->where('class_sessions.status', 'SCHEDULED')
        ->orderBy('schedules.start_time')
        ->select('class_sessions.*')
        ->get();
}
```

Pass ke view via `compact(... 'isAdmin', 'absensiHariIni' ...)`.

Variabel keuangan yang sudah ada (`saldoKas`, `kasmasukTotal`, `kaskeluarTotal`,
`aging`, `agingCount`, `totalPiutang`, `honorBelumBayar`) tetap dihitung untuk semua
role — tidak ada perubahan query yang ada, hanya tampilan yang di-conditional.

### 4.2 dashboard.blade.php

- Baris 1: Bungkus seluruh div grid KPI dengan `@if(!$isAdmin) ... @endif`
- Baris 3: Ubah grid dari `lg:grid-cols-2` menjadi conditional:
  - Admin: `grid-cols-1` (full width)
  - Non-Admin: `lg:grid-cols-2` (seperti sekarang)
- Baris 4 kiri: Ganti konten Statistik Murid dengan Daftar Absensi Hari Ini,
  dibungkus `@if($isAdmin) ... @else ... @endif`
- Tabel Tagihan kolom Sisa: Bungkus `<th>` dan `<td>` Sisa dengan `@if(!$isAdmin)`
- Baris 5: Bungkus seluruh div Honor dengan `@if(!$isAdmin) ... @endif`

---

## 5. Yang Tidak Berubah

- Layout Owner: tidak ada perubahan sama sekali
- Layout Auditor: tidak ada perubahan sama sekali
- Bar Chart Absensi data/query: tidak berubah
- Tagihan Belum Lunas data/query: tidak berubah
- Semua route dan permission: tidak berubah

---

## 6. Catatan Teknis

- `session_date` di ClassSession tidak punya cast 'date' (lihat CLAUDE.md) —
  gunakan `now()->toDateString()` untuk perbandingan string aman.
- Kolom `start_time` ada di tabel `schedules`, bukan `class_sessions` —
  wajib join untuk sorting.
- Jika sesi Kids Class tidak punya `schedule_id` yang valid, `schedule.room`
  bisa null — tampilkan `—` sebagai fallback.
