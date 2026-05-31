# SRS — Musik KITA Operations System

| Field | Nilai |
|-------|--------|
| **Versi** | 2026-05-31 |
| **Status** | Sesuai implementasi aktual di repository |
| **Source of truth teknis** | `routes/web.php`, `database/migrations/`, `app/Services/`, `routes/console.php` |
| **Dokumen pendukung** | `CLAUDE.md`, `docs/ONBOARDING.md`, `.cursor/rules/*.mdc` |

---

## 1. Tujuan & Ruang Lingkup

### 1.1 Tujuan

Sistem administrasi dan keuangan internal studio **Musik KITA** menggantikan proses Excel: murid, jadwal, absensi, tagihan, honor, event, pengeluaran, laporan.

### 1.2 Lingkungan

| Aspek | Nilai |
|--------|--------|
| Framework | Laravel 11, PHP 8.3 |
| DB produksi | MySQL via Laragon (`mk_operasional`) |
| DB test | SQLite in-memory (`phpunit.xml`) |
| Frontend | Blade + Tailwind 3 + Alpine.js (Breeze) |
| Auth | Laravel Breeze + Spatie Permission v6 |
| Build | Vite |
| Deploy | LAN lokal offline |

### 1.3 Scope IN (terimplementasi)

- RBAC: **Owner**, **Admin**, **Auditor**, **Guru**
- M01–M09 sesuai modul di `docs/srs/modules/`
- Import murid Excel, manajemen user, portal Guru, laporan progres murid

### 1.4 Scope OUT / belum penuh

| Item | Keterangan |
|------|------------|
| `AutoMundurService` | **Tidak ada** di codebase; hanya notifikasi overdue (`students:check-overdue`) |
| Payment gateway / WhatsApp API | Tidak ada (Fase 1 offline) |
| Auto-mundur otomatis (cron) | BR ada; eksekusi withdraw otomatis belum sebagai command terjadwal |
| Export laporan Excel/PDF suite lengkap | `ReportController` terbatas (`students`, `finance`) |
| Broadcast WA template | Tidak ada di controller |

---

## 2. Peran & Hak Akses

Sumber: `routes/web.php`.

| Role | Write operasional | Write sensitif | Read |
|------|-------------------|----------------|------|
| **Owner** | Semua operasional | Harga paket, payroll, invoice-component, import, void payment, honor calculate/mark-paid, event write, hapus expense/kategori, users, report-templates | Semua + finance + audit log |
| **Admin** | Murid, jadwal, absensi, invoice items, bayar, diskon, event peserta, expenses (tanpa delete) | — | Index/show |
| **Auditor** | — | — | Index/show |
| **Guru** | Absensi sesi sendiri, saran `IZIN_PENDING`, laporan progres | — | Portal `/guru/*` |

**Akun seed:** `owner@musikkita.local`, `admin@`, `auditor@` — password `password` (ganti sebelum live).

**Login:** email atau **username**.

**Route order:** grup write dengan path statis (`/students/create`) harus didaftarkan **sebelum** wildcard read (`/students/{student}`).

---

## 3. Arsitektur

```
HTTP → routes/web.php (auth + role)
     → App\Http\Controllers\ (tanpa subfolder Admin/)
     → Form Requests
     → app/Services/
     → Models → MySQL
```

### Scheduled tasks (`routes/console.php`)

| Command | Jadwal |
|---------|--------|
| `sessions:generate-month` | Tgl 25, 06:00 |
| `invoices:generate-spp` | Tgl 1, 06:00 |
| `honor:calculate` | Harian 06:00, hanya H-2 akhir bulan |
| `invoices:apply-fines` | Harian 06:00, day ≥ 11 |
| `students:check-overdue` | Tgl 1, 06:05 |

Setup Windows Task Scheduler: `docs/SCHEDULER.md` (jika ada).

---

## 4. Modul (detail per file)

