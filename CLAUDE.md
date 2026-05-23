# MUSIK KITA — Operations System (musik-kita-ops)
## Briefing Document untuk Claude Code — v1.4

> Dibuat berdasarkan: BRD-Final-Musik-KITA-v1.0.md + Revisi-BRD-SAD-v1.0-ke-v1.1.md
> Update v1.2 (2026-05-07): sinkronisasi tech stack & schema dengan kondisi kode aktual.
> Update v1.3 (2026-05-23): multi-kelas, diskon invoice, cuti, reschedule Fase 2, QRIS/DEBIT, slip honor unifikasi, ruangan fleksibel.
> Update v1.4 (2026-05-23): jadwal otomatis dengan kalender akademik — replacement_date pada holidays, honor LIBUR logic, H_IZIN honor code, guru pendamping event.
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

## DAFTAR RUANGAN AKTIF KAPASITAS DAN FASILITASNYA

| CODE | NAMA          | Fasilitas                    |
|------|---------------|------------------------------|
| R1   | STUDIO 1      | Vocal, Kids Class, Gitar     |
| R2   | STUDIO 2      | Piano, Vocal, Gitar          |
| R3   | STUDIO 3      | Piano                        |
| R4   | STUDIO 4      | Piano, Gitar                 |
| R5   | STUDIO 5      | Bass, Gitar                  |
| R6   | STUDIO 6      | Violin                       |
| R7   | STUDIO 7      | Piano, Vocal                 |
| R8   | STUDIO 8      | Drum                         |
| R9   | STUDIO 9      | Drum                         |

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
Biaya buku			  : harga ditentukan oleh owner pengisian manual
```

---

## 🗄️ DATABASE -- SKEMA KRITIS

### PERINGATAN -- NAMA KOLOM YANG SERING SALAH

```
BENAR                                     SALAH (jangan pakai)
packages.code                             packages.name
packages.duration_min                     packages.duration_minutes
packages.price_per_month                  packages.price
students.full_name                        students.name
students.student_code                     students.code
$student->primaryEnrollment->package      $student->package (accessor lama SUDAH TIDAK ADA)
$student->primaryEnrollment->teacher      $student->teacher (accessor lama SUDAH TIDAK ADA)
enrollments.is_primary                    students.package_id (kolom ini SUDAH DIHAPUS)
```

KRITIS (v1.3): Kolom `students.package_id`, `assigned_teacher_id`, `assigned_room_id`,
`preferred_day`, `preferred_time` SUDAH DIHAPUS di migrasi multi-kelas (Mei 2026).
Jangan gunakan kolom-kolom tersebut di kode baru — akan runtime error.

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

**students** (skema aktual — setelah migrasi multi-kelas, Mei 2026)
```
id, student_code (M-YYYY-NNNN, unique),
full_name, nickname, gender (L|P),
birth_date, phone, email, address, notes,
parent_name, parent_phone, parent_email,
parent_relationship (Ayah|Ibu|Wali),
status (enum: Calon|Trial|Aktif|Cuti|Selesai|Mengundurkan Diri),
primary_enrollment_id (FK → enrollments, nullable),
cuti_from (date, nullable), cuti_until (date, nullable),
trial_date, active_since, last_session_at, timestamps
```
CATATAN ENUM STATUS: pakai Title Case Indonesia (`Calon`, `Trial`, `Aktif`,
`Cuti`, `Selesai`, `Mengundurkan Diri`) — BUKAN UPPERCASE seperti
`class_type`. Decision ini diambil agar status langsung tampil di UI
tanpa transform. Jangan diubah ke UPPERCASE.

CATATAN KRITIS: Kolom `package_id`, `assigned_teacher_id`, `assigned_room_id`,
`preferred_day`, `preferred_time` SUDAH DIHAPUS di migrasi multi-kelas (Mei 2026).
Semua relasi kelas sekarang via tabel `enrollments`.
Untuk akses paket/guru/ruang aktif murid: gunakan `$student->primaryEnrollment->package` dll.

**enrollments**
```
id, student_id, package_id, teacher_id,
is_primary (boolean, default false — satu enrollment utama per murid),
effective_date, end_date, notes,
status (enum: ACTIVE|ON_LEAVE|INACTIVE|COMPLETED), timestamps
```
CATATAN: `ON_LEAVE` diset saat murid mengajukan cuti; kembali ke `ACTIVE` saat cuti berakhir.
`is_primary` menentukan enrollment yang dipakai untuk generate invoice SPP otomatis.

**schedules** (jadwal mingguan tetap)
```
id, enrollment_id, day_of_week (0=Minggu, 6=Sabtu),
start_time, end_time, room_id, is_active, timestamps
```

**sessions** (sesi konkret per tanggal)
```
id, schedule_id, enrollment_id, student_id, teacher_id,
session_date (DATE — raw string, TIDAK ada cast di model),
status (enum: SCHEDULED|HADIR|HADIR_TERLAMBAT|IZIN_RESCHEDULE|
              IZIN_VIDEO|HANGUS|LIBUR|DIGANTI|CANCELLED),
