# Tablet Support — Owner Pages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah `overflow-x-auto` wrapper di 6 file Blade agar tabel-tabel yang dipakai Owner bisa di-scroll horizontal di tablet portrait 800px, plus perbaiki grid breakpoint di halaman laporan murid.

**Architecture:** Semua perubahan adalah murni HTML class Tailwind — tidak ada perubahan logika PHP, controller, model, atau route. Pattern yang dipakai: tambah `<div class="overflow-x-auto">` membungkus `<table>` di dalam container yang sudah ada. Class `overflow-x-auto` sudah ada di compiled CSS (dipakai di invoices/index dan sessions/index), jadi tidak perlu `npm run build`.

**Tech Stack:** Laravel 11 Blade templates, Tailwind CSS 3.x

---

## File Map

| File | Baris | Perubahan |
|---|---|---|
| `resources/views/honors/index.blade.php` | 137 | Tambah wrapper `overflow-x-auto` di `<table>` |
| `resources/views/honors/show.blade.php` | 170, 206 | Tambah wrapper `overflow-x-auto` di 2 `<table>` |
| `resources/views/reports/finance.blade.php` | 138, 194 | Tambah wrapper `overflow-x-auto` di 2 `<table>` |
| `resources/views/reports/students.blade.php` | 32, 56, 105 | Ubah breakpoint grid + tambah wrapper di 2 `<table>` |
| `resources/views/audit-logs/index.blade.php` | 73 | Tambah wrapper `overflow-x-auto` di `<table>` |
| `resources/views/students/index.blade.php` | 107 | Tambah wrapper `overflow-x-auto` di `<table>` |

---

## Task 1: honors/index.blade.php

**File:** `resources/views/honors/index.blade.php`

Tabel 8 kolom (Guru, Bulan, Honor Pokok, Transport, Lain-lain, Total, Status, Aksi) tidak bisa di-scroll di 800px.

- [ ] **Buka file dan temukan tabel di sekitar baris 137**

  Cari blok berikut:
  ```blade
  @else
      <table class="w-full text-sm">
  ```

- [ ] **Bungkus `<table>` dengan div `overflow-x-auto`**

  Ubah dari:
  ```blade
  @else
      <table class="w-full text-sm">
  ```
  Menjadi:
  ```blade
  @else
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
  ```

  Dan temukan `</table>` penutupnya (sekitar baris 210), ubah dari:
  ```blade
      </table>
  @endif
  ```
  Menjadi:
  ```blade
      </table>
      </div>
  @endif
  ```

- [ ] **Commit**

  ```bash
  git add resources/views/honors/index.blade.php
  git commit -m "M06: Tambah overflow-x-auto di tabel slip honor index (tablet support)"
  ```

---

## Task 2: honors/show.blade.php

**File:** `resources/views/honors/show.blade.php`

Dua tabel di halaman detail slip honor: tabel komponen honor (baris ~170) dan tabel riwayat sesi (baris ~206).

- [ ] **Bungkus tabel pertama (ringkasan komponen honor, sekitar baris 170)**

  Cari:
  ```blade
  {{-- Tabel ringkasan per kode --}}
  <table class="w-full text-sm mb-6">
  ```
  Ubah menjadi:
  ```blade
  {{-- Tabel ringkasan per kode --}}
  <div class="overflow-x-auto">
  <table class="w-full text-sm mb-6">
  ```
  Temukan `</table>` pertama (sekitar baris 199), tambah `</div>` setelahnya:
  ```blade
  </table>
  </div>
  ```

- [ ] **Bungkus tabel kedua (riwayat sesi, sekitar baris 206)**

  Cari:
  ```blade
  <table class="w-full text-xs mt-2">
  ```
  Ubah menjadi:
  ```blade
  <div class="overflow-x-auto">
  <table class="w-full text-xs mt-2">
  ```
  Temukan `</table>` kedua (sekitar baris 234), tambah `</div>` setelahnya:
  ```blade
  </table>
  </div>
  ```

- [ ] **Commit**

  ```bash
  git add resources/views/honors/show.blade.php
  git commit -m "M06: Tambah overflow-x-auto di tabel detail slip honor (tablet support)"
  ```

---

## Task 3: reports/finance.blade.php

**File:** `resources/views/reports/finance.blade.php`

Dua tabel laporan keuangan: tabel honor guru (baris ~138) dan tabel pengeluaran per kategori (baris ~194).

- [ ] **Bungkus tabel honor guru (sekitar baris 138)**

  Cari:
  ```blade
  <table class="w-full text-sm">
      <thead class="border-b text-xs text-gray-500 uppercase">
  ```
  *(tabel pertama di file ini — pastikan ini yang di section Honor Guru)*

  Ubah menjadi:
  ```blade
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
      <thead class="border-b text-xs text-gray-500 uppercase">
  ```
  Temukan `</table>` pertama (sekitar baris 181), tambah `</div>` setelahnya:
  ```blade
  </table>
  </div>
  ```

- [ ] **Bungkus tabel pengeluaran per kategori (sekitar baris 194)**

  Cari `<table class="w-full text-sm">` yang kedua di file ini (sekitar baris 194) dan bungkus dengan cara yang sama:
  ```blade
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
  ```
  Temukan `</table>` penutupnya (sekitar baris 220), tambah `</div>`:
  ```blade
  </table>
  </div>
  ```

