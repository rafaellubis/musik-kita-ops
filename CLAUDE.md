# MUSIK KITA — Operations System (musik-kita-ops)
## Briefing Document untuk Claude Code — v1.2

> Dibuat berdasarkan: BRD-Final-Musik-KITA-v1.0.md + Revisi-BRD-SAD-v1.0-ke-v1.1.md
> Update v1.2 (2026-05-07): sinkronisasi tech stack & schema dengan kondisi kode aktual.
> Tanggal: Mei 2026

---

## 🎵 TENTANG PROJECT

Sistem administrasi dan keuangan internal untuk studio musik **"Musik KITA"**.
Menggantikan seluruh proses operasional Excel yang sudah tidak skalabel.
Dibangun oleh solo developer (owner studio) — pendekatan pemula yang sedang belajar.

**Tulis code yang clean, mudah dibaca, dan well-commented dalam Bahasa Indonesia.**
**Jika ada 2 cara, pilih yang lebih mudah dipahami pemula. Jelaskan alasan approach yang dipilih.**

---

## 🖥️ ENVIRONMENT & TECH STACK

```
Backend     : Laravel 11
PHP         : PHP 8.3
Database    : MySQL (via Laragon)
Frontend    : Blade Templates + Tailwind CSS 3.x + Alpine.js
              (default Laravel Breeze - JANGAN ganti ke Bootstrap)
Build       : Vite (npm run dev / npm run build)
Auth        : Laravel Breeze + Spatie Permission v6 (RBAC)
Server      : Laragon di Windows (C:/laragon/www/musik-kita-ops)
Version     : Git + GitHub Private Repository
Backup      : Git push ke GitHub + HDD eksternal
```

### Akun Login Default (setelah `php artisan db:seed`)
```
owner@musikkita.local   / password   -> role Owner
admin@musikkita.local   / password   -> role Admin
auditor@musikkita.local / password   -> role Auditor
```
GANTI password ketiga akun ini sebelum studio dipakai live.

### Deployment
- Sistem berjalan di **LAN lokal studio** -- TIDAK tersedia online (Fase 1)
- Akses via IP lokal WiFi studio (contoh: 192.168.1.x)
- Tidak ada integrasi payment gateway / WhatsApp API di Fase 1

---

## 👥 DATA OPERASIONAL

```
Murid aktif      : +-300 orang
Guru aktif       : 18 pengajar (CONFIRMED -- jangan asumsikan angka lain)
Sesi/bulan       : +-1.200 sesi privat + sesi Kids Class grup
Slip honor/bulan : 18 slip honor guru
Instrumen        : Piano, Gitar, Drum, Vocal, Bass, Violin, Saxophone, Kids Class
```

---

## 👨‍🏫 DAFTAR 18 GURU AKTIF

| No | Nama    | Instrumen                     |
|----|---------|-------------------------------|
| 1  | THOMAS  | Drum                          |
| 2  | ADI     | Piano                         |
| 3  | DEBORA  | Piano                         |
| 4  | MAJOR   | Drum                          |
| 5  | YUAN    | Bass, Piano, Gitar            |
| 6  | NAEL    | Piano, Gitar                  |
| 7  | ARYA    | Piano, Drum                   |
| 8  | DANIEL  | Piano, Gitar                  |
| 9  | T. HADI | Piano, Gitar, Vocal           |
| 10 | DEVI    | Vocal                         |
| 11 | INDRI   | Piano                         |
| 12 | PAULINE | Vocal, Piano                  |
| 13 | RIBKA   | Violin                        |
| 14 | DEDO    | Piano                         |
| 15 | CHARLY  | Drum                          |
| 16 | ICA     | Kids Class                    |
| 17 | SAMUEL  | Piano                         |
| 18 | FIDEL   | Piano, Vocal                  |

**Catatan kritis:** Bass hanya YUAN, Violin hanya RIBKA, Kids Class hanya ICA.
Saxophone: tidak ada guru aktif -- paket otomatis dinonaktifkan.

---

## 🔐 USER ROLES (RBAC via Spatie Permission)