substitute_teacher_id (nullable),
late_minutes, notes, honor_code, honor_amount, timestamps
```
CATATAN (v1.4): `session_date` TIDAK punya cast 'date' di ClassSession model.
Gunakan `Carbon::parse($session->session_date)` untuk operasi tanggal.
Jangan akses `$session->session_date->format(...)` langsung — akan error.
`honor_code` adalah VARCHAR (bukan enum) — nilai: H_REG|H_TRIAL|TRIAL_NS|H_VIDEO|
H_LIBUR|H_HANGUS|H_PENG|H_KIDS|H_UJIAN|H_IZIN atau NULL.
Generator set `honor_code` + `honor_amount` langsung saat buat sesi LIBUR.

**invoices**
```
id, invoice_number (INV/YYYY/MM/NNNN),
student_id, enrollment_id (FK → enrollments, nullable),
month, year,
class_type (snapshot class_type paket saat dibuat, nullable),
payment_mode (enum: FULL|INSTALLMENT, default FULL),
installment_number (nullable, nilai 1|2|3 — urutan termin cicilan),
installment_group_id (UUID string, nullable — pengikat 3 invoice cicilan),
total_amount, paid_amount,
status (enum: UNPAID|PARTIAL|PAID), due_date, timestamps
```
CATATAN: `payment_mode=INSTALLMENT` hanya untuk `class_type=KIDS_CLASS_BUNDLE`.
Tiga invoice cicilan diikat oleh `installment_group_id` yang sama (UUID).

**invoice_items**
```
id, invoice_id,
parent_item_id (FK → invoice_items self-ref, nullable — untuk item DISKON),
item_code (enum: REG|SPP|KIDS_FP|CUTI|UJI|MC|DENDA|DISKON),
description, amount,
discount_type (NOMINAL|PERCENT, nullable — hanya diisi item DISKON),
discount_value (integer, nullable — nilai Rp atau % tergantung discount_type),
discount_reason (string max 500, nullable — wajib diisi saat buat item DISKON),
metadata (JSON, nullable), timestamps
```
CATATAN: Item DISKON wajib punya `parent_item_id` yang menunjuk item yang didiskon.
Diskon NOMINAL langsung dikurangi dari amount parent. Diskon PERCENT dihitung dari amount parent.

**payments**
```
id, receipt_number (KW/YYYY/MM/NNNN),
invoice_id, amount,
method (enum: CASH|TRANSFER|QRIS|DEBIT),
payment_date, proof_image,
notes (text, nullable),
created_by (FK → users, nullable — siapa yang catat pembayaran),
voided_at, voided_by, voided_reason (text, nullable), timestamps
```

**invoice_components** (katalog item tagihan — dikelola Owner via M01 Master Data)
```
id, code (unique), name,
type (enum: REGULER|TRIAL|KIDS_FINAL|CUTI|UJIAN|MINI_CONCERT|DENDA),
amount_or_formula (string — nominal Rp atau rumus, misal "package_price * 0.5 / 4"),
description (nullable), is_active, sort_order, timestamps
```
CATATAN: Owner bisa tambah/edit item tagihan via master data. Admin memilih dari katalog ini
saat tambah baris manual ke invoice.

**teachers**
```
id, code, name, email, phone,
bank_name (nullable), bank_account (nullable), bank_account_holder (nullable),
joined_date, is_active, notes, timestamps
```
CATATAN: Field bank (`bank_name`, `bank_account`, `bank_account_holder`) ditambah Mei 2026
untuk ditampilkan di header slip honor cetak.

**rooms**
```
id, code, name, capacity,
supported_instruments (JSON, nullable — array instrumen yg didukung, misal ["Piano","Gitar"]),
is_active, timestamps
```
CATATAN: Kolom boolean `has_piano`, `has_drum`, `has_amplifier` SUDAH DIHAPUS (Mei 2026).
Diganti `supported_instruments` JSON untuk fleksibilitas instrumen baru.

**teacher_honor_slips**
```
id, slip_number (SLIP/YYYY/MM/NNNN),
teacher_id, month, year,
base_honor (otomatis dari kalkulasi sesi),
event_honor (input manual — honor event, misal Mini Concert), event_honor_note,
transport_honor (input manual), other_honor (input manual), other_honor_note,
total_honor (= base + event + transport + other),
status (enum: DRAFT|CALCULATED|PAID),
paid_at, paid_by (FK → users), created_by (FK → users), timestamps
```
CATATAN: Kolom `event_honor` dan `event_honor_note` ditambah Mei 2026 saat tabel
`event_honor_slips` dihapus — honor event digabungkan ke slip honor utama guru.

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

**holidays** (hari libur untuk session generator)
```
id, date (date, unique),
name, type (enum: Nasional|Cuti Bersama|Internal),
replacement_date (date, nullable, unique — tanggal sesi pengganti, harus bulan yang sama),
is_honor_paid (boolean, default true — false untuk Internal/Konser KITA),
is_active, notes, timestamps
```
CATATAN (v1.4): Kolom `replacement_date` dan `is_honor_paid` ditambah Mei 2026.
- `replacement_date`: jika diisi, generator membuat sesi pengganti di tanggal ini (umumnya minggu ke-5).
  Unique constraint — dua holiday tidak boleh punya tanggal pengganti yang sama.
  Field ini SELALU NULL untuk tipe Internal (validasi di controller).
- `is_honor_paid=false`: honor guru Rp 0 untuk sesi LIBUR ini (Konser KITA, event studio).
  Otomatis di-set false saat tipe Internal.

**event_participants** (peserta event M08)
```
id, event_id, student_id, enrollment_id,
accompanying_teacher_id (FK → teachers, nullable — guru pendamping di Konser KITA),
participation_type, fee_amount, invoice_id, invoice_item_id,
exam_result, grade_before, grade_after, exam_notes, timestamps
```
CATATAN (v1.4): Kolom `accompanying_teacher_id` ditambah Mei 2026 untuk tracking
guru yang mendampingi murid di Konser KITA. NULL = tidak ada pendamping / guru tidak bisa hadir.
nullOnDelete: jika guru dihapus dari sistem, kolom ini otomatis jadi NULL.

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
BR-3.4  : Sesi libur nasional TANPA replacement_date → TIDAK diganti (sesi LIBUR saja)
          Sesi libur nasional DGN replacement_date → sesi LIBUR + sesi pengganti di tanggal tsb
BR-3.5  : Minggu ke-5 TIDAK dilaksanakan secara reguler (maks 4 sesi/bulan counter)
          KECUALI: minggu ke-5 BOLEH dipakai sebagai replacement session (di luar counter 4)
BR-3.6  : Murid tetap bayar penuh meski bulan hanya 3 sesi
BR-3.9  : Pemindahan jadwal mingguan tetap BOLEH dalam bulan yang sama [REVISI v1.1]
          (sebelumnya: hanya berlaku mulai bulan berikutnya)
BR-3.10 : Reschedule Fase 2 — sesi pengganti dibuat otomatis oleh RescheduleService
BR-3.11 : Conflict detection saat reschedule: 1 guru tidak boleh 2 sesi bersamaan
BR-3.12 : Conflict detection saat reschedule: 1 ruang tidak boleh 2 sesi bersamaan
BR-3.13 : Admin input tanggal, jam, ruang pengganti via mini-modal di halaman absensi
BR-3.14 : Holiday tipe 'Internal' (Konser KITA, event studio) → honor guru Rp 0 [v1.4]
          Field is_honor_paid=false di tabel holidays
BR-3.15 : replacement_date wajib dalam bulan yang sama dengan tanggal libur [v1.4]
          replacement_date TIDAK boleh diisi untuk tipe Internal
BR-3.16 : Conflict detection untuk replacement sessions: cek ClassSession konkret di tanggal
          tsb (bukan jadwal mingguan) — skip jika guru/ruang sudah terpakai di jam yang sama
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
BR-5.19 : Metode pembayaran yang valid: CASH, TRANSFER, QRIS, DEBIT
```

