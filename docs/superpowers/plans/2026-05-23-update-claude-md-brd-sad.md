# Update CLAUDE.md — Sinkronisasi BRD/SAD ke Kondisi Aktual (v1.3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update CLAUDE.md agar akurat mencerminkan semua perubahan schema, fitur baru, dan keputusan arsitektur yang terjadi sejak v1.2 (2026-05-07).

**Architecture:** Edit langsung CLAUDE.md — tidak ada perubahan kode aplikasi. Semua perubahan bersifat dokumentasi murni berdasarkan migrasi dan model aktual yang sudah di-commit.

**Tech Stack:** Markdown (CLAUDE.md)

---

## Ringkasan Perubahan yang Perlu Didokumentasi

Sejak v1.2 (2026-05-07), perubahan berikut sudah ada di kode tapi belum di CLAUDE.md:

| Area | Perubahan |
|------|-----------|
| `students` schema | Hapus 5 kolom lama, tambah `primary_enrollment_id`, `cuti_from`, `cuti_until` |
| `enrollments` schema | Tambah `is_primary`, enum status tambah `ON_LEAVE` |
| `invoices` schema | Tambah `enrollment_id`, `class_type`, `payment_mode`, `installment_*` |
| `invoice_items` schema | Tambah `parent_item_id`, `discount_type`, `discount_value`, `discount_reason` |
| `payments` schema | Method enum tambah `QRIS\|DEBIT`, tambah `notes`, `created_by`, `voided_reason` |
| `invoice_components` | Tabel baru (katalog item tagihan, dikelola Owner) |
| `rooms` schema | Ganti 3 boolean → `supported_instruments` JSON |
| `teacher_honor_slips` schema | Tambah `event_honor`, `event_honor_note`, `paid_by`, `created_by` |
| `teachers` schema | Tambah `bank_name`, `bank_account`, `bank_account_holder` |
| `class_sessions` schema | Status enum tambah `CANCELLED` |
| Business rules | Multi-kelas, diskon invoice, cuti (BR), QRIS/DEBIT, reschedule Fase 2 |
| Services | Tambah `RescheduleService` |
| Controllers | Tambah `EnrollmentController` |
| PERINGATAN kolom | Update warning students — kolom lama sudah dihapus |
| Versi | v1.2 → v1.3 |

---

## File yang Dimodifikasi

- Modify: `CLAUDE.md` (semua task di bawah menyentuh file ini)

---

## Task 1: Update Header Versi + Tanggal

**File:** `CLAUDE.md` baris 1-5

- [ ] **Step 1: Ganti baris header versi**

Cari teks:
```
> Dibuat berdasarkan: BRD-Final-Musik-KITA-v1.0.md + Revisi-BRD-SAD-v1.0-ke-v1.1.md
> Update v1.2 (2026-05-07): sinkronisasi tech stack & schema dengan kondisi kode aktual.
> Tanggal: Mei 2026
```

Ganti dengan:
```
> Dibuat berdasarkan: BRD-Final-Musik-KITA-v1.0.md + Revisi-BRD-SAD-v1.0-ke-v1.1.md
> Update v1.2 (2026-05-07): sinkronisasi tech stack & schema dengan kondisi kode aktual.
> Update v1.3 (2026-05-23): multi-kelas, diskon invoice, cuti, reschedule Fase 2, QRIS/DEBIT, slip honor unifikasi, ruangan fleksibel.
> Tanggal: Mei 2026
```

- [ ] **Step 2: Ganti baris `## 🖥️ ENVIRONMENT & TECH STACK` — versi di akhir file**

Cari:
```
*Versi: 1.1 | Mei 2026*
```

Ganti dengan:
```
*Versi: 1.3 | Mei 2026*
```

---

## Task 2: Update Skema `students`

**File:** `CLAUDE.md` — seksi `**students**`

Konteks: Migrasi `2026_05_22_060000_multi_kelas_schema.php` menghapus 5 kolom enrollment dari tabel students dan menambah `primary_enrollment_id`. Migrasi `2026_05_22_045126_add_cuti_columns_to_students_table.php` menambah `cuti_from` dan `cuti_until`.