```
3 Role yang ada di Fase 1:

OWNER
-> Akses penuh ke semua fitur
-> Ubah pricelist / harga paket
-> Hapus master data
-> Manage user
-> Void pembayaran
-> Lihat audit log mentah
-> Tandai honor dibayar

ADMIN
-> Operasional harian
-> Daftar murid, jadwal, absensi, tagihan, pembayaran
-> TIDAK BOLEH: ubah harga, hapus master data, manage user, void payment

AUDITOR
-> Read-only seluruh data dan laporan
-> TIDAK BOLEH edit/hapus apapun

PENTING: TIDAK ADA role GURU di sistem ini untuk Fase 1
Guru tidak login ke sistem -- absensi diinput oleh Admin
```

---

## 📦 PAKET & PRICELIST (Per 1 Maret 2026)

### Class Type
```
Reguler     : Privat 1-on-1, berjenjang grade, 30 menit, 4x/bulan
Hobby       : Privat 1-on-1, non-jenjang, 30 atau 45 menit, 4x/bulan
Kids Class  : Grup 3-4 anak, program 6 bulan, 45 menit, 4x/bulan
Saxophone   : Hobby only, sementara TIDAK TERSEDIA (tidak ada guru)
```

### Tabel Harga & Honor per Sesi
```
Formula honor: harga_paket x 50% / 4

Reguler (semua instrumen reguler - Piano/Gitar/Drum/Vocal/Bass/Violin):
Grade      | Durasi | Harga/Bulan | Honor/Sesi
Basic      | 30'    | Rp 340.000  | Rp 42.500
Level 1    | 30'    | Rp 370.000  | Rp 46.250
Level 2    | 30'    | Rp 400.000  | Rp 50.000
Level 3    | 30'    | Rp 430.000  | Rp 53.750
Level 4    | 30'    | Rp 460.000  | Rp 57.500

Hobby (semua instrumen reguler):
Tipe       | Durasi | Harga/Bulan | Honor/Sesi
Hobby      | 30'    | Rp 390.000  | Rp 48.750
Hobby      | 45'    | Rp 450.000  | Rp 56.250

Saxophone Hobby (NONAKTIF - tidak ada guru):
Saxophone  | 30'    | Rp 420.000  | Rp 52.500
Saxophone  | 45'    | Rp 470.000  | Rp 58.750

Kids Class:
Per murid/bulan : Rp 340.000 | Honor/sesi: Rp 42.500 x jumlah murid terdaftar
Final project   : Rp 140.000/murid
Total 6 bulan   : Rp 2.180.000/murid
```

### Biaya Tambahan
```
Registrasi murid baru : Rp 250.000 (termasuk buku catatan)
Cuti                  : Rp 100.000/pengajuan (maks 1 bulan, perpanjang 1x)
Ujian + Mini Concert  : Rp 395.000
Mini Concert saja     : Rp 295.000
Final Project Kids    : Rp 140.000/murid
Denda SPP             : Rp 5.000/hari mulai tanggal 11
```

---

## 🗄️ DATABASE -- SKEMA KRITIS

### PERINGATAN -- NAMA KOLOM YANG SERING SALAH

```
BENAR                       SALAH (jangan pakai)
packages.code               packages.name
packages.duration_min       packages.duration_minutes
packages.price_per_month    packages.price (bukan kolom — alias accessor formatted_price ada)
students.full_name          students.name
students.student_code       students.code
```

CATATAN: Honor per sesi TIDAK disimpan sebagai kolom di `packages`.
Honor dihitung dari formula PayrollConfig (mis. `package_price * 0.5 / 4`).

### Enum class_type -- 4 Nilai Valid Saja

```php
// HANYA 4 nilai ini yang valid:
'REGULER'
'HOBBY'
'KIDS_CLASS'
'KIDS_CLASS_BUNDLE'

// Contoh penggunaan benar:
in_array($pkg->class_type, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE'])

// SALAH -- jangan pakai:
'Kids Class'    // tidak valid
'kids_class'    // tidak valid
'Reguler'       // tidak valid (harus KAPITAL SEMUA)
```

### Skema Tabel Utama

**packages**
```
id, code, instrument_id, class_type (enum),
grade (nullable, untuk REGULER), duration_min,
price_per_month, is_active, sort_order, timestamps
```
Honor per sesi TIDAK disimpan kolom. Hitung via PayrollConfig.