### Honor Guru -- 10 Skenario
```
Formula dasar: harga_paket x 50% / 4

Kode      | Skenario                        | Formula
H_REG     | Sesi terlaksana (hadir/telat)   | harga x 50% / 4
H_TRIAL   | Sesi trial (murid HADIR)        | Sama H_REG sesuai paket calon
TRIAL_NS  | Trial murid NO-SHOW [v1.1]      | Rp 0 (honor NOL)
H_VIDEO   | Izin video pengganti            | Sama H_REG
H_LIBUR   | Sesi libur nasional TANPA replacement_date | Sama H_REG (full pay, BR-4.10)
H_HANGUS  | Murid no-show / hangus          | Sama H_REG (full pay)
H_PENG    | Diajar guru pengganti           | H_REG -> ke guru pengganti
H_KIDS    | Sesi Kids Class                 | murid_terdaftar x Rp 42.500
H_UJIAN   | Pengawas ujian grade            | Rp 250.000 flat/ujian
H_IZIN    | IZIN_RESCHEDULE sesi original (guru tidak datang)| Rp 0 — honor dibayar via sesi pengganti
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

### Multi-Kelas (Murid Banyak Enrollment)
```
BR-MK.1 : Murid BOLEH punya lebih dari satu enrollment ACTIVE bersamaan
           (contoh: Piano Regular + Gitar Hobby)
