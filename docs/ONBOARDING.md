# Onboarding Guide — Musik KITA Sistem Operasional

> Dibuat otomatis dari knowledge graph `/understand-anything`. Diperbarui: 2026-05-27.

---

## 1. Project Overview

**Musik KITA Sistem Operasional** adalah sistem administrasi dan keuangan internal untuk studio musik "Musik KITA". Sistem ini menggantikan proses Excel yang tidak skalabel dan dijalankan secara offline di jaringan LAN lokal studio.

| Aspek | Detail |
|---|---|
| **Framework** | Laravel 11 (PHP 8.3) |
| **Frontend** | Blade Templates + Tailwind CSS 3 + Alpine.js + ApexCharts |
| **Auth & RBAC** | Laravel Breeze + Spatie Permission v6 |
| **Build** | Vite (`npm run dev` / `npm run build`) |
| **Database** | MySQL via Laragon (`mk_operasional`) |
| **Testing** | PHPUnit + SQLite in-memory (agar tidak menyentuh DB produksi) |
| **Import** | Maatwebsite Excel (import data 300+ murid dari Excel lama) |
| **Deployment** | LAN lokal studio — tidak tersedia online |

### Akun Default (setelah `php artisan db:seed`)
```
owner@musikkita.local   / password   → role Owner   (akses penuh)
admin@musikkita.local   / password   → role Admin   (operasional harian)
auditor@musikkita.local / password   → role Auditor (read-only)
```
**Ganti password ketiga akun ini sebelum sistem dipakai live.**

### Skala Operasional
- ~300 murid aktif, 18 guru pengajar
- ~1.200 sesi privat/bulan + sesi Kids Class grup
- 18 slip honor guru/bulan
- 9 studio (R1–R9), 8 instrumen aktif (Saxophone nonaktif)

---

## 2. Architecture Layers

Sistem terdiri dari 5 layer yang mengikuti pola Laravel standar:

### Layer 1 — Routing & Entry
**Titik masuk semua HTTP request.** Laravel menggunakan front controller pattern: semua request masuk via `public/index.php`.

| File | Peran |
|---|---|
| `public/index.php` | Entry point tunggal semua HTTP request |
| `bootstrap/app.php` | Konfigurasi framework, middleware global, alias route |
| `routes/web.php` | Peta lengkap ~416 baris semua fitur M01–M09 |
| `routes/auth.php` | Route autentikasi Laravel Breeze |
| `routes/console.php` | Artisan command schedule (cron job) |

### Layer 2 — HTTP Layer
**Controller dan Form Request** yang menerima input, memanggil service, dan mengembalikan response ke Blade.

Semua controller berada di `app/Http/Controllers/` (tidak ada subfolder `Admin/`).

**Controller utama:**
| Controller | Modul | Tanggung Jawab |
|---|---|---|
| `StudentController` | M02 | CRUD murid + lifecycle transitions |
| `EnrollmentController` | M02 | Multi-kelas: tambah, set primary, stop enrollment |
| `AbsensiController` | M04 | Input absensi harian via AJAX + modal reschedule |
| `InvoiceController` | M05 | Manajemen invoice SPP + diskon |
| `PaymentController` | M05 | Catat pembayaran (CASH/TRANSFER/QRIS/DEBIT) + void |
| `HonorController` | M06 | Review & cetak slip honor guru |
| `EventController` | M08 | Siklus Mini Concert & Ujian Grade |
| `DashboardController` | M09 | Agregasi data P&L + statistik operasional |
| `ScheduleController` | M03 | Jadwal mingguan tetap per enrollment |
| `HolidayController` | M01 | Hari libur + tanggal pengganti |

### Layer 3 — Business Logic Layer
**Service classes dan Artisan commands** yang mengimplementasikan aturan bisnis inti.

| File | Fungsi |
|---|---|
| `StudentLifecycleService` | State machine murid: 6 status, 12 transisi valid |
| `SessionGeneratorService` | Generate 1.200+ sesi bulanan, handle libur & conflict |
| `HonorCalculationService` | Kalkulasi honor guru: 10 skenario honor_code |
| `AttendanceService` | Proses absensi + otomatis hitung honor_code per sesi |
| `InvoiceService` | Generate SPP bulanan, denda harian, cicilan Kids Bundle |
| `RescheduleService` | Buat sesi pengganti dengan conflict detection |
| `EventHonorService` | Honor guru pendamping + pengawas ujian ke slip utama |
| `DiscountService` | Terapkan diskon NOMINAL/PERCENT ke invoice item |
| `ScheduleConflictDetector` | Deteksi double-booking guru dan ruang |
| `StudentImportService` | Import data 300+ murid dari Excel template |

