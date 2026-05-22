# Spesifikasi Fitur: Multi-Kelas per Murid

**Tanggal:** 2026-05-22
**Status:** Disetujui — siap implementasi

---

## Latar Belakang

Saat ini satu murid hanya bisa terdaftar di satu kelas (satu instrumen). Beberapa murid ingin mengambil dua instrumen sekaligus (contoh: Drum + Gitar). Fitur ini memungkinkan satu murid memiliki N kelas aktif sekaligus, masing-masing dengan paket, guru, jadwal, dan invoice SPP tersendiri.

---

## Keputusan Arsitektur

### Pendekatan: Primary Pointer

- `students` tidak lagi menyimpan `package_id`, `assigned_teacher_id`, dll secara langsung
- Semua data kelas hidup di tabel `enrollments` (N baris per murid)
- `students.primary_enrollment_id` menunjuk ke satu enrollment sebagai "kelas utama" untuk keperluan tampilan
- View lama tetap berjalan via accessor `$student->package` yang di-delegasikan ke `primaryEnrollment`

### Invoice: Per Enrollment

- Setiap enrollment aktif menghasilkan 1 invoice SPP per bulan
- Invoice dibedakan dengan label instrumen di deskripsi dan `enrollment_id` FK
- Denda dihitung per invoice masing-masing
- Tampilan di halaman murid: satu daftar, tiap baris ada tag instrumen berwarna

### CUTI: Global (untuk sekarang)

- CUTI tetap berlaku untuk semua kelas sekaligus (`student.status = 'Cuti'`)
- Schema `enrollments.status` sudah menyertakan `ON_LEAVE` untuk future-proofing
- `cuti_from` dan `cuti_until` dipindah dari `students` ke `enrollments`
- Saat student CUTI global: semua enrollment-nya di-set `ON_LEAVE`

### Import Murid: Satu Kelas per Baris

- Template Excel tidak berubah — import hanya menangani kelas utama
- Kelas ke-2 ditambahkan manual via UI setelah import selesai

---

## Perubahan Schema

### Tabel `students` — kolom dihapus dan ditambah

```sql
-- Dihapus
ALTER TABLE students
  DROP COLUMN package_id,
  DROP COLUMN assigned_teacher_id,
  DROP COLUMN assigned_room_id,
  DROP COLUMN preferred_day,
  DROP COLUMN preferred_time;

-- Ditambah
ALTER TABLE students
  ADD COLUMN primary_enrollment_id BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_students_primary_enrollment
    FOREIGN KEY (primary_enrollment_id) REFERENCES enrollments(id)
    ON DELETE SET NULL;
```

**Data migration:** Isi `primary_enrollment_id` dari enrollment ACTIVE masing-masing murid yang ada.

### Tabel `enrollments` — kolom ditambah, enum diperluas

```sql
ALTER TABLE enrollments
  ADD COLUMN is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  MODIFY COLUMN status ENUM('ACTIVE','ON_LEAVE','INACTIVE','COMPLETED')
    NOT NULL DEFAULT 'ACTIVE';
```

**Data migration:** Set `is_primary = TRUE` untuk semua enrollment ACTIVE yang saat ini ada.

**Catatan `ON_LEAVE`:** Belum dipakai oleh UI maupun kode apapun. Groundwork untuk per-enrollment CUTI di masa depan. CUTI saat ini tetap dikelola di level student (`students.cuti_from`, `students.cuti_until` tidak dipindah). `SessionGeneratorService` sudah skip murid dengan `student.status != 'Aktif'` — tidak perlu set tiap enrollment ke `ON_LEAVE` untuk kasus CUTI global.

### Tabel `invoices` — kolom ditambah

```sql
ALTER TABLE invoices
  ADD COLUMN enrollment_id BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_invoices_enrollment
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
    ON DELETE SET NULL;
```

**Nullable:** Invoice lama (REG, CUTI, UJI, MC, KIDS_FP) tidak terkait enrollment tertentu — `enrollment_id` dibiarkan NULL. Hanya invoice SPP bulanan yang diisi `enrollment_id`.

### Tabel `schedules`, `class_sessions` — tidak berubah

`SessionGeneratorService` dan `HonorCalculationService` sudah bekerja per-enrollment secara natural — tidak ada perubahan yang dibutuhkan.