| Modul | File SRS |
|-------|----------|
| M01 Master Data | [modules/M01-master-data.md](./modules/M01-master-data.md) |
| M02 Pendaftaran | [modules/M02-pendaftaran.md](./modules/M02-pendaftaran.md) |
| M03 Penjadwalan | [modules/M03-penjadwalan.md](./modules/M03-penjadwalan.md) |
| M04 Absensi | [modules/M04-absensi.md](./modules/M04-absensi.md) |
| M05 Keuangan | [modules/M05-keuangan.md](./modules/M05-keuangan.md) |
| M06 Honor | [modules/M06-honor.md](./modules/M06-honor.md) |
| M07 Pengeluaran | [modules/M07-pengeluaran.md](./modules/M07-pengeluaran.md) |
| M08 Event | [modules/M08-event.md](./modules/M08-event.md) |
| M09 Laporan | [modules/M09-laporan.md](./modules/M09-laporan.md) |
| Portal Guru | [modules/M10-guru-portal.md](./modules/M10-guru-portal.md) |
| Laporan Progres | [modules/M11-laporan-progres.md](./modules/M11-laporan-progres.md) |

---

## 5. Skema Data — Peringatan Global

| Benar | Salah / dihapus |
|-------|------------------|
| `packages.code` | `packages.name` |
| `packages.duration_min` | `duration_minutes` |
| `students.full_name` | `students.name` |
| `$student->primaryEnrollment` | `$student->package` |
| `students.package_id`, `assigned_teacher_id`, dll. | **Dihapus** — pakai `enrollments` |

### `packages.class_type` (MySQL production)

`REGULER` | `HOBBY` | `DUO` | `KIDS_CLASS` | `KIDS_CLASS_BUNDLE`

### Status murid (Title Case)

`Calon` | `Trial` | `Aktif` | `Cuti` | `Selesai` | `Mengundurkan Diri`

### Enrollment status

`ACTIVE` | `ON_LEAVE` | `INACTIVE` | `COMPLETED` | `TRIAL`

### Metode bayar

`CASH` | `TRANSFER` | `QRIS` | `DEBIT`

### Honor per sesi

Disimpan di `class_sessions.honor_code` / `honor_amount` — **bukan** kolom di `packages`.

### `class_sessions.session_date`

DATE string — **tanpa** cast `date` di model; pakai `Carbon::parse()`.

---

## 6. Services & Commands (inventori)

**Services:** `StudentLifecycleService`, `SessionGeneratorService`, `HonorCalculationService`, `AttendanceService`, `InvoiceService`, `PaymentService`, `DiscountService`, `RescheduleService`, `ScheduleConflictDetector`, `EventHonorService`, `TeacherService`, `StudentImportService`, `UserUsernameService`

**Commands:** `GenerateMonthlySessions`, `GenerateMonthlySpp`, `CalculateHonor`, `ApplyLateFines`, `CheckOverdueStudents`, `GuruCreateAccounts`, `ClearStudentData`

---

## 7. Konvensi Kode

- Komentar: Bahasa Indonesia | Variabel/method: English | UI/validasi: Bahasa Indonesia
- Validasi di **Form Request**
- Audit log untuk aksi penting
- Tailwind (bukan Bootstrap)
- **Jangan** `migrate:fresh` tanpa konfirmasi eksplisit

---

## 8. Testing

`php artisan test` — PHPUnit, SQLite in-memory. ~59 file test (multi-kelas, diskon, guru, DUO, absensi, dll.).

---

## 9. Divergensi Dokumen Lama vs Kode Aktual

| Topik | Dokumen lama | Kode aktual |
|-------|--------------|-------------|
| Login Guru | Fase 1 tidak ada | Role `Guru` + `/guru/*` |
| `class_type` | 4 nilai | + `DUO` |
| Status absensi | 9 status | + `IZIN_PENDING` (10) |
| Auto-mundur | BR + notifikasi | Notifikasi saja; tanpa cron withdraw |
| Hapus diskon Owner-only (BR-DSK.5) | Owner | Route **Owner\|Admin** |
| `AutoMundurService` | Disebut di rules | **Tidak ada** file |

**Tindakan:** Perbarui `CLAUDE.md` / `.cursor/rules` saat BR disepakati ulang.

---

## 10. Acceptance Criteria — Sistem

- [ ] Login Owner, Admin, Auditor, Guru (email/username)
- [ ] Matriks role §2 dipatuhi di route baru
- [ ] Lifecycle murid + multi-kelas + SPP per enrollment ACTIVE
- [ ] Generator sesi + absensi + honor codes konsisten
- [ ] Void payment hanya Owner
- [ ] Cron terdaftar: `php artisan schedule:list`
- [ ] `php artisan test` lulus

---

*SRS ini untuk komunikasi dengan AI dan onboarding — bukan pengganti BRD legal.*