**students** (skema actual setelah implementasi M02)
```
id, student_code (M-YYYY-NNNN, unique),
full_name, nickname, gender (L|P),
birth_date, phone, email, address, notes,
parent_name, parent_phone, parent_email,
parent_relationship (Ayah|Ibu|Wali),
status (enum: Calon|Trial|Aktif|Cuti|Selesai|Mengundurkan Diri),
package_id, assigned_teacher_id, assigned_room_id,
preferred_day, preferred_time, trial_date, active_since,
last_session_at, timestamps
```
CATATAN ENUM STATUS: pakai Title Case Indonesia (`Calon`, `Trial`, `Aktif`,
`Cuti`, `Selesai`, `Mengundurkan Diri`) — BUKAN UPPERCASE seperti
`class_type`. Decision ini diambil agar status langsung tampil di UI
tanpa transform. Jangan diubah ke UPPERCASE.

**enrollments**
```
id, student_id, package_id, teacher_id,
effective_date, end_date,
status (enum: ACTIVE|INACTIVE|COMPLETED), timestamps
```

**schedules** (jadwal mingguan tetap)
```
id, enrollment_id, day_of_week (0=Minggu, 6=Sabtu),
start_time, end_time, room_id, is_active, timestamps
```

**sessions** (sesi konkret per tanggal)
```
id, schedule_id, enrollment_id, student_id, teacher_id,
session_date,
status (enum: SCHEDULED|HADIR|HADIR_TERLAMBAT|IZIN_RESCHEDULE|
              IZIN_VIDEO|HANGUS|LIBUR|DIGANTI),
substitute_teacher_id (nullable),
late_minutes, notes, honor_code, honor_amount, timestamps
```

**invoices**
```
id, invoice_number (INV/YYYY/MM/NNNN),
student_id, month, year,
total_amount, paid_amount,
status (enum: UNPAID|PARTIAL|PAID), due_date, timestamps
```

**invoice_items**
```
id, invoice_id,
item_code (enum: REG|SPP|KIDS_FP|CUTI|UJI|MC|DENDA),
description, amount, timestamps
```

**payments**
```
id, invoice_id, amount,
method (enum: CASH|TRANSFER),
payment_date, proof_image,
receipt_number (KW/YYYY/MM/NNNN),
voided_at, voided_by, timestamps
```

**teacher_honor_slips**
```
id, slip_number (SLIP/YYYY/MM/NNNN),
teacher_id, month, year,
base_honor, transport_honor (input manual),
other_honor (input manual), other_honor_note (keterangan),
total_honor,
status (enum: DRAFT|CALCULATED|PAID), paid_at, timestamps
```

**student_status_histories** (audit trail lifecycle murid)
```
id, student_id, from_status, to_status,
reason, skipped_trial (boolean),
metadata (JSON -- termasuk skipped_trial: true/false),
changed_by, timestamps
```

**audit_logs**
```
id, user_id,
action (enum: CREATE|UPDATE|DELETE|LOGIN|LOGOUT|PRINT|VOID),
entity_type, entity_id,
old_values (JSON), new_values (JSON), timestamps
```

---

## 📋 BUSINESS RULES KRITIS (v1.1)

### Pendaftaran & Trial
```
BR-1.1  : Email murid WAJIB ada di tabel dan form (isian nullable/opsional)
BR-1.2  : Trial gratis 1 sesi untuk calon murid
BR-1.3  : Trial durasi = 30 MENIT untuk SEMUA tipe paket [REVISI v1.1]
          (sebelumnya ikut durasi paket yang diminati)
BR-1.4  : Honor guru saat trial:
          - Murid HADIR  -> honor dibayar PENUH
          - Murid NO-SHOW -> honor NOL Rp 0 [REVISI v1.1]
BR-1.5  : Registrasi Rp 250.000 wajib saat lanjut aktif
BR-1.8  : Murid valid aktif setelah registrasi + SPP bulan pertama LUNAS
BR-1.9  : Kids Class mulai jika minimal 3 anak
          Status 'Calon - Menunggu Kuota' jika kuota <3
```

### Penjadwalan
```
BR-3.2  : Generate sesi otomatis berdasarkan jadwal mingguan
BR-3.3  : Min 3 sesi, max 4 sesi per murid per bulan
BR-3.4  : Sesi libur nasional TIDAK diganti
BR-3.5  : Minggu ke-5 TIDAK dilaksanakan (maks 4 sesi/bulan)
BR-3.6  : Murid tetap bayar penuh meski bulan hanya 3 sesi
BR-3.9  : Pemindahan jadwal mingguan tetap BOLEH dalam bulan yang sama [REVISI v1.1]
          (sebelumnya: hanya berlaku mulai bulan berikutnya)
```