- [ ] **Step 1: Ganti blok skema students**

Cari:
```
**students** (skema actual setelah implementasi M02)
```
sampai baris:
```
last_session_at, timestamps
```

Ganti seluruh blok itu dengan:
```
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

CATATAN KRITIS: Kolom `package_id`, `assigned_teacher_id`, `assigned_room_id`,
`preferred_day`, `preferred_time` SUDAH DIHAPUS di migrasi multi-kelas (Mei 2026).
Semua relasi kelas sekarang via tabel `enrollments`.
Untuk akses paket/guru/ruang aktif murid: gunakan `student->primaryEnrollment->package` dll.
```

---

## Task 3: Update Skema `enrollments`

**File:** `CLAUDE.md` — seksi `**enrollments**`

Konteks: Migrasi `2026_05_22_060000_multi_kelas_schema.php` menambah `is_primary` dan memperluas enum status dengan `ON_LEAVE`.

- [ ] **Step 1: Ganti blok skema enrollments**

Cari:
```
**enrollments**
```
id, student_id, package_id, teacher_id,
effective_date, end_date,
status (enum: ACTIVE|INACTIVE|COMPLETED), timestamps
```

Ganti dengan:
```
**enrollments**
```
id, student_id, package_id, teacher_id,
is_primary (boolean, default false — satu enrollment utama per murid),
effective_date, end_date, notes,
status (enum: ACTIVE|ON_LEAVE|INACTIVE|COMPLETED), timestamps
```

CATATAN: `ON_LEAVE` diset saat murid mengajukan cuti. Kembali ke `ACTIVE` saat cuti berakhir.
`is_primary` menentukan enrollment yang dipakai untuk generate invoice SPP otomatis.
```

---

## Task 4: Update Skema `invoices`

**File:** `CLAUDE.md` — seksi `**invoices**`

Konteks: Migrasi `2026_05_15_210608_add_installment_fields_to_invoices_table.php` menambah kolom cicilan Kids Class Bundle. Migrasi `2026_05_22_060000_multi_kelas_schema.php` menambah `enrollment_id`.

- [ ] **Step 1: Ganti blok skema invoices**

Cari:
```
**invoices**
```
id, invoice_number (INV/YYYY/MM/NNNN),
student_id, month, year,
total_amount, paid_amount,
status (enum: UNPAID|PARTIAL|PAID), due_date, timestamps
```

Ganti dengan:
```
**invoices**
```
id, invoice_number (INV/YYYY/MM/NNNN),
student_id, enrollment_id (FK → enrollments, nullable),
month, year,
class_type (snapshot class_type paket saat dibuat, nullable),
payment_mode (enum: FULL|INSTALLMENT, default FULL),
installment_number (nullable, nilai 1|2|3 — urutan termin),
installment_group_id (UUID string, nullable — pengikat 3 invoice cicilan),
total_amount, paid_amount,
status (enum: UNPAID|PARTIAL|PAID), due_date, timestamps
```

CATATAN: `payment_mode=INSTALLMENT` hanya untuk `class_type=KIDS_CLASS_BUNDLE`.
Tiga invoice cicilan diikat oleh `installment_group_id` yang sama.
```

---

## Task 5: Update Skema `invoice_items` + `payments`

**File:** `CLAUDE.md` — seksi `**invoice_items**` dan `**payments**`

Konteks: Diskon per item (migrasi `2026_05_22_013620`). Metode QRIS/DEBIT (migrasi `2026_05_07_150000`). Payments ditambah `notes`, `created_by`, `voided_reason`.

- [ ] **Step 1: Ganti blok skema invoice_items**

Cari:
```
**invoice_items**
```
id, invoice_id,
item_code (enum: REG|SPP|KIDS_FP|CUTI|UJI|MC|DENDA),
description, amount, timestamps
```

Ganti dengan:
```
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
```

- [ ] **Step 2: Ganti blok skema payments**

Cari:
```
**payments**
```
id, invoice_id, amount,
method (enum: CASH|TRANSFER),
payment_date, proof_image,
receipt_number (KW/YYYY/MM/NNNN),
voided_at, voided_by, timestamps
```