**Artisan Commands (cron job):**
| Command | Jadwal | Fungsi |
|---|---|---|
| `sessions:generate` | Tgl 25 tiap bulan | Generate sesi bulan berikutnya |
| `spp:generate` | Tgl 1 tiap bulan | Generate invoice SPP otomatis |
| `fines:apply` | Setiap hari | Tambah denda Rp 5.000/hari mulai tgl 11 |
| `honor:calculate` | H-2 akhir bulan | Kalkulasi honor semua guru |
| `students:check-overdue` | Harian | Kirim notifikasi murid tunggakan >1 bulan |

### Layer 4 — Data Layer
**Eloquent Models, Migrasi, Seeder, dan Factory.**

**Model inti:**
| Model | Tabel | Keterangan |
|---|---|---|
| `Student` | `students` | Entitas sentral (fan-in 56 — direferensikan terbanyak) |
| `Enrollment` | `enrollments` | Hubungkan murid ↔ paket + guru (multi-kelas) |
| `ClassSession` | `class_sessions` | Sesi konkret per tanggal, 9 status absensi |
| `Schedule` | `schedules` | Jadwal mingguan tetap per enrollment |
| `Invoice` | `invoices` | Tagihan murid, nomor INV/YYYY/MM/NNNN |
| `InvoiceItem` | `invoice_items` | Baris tagihan + item diskon (self-referencing FK) |
| `Payment` | `payments` | Pembayaran, nomor KW/YYYY/MM/NNNN |
| `HonorSlip` | `teacher_honor_slips` | Slip honor guru per bulan |
| `Package` | `packages` | Katalog paket kelas dengan harga |
| `Teacher` | `teachers` | Data guru termasuk info bank untuk slip honor |
| `Holiday` | `holidays` | Hari libur + `replacement_date` + `is_honor_paid` |
| `ClassSession` | `class_sessions` | Hasil materialisasi jadwal mingguan |
| `EventParticipant` | `event_participants` | Peserta event + `accompanying_teacher_id` |
| `PayrollConfig` | `payroll_configs` | Formula honor tersimpan di DB (bisa diubah tanpa deploy) |

### Layer 5 — Presentation Layer
**Blade Templates, CSS, dan Alpine.js** yang membentuk antarmuka operator studio.

| File/Folder | Peran |
|---|---|
| `resources/views/layouts/app.blade.php` | Shell visual: sidebar, topbar jam digital, notifikasi |
| `resources/css/app.css` | Tema Mint & Mahogany, scoping `.dark-content` / `.light-content` |
| `tailwind.config.js` | Token warna `mk.*` (bg-mk-sidebar, mk-accent gold, dll) |
| `resources/views/absensi/` | Halaman absensi harian (paling kompleks secara UI) |
| `resources/views/students/` | Detail murid + tab kelas multi-enrollment |
| `resources/views/honors/` | Slip honor termasuk halaman cetak |
| `resources/views/dashboard.blade.php` | Dashboard P&L dengan grafik ApexCharts |

---

## 3. Key Concepts

### Multi-Enrollment (Multi-Kelas)
Satu murid bisa punya beberapa enrollment aktif sekaligus (contoh: Piano Reguler + Gitar Hobby). Satu enrollment ditandai `is_primary=true` dan tersimpan di `students.primary_enrollment_id` — enrollment inilah yang men-trigger invoice SPP otomatis. Akses paket/guru/ruang murid **selalu** via `$student->primaryEnrollment->package`, **bukan** via kolom langsung di `students` (kolom-kolom itu sudah dihapus di migrasi Mei 2026).

### State Machine Murid
```
Calon → Trial → Aktif → Cuti → Mundur
                  ↓              ↓
               Selesai        Aktif (reaktivasi)
```
Setiap transisi diimplementasikan sebagai method di `StudentLifecycleService` dan membawa side effect (buat enrollment, generate invoice, ubah status enrollment). Seluruh riwayat transisi tersimpan di `student_status_histories`.

### Honor Guru — 10 Skenario
Honor guru tidak tersimpan sebagai kolom tetap — ia **dihitung** dari formula `PayrollConfig` dan kode yang ditetapkan `AttendanceService` saat absensi dicatat (`honor_code` di tabel `class_sessions`):