BR-MK.2 : Setiap murid punya tepat satu primary enrollment (students.primary_enrollment_id)
BR-MK.3 : enrollments.is_primary menandai enrollment utama per murid
BR-MK.4 : Invoice SPP auto-generate hanya untuk primary enrollment
           (enrollment non-primary ditagih manual jika diperlukan)
BR-MK.5 : Murid bisa tambah kelas baru via tab 'Kelas' di halaman detail murid
BR-MK.6 : Owner/Admin bisa ganti primary enrollment via EnrollmentController::setPrimary()
BR-MK.7 : Hentikan enrollment non-primary via EnrollmentController::stop()
           (status → COMPLETED, tidak mempengaruhi status murid)
BR-MK.8 : Semua enrollment COMPLETED/INACTIVE → murid otomatis mundur
```

### Cuti Murid
```
BR-CUTI.1 : Saat Admin mengajukan cuti, students.cuti_from dan cuti_until diisi
BR-CUTI.2 : Enrollment aktif berubah status → ON_LEAVE saat cuti dimulai
BR-CUTI.3 : Sesi tidak di-generate untuk enrollment dengan status ON_LEAVE
BR-CUTI.4 : Biaya cuti Rp 100.000 wajib dibayar saat pengajuan
BR-CUTI.5 : Saat kembali Aktif: enrollment kembali ke ACTIVE, cuti_from/until di-clear
BR-CUTI.6 : Perpanjang cuti maks 1x (total maksimal 2 bulan)
```

### Diskon Invoice
```
BR-DSK.1 : Owner atau Admin bisa tambah item DISKON ke invoice yang UNPAID/PARTIAL
BR-DSK.2 : Item DISKON wajib punya parent_item_id (FK ke item invoice yang didiskon)
BR-DSK.3 : discount_type: NOMINAL (Rp flat) atau PERCENT (% dari amount parent)
BR-DSK.4 : discount_reason WAJIB diisi saat membuat item DISKON
BR-DSK.5 : Hapus/void item DISKON hanya Owner
BR-DSK.6 : item_code untuk diskon: 'DISKON'
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
DISKON   | Diskon manual           | NOMINAL (Rp flat) atau PERCENT (% dari item parent)
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
- Kalender akademik: Owner isi replacement_date per hari libur → generator buat sesi pengganti [v1.4]
- Holiday tipe Internal (Konser KITA): honor guru Rp 0, TIDAK bisa punya replacement_date [v1.4]
- Holiday form: Alpine.js auto-suggest minggu ke-5 sebagai tanggal pengganti [v1.4]