### Absensi
```
BR-4.4  : Izin berhak reschedule JIKA: info >=5 jam + izin PERTAMA di bulan tsb
BR-4.5  : Sesi reschedule bisa di bulan berjalan atau rapel bulan depan
BR-4.6  : Izin ke-2+ = video pengganti (sesi dianggap MASUK, tidak hangus)
BR-4.7  : Info <5 jam atau tanpa info = HANGUS (sesi dianggap MASUK)
BR-4.9  : Diganti guru lain -> honor ke guru PENGGANTI, bukan guru utama
BR-4.10 : Libur nasional -> honor guru tetap DIBAYAR PENUH
```

### Tagihan & Denda
```
BR-5.1  : Invoice SPP otomatis generate tanggal 1 setiap bulan
BR-5.2  : Tempo bayar tanggal 1-10
BR-5.3  : Denda Rp 5.000/hari mulai tanggal 11 (cron job harian)
BR-5.4  : Lunas = SPP + SELURUH denda terbayar
BR-5.14 : Tunggakan >1 bulan tanpa konfirmasi -> auto-mundur
BR-5.16 : Nomor invoice: INV/YYYY/MM/NNNN (reset per bulan)
BR-5.17 : Nomor kuitansi: KW/YYYY/MM/NNNN (reset per bulan)
BR-5.18 : Void pembayaran hanya bisa dilakukan OWNER (bukan Admin)
```

### Honor Guru -- 9 Skenario
```
Formula dasar: harga_paket x 50% / 4

Kode      | Skenario                        | Formula
H_REG     | Sesi terlaksana (hadir/telat)   | harga x 50% / 4
H_TRIAL   | Sesi trial (murid HADIR)        | Sama H_REG sesuai paket calon
TRIAL_NS  | Trial murid NO-SHOW [v1.1]      | Rp 0 (honor NOL)
H_VIDEO   | Izin video pengganti            | Sama H_REG
H_LIBUR   | Sesi libur nasional             | Sama H_REG (full pay)
H_HANGUS  | Murid no-show / hangus          | Sama H_REG (full pay)
H_PENG    | Diajar guru pengganti           | H_REG -> ke guru pengganti
H_KIDS    | Sesi Kids Class                 | murid_terdaftar x Rp 42.500
H_UJIAN   | Pengawas ujian grade            | Rp 250.000 flat/ujian
(none)    | Murid sedang cuti               | Rp 0 (sesi tidak ter-generate)

Cut-off honor: H-2 sebelum akhir bulan [REVISI v1.1]
(sebelumnya: tanggal terakhir bulan)
```

### Komponen Slip Gaji Guru (M08 - Mini Concert) [REVISI v1.1]
```
Slip gaji guru event Mini Concert WAJIB berisi:
1. Honor event (otomatis dari sesi yang ada)
2. Honor transport (INPUT MANUAL -- tidak ada rumus otomatis)
3. Honor lain-lain (INPUT MANUAL + field keterangan wajib diisi)
```

### Kids Class
```
BR-10.1 : Usia 4 tahun s/d < 5 tahun [REVISI v1.1 -- sebelumnya <6 tahun]
BR-10.2 : Min 3, max 4 anak per kelas
BR-10.3 : Status 'Calon - Menunggu Kuota' -> tidak kena SPP
BR-10.6 : Final project Rp 140.000/murid di akhir 6 bulan
BR-10.7 : Lulus -> status 'Selesai' -> re-enroll privat TANPA bayar registrasi ulang
BR-10.10: Opsi bayar: lunas di awal ATAU cicil 3 termin (bulan ke-1, 2, dan 4)
```

---

## 🔄 STATUS MURID (6 Status dengan State Machine)

```
Status       | Keterangan
CALON        | Sudah daftar, belum trial
TRIAL        | Sedang/sudah trial, belum aktif
AKTIF        | Murid berjalan, ada enrollment aktif, SPP ditagih
CUTI         | Sedang cuti berbayar, sesi di-pause
MUNDUR       | Keluar (manual oleh admin atau auto oleh sistem)
SELESAI      | Lulus Kids Class 6 bulan
```