| Kode | Skenario | Honor |
|---|---|---|
| `H_REG` | Sesi terlaksana normal | harga × 50% / 4 |
| `H_TRIAL` | Trial murid hadir | sama H_REG |
| `TRIAL_NS` | Trial murid no-show | Rp 0 |
| `H_VIDEO` | Izin video pengganti | sama H_REG |
| `H_LIBUR` | Libur nasional (tanpa replacement) | sama H_REG (tetap bayar) |
| `H_HANGUS` | Murid no-show / hangus | sama H_REG (tetap bayar) |
| `H_PENG` | Diajar guru pengganti | ke guru pengganti |
| `H_KIDS` | Sesi Kids Class | murid_terdaftar × Rp 42.500 |
| `H_UJIAN` | Pengawas ujian grade | Rp 250.000 flat |
| `H_IZIN` | IZIN_RESCHEDULE (sesi original) | Rp 0 (dibayar via sesi pengganti) |

### Session Generator
Setiap tanggal 25, `SessionGeneratorService` membuat sesi konkret bulan berikutnya dari jadwal mingguan (`schedules`). Generator ini:
- Membatasi 3–4 sesi per murid per bulan
- Menandai sesi libur nasional sebagai `LIBUR`
- Membuat sesi pengganti jika holiday punya `replacement_date`
- Meng-skip enrollment dengan status `ON_LEAVE` (murid cuti)
- Mendeteksi dan meng-skip konflik guru/ruang

### Invoice & Denda
Invoice SPP digenerate tanggal 1 per bulan (via cron `spp:generate`). Tempo bayar tgl 1–10. Mulai tgl 11, cron `fines:apply` menambahkan denda Rp 5.000/hari sebagai `InvoiceItem` dengan kode `DENDA`. Diskon dapat ditambahkan manual oleh Owner/Admin dengan `parent_item_id` yang menunjuk ke item yang didiskon.

### RBAC (3 Role)
| Role | Akses |
|---|---|
| **Owner** | Full access: ubah harga, void payment, manage user, audit log, tandai honor dibayar |
| **Admin** | Operasional: daftar murid, jadwal, absensi, tagihan, pembayaran |
| **Auditor** | Read-only semua data dan laporan |

Tidak ada role Guru — guru tidak login ke sistem; absensi diinput oleh Admin.

### Naming Conventions Kritis
```
✓ packages.code              (bukan packages.name)
✓ packages.duration_min      (bukan duration_minutes)
✓ class_type: REGULER / HOBBY / KIDS_CLASS / KIDS_CLASS_BUNDLE  (huruf kapital semua)
✓ student.status: Calon / Trial / Aktif / Cuti / Selesai / Mengundurkan Diri  (Title Case)
✓ $student->primaryEnrollment->package  (bukan $student->package — accessor lama sudah dihapus)
```

---

## 4. Guided Tour (15 Langkah)

Ikuti urutan ini untuk memahami codebase dari fondasi hingga fitur lengkap:

### Langkah 1 — Spesifikasi & Business Rules
**Baca `CLAUDE.md` terlebih dahulu.** Dokumen ini adalah satu-satunya sumber kebenaran proyek — memuat 78+ business rules, skema database kritis, 10 skenario honor guru, state machine status murid, design system UI Mahogany, dan konvensi kode. Membacanya menjawab pertanyaan "mengapa kode ditulis seperti ini" sebelum melihat implementasinya.

### Langkah 2 — Tech Stack & Dependensi
Baca `composer.json` dan `package.json`. Kombinasi Laravel 11 + Spatie Permission + Blade + Alpine.js dipilih agar mudah dipahami solo developer pemula — tidak ada JavaScript framework besar.

> **Catatan Laravel 11:** Tidak ada `app/Http/Kernel.php` lagi. Middleware global dan alias route didaftarkan langsung di `bootstrap/app.php` via `->withMiddleware()`.

### Langkah 3 — Entry Point & Bootstrap
`public/index.php` adalah pintu masuk tunggal semua HTTP request (front controller pattern). Ia memuat `bootstrap/app.php` yang mengonfigurasi routing dan mendaftarkan middleware RBAC Spatie Permission.

### Langkah 4 — Peta Seluruh Fitur: `routes/web.php`
Dengan fan-out tertinggi (26 edge), file ini (~416 baris) adalah peta lengkap semua fitur sistem. Setiap grup route dijaga `middleware(['auth', 'role:Owner|Admin'])`. Membaca struktur route memberi gambaran cepat sebelum masuk ke logika bisnis.