### M04 -- Absensi
- Input 9 status per sesi setelah sesi berlangsung (termasuk CANCELLED dan DIGANTI)
- Reschedule Fase 2: Admin pilih tanggal/jam/ruang pengganti via mini-modal
- RescheduleService cek konflik guru + ruang sebelum buat sesi pengganti
- Kids Class: absensi per murid dalam grup
- Guru pengganti: set substitute_teacher_id -> honor otomatis ke pengganti

### M05 -- Keuangan Murid
- Generate invoice SPP otomatis tanggal 1 setiap bulan
- Denda harian cron job mulai tanggal 11
- Catat pembayaran cash/transfer/QRIS/debit + upload bukti foto
- Diskon per item: NOMINAL atau PERCENT, wajib isi alasan diskon
- Generate kuitansi KW/YYYY/MM/NNNN
- Cetak A4 / download PDF
- Auto-mundur warning H-7 di dashboard untuk murid tunggakan >1 bulan

### M06 -- Honor Guru
- Kalkulasi honor otomatis H-2 sebelum akhir bulan
- Generate slip SLIP/YYYY/MM/NNNN per guru
- Komponen: honor pokok (auto) + honor event (manual) + transport (manual) + lain-lain (manual + keterangan)
- Cetak slip honor: rincian per murid + info bank guru di header
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
- Guru pendamping per peserta Konser KITA: Owner/Admin assign via dropdown di halaman detail event [v1.4]
  Hanya bisa diubah selama event masih DRAFT; NULL = guru tidak mendampingi

### M09 -- Laporan & Notifikasi
- Dashboard P&L real-time (revenue, expense, aging receivable)
- Kinerja guru, retensi murid, okupansi studio
- Export laporan PDF & Excel
- Generator template broadcast WhatsApp (admin copy-paste manual)
- Audit Log Viewer (Owner only)

---

## 🚫 YANG TIDAK BOLEH DILAKUKAN

```
 Jika instruksi atau prompt ambigu, TANYA TERLEBIH DAHULU sebelum memulai coding. jangan berasumsi dan langsung mengerjakan tanpa konfirmasi.

STRUKTUR DAN FILE 
- Jangan hapus file tanpa konfirmasi
- Jangan pindahkan file tanpa konfirmasi

SKEMA DATABASE:
X Pakai packages.name      -> harus packages.code
X Pakai duration_minutes   -> harus duration_min
X Hardcode 'Kids Class'    -> pakai 'KIDS_CLASS'
X Hardcode 'Reguler'       -> pakai 'REGULER' (kapital semua)
X Akses students.package_id / assigned_teacher_id / assigned_room_id
  -> Kolom ini SUDAH DIHAPUS. Gunakan $student->primaryEnrollment->package/teacher/room
X Buat invoice tanpa enrollment_id -> selalu link ke enrollment yang relevan
X Hardcode metode bayar CASH|TRANSFER saja -> tambahkan QRIS dan DEBIT

KODE 
- jangan gunakan tipe 'any' di Typescrip

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
X Paket reguler tidak ada pembayaran partial, hanya KIDS CLASS BUNDLE yang bisa partian termin 3 bulan dengan harga bagi 3 rata

TECH STACK:
X Pakai Bootstrap CSS      -> project ini Tailwind CSS (Breeze default)
X Ganti utility class jadi class custom CSS -> ikuti pola Tailwind
X Buat role GURU           -> Fase 1 tidak ada login guru
X Push .env ke GitHub      -> berisi password database

DATABASE — PERINGATAN KRITIS:
X php artisan migrate:fresh pada database utama (mk_operasional) TANPA konfirmasi eksplisit
  -> migrate:fresh MENGHAPUS SEMUA DATA. Selalu konfirmasi dengan user sebelum dijalankan.
  -> Insiden: database production terhapus karena phpunit.xml tidak pakai SQLite in-memory.
  -> php artisan test AMAN hanya jika phpunit.xml sudah override DB_CONNECTION=sqlite + DB_DATABASE=:memory:
  -> Sebelum migrate:fresh: cek DB_CONNECTION di .env, pastikan bukan database production.
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
[ ] Akses relasi murid ↔ kelas via enrollment (bukan kolom students langsung)
[ ] Gunakan $student->primaryEnrollment untuk akses paket/guru/ruang utama
[ ] Invoice auto-generate mengikuti primary enrollment
[ ] Diskon invoice: wajib parent_item_id + discount_reason
[ ] Metode bayar: CASH|TRANSFER|QRIS|DEBIT (bukan hanya CASH|TRANSFER)
```