Ganti dengan:
```
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

---

## Task 6: Tambah Tabel `invoice_components`, Update `rooms`, `teachers`, `teacher_honor_slips`, `sessions`

**File:** `CLAUDE.md` — beberapa seksi skema

- [ ] **Step 1: Tambah tabel `invoice_components` setelah blok `invoice_items`**

Sisipkan blok baru setelah seksi `invoice_items`:

```
**invoice_components** (katalog item tagihan — dikelola Owner via M01 Master Data)
```
id, code (unique), name,
type (enum: REGULER|TRIAL|KIDS_FINAL|CUTI|UJIAN|MINI_CONCERT|DENDA),
amount_or_formula (string — nominal Rp atau rumus, misal "package_price * 0.5 / 4"),
description (nullable),
is_active, sort_order, timestamps
```

CATATAN: Owner bisa tambah/edit item tagihan via master data. Admin bisa pilih item dari katalog
ini saat tambah baris manual ke invoice.
```

- [ ] **Step 2: Ganti blok `rooms` — tambahkan keterangan `supported_instruments`**

Cari blok skema rooms (jika ada di CLAUDE.md) atau tambahkan seksi baru.
Jika belum ada, tambahkan sesudah seksi `teacher_honor_slips`:

```
**rooms**
```
id, code, name, capacity,
supported_instruments (JSON, nullable — array instrumen yang didukung, misal ["Piano","Gitar"]),
is_active, timestamps
```

CATATAN: Kolom boolean `has_piano`, `has_drum`, `has_amplifier` SUDAH DIHAPUS (Mei 2026).
Diganti `supported_instruments` JSON untuk fleksibilitas instrumen baru.
```

- [ ] **Step 3: Ganti blok skema `teachers` — tambah field bank**

Cari blok skema teachers (jika ada) atau tambahkan keterangan di seksi M01.
Tambahkan keterangan fields bank:

```
**teachers**
```
id, code, name, email, phone,
bank_name (nullable), bank_account (nullable), bank_account_holder (nullable),
joined_date, is_active, notes, timestamps
```

CATATAN: Field bank (`bank_name`, `bank_account`, `bank_account_holder`) ditambah Mei 2026
untuk ditampilkan di slip honor cetak.
```

- [ ] **Step 4: Ganti blok skema `teacher_honor_slips`**

Cari:
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

Ganti dengan:
```
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
`event_honor_slips` dihapus dan honor event digabungkan ke slip honor utama guru.
```

- [ ] **Step 5: Update blok skema `sessions` — tambah status CANCELLED**

Cari:
```
status (enum: SCHEDULED|HADIR|HADIR_TERLAMBAT|IZIN_RESCHEDULE|
              IZIN_VIDEO|HANGUS|LIBUR|DIGANTI),
```

Ganti dengan:
```
status (enum: SCHEDULED|HADIR|HADIR_TERLAMBAT|IZIN_RESCHEDULE|
              IZIN_VIDEO|HANGUS|LIBUR|DIGANTI|CANCELLED),
```

---

## Task 7: Update Business Rules — Multi-Kelas

**File:** `CLAUDE.md` — seksi `## 📋 BUSINESS RULES KRITIS`

- [ ] **Step 1: Tambah sub-seksi baru "Multi-Kelas" setelah seksi "Kids Class"**

```markdown
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
BR-MK.8 : Hentikan semua enrollment → murid otomatis mundur
```
```

---

## Task 8: Update Business Rules — Diskon + QRIS/DEBIT + Cuti + Reschedule

**File:** `CLAUDE.md` — seksi `## 📋 BUSINESS RULES KRITIS`

- [ ] **Step 1: Tambah sub-seksi "Diskon Invoice" setelah "Tagihan & Denda"**