### Langkah 5 — Domain Model: Master Data
`Package`, `Teacher`, `Room`, `Instrument` adalah fondasi yang direferensikan hampir semua fitur lain. Package mendefinisikan harga per bulan — honor guru dihitung dari formula ini. Room menggunakan kolom JSON `supported_instruments` (pengganti boolean `has_piano`/`has_drum` yang sudah dihapus).

### Langkah 6 — Inti Sistem: `Student` & `Enrollment`
`Student` (fan-in 56, tertinggi di seluruh codebase) adalah model sentral. `Enrollment` menghubungkan murid ke paket + guru + ruang, menggantikan kolom-kolom langsung di `students` yang dihapus di migrasi multi-kelas Mei 2026. Satu murid bisa punya banyak enrollment aktif sekaligus.

### Langkah 7 — State Machine Lifecycle Murid
`StudentLifecycleService` mengimplementasikan state machine lengkap dengan side effect di setiap transisi. `StudentController` adalah HTTP adapter yang memanggil service ini dari form actions. Pola Service Layer memisahkan business logic dari HTTP concern.

### Langkah 8 — Jadwal & Generator Sesi Otomatis
`Schedule` (jadwal mingguan tetap) → `SessionGeneratorService` (berjalan tgl 25 via `GenerateMonthlySessions`) → `ClassSession` (sesi konkret per tanggal). Generator menangani libur, replacement session, pembatasan 3–4 sesi/bulan, dan conflict detection.

> **Perintah manual:** `php artisan sessions:generate 2026 06`

### Langkah 9 — Absensi & Reschedule Harian
`ClassSession` adalah hasil materialisasi jadwal — setiap sesi konkret dengan 9 status absensi. `AttendanceService` memproses setiap update status dan menghitung `honor_code` + `honor_amount` secara langsung. `AbsensiController` menyediakan interface AJAX (dual response HTML/JSON via `expectsJson()`). `RescheduleService` membuat sesi pengganti dengan conflict detection.

### Langkah 10 — Engine Tagihan & Pembayaran
`Invoice` (fan-in 19) adalah model keuangan inti. `InvoiceService` menangani siklus lengkap: generate SPP tgl 1, hitung denda tgl 11+, buat invoice cicilan 3-termin Kids Bundle. `PaymentController` mencatat pembayaran + upload bukti — hanya Owner yang bisa void. Self-referencing FK di `invoice_items.parent_item_id` memungkinkan item DISKON melampirkan diri ke item yang didiskon.

### Langkah 11 — Kalkulasi & Slip Honor Guru
`HonorCalculationService` mengimplementasikan 10 skenario honor_code dengan cut-off H-2 sebelum akhir bulan. `EventHonorService` menambahkan honor guru pendamping Konser KITA ke slip yang sama. `PayrollConfig` menyimpan formula honor sebagai string di database — bisa diubah Owner tanpa deploy ulang.

> **Penting:** Honor Kids Class dihitung dari `jumlah murid terdaftar × Rp 42.500`, bukan formula `harga × 50% / 4`.

### Langkah 12 — Event: Mini Concert & Ujian Grade
`EventController` mengelola siklus lengkap event: buat event → daftarkan murid → auto-generate invoice → catat hasil ujian → grade naik otomatis → selesai. Saat selesai, `EventHonorService` menyuntikkan honor ke slip guru pengawas dan pendamping.

### Langkah 13 — Dashboard & Notifikasi Auto-Mundur
`DashboardController` mengagregasi P&L bulan berjalan, statistik murid, dan piutang menunggak. `CheckOverdueStudents` (Artisan command terjadwal) mengirim Laravel Database Notification ke Owner/Admin saat ada murid tunggakan >1 bulan. `AppServiceProvider` mendaftarkan View Composer global yang menyuntikkan data notifikasi ke semua view.

### Langkah 14 — UI System: Layout & Tema Mahogany
`resources/views/layouts/app.blade.php` adalah shell visual: sidebar mahoni gelap, topbar dengan jam digital real-time (Alpine.js), dan notifikasi overdue. `resources/css/app.css` mendefinisikan tema dengan scoping `.dark-content`/`.light-content`. Token warna `mk.*` di `tailwind.config.js` (`bg-mk-sidebar`, `mk-accent` gold) dipakai di seluruh Blade template.