---

## 🎨 UI DESIGN SYSTEM

> Dicatat berdasarkan kondisi aktual kode (Mei 2026).
> Jangan ubah nilai warna ini tanpa update bagian ini juga.

### Sistem Tema

- **Dua tema**: Dark (default) dan Light, toggle via tombol ☀️/🌙 di topbar
- **State disimpan** di `localStorage` key `mk-theme` (`'dark'` atau `'light'`)
- **Alpine.js** mengontrol toggle: `:data-theme="theme"` di-set pada `<div>` root layout
- **Script inline** di `<head>` mencegah flash: baca localStorage sebelum Alpine load
- **CSS scope**: `.dark-content` (main + page-header) dan `.light-content` (main + page-header)
- **Sidebar** tetap gelap di kedua mode — tidak ikut toggle
- **File CSS utama**: `resources/css/app.css` (semua override tema di sini)
- **Font**: DM Sans (body), Playfair Display (h1 heading)

### Struktur Layout (`resources/views/layouts/app.blade.php`)

```
<body class="bg-mk-bg text-mk-text">                  ← warna dari tailwind.config mk.*
  <div :data-theme="theme">                           ← Alpine toggle
    <aside class="bg-mk-sidebar border-r ...">        ← sidebar selalu gelap
    <div class="flex-1 flex flex-col">
      <div class="mk-topbar bg-mk-sidebar ...">       ← topbar = warna sidebar
      <div class="mk-page-header bg-mk-card ...">     ← page header, dapat .dark/.light-content
      <main class="bg-mk-bg ...">                     ← konten, dapat .dark/.light-content
```

### Token Warna — `tailwind.config.js` (mk.*)

Ini adalah warna **default dark mode**. Light mode di-override via CSS `[data-theme="light"]`.

```js
mk: {
    bg:        '#1A0E06',              // body + main background
    sidebar:   '#1C1410',              // sidebar + topbar background
    card:      '#241608',              // page header background
    cardHover: '#2E1C0E',              // hover state card
    border:    'rgba(212,168,83,0.08)',// border default
    accent:    '#D4A853',              // gold accent (logo, badge aktif)
    accentDim: 'rgba(212,168,83,0.15)',// gold subtle (avatar bg)
    text:      '#EDE0CC',              // teks utama
    muted:     '#8A6848',              // teks sekunder
    dim:       '#6B4A2A',              // teks redup (tanggal, placeholder struktural)
}
```

### Override Dark Mode — `.dark-content` (di `app.css`)

Semua di-scope ke `.dark-content` agar tidak bocor ke halaman login/print.

```
Default border (bare border-b/t):  rgba(212,168,83,0.10)   ← universal * override
bg-white (card utama):             #241608
bg-gray-50 (thead, section):       rgba(212,168,83,0.04)
bg-gray-100:                       rgba(212,168,83,0.07)
hover bg:                          #2E1C0E .. #3F2618
border-gray-100/200:               rgba(212,168,83,0.10)
border-gray-300:                   rgba(212,168,83,0.16)
text-gray-900/800 (teks utama):    #EDE0CC
text-gray-700:                     #C0A882
text-gray-600:                     #8A6848
text-gray-500:                     #6B4A2A
text-indigo-600 (link):            #C8A870
Form input bg:                     #2E1C0E
Form input border:                 rgba(212,168,83,0.18)
Form input text:                   #EDE0CC
Form readonly text:                #C0A882  ← WCAG AA ~5.8:1
Form disabled text:                #4A3020
Shadow:                            rgba(18,8,2,0.55/0.65)
Scrollbar thumb:                   rgba(212,168,83,0.20/0.35)
```

Badge backgrounds dark mode (earthy, bukan neon):
```
green-50/100:   rgba(58,125,68,  0.14/0.20)   text: #6BC07A
yellow/amber:   rgba(212,168,83, 0.12/0.18)   text: #D4A853
orange-50/100:  rgba(181,101,29, 0.14/0.20)   text: #D4853A
blue/indigo:    rgba(58,97,134,  0.14/0.18)   text: #7AAAC8 / #8A9AC8
red-50/100:     rgba(176,58,46,  0.14/0.20)   text: #D07868
purple-50/100:  rgba(123,94,167, 0.14/0.18)   text: #B09AD8
```