- [ ] **Commit**

  ```bash
  git add resources/views/reports/finance.blade.php
  git commit -m "M09: Tambah overflow-x-auto di tabel laporan keuangan (tablet support)"
  ```

---

## Task 4: reports/students.blade.php

**File:** `resources/views/reports/students.blade.php`

Tiga perubahan: (1) grid breakpoint terlalu agresif, (2) tabel distribusi status tanpa scroll, (3) tabel distribusi instrumen tanpa scroll.

- [ ] **Perbaiki grid breakpoint stat cards (baris 32)**

  Cari:
  ```blade
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
  ```
  Ubah menjadi:
  ```blade
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
  ```
  Penjelasan: `sm:` (640px) terlalu agresif untuk langsung 4 kolom — di 800px lebih nyaman 3 kolom dulu, baru 4 kolom di `lg:` (1024px).

- [ ] **Bungkus tabel distribusi per status (sekitar baris 56)**

  Cari konteks:
  ```blade
  <h3 class="font-semibold text-sm">Distribusi per Status</h3>
  ```
  Di bawahnya ada `<table class="w-full text-sm">`. Bungkus dengan:
  ```blade
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
  ```
  Temukan `</table>` (sekitar baris 96), tambah `</div>`:
  ```blade
  </table>
  </div>
  ```

- [ ] **Bungkus tabel distribusi per instrumen (sekitar baris 105)**

  Cari tabel kedua di file ini (sekitar baris 105) dan bungkus dengan cara yang sama:
  ```blade
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
  ```
  Temukan `</table>` (sekitar baris 132), tambah `</div>`:
  ```blade
  </table>
  </div>
  ```

- [ ] **Commit**

  ```bash
  git add resources/views/reports/students.blade.php
  git commit -m "M09: Fix grid breakpoint + overflow-x-auto di laporan murid (tablet support)"
  ```

---

## Task 5: audit-logs/index.blade.php

**File:** `resources/views/audit-logs/index.blade.php`

Tabel 6 kolom (Waktu, User, Aksi, Entitas, Label, Catatan) sangat lebar, tidak ada scroll horizontal.

- [ ] **Bungkus tabel audit log (sekitar baris 73)**

  Cari:
  ```blade
  <table class="w-full text-xs">
  ```
  Ubah menjadi:
  ```blade
  <div class="overflow-x-auto">
  <table class="w-full text-xs">
  ```
  Temukan `</table>` (sekitar baris 132), tambah `</div>`:
  ```blade
  </table>
  </div>
  ```

- [ ] **Commit**

  ```bash
  git add resources/views/audit-logs/index.blade.php
  git commit -m "M09: Tambah overflow-x-auto di tabel audit log (tablet support)"
  ```

---

## Task 6: students/index.blade.php

**File:** `resources/views/students/index.blade.php`

Tabel 9 kolom murid. Container outer sudah punya `overflow-hidden` (untuk clip rounded corners) — ini kompatibel dengan menambah wrapper `overflow-x-auto` di dalamnya.

- [ ] **Bungkus `<table>` di dalam container (sekitar baris 106-107)**

  Cari konteks:
  ```blade
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:140ms">
      <table class="w-full">
  ```
  Ubah menjadi:
  ```blade
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:140ms">
      <div class="overflow-x-auto">
      <table class="w-full">
  ```
  Temukan `</table>` (sekitar baris 180), tambah `</div>` setelahnya:
  ```blade
      </table>
      </div>
  ```

- [ ] **Commit**

  ```bash
  git add resources/views/students/index.blade.php
  git commit -m "M02: Tambah overflow-x-auto di tabel daftar murid (tablet support)"
  ```

---

## Task 7: Verifikasi Manual di Browser

Tidak ada unit test untuk perubahan HTML class — verifikasi dilakukan visual di browser DevTools.

- [ ] **Buka browser → DevTools → Toggle device toolbar (Ctrl+Shift+M)**

- [ ] **Set dimensi ke 800 × 1024 (portrait tablet)**

- [ ] **Cek 6 halaman berikut satu per satu:**

  | URL | Yang dicek |
  |---|---|
  | `/honors` | Tabel slip honor bisa di-scroll horizontal (ada scrollbar atau gesture swipe) |
  | `/honors/{id}` | Tabel komponen + tabel sesi bisa di-scroll |
  | `/reports/finance` | Tabel honor guru + tabel pengeluaran bisa di-scroll |
  | `/reports/students` | Stat cards tampil 3 kolom (bukan 4), tabel bisa di-scroll |
  | `/audit-logs` | Tabel audit bisa di-scroll |
  | `/students` | Tabel murid bisa di-scroll |

- [ ] **Set dimensi ke 1340 × 800 (landscape tablet) — pastikan tidak ada regresi:**

  - Sidebar muncul otomatis (bukan hamburger)
  - Semua tabel tampil normal
  - Tidak ada layout yang rusak

- [ ] **Set dimensi ke 1440 × 900 (desktop) — pastikan tidak ada regresi:**

  - Semua halaman tampil sama seperti sebelumnya

- [ ] **Commit final jika tidak ada masalah**

  ```bash
  git push origin main
  ```