### Transisi Valid
```
CALON       -> TRIAL   (admin schedule trial)
CALON       -> AKTIF   (skip trial -- WAJIB isi reason) [keputusan v1.1]
TRIAL       -> AKTIF   (konversi setelah trial sukses)
TRIAL       -> MUNDUR  (tidak melanjutkan setelah trial)
AKTIF       -> CUTI    (pengajuan cuti + bayar Rp 100.000)
AKTIF       -> MUNDUR  (manual atau auto-mundur)
AKTIF       -> SELESAI (Kids Class lulus 6 bulan)
CUTI        -> AKTIF   (otomatis setelah periode cuti berakhir)
CUTI        -> CUTI    (perpanjang 1x, maks 2 bulan total)
CUTI        -> MUNDUR  (tidak bayar cuti H+1 atau melebihi 2 bulan)
SELESAI     -> AKTIF   (re-enroll privat, TANPA registrasi ulang)
MUNDUR      -> AKTIF   (re-aktivasi, WAJIB bayar Rp 250.000)
```

### Skip Trial (Hybrid Flow -- Keputusan Desain v1.1)
```
Murid baru BOLEH skip trial dan langsung AKTIF.
WAJIB isi field 'reason' dengan salah satu:
- walk_in     : Datang langsung dan langsung bayar (confident)
- migrasi     : Data dari sistem lama / import
- reaktivasi  : Murid lama yang kembali
- lulus_kids  : Lulusan Kids Class lanjut privat

Estimasi volume walk-in: ~10% dari total pendaftaran.
Catat di student_status_histories dengan metadata: {skipped_trial: true}
```

---

## 📊 KOMPONEN TAGIHAN (Invoice Items)

```
Kode     | Nama                    | Nominal/Formula
REG      | Registrasi              | Rp 250.000
SPP      | SPP Bulanan             | packages.price
KIDS_FP  | Final Project Kids      | Rp 140.000/murid
CUTI     | Biaya Cuti              | Rp 100.000/pengajuan
UJI      | Ujian + Mini Concert    | Rp 395.000
MC       | Mini Concert saja       | Rp 295.000
DENDA    | Denda keterlambatan     | Rp 5.000 x MAX(0, hari - 10)
```

---

## 🔄 RINGKASAN WORKFLOW MODUL

### M01 -- Master Data
- CRUD instrumen, paket, grade, ruang, guru (+ matriks instrumen), hari libur
- Hanya Owner yang boleh ubah harga paket
- Hapus guru dengan sesi historis -> DITOLAK, gunakan 'Nonaktifkan'
- Paket Saxophone otomatis nonaktif jika tidak ada guru aktif

### M02 -- Pendaftaran & Trial
- Form murid baru -> status CALON
- Schedule trial -> status TRIAL -> honor sesuai kehadiran (hadir=bayar, no-show=NOL)
- Konversi ke AKTIF -> generate invoice registrasi + SPP
- Skip trial -> langsung AKTIF -> WAJIB isi reason

### M03 -- Penjadwalan
- Jadwal mingguan tetap per enrollment (hari, jam, guru, ruang)
- Generator sesi otomatis (cron tanggal 25 untuk bulan berikutnya)
- Cek konflik jadwal: 1 guru tidak boleh 2 sesi bersamaan, 1 ruang tidak boleh 2 sesi bersamaan
- Reschedule: validasi >=5 jam + izin pertama bulan tsb
- Rapel ke bulan depan jika tidak ada slot pengganti

### M04 -- Absensi
- Input 7 status per sesi setelah sesi berlangsung
- Kids Class: absensi per murid dalam grup
- Guru pengganti: set substitute_teacher_id -> honor otomatis ke pengganti

### M05 -- Keuangan Murid
- Generate invoice SPP otomatis tanggal 1 setiap bulan
- Denda harian cron job mulai tanggal 11
- Catat pembayaran cash/transfer + upload bukti foto
- Generate kuitansi KW/YYYY/MM/NNNN
- Cetak A4 / download PDF
- Auto-mundur warning H-7 di dashboard untuk murid tunggakan >1 bulan

### M06 -- Honor Guru
- Kalkulasi honor otomatis H-2 sebelum akhir bulan
- Generate slip SLIP/YYYY/MM/NNNN per guru
- Komponen: honor pokok (auto) + transport (manual) + lain-lain (manual + keterangan)
- Owner review -> Tandai Dibayar -> slip terkunci dari edit

