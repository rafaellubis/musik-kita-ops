# Design: Import Murid Kids Class (Bundle & Monthly)

**Tanggal:** 2026-05-26
**Status:** Approved
**Scope:** Import murid Kids Class yang sudah mid-program dari Excel lama ke sistem baru

---

## Konteks

Murid Kids Class belum diimport karena ada kebingungan soal penanganan tagihan:
- Murid sudah mid-program (6 bulan), sebagian sudah bayar beberapa termin
- Ada dua tipe: KIDS_CLASS_BUNDLE (cicil 3 termin) dan KIDS_CLASS (bulanan)
- Data di Excel lama sudah tersedia per termin mana yang lunas dan mana yang belum

Pendekatan yang dipilih: **import data murid dulu via template existing, setup billing setelahnya secara manual** — dengan satu tambahan kecil untuk KIDS_CLASS_BUNDLE.

---

## Bagian 1 — Import Data Murid (Zero Dev Work)

Gunakan template Excel yang sudah ada (`/import`), tidak ada perubahan template.

Kolom yang diisi untuk murid Kids Class:

| Kolom | Keterangan |
|---|---|
| `full_name`, `nickname`, `gender`, `birth_date` | Data murid seperti biasa |
| `phone`, `email`, `address`, `notes` | Data kontak |
| `parent_name`, `parent_phone`, `parent_relationship` | Wajib (murid Kids Class usia 4-5 tahun) |
| `status` | `Aktif` |
| `package_code` | Kode paket KIDS_CLASS atau KIDS_CLASS_BUNDLE |
| `teacher_code` | Kode ICA |
| `preferred_day`, `preferred_time` | Jadwal kelas mingguan |
| `kode_ruangan` | R1 (Studio 1 — mendukung Kids Class) |
| `active_since` | Tanggal mulai aktif (bisa perkiraan awal program) |

**Hasil setelah import:**
- Student record terbuat dengan status `Aktif`
- Enrollment ACTIVE terbuat, terikat ke package KIDS_CLASS / KIDS_CLASS_BUNDLE
- Schedule mingguan terbuat (hari, jam, ruang)
- **Tidak ada invoice yang dibuat** — billing di-setup di langkah berikutnya

---

## Bagian 2 — Generate Tagihan KIDS_CLASS_BUNDLE (1 Fitur Baru Kecil)

### Masalah

`InvoiceService::createKidsBundleInstallments()` sudah ada tapi tidak ada UI yang memanggilnya untuk murid existing hasil import. Murid yang melalui flow normal (Trial → Aktif) sudah ter-handle, tapi murid import tidak.

### Solusi

Tambah tombol **"Generate Cicilan Bundle"** di halaman detail murid, di section Invoice/Keuangan.

**Kondisi tampil tombol:**
- Enrollment aktif murid adalah `KIDS_CLASS_BUNDLE`
- Murid belum punya invoice dengan `payment_mode = INSTALLMENT` sama sekali

**Alur kerja:**
1. Admin klik tombol → modal kecil muncul
2. Admin isi 1 input: **Tanggal Mulai Program** (bulan ke-1 dari 6 bulan, format YYYY-MM-DD)
3. Submit → sistem panggil `InvoiceService::createKidsBundleInstallments()` dengan enrollment aktif dan start date tersebut
4. 3 invoice termin terbuat otomatis:
   - Termin 1: due date tgl 10 bulan ke-1
   - Termin 2: due date tgl 10 bulan ke-2
   - Termin 3: due date tgl 10 bulan ke-4
5. Redirect ke halaman murid dengan flash success

**Route baru:**
```
POST /students/{student}/generate-bundle
```

**Controller:** `InvoiceController` atau method baru di `StudentController` — pilih yang paling sesuai konteks halaman.

**Validasi:**
- `program_start_date`: required, format date, tidak boleh di masa depan lebih dari 6 bulan

### Menandai Termin yang Sudah Lunas

Setelah 3 invoice terbuat, untuk termin yang **sudah dibayar di Excel lama**:

1. Admin buka invoice detail termin yang bersangkutan
2. Klik "Catat Pembayaran" (UI sudah ada)
3. Isi: nominal = amount termin, tanggal = tanggal bayar historis (perkiraan boleh), method = CASH/TRANSFER sesuai data Excel, notes = `"Lunas sebelum migrasi sistem"`
4. Invoice otomatis berubah status PAID

Tidak ada logika khusus — pakai flow payment yang sudah ada sepenuhnya.

---

## Bagian 3 — Tagihan KIDS_CLASS Monthly (Zero Dev Work)

Tidak ada fitur baru yang dibutuhkan.

**Bulan berjalan dan ke depan:**
- Jalankan "Generate SPP" dari halaman Invoices seperti biasa
- Murid KIDS_CLASS yang baru diimport ikut ter-generate otomatis
- `generateMonthlySPP()` sudah idempotent — aman dijalankan berkali-kali

**Bulan lalu yang sudah lunas:**
- Tidak diimport ke sistem — histori lama cukup di Excel sebagai arsip
- Sistem baru mulai track dari bulan import ke depan

**Bulan lalu yang masih outstanding (belum bayar):**
- Buat invoice manual via halaman invoice murid: tombol "Tambah Tagihan" → pilih item SPP → isi bulan dan nominal
- Biarkan status UNPAID — akan ditagih seperti invoice biasa

---

## Ringkasan Dev Effort

| Komponen | Status |
|---|---|
| Import via template Excel existing | Zero dev — gunakan apa yang ada |
| Tombol + modal "Generate Cicilan Bundle" | **~0.5 hari** — 1 controller action + blade fragment |
| Catat pembayaran termin lama | Zero dev — gunakan UI Catat Pembayaran existing |
| SPP monthly generate | Zero dev — gunakan tombol Generate SPP existing |
| SPP monthly outstanding lama | Zero dev — buat invoice manual via UI |

**Total dev baru:** ~0.5 hari untuk tombol generate bundle saja.

---

## Batasan Desain

- Histori pembayaran Kids Class bulan-bulan lama (untuk monthly) **tidak diimport** — hanya outstanding yang dibuat manual
- Untuk bundle: due date termin dihitung dari `program_start_date` yang diinput Admin — Admin bertanggung jawab memasukkan tanggal yang benar
- Tidak ada validasi bahwa `program_start_date` konsisten dengan `active_since` di enrollment — cukup dipercayakan ke Admin
