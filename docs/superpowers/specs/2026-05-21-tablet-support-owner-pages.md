# Spec: Tablet Support — Owner Pages (Opsi A)

**Tanggal:** 2026-05-21
**Konteks:** Owner studio akan sering menggunakan tablet (resolusi 1340×800px) sebagai perangkat operasional. Admin selalu menggunakan PC.
**Scope:** Perbaikan responsivitas hanya pada halaman yang dipakai Owner.

---

## Latar Belakang

Audit UI menunjukkan bahwa layout utama (sidebar, topbar, grid dashboard) sudah responsif untuk tablet landscape (1340px = zona `lg:`). Masalah muncul di portrait 800px (zona `md:`) — beberapa tabel lebar tidak memiliki `overflow-x-auto` sehingga konten terpotong atau menggeser layout.

**Target device:** Tablet portrait 800px (`md:` breakpoint Tailwind = 768px+).

---

## Yang Sudah Bekerja (Tidak Diubah)

- Sidebar: collapse di `< lg:` (hamburger), fixed di `lg:+` — sudah benar
- Dashboard grid: `grid-cols-2 lg:grid-cols-4` — sudah responsif
- Invoice index/show: sudah ada `overflow-x-auto`
- Sessions index: sudah ada `overflow-x-auto`
- Halaman print (honors/print, invoices/print): by-design fixed, tidak diubah

---

## Perubahan yang Diperlukan

### Prinsip
Tidak ada perubahan layout, logika, controller, atau JavaScript. Semua perubahan adalah penambahan CSS class Tailwind yang sudah ada di project.

---

### 1. `honors/index.blade.php`

**Masalah:** Tabel 8 kolom (Guru, Bulan, Honor Pokok, Transport, Lain-lain, Total, Status, Aksi) tanpa scroll horizontal.

**Fix:** Tambah `<div class="overflow-x-auto">` membungkus `<table>` di dalam container yang ada.

```blade
{{-- Sebelum --}}
<div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <table class="min-w-full ...">

{{-- Sesudah --}}
<div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full ...">
    </div>
```

---

### 2. `honors/show.blade.php`

**Masalah:** 2 tabel di halaman detail slip honor tanpa scroll horizontal.
- Tabel komponen honor (4 kolom)
- Tabel riwayat sesi (5 kolom: Tanggal, Murid, Status, Kode, Honor)

**Fix:** Bungkus masing-masing tabel dengan `<div class="overflow-x-auto">`.

---

### 3. `reports/finance.blade.php`

**Masalah:** 3 tabel laporan keuangan tanpa scroll horizontal.
- Tabel honor guru (5 kolom)
- Tabel pengeluaran per kategori (4 kolom)
- Tabel lainnya jika ada

**Fix:** Bungkus setiap `<table>` dengan `<div class="overflow-x-auto">`.

---

### 4. `reports/students.blade.php`

**Masalah:** Grid stat cards pakai `sm:grid-cols-4` — di 800px (masuk `sm:`) langsung 4 kolom, terlalu rapat.

**Fix:** Tambah breakpoint `md:` agar 3 kolom dulu di tablet, baru 4 kolom di desktop.

```blade
{{-- Sebelum --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">

{{-- Sesudah --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
```

---

### 5. `audit-logs/index.blade.php`

**Masalah:** Tabel 6 kolom (Waktu, User, Aksi, Entitas, Label, Catatan) tanpa scroll horizontal — sangat lebar.

**Fix:** Tambah `<div class="overflow-x-auto">` membungkus `<table>`.

---

### 6. `students/index.blade.php`

**Masalah:** Tabel 9 kolom tanpa scroll horizontal. Meski ini halaman Admin, Owner juga bisa membuka halaman ini di tablet.

**Fix:** Tambah `<div class="overflow-x-auto">` membungkus `<table>`.

---

## Yang TIDAK Diubah

| File | Alasan |
|---|---|
| `resources/views/layouts/app.blade.php` | Sidebar behavior sudah benar |
| `resources/views/absensi/` | Admin-only, selalu di PC |
| `resources/views/students/_form.blade.php` | Sudah responsive (`md:grid-cols-2`) |
| `resources/views/honors/print.blade.php` | By-design untuk cetak A4 |
| `resources/views/invoices/print.blade.php` | By-design untuk cetak A4 |
| Semua controller, model, route | Tidak ada perubahan logika |

---

## Verifikasi Manual

Setelah implementasi, verifikasi di browser dengan DevTools:

1. Buka DevTools → Responsive mode → set 800×1024px
2. Cek halaman berikut satu per satu:
   - `/honors` — tabel slip honor bisa di-scroll horizontal
   - `/honors/{id}` — tabel detail bisa di-scroll
   - `/reports/finance` — tabel laporan bisa di-scroll
   - `/reports/students` — grid stat 3 kolom di 800px, bukan 4
   - `/audit-logs` — tabel bisa di-scroll
   - `/students` — tabel bisa di-scroll
3. Set ke 1340×800px (landscape) — pastikan sidebar tetap muncul dan layout tidak berubah

---

## Estimasi

- File yang diubah: 6 file Blade
- Total perubahan: ~15-20 baris (div wrapper + 1 class change)
- Risiko regresi: Sangat rendah — hanya menambah wrapper, tidak mengubah yang ada
- Tidak perlu `npm run build` — tidak ada perubahan CSS/Tailwind class baru