---

## Komponen yang Dibangun

### 1. Migration

Satu file migration berisi semua perubahan schema di atas + data migration (isi `primary_enrollment_id` dan `is_primary` dari data existing).

### 2. Model Updates

**`Student` model:**
- Hapus relasi `package()`, `assignedTeacher()`, `assignedRoom()`
- Tambah relasi `primaryEnrollment(): BelongsTo`
- Tambah accessor untuk backward compatibility:
  ```php
  public function getPackageAttribute()     { return $this->primaryEnrollment?->package; }
  public function getAssignedTeacherAttribute() { return $this->primaryEnrollment?->teacher; }
  ```
- Hapus `package_id`, `assigned_teacher_id`, `assigned_room_id`, `preferred_day`, `preferred_time` dari `$fillable`
- Tambah `primary_enrollment_id` ke `$fillable`

**`Enrollment` model:**
- Tambah `is_primary`, `cuti_from`, `cuti_until` ke `$fillable`
- Update `$casts` untuk `cuti_from` dan `cuti_until` sebagai `date`
- `scopeActive()` tetap — filter `status = 'ACTIVE'` sudah benar

### 3. EnrollmentController (baru)

**Routes:**
```
POST   /students/{student}/enrollments          → store      [Owner|Admin]
PATCH  /students/{student}/enrollments/{enrollment}/primary → setPrimary [Owner|Admin]
DELETE /students/{student}/enrollments/{enrollment}        → destroy    [Owner|Admin]
```

**`store` — Tambah Kelas:**
1. Validasi: instrumen, paket, guru (harus mengajar instrumen tsb), ruangan, hari, jam_mulai, tanggal_efektif, jadikan_utama (boolean)
2. Cek konflik jadwal guru dan ruangan via `ScheduleConflictDetector`
3. Buat `Enrollment` baru dengan `status = ACTIVE`, `is_primary = false`
4. Jika `jadikan_utama = true`: set enrollment lama `is_primary = false`, set enrollment baru `is_primary = true`, update `student.primary_enrollment_id`
5. Buat `Schedule` untuk enrollment baru (hari, jam, ruangan)
6. Catat audit log

**`setPrimary` — Jadikan Kelas Utama:**
1. Validasi: enrollment harus milik student ini, status harus ACTIVE
2. Set semua enrollment murid `is_primary = false`
3. Set enrollment target `is_primary = true`
4. Update `student.primary_enrollment_id`
5. Catat audit log

**`destroy` — Hentikan Kelas:**
1. Validasi: enrollment harus milik student ini, status harus ACTIVE
2. Jika `is_primary = true`:
   - Cek apakah ada enrollment ACTIVE lain
   - Jika ada: tampilkan konfirmasi ke admin untuk pilih kelas utama baru (return response dengan daftar kelas lain)
   - Setelah admin konfirmasi: jalankan `setPrimary` untuk kelas yang dipilih
3. Set `enrollment.status = INACTIVE`, `enrollment.end_date = today`
4. Set semua schedule enrollment ini `is_active = false`
5. Invoice yang belum lunas: dibiarkan tetap ada (admin void manual jika diperlukan)
6. Invoice bulan berikutnya: tidak akan di-generate karena enrollment sudah INACTIVE
7. Catat audit log

### 4. Views — Tab "Kelas" di Halaman Murid

File baru: `resources/views/students/partials/tab-kelas.blade.php`

**Struktur:**
```
Section "Kelas Berjalan"                    [+ Tambah Kelas]
├─ [★ Kelas Utama] Drum — Basic 30'
│   Guru: Thomas · Senin 15:00 · Studio 8 · Mulai 1 Mar 2026
│   Badge: Berjalan                         [Hentikan]
└─ Gitar — Hobby 30'
    Guru: Nael · Rabu 16:00 · Studio 4 · Mulai 1 Mei 2026
    Badge: Berjalan             [Jadikan Utama] [Hentikan]

Section "Riwayat Kelas"
└─ Piano — Level 1 30'
    Guru: Adi · Jan 2025 – Jun 2025
    Badge: Selesai
```

**Form "Tambah Kelas"** (modal/inline):
- Instrumen → Paket (filtered by instrumen) → Guru (filtered by instrumen) → Ruangan
- Hari, Jam Mulai, Berlaku Mulai
- Jadikan Kelas Utama? (default: Tidak)
- Tombol: Batal | Simpan & Buat Jadwal

**Konfirmasi "Hentikan Kelas Utama":**
- Muncul hanya jika yang dihentikan adalah kelas utama dan ada kelas lain
- Dropdown: "Jadikan [nama kelas lain] sebagai kelas utama baru"
- Tombol: Batal | Hentikan & Ganti Utama

### 5. InvoiceService — Update `generateMonthlySpp()`

**Sebelum:** Loop murid Aktif → ambil `student.package_id` → buat 1 invoice SPP

**Sesudah:** Loop murid Aktif → loop `student.enrollments()->active()` → buat 1 invoice SPP per enrollment

```php
// Pseudocode perubahan
foreach ($activeStudents as $student) {
    $activeEnrollments = $student->enrollments()->active()->with('package')->get();
    foreach ($activeEnrollments as $enrollment) {
        $this->createSppInvoice($student, $enrollment, $year, $month);
        // invoice.enrollment_id = $enrollment->id
        // deskripsi: "SPP {bulan} — {instrumen}"
    }
}
```

Invoice SPP yang di-generate menyertakan `enrollment_id` dan label instrumen di deskripsi. Di view daftar invoice murid, tiap baris ditampilkan dengan tag instrumen berwarna.

### 6. StudentLifecycleService — Hapus Guard Single Enrollment

Hapus validasi yang saat ini mencegah lebih dari 1 enrollment ACTIVE per murid.

CUTI tidak memerlukan perubahan tambahan: `SessionGeneratorService` sudah memfilter
`student.status = 'Aktif'` — kalau murid Cuti, semua sesi-nya otomatis tidak di-generate
tanpa perlu menyentuh status tiap enrollment.

### 7. StudentImportService — Tidak Berubah

Template Excel tidak berubah. Import tetap membuat 1 enrollment (kelas utama, `is_primary = true`) per murid. Kelas ke-2 ditambahkan via UI setelah import.

---

## Business Rules Tambahan

```
BR-MK-1 : Satu murid boleh punya N kelas ACTIVE sekaligus (tidak ada batas atas)
BR-MK-2 : Setiap murid wajib punya tepat 1 enrollment dengan is_primary = true
           selama status murid = Aktif
BR-MK-3 : Invoice SPP di-generate per enrollment ACTIVE, bukan per murid
BR-MK-4 : Denda dihitung per invoice secara independen
BR-MK-5 : Hentikan kelas → invoice bulan berjalan yang belum lunas tetap ada;
           admin bisa void manual (Owner only, sesuai BR-5.18)
BR-MK-6 : Menghentikan kelas utama wajib diikuti konfirmasi pilih kelas utama baru
           jika masih ada kelas lain yang ACTIVE
BR-MK-7 : CUTI murid tetap global — dikelola via student.status = 'Cuti';
           SessionGeneratorService tidak generate sesi untuk murid non-Aktif.
           enrollments.status tidak diubah saat CUTI global.
BR-MK-8 : Guru tidak boleh mengajar di 2 jadwal yang overlap —
           ScheduleConflictDetector sudah handle ini, tidak ada perubahan
BR-MK-9 : Saat tambah kelas, paket dipilih berdasarkan instrumen yang dipilih
           (bukan bebas lintas instrumen)
```

---

## Yang Tidak Berubah

- `SessionGeneratorService` — sudah loop per schedule/enrollment, natural support multi-kelas
- `HonorCalculationService` — sudah aggregate per teacher per session, tidak terdampak
- `ScheduleConflictDetector` — sudah cek konflik guru dan ruangan, tidak terdampak
- Template import Excel — tidak berubah
- Tabel `schedules`, `class_sessions` — tidak ada perubahan schema

---

## Catatan untuk Implementasi

- Accessor `$student->package` dan `$student->assignedTeacher` harus tetap berfungsi
  agar semua view existing (index, daftar invoice, dll) tidak perlu diubah satu per satu
- Data migration (isi `primary_enrollment_id` dan `is_primary`) harus dijalankan
  di dalam migration yang sama agar tidak ada jeda state tidak konsisten
- Urutan migration penting: tambah kolom baru dulu, jalankan data migration,
  baru hapus kolom lama dari `students`