> **Aturan penting:** Selalu pakai class Tailwind standar (`bg-white`, `bg-gray-50`) di template baru — sudah di-override otomatis oleh `.dark-content`/`.light-content`. Jangan hardcode warna cold/navy.

### Langkah 15 — Database & Seeder
`config/database.php` mendefinisikan dua koneksi: MySQL `mk_operasional` untuk produksi dan SQLite in-memory untuk testing. `DatabaseSeeder` membuat tiga user default dan menjalankan semua seeder secara idempoten via `firstOrCreate`.

> **PERINGATAN KRITIS:** Jangan jalankan `php artisan migrate:fresh` tanpa konfirmasi eksplisit — akan menghapus semua data. `php artisan test` aman hanya jika `phpunit.xml` sudah mengoverride ke SQLite in-memory.

---

## 5. File Map

### Routing & Entry
```
routes/web.php                      → Semua route M01–M09, middleware auth + role
routes/auth.php                     → Route login, register, password reset (Breeze)
routes/console.php                  → Artisan schedule (cron job terdaftar)
public/index.php                    → Front controller entry point
bootstrap/app.php                   → Konfigurasi middleware & routing Laravel 11
```

### Controllers (app/Http/Controllers/)
```
StudentController.php               → M02: CRUD + lifecycle murid
EnrollmentController.php            → M02: Multi-kelas, set primary, stop
ScheduleController.php              → M03: Jadwal mingguan tetap
AbsensiController.php               → M04: Absensi harian + reschedule modal
InvoiceController.php               → M05: Invoice SPP + diskon
PaymentController.php               → M05: Pembayaran + void
HonorController.php                 → M06: Slip honor guru
EventController.php                 → M08: Mini Concert + Ujian
DashboardController.php             → M09: P&L + statistik
HolidayController.php               → M01: Hari libur + replacement date
PackageController.php               → M01: Katalog paket kelas
TeacherController.php               → M01: Master data guru
RoomController.php                  → M01: Master data ruang
InstrumentController.php            → M01: Master data instrumen
KalenderController.php              → Kalender jadwal mingguan visual
ReportController.php                → Laporan PDF & Excel
ImportController.php                → Import data murid dari Excel
AuditLogController.php              → Audit log viewer (Owner only)
```

### Services (app/Services/)
```
StudentLifecycleService.php         → State machine murid (paling kritis)
SessionGeneratorService.php         → Generate sesi bulanan otomatis
HonorCalculationService.php         → Kalkulasi 10 skenario honor
AttendanceService.php               → Proses absensi + hitung honor_code
InvoiceService.php                  → Siklus invoice: generate, denda, cicilan
RescheduleService.php               → Buat sesi pengganti + conflict detection
EventHonorService.php               → Honor guru event ke slip utama
DiscountService.php                 → Terapkan diskon NOMINAL/PERCENT
ScheduleConflictDetector.php        → Deteksi double-booking
StudentImportService.php            → Import Excel 300+ murid
```

### Models (app/Models/)
```
Student.php                         → Entitas sentral (fan-in 56)
Enrollment.php                      → Multi-kelas hub
ClassSession.php                    → Sesi konkret, 9 status, honor_code
Schedule.php                        → Jadwal mingguan tetap
Invoice.php                         → Tagihan (fan-in 19)
InvoiceItem.php                     → Baris tagihan + diskon (self-referencing)
Payment.php                         → Pembayaran
HonorSlip.php                       → Slip honor guru per bulan
Package.php                         → Katalog paket kelas
Teacher.php                         → Data guru + info bank
Room.php                            → Ruang + supported_instruments JSON
Holiday.php                         → Hari libur + replacement_date
Event.php                           → Event Mini Concert / Ujian
EventParticipant.php                → Peserta event + accompanying_teacher
PayrollConfig.php                   → Formula honor (diubah Owner dari UI)
```

### Views (resources/views/)
```
layouts/app.blade.php               → Shell visual: sidebar, topbar, notifikasi
dashboard.blade.php                 → P&L + grafik ApexCharts
students/                           → CRUD murid + tab multi-enrollment
absensi/                            → Input absensi harian (AJAX)
honors/                             → Slip honor + halaman cetak
invoices/                           → Invoice + cetak kuitansi
events/                             → Mini Concert + Ujian Grade
kalender/                           → Kalender jadwal visual
reports/                            → Laporan PDF & Excel
```

---

## 6. Complexity Hotspots

Area-area berikut adalah yang paling kompleks dalam codebase — pendekati dengan hati-hati:

### Kritis (Business Logic Terdalam)

| File | Mengapa Kompleks |
|---|---|
| `app/Services/StudentLifecycleService.php` | Service terbesar: 6 status, 12 transisi, setiap transisi punya side effect (buat enrollment, generate invoice, ubah status relasi) |
| `app/Services/SessionGeneratorService.php` | Mengorkestrasi 1.200+ sesi/bulan: iterasi jadwal mingguan, handle libur + replacement, counter 3–4 sesi, conflict detection |
| `app/Services/HonorCalculationService.php` | 10 skenario honor_code yang saling eksklusif; Kids Class pakai formula berbeda; cut-off H-2 butuh kalkulasi tanggal akurat |
| `app/Services/InvoiceService.php` | Siklus invoice lengkap: SPP auto, denda harian, cicilan 3-termin Kids Bundle (3 invoice diikat `installment_group_id`) |
| `app/Services/AttendanceService.php` | Satu method memproses 9 status berbeda dan menentukan honor_code yang berbeda — satu kesalahan kondisional berdampak ke payroll |

### Penting (Banyak Dependent)

| File | Mengapa Penting |
|---|---|
| `app/Models/Student.php` | Fan-in 56 — hampir seluruh codebase bergantung padanya; jangan ubah tanpa menelusuri semua penggunaan |
| `routes/web.php` | 416 baris, 26 outgoing edge — perubahan route berpotensi memutus banyak fitur |
| `app/Http/Controllers/AbsensiController.php` | Dual-response (HTML + JSON), modal reschedule, split session — logika UI paling rumit |
| `app/Services/RescheduleService.php` | Buat sesi pengganti dengan conflict detection ganda (guru + ruang) di tanggal konkret |

### Jebakan Umum untuk Developer Baru

1. **Jangan akses `$student->package` atau `$student->teacher`** — accessor lama ini sudah dihapus. Gunakan `$student->primaryEnrollment->package`.
2. **`session_date` di `ClassSession` tidak punya cast `date`** — gunakan `Carbon::parse($session->session_date)`, jangan akses `->format()` langsung.
3. **`class_type` harus KAPITAL SEMUA** — `'REGULER'` bukan `'Reguler'`, `'KIDS_CLASS'` bukan `'Kids Class'`.
4. **Honor Kids Class pakai formula berbeda** — selalu cek `class_type` sebelum memilih formula kalkulasi.
5. **Void payment hanya Owner** — jangan beri akses ini ke Admin meskipun tampak logis.
6. **`php artisan migrate:fresh` menghapus semua data** — selalu konfirmasi eksplisit sebelum dijalankan.

---

## 7. Setup Development

```bash
# Clone & install dependensi
git clone <repo-url> musik-kita-ops
cd musik-kita-ops
composer install
npm install

# Konfigurasi environment
cp .env.example .env
php artisan key:generate
# Edit .env: DB_DATABASE=mk_operasional, DB_USERNAME=root, DB_PASSWORD=

# Setup database
php artisan migrate
php artisan db:seed

# Jalankan development server
php artisan serve        # Backend di http://127.0.0.1:8000
npm run dev              # Frontend Vite (hot reload)

# Jalankan test (aman — pakai SQLite in-memory)
php artisan test
```

---

## 8. Modules Quick Reference

| Modul | Area | Fitur Utama |
|---|---|---|
| M01 | Master Data | Instrumen, paket, guru, ruang, hari libur, formula honor |
| M02 | Pendaftaran & Trial | Form murid baru, schedule trial, konversi aktif, skip trial |
| M03 | Penjadwalan | Jadwal mingguan, generator sesi otomatis, kalender akademik |
| M04 | Absensi | Input 9 status, reschedule modal, guru pengganti |
| M05 | Keuangan Murid | Invoice SPP, denda, pembayaran, diskon, kuitansi cetak |
| M06 | Honor Guru | Kalkulasi otomatis, slip honor, cetak, tandai dibayar |
| M07 | Pengeluaran & Kas | Catat pengeluaran per kategori, petty cash |
| M08 | Event | Mini Concert, ujian grade, honor pengawas + pendamping |
| M09 | Laporan & Notifikasi | Dashboard P&L, laporan PDF/Excel, audit log, WA template |

---

*Onboarding guide ini dibuat dari knowledge graph `/understand-anything` yang menganalisis 339 file kode. Perbarui dengan menjalankan `/understand` ulang setelah perubahan arsitektur besar.*