```markdown
### Diskon Invoice
```
BR-DSK.1 : Owner atau Admin bisa tambah item DISKON ke invoice yang masih UNPAID/PARTIAL
BR-DSK.2 : Item DISKON wajib punya parent_item_id (FK ke item invoice yang didiskon)
BR-DSK.3 : discount_type: NOMINAL (Rp flat) atau PERCENT (% dari amount parent)
BR-DSK.4 : discount_reason WAJIB diisi saat membuat item DISKON
BR-DSK.5 : Hapus/void item DISKON hanya Owner
BR-DSK.6 : item_code untuk diskon: 'DISKON'
```
```

- [ ] **Step 2: Update BR-5 (Tagihan & Denda) — tambah metode bayar baru**

Cari baris:
```
BR-5.18 : Void pembayaran hanya bisa dilakukan OWNER (bukan Admin)
```

Tambahkan setelah baris tersebut:
```
BR-5.19 : Metode pembayaran yang valid: CASH, TRANSFER, QRIS, DEBIT
```

- [ ] **Step 3: Tambah sub-seksi "Cuti Murid" setelah "Penjadwalan"**

```markdown
### Cuti Murid
```
BR-CUTI.1 : Saat Admin mengajukan cuti, students.cuti_from dan cuti_until diisi
BR-CUTI.2 : Enrollment aktif berubah status → ON_LEAVE saat cuti dimulai
BR-CUTI.3 : Sesi tidak di-generate untuk enrollment dengan status ON_LEAVE
BR-CUTI.4 : Biaya cuti Rp 100.000 wajib dibayar saat pengajuan (BR-5.x existing)
BR-CUTI.5 : Saat kembali Aktif: enrollment kembali ke ACTIVE, cuti_from/until di-clear
BR-CUTI.6 : Perpanjang cuti maks 1x (total maksimal 2 bulan, sesuai BR existing)
```
```

- [ ] **Step 4: Update seksi "Penjadwalan" — tambah keterangan Reschedule Fase 2**

Cari:
```
BR-3.9  : Pemindahan jadwal mingguan tetap BOLEH dalam bulan yang sama [REVISI v1.1]
```

Tambahkan sesudahnya:
```
BR-3.10 : Reschedule Fase 2 — sesi pengganti dibuat otomatis oleh RescheduleService
BR-3.11 : Conflict detection saat reschedule: 1 guru tidak boleh 2 sesi bersamaan
BR-3.12 : Conflict detection saat reschedule: 1 ruang tidak boleh 2 sesi bersamaan
BR-3.13 : Admin input tanggal, jam, ruang pengganti via mini-modal di halaman absensi
```

---

## Task 9: Update Komponen Tagihan + Honor — Tambah DISKON

**File:** `CLAUDE.md` — seksi `## 📊 KOMPONEN TAGIHAN` dan `### Honor Guru — 9 Skenario`

- [ ] **Step 1: Tambah DISKON ke tabel Komponen Tagihan**

Cari baris:
```
DENDA    | Denda keterlambatan     | Rp 5.000 x MAX(0, hari - 10)
```

Tambahkan setelah baris tersebut:
```
DISKON   | Diskon manual           | NOMINAL (Rp flat) atau PERCENT (% dari item parent)
```

- [ ] **Step 2: Update deskripsi honor_slips di seksi M06**

Cari di seksi `### M06 -- Honor Guru`:
```
- Komponen: honor pokok (auto) + transport (manual) + lain-lain (manual + keterangan)
```

Ganti dengan:
```
- Komponen: honor pokok (auto) + honor event (manual) + transport (manual) + lain-lain (manual + keterangan)
- Honor event: input manual oleh Owner — untuk event Mini Concert, Ujian, dll
- Cetak slip honor: tampilkan rincian per murid + info bank guru di header
```

---

## Task 10: Update PERINGATAN Nama Kolom + Checklist + Services/Controllers

**File:** `CLAUDE.md` — seksi PERINGATAN, CHECKLIST, Naming Convention

- [ ] **Step 1: Update blok PERINGATAN — NAMA KOLOM YANG SERING SALAH**

Cari:
```
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
```

Ganti dengan:
```
### PERINGATAN -- NAMA KOLOM YANG SERING SALAH

```
BENAR                                   SALAH (jangan pakai)
packages.code                           packages.name
packages.duration_min                   packages.duration_minutes
packages.price_per_month                packages.price
students.full_name                      students.name
students.student_code                   students.code
student->primaryEnrollment->package     student->package (accessor lama SUDAH TIDAK ADA)
student->primaryEnrollment->teacher     student->teacher (accessor lama SUDAH TIDAK ADA)
enrollments.is_primary                  students.package_id (kolom ini SUDAH DIHAPUS)
```

KRITIS: Kolom students.package_id, assigned_teacher_id, assigned_room_id,
preferred_day, preferred_time SUDAH DIHAPUS di migrasi multi-kelas (Mei 2026).
Jangan gunakan kolom-kolom tersebut di kode baru — akan error.

CATATAN: Honor per sesi TIDAK disimpan sebagai kolom di `packages`.
Honor dihitung dari formula PayrollConfig (mis. `package_price * 0.5 / 4`).
```

- [ ] **Step 2: Update CHECKLIST — tambah item multi-kelas**

Cari baris checklist terakhir di seksi `## ✅ CHECKLIST SEBELUM GENERATE CODE`:
```
[ ] Role/permission check sudah ada (Spatie Permission)
```

Tambahkan sesudahnya:
```
[ ] Akses relasi murid ↔ kelas via enrollment (bukan kolom students langsung)
[ ] Gunakan student->primaryEnrollment untuk akses paket/guru/ruang utama
[ ] Invoice auto-generate mengikuti primary enrollment
[ ] Diskon invoice: wajib parent_item_id + discount_reason
[ ] Metode bayar: CASH|TRANSFER|QRIS|DEBIT (bukan hanya CASH|TRANSFER)
```

- [ ] **Step 3: Update bagian `## 🚫 YANG TIDAK BOLEH DILAKUKAN` — tambah larangan baru**

Cari blok:
```
DATABASE — PERINGATAN KRITIS:
```

Tambahkan sebelum baris tersebut:
```
X Akses students.package_id / assigned_teacher_id / assigned_room_id
  -> Kolom ini SUDAH DIHAPUS. Gunakan student->primaryEnrollment->package/teacher/room
X Buat invoice tanpa set enrollment_id     -> selalu link invoice ke enrollment yang relevan
X Hardcode metode bayar CASH|TRANSFER saja -> tambahkan QRIS dan DEBIT
```

- [ ] **Step 4: Tambah `RescheduleService` dan `EnrollmentController` ke daftar naming convention**

Cari baris:
```
App\Services\AutoMundurService
```

Tambahkan sesudahnya:
```
App\Services\RescheduleService
```

Cari baris di daftar Controllers:
```
App\Http\Controllers\DashboardController
```

Tambahkan sesudahnya:
```
App\Http\Controllers\EnrollmentController
```

---

## Task 11: Commit

- [ ] **Step 1: Review perubahan**

```bash
git diff CLAUDE.md
```

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "Docs: Update CLAUDE.md v1.3 — sinkronisasi schema + fitur post-05-07"
```

---

## Self-Review Checklist

- [x] **Spec coverage:** Semua 39 migrasi yang ada sudah dicek — perubahan yang belum terdokumentasi sudah masuk ke plan
- [x] **Placeholder scan:** Semua task punya konten aktual (bukan TBD/TODO)
- [x] **Konsistensi:** Nama kolom, enum values, dan FK konsisten dengan kode aktual di migrations + models
- [x] **Breaking changes:** Warning `students.package_id` sudah masuk di Task 10 (PERINGATAN) dan Task 2 (schema)
- [x] **Gap check:** `invoice_components` tabel baru → Task 6. `rooms.supported_instruments` → Task 6. `event_honor` unifikasi → Task 6.

---

*Plan ini dibuat 2026-05-23. Semua perubahan berbasis migrasi committed di branch main.*