### M07 -- Pengeluaran & Kas
- Catat pengeluaran per kategori (Sewa, Listrik, Gaji Staff, Peralatan, dll)
- Petty cash harian dengan saldo berjalan
- Masuk laporan P&L otomatis sesuai kategori dan periode

### M08 -- Event (Mini Concert & Ujian)
- Buat event Mini Concert (2x/tahun)
- Daftar murid: Ujian + Tampil (Rp 395.000) atau Tampil saja (Rp 295.000)
- Input hasil ujian -> grade naik otomatis jika Lulus
- Honor pengawas Rp 250.000 flat/ujian
- Slip gaji event: honor + transport (manual) + lain-lain (manual + keterangan)

### M09 -- Laporan & Notifikasi
- Dashboard P&L real-time (revenue, expense, aging receivable)
- Kinerja guru, retensi murid, okupansi studio
- Export laporan PDF & Excel
- Generator template broadcast WhatsApp (admin copy-paste manual)
- Audit Log Viewer (Owner only)

---

## 🚫 YANG TIDAK BOLEH DILAKUKAN

```
SKEMA DATABASE:
X Pakai packages.name      -> harus packages.code
X Pakai duration_minutes   -> harus duration_min
X Hardcode 'Kids Class'    -> pakai 'KIDS_CLASS'
X Hardcode 'Reguler'       -> pakai 'REGULER' (kapital semua)

BUSINESS RULES:
X Trial >30 menit          -> semua trial 30 menit [v1.1]
X Honor trial jika no-show -> honor NOL Rp 0 [v1.1]
X Kids Class usia >=5      -> max <5 tahun [v1.1]
X Honor calc tanggal akhir -> harus H-2 sebelum akhir bulan [v1.1]
X Transport honor otomatis -> WAJIB input manual
X Slip gaji tanpa komponen transport dan lain-lain [v1.1]
X Jadwal pindah lintas bulan -> hanya dalam bulan yang sama [v1.1]
X Void payment oleh Admin  -> hanya Owner
X Edit slip honor 'Dibayarkan'
X Hapus audit log via UI

TECH STACK:
X Pakai Bootstrap CSS      -> project ini Tailwind CSS (Breeze default)
X Ganti utility class jadi class custom CSS -> ikuti pola Tailwind
X Buat role GURU           -> Fase 1 tidak ada login guru
X Push .env ke GitHub      -> berisi password database
```

---

## ✅ CHECKLIST SEBELUM GENERATE CODE

```
[ ] Nama kolom: code (bukan name), duration_min (bukan duration_minutes)
[ ] class_type enum: REGULER, HOBBY, KIDS_CLASS, KIDS_CLASS_BUNDLE
[ ] Status enum murid: Title Case (Calon/Trial/Aktif/Cuti/Selesai/Mengundurkan Diri)
[ ] Frontend: Tailwind CSS (bukan Bootstrap)
[ ] Role: Owner, Admin, Auditor (bukan Admin, Guru)
[ ] Route write sensitif (harga, payroll, invoice-component) di middleware role:Owner
[ ] Route write operasional (instruments, teachers, holidays, rooms, students) di role:Owner|Admin
[ ] Route read di role:Owner|Admin|Auditor
[ ] Validasi di Form Request dengan pesan Bahasa Indonesia
[ ] Honor trial no-show = Rp 0
[ ] Honor cut-off: H-2 sebelum akhir bulan
[ ] Slip gaji event ada field transport + lain-lain + keterangan
[ ] Trial: 30 menit semua tipe
[ ] Kids Class usia: 4 s/d <5 tahun
[ ] Komentar kode dalam Bahasa Indonesia
[ ] Pesan UI/validasi dalam Bahasa Indonesia
[ ] Ada audit log entry untuk action penting
[ ] Role/permission check sudah ada (Spatie Permission)
```

---

## 🎨 CODING CONVENTIONS

```
Komentar kode  : Bahasa Indonesia
Variabel/method: English (camelCase / snake_case)
Pesan UI       : Bahasa Indonesia
Validasi       : Bahasa Indonesia
```