Action buttons dark mode: blue/purple/indigo → gold `rgba(212,168,83,0.9)` teks `#1A1000`.
Green dan red button tetap semantik (tidak diubah ke gold).

Structural override dark mode (scoped ke `[data-theme="dark"]`):
```
aside border-right:        rgba(212,168,83,0.08)
.mk-topbar border-bottom:  rgba(212,168,83,0.08)
.mk-page-header border-b:  rgba(212,168,83,0.08)
hover:bg-white/5:          rgba(212,168,83,0.06)
```

### Override Light Mode — `.light-content` + `[data-theme="light"]` (di `app.css`)

```
body background:           #F2E9DC
bg-white (card):           #FBF5EC
bg-gray-50:                rgba(101,65,27,0.04)
text utama:                #2C1A07
text-gray-700:             #3D2610
text-gray-600:             #7A5C3A
text-indigo-600 (link):    #A0522D
Form input bg:             #FFFFFF
Form input border:         rgba(101,65,27,0.25)
Shadow:                    rgba(101,65,27,0.12/0.16)
Scrollbar:                 rgba(101,65,27,0.20/0.35)
Topbar bg:                 rgba(242,233,220,0.96) + blur(8px)
Topbar border:             rgba(101,65,27,0.15)
Sidebar bg (light mode):   #3B2208  ← tetap mahoni gelap
```

Action buttons light mode: blue/purple/indigo → sienna `#A0522D`.

### Aturan Penting untuk Claude

```
X Jangan hardcode warna cold/navy: #1E2235, #252B42, #161B2E, rgba(255,255,255,0.X)
  sebagai border atau background di Blade template baru.

X Jangan pakai border-b/t/l/r tanpa class warna di luar .dark-content/.light-content
  scope — akan muncul sebagai garis putih terang di dark mode.

✓ Untuk elemen baru di Blade: pakai bg-white, bg-gray-50, border-gray-200, dll.
  (Tailwind standard) — sudah di-override otomatis oleh .dark-content/.light-content.

✓ Untuk warna struktural (sidebar, topbar, page-header): pakai kelas mk-*
  dari tailwind.config (bg-mk-sidebar, bg-mk-card, text-mk-text, dll).

✓ Setelah tambah class baru yang butuh dark/light override, tambahkan ke
  resources/css/app.css di blok .dark-content dan .light-content yang sesuai.

✓ Setelah edit app.css atau tailwind.config.js: jalankan npm run build.
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

Controllers (semua di root namespace App\Http\Controllers):
App\Http\Controllers\StudentController
App\Http\Controllers\TeacherController
App\Http\Controllers\PackageController
App\Http\Controllers\RoomController
App\Http\Controllers\InstrumentController
App\Http\Controllers\HolidayController
App\Http\Controllers\ScheduleController
App\Http\Controllers\SessionController
App\Http\Controllers\AbsensiController
App\Http\Controllers\InvoiceController
App\Http\Controllers\PaymentController
App\Http\Controllers\HonorController
App\Http\Controllers\EventController
App\Http\Controllers\ImportController
App\Http\Controllers\EnrollmentController
App\Http\Controllers\DashboardController
App\Http\Controllers\ReportController
App\Http\Controllers\AuditLogController

Services:
App\Services\HonorCalculationService
App\Services\SessionGeneratorService
App\Services\InvoiceGeneratorService
App\Services\AutoMundurService
App\Services\TrialManagementService
App\Services\RescheduleService
```

### Struktur Folder
```
app/
  Http/
    Controllers/      <- Semua controller di root (TIDAK ada subfolder Admin/)
    Requests/         <- Form Request validasi
  Models/
  Services/           <- Business logic kompleks
  Policies/           <- Authorization per model

resources/views/
  absensi/            <- Halaman absensi harian (M04)
  audit-logs/
  events/
  expenses/
  holidays/
  honors/
  imports/
  instruments/
  invoices/
  packages/
  payments/
  reports/
  rooms/
  sessions/
  students/
  teachers/
  layouts/            <- Layout template (app.blade.php, navigation.blade.php)
  components/         <- Blade components (sidebar-item, dll)

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
php artisan make:controller NamaController --resource
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
*Versi: 1.3 | Mei 2026*