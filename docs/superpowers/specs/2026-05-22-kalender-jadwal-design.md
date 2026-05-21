# Design Spec: Kalender Jadwal Mingguan

**Tanggal:** 2026-05-22
**Modul:** Kalender (fitur baru, di luar modul M01–M09)
**Status:** Approved — siap implementasi

---

## Ringkasan

Halaman kalender read-only yang menampilkan seluruh sesi konkret dalam satu minggu sebagai grid hari × jam. Tujuan utama: membantu Admin dan Owner melihat distribusi jadwal, status sesi, dan konflik ruangan/guru secara visual dalam satu pandangan.

---

## Keputusan Desain

| Aspek | Keputusan |
|---|---|
| Tipe tampilan | Grid mingguan (kolom = Senin–Sabtu, baris = slot jam) |
| Sumber data | `class_sessions` — sesi konkret per tanggal, bukan jadwal template |
| Interaksi | Read-only. Klik event → popup detail + shortcut link |
| Warna event | Per status sesi (Terjadwal/Hadir/Izin/Hangus/Libur) |
| Navigasi minggu | Full page reload via query param `?week=YYYY-MM-DD` |
| Filter | Per guru dan per ruangan via dropdown, persisten di navigasi |
| Implementasi | Full server-render (Blade), tidak ada AJAX/Livewire |

---

## Routing & Controller

**Route:**
```
GET /kalender  →  KalenderController@index
Middleware: auth, role:Owner|Admin|Auditor
```

**Query params:**
- `week` — tanggal Senin minggu yang ditampilkan (default: Senin minggu ini)
- `teacher_id` — filter per guru (opsional)
- `room_id` — filter per ruangan (opsional)

**Controller baru:** `App\Http\Controllers\KalenderController`

Langkah-langkah di `index()`:
1. Parse `week` → Carbon Senin, hitung Sabtu (+5 hari)
2. Query `ClassSession::whereBetween('session_date', [$weekStart, $weekEnd])` dengan eager load:
   `enrollment.student`, `enrollment.teacher`, `enrollment.package.instrument`, `schedule`
3. Apply filter `teacher_id` dan `room_id` jika ada
4. Group hasil per `day_of_week` lalu per `start_time`
5. Pass ke view: `$grid`, `$weekStart`, `$weekEnd`, `$teachers` (untuk dropdown), `$rooms` (untuk dropdown)

---

## Struktur View

**File:** `resources/views/kalender/index.blade.php`

### Week Navigator
```
[← Minggu Lalu]   Senin 12 – Sabtu 17 Mei 2026   [Minggu Ini]   [Minggu Depan →]
```
- Setiap tombol adalah `<a href="?week=...&teacher_id=...&room_id=...">` (full page reload)
- Filter tetap disertakan di semua link navigator agar tidak hilang saat ganti minggu

### Filter Bar
- Dropdown `Semua Guru ▾` dan `Semua Ruangan ▾`
- Submit otomatis via Alpine `@change="$el.form.submit()"`
- Mempertahankan `week` aktif saat filter berubah

### Grid
- **Kolom:** label jam (kiri) + Senin, Selasa, Rabu, Kamis, Jumat, Sabtu
- **Baris:** hanya slot jam yang memiliki minimal 1 sesi di minggu tersebut (dinamis, bukan hardcode 08:00–20:00)
- **Interval:** 30 menit, berdasarkan `start_time` sesi aktual

### Event Cell
Setiap sesi ditampilkan sebagai blok kecil di sel yang sesuai:
```
[background warna status]
Piano · ADI
R2 · 09:00
```
Klik event → trigger Alpine popup.

**Warna per status:**
| Status | Warna |
|---|---|
| SCHEDULED | Abu-abu (belum diisi) |
| HADIR / HADIR_TERLAMBAT | Hijau |
| IZIN_RESCHEDULE / IZIN_VIDEO | Kuning |
| HANGUS | Merah |
| LIBUR / DIGANTI | Abu-abu muda + teks dicoret |

### Popup Detail (Alpine)
Modal overlay kecil muncul saat event diklik, berisi:
- Nama murid lengkap + kode (`M-2026-0012`)
- Guru · Ruangan · Jam mulai–selesai
- Status sesi (badge berwarna)
- Paket / instrumen
- Tombol: **"Detail Murid"** → `route('students.show', $studentId)`
- Tombol: **"Catat Absensi"** → `route('sessions.index')` — hanya tampil jika status `SCHEDULED`

Popup ditutup dengan klik di luar overlay atau tombol ×.

---

## Edge Cases

| Kondisi | Tampilan |
|---|---|
| Minggu belum ada sesi di-generate | Banner info: "Sesi belum di-generate untuk minggu ini. Generator otomatis jalan tanggal 25." |
| Filter aktif tapi tidak ada hasil | Grid kosong + teks: "Tidak ada sesi untuk filter ini minggu ini." |
| Sesi tanpa `schedule` (data legacy) | Skip dari grid — `start_time` ada di `schedules`, bukan di `sessions` langsung |
| Popup — murid sudah berstatus Mundur | Tampil data historis, tombol "Catat Absensi" tidak muncul |
| Navigasi ke minggu lampau/jauh ke depan | Tidak ada batasan — bebas maju/mundur untuk keperluan audit |

---

## Navigasi Sidebar

Tambahkan menu baru di sidebar: **Kalender Jadwal** (ikon kalender), accessible oleh Owner, Admin, Auditor.

---

## Yang Tidak Ada di Scope Ini

- Edit jadwal dari kalender (gunakan halaman enrollment/schedule yang ada)
- Drag & drop sesi
- Print/export kalender
- View per guru atau per ruangan (bisa jadi enhancement nanti)
- Notifikasi konflik (konflik sudah dicegah di ScheduleController saat input)