### Naming Convention
```
Models:
Student, Teacher, Package, Enrollment, Schedule,
Session, Invoice, InvoiceItem, Payment, HonorSlip, AuditLog

Controllers (namespace Admin):
App\Http\Controllers\Admin\StudentController
App\Http\Controllers\Admin\TeacherController
App\Http\Controllers\Admin\PackageController
App\Http\Controllers\Admin\EnrollmentController
App\Http\Controllers\Admin\ScheduleController
App\Http\Controllers\Admin\SessionController
App\Http\Controllers\Admin\InvoiceController
App\Http\Controllers\Admin\HonorController
App\Http\Controllers\Admin\EventController

Services:
App\Services\HonorCalculationService
App\Services\SessionGeneratorService
App\Services\InvoiceGeneratorService
App\Services\AutoMundurService
App\Services\TrialManagementService
```

### Struktur Folder
```
app/
  Http/
    Controllers/
      Admin/          <- Controller admin panel
    Requests/         <- Form Request validasi
    Resources/        <- API Resources
  Models/
  Services/           <- Business logic kompleks
  Policies/           <- Authorization per model

resources/views/
  admin/              <- Views admin panel
  layouts/            <- Layout template Bootstrap 5

database/
  migrations/
  seeders/
```

---

## 🔀 GIT WORKFLOW

### Format Commit Message
```
[Modul/Area] Deskripsi singkat

Contoh:
git commit -m "M02: Tambah form Trial Class dengan validasi"
git commit -m "M06: Fix perhitungan honor H-2 sebelum akhir bulan"
git commit -m "Honor: Tambah komponen transport dan lain-lain di slip gaji"
git commit -m "Fix: Bug class_type enum di seeder Kids Class"
git commit -m "DB: Migration tabel sessions dengan 7 status absensi"
```

### Kapan Commit
```
Setelah selesai 1 fitur atau sub-fitur
Sebelum Claude Code mulai perubahan besar
Setelah fix bug penting
Setiap akhir sesi kerja
```

### Workflow dengan Claude Code
```
git status                        <- cek kondisi sebelum mulai
... Claude Code buat perubahan ...
git diff                          <- review apa yang berubah
git add . && git commit -m "..."  <- simpan jika oke
git push                          <- upload ke GitHub
git checkout .                    <- BATALKAN jika tidak oke
```

---

## 🔧 ARTISAN COMMANDS

```bash
php artisan migrate
php artisan migrate:fresh --seed        # HATI-HATI: hapus semua data!
php artisan make:model NamaModel -mfsc
php artisan make:controller Admin/NamaController --resource
php artisan make:request StoreNamaRequest
php artisan route:list
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

---

## 📅 ROADMAP MVP

```
Fase 1 (4-6 minggu) -- Core:
Auth + RBAC, Master Data, Pendaftaran + Trial,
Jadwal mingguan, Generator sesi, Absensi dasar,
Invoice SPP, Pembayaran + Kuitansi, Honor dasar,
Import data Excel lama (300+ murid)

Fase 2 (3-4 minggu) -- Operational Excellence:
Cuti, Reschedule lengkap, Honor lengkap,
Denda otomatis (cron), Auto-mundur + warning,
Event Mini Concert + Ujian, Pengeluaran & Kas,
Kids Class cicil 3 termin, Broadcast WA template

Fase 3 (2-3 minggu) -- Analytics & Refinement:
Dashboard P&L, Laporan lengkap (PDF/Excel),
Audit Log Viewer, UI Polish, Performa, Security,
UAT lengkap, Dokumentasi user manual
```

---

## 💡 NOTES UNTUK CLAUDE CODE

```
- Owner = solo developer pemula -> jelaskan logic dengan komentar detail
- Prioritaskan simplicity over complexity
- Jika ada ambiguitas business rule, tanyakan dahulu sebelum implement
- Selalu validasi input di Form Request (bukan di Controller langsung)
- Gunakan Spatie Permission untuk semua role check
- Audit log untuk setiap action penting (create/update/delete/void)
- Performance target: dashboard <3 detik, generator sesi 300 murid <30 detik
- Tidak ada internet = tidak masalah (sistem berjalan full offline di LAN)
```

---

*Source: BRD-Final-Musik-KITA-v1.0 + Revisi-BRD-SAD-v1.0-ke-v1.1*
*Update file ini jika ada perubahan business rules atau schema*
*Versi: 1.1 | Mei 2026*