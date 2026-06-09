# Musik KITA Ops — Onboarding Guide

> Generated from the project knowledge graph — 2026-06-09
> 1,371 nodes · 2,076 edges · 626 files · 9 architectural layers

---

## Project Overview

**Musik KITA Operations** is a studio music school administration and finance management system built on **Laravel 11**. It replaces a manual Excel workflow for a music studio serving approximately **300 students** and **18 teachers**, processing over **1,200 private sessions per month** across **9 studio rooms**.

### Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 · Laravel 11 · Laravel Breeze |
| Frontend | Blade Templates · Tailwind CSS 3 · Alpine.js |
| Auth & RBAC | Laravel Breeze + Spatie Permission v6 (4 roles) |
| Database | MySQL (via Laragon on Windows) |
| Build | Vite |
| PDF | DOMPDF |
| Excel | Laravel Excel |
| WhatsApp | Fonnte (Wablas) API — for notifications & reminders |

### User Roles

| Role | Access Level |
|---|---|
| **Owner** | Full access — pricing, master data deletion, void payments, manage users, audit log, mark honor as paid |
| **Admin** | Daily operations — students, schedules, attendance, billing, payments. Cannot change prices, delete master data, or void payments |
| **Auditor** | Read-only across all data and reports |
| **Guru** | Teacher self-service portal — attendance, notes, progress reports, honor slip viewing |

---

## Architecture Layers

The project follows a clean **Laravel MVC + Service** architecture with 9 distinct layers:

### 1. API Layer (67 files)
> HTTP route definitions, controllers, and form request validation for all web endpoints.

Controllers are organized by domain module (Absensi, Enrollment, Schedule, Student, Invoice, Payment, Honor, Event, etc.) in `app/Http/Controllers/`. Form Request validators in `app/Http/Requests/` enforce input validation with Indonesian-language error messages. Route definitions in `routes/web.php` apply RBAC middleware per route group.

### 2. Service Layer (33 files)
> Business logic services, queue jobs, notifications, and data export utilities.

Services encapsulate the system's complex business rules:
- **SessionGeneratorService** — Monthly session generation with academic calendar rules
- **AttendanceService** — 10 honor-code scenarios per attendance status
- **RescheduleService** — Phase 2 rescheduling with conflict detection
- **InvoiceService** — SPP auto-generation, installments, late fines
- **HonorCalculationService** — Teacher payroll from session data
- **StudentLifecycleService** — 6-status state machine transitions
- **EventHonorService** — Event honor injection for accompanying teachers

### 3. Data Layer (140 files)
> Eloquent models, database migrations, seeders, and test factories.

**Core Models** (highest fan-in): `Student` (77 edges), `ClassSession`, `Enrollment`, `Package`, `Teacher`, `Invoice`, `HonorSlip`. Models use Laravel Eloquent ORM with rich relationships: `$student->primaryEnrollment->package->price_per_month`.

**Migrations** (~65 files) trace the full schema evolution from initial Laravel tables through multi-class support, academic calendar, progress reports, Guru portal, WhatsApp templates, and Duo class type.

### 4. Presentation Layer (132 files)
> Blade templates, Tailwind CSS, JavaScript, Blade components, and UI mockups.

Views organized by module under `resources/views/`: `absensi/`, `students/`, `invoices/`, `honors/`, `events/`, `guru/`, `progress-reports/`, etc. Uses a single light-mode mint-and-mahogany theme. Alpine.js powers all inline interactions (status dropdowns, modals, searchable selects). ApexCharts handles dashboard charts.

### 5. Console Layer (8 files)
> Artisan CLI commands for scheduled tasks.

- `GenerateMonthlySessions` — Cron job (25th of month) for next month's sessions
- `GenerateMonthlySpp` — Invoice generation on the 1st
- `ApplyLateFines` — Daily fine application (Rp 5,000/day from 11th)
- `CalculateHonor` — Monthly teacher payroll (H-2 before month end)
- `CheckOverdueStudents` — Auto-mundur detection for >1 month unpaid
- `SendScheduleReminders` — WhatsApp schedule reminders
- `GuruCreateAccounts` — One-time teacher account creation

### 6. Configuration Layer (27 files)
> Application configuration, build tool settings, service provider registration.

`config/` contains standard Laravel config files plus custom ones: `instruments.php`, `schedule_reminder.php`, `session_report_wa.php`, `studio.php`. Tailwind and Vite configs at project root.

### 7. Test Layer (82 files)
> Feature and unit test suites covering all 11 modules (M01-M11).

Tests use PHPUnit with SQLite in-memory database. Key test files include extensive coverage of absensi (attendance), reschedule, session generation, student import, invoice generation, honor calculation, and multi-enrollment scenarios.

### 8. Bootstrap Layer (7 files)
> Application entry points, HTTP front controller, core service provider.

`public/index.php` → `bootstrap/app.php` → `AppServiceProvider` (registers View Composer for overdue notifications in topbar).

### 9. Documentation Layer (127 files)
> Project documentation, SRS modules, development plans, bug reports, AI coding rules.

The SRS (`docs/srs/SRS-musik-kita-ops-2026-05-31.md`) is the authoritative system specification. Module-specific SRS docs are in `docs/srs/modules/`. Implementation plans live under `docs/superpowers/plans/` and design specs under `docs/superpowers/specs/`. The `CLAUDE.md` root file is the definitive project briefing for AI-assisted development.

---

## Guided Tour — 13 Steps

### Step 1: Project Overview
**Start here:** Read `CLAUDE.md` — the comprehensive briefing covering architecture, schema, business rules, UI design, coding conventions, and all 11 modules.

### Step 2: Application Entry and Routing
**Files:** `public/index.php` → `routes/web.php` → `bootstrap/app.php`
Understand how every request flows through the Laravel front controller, how RBAC middleware gates every route, and how Spatie role/permission checks are configured.

### Step 3: Core Domain Models
**Files:** `app/Models/Student.php`, `app/Models/Enrollment.php`, `app/Models/Package.php`
These three models form the data vocabulary of the entire system. The Enrollment model bridges students to packages and teachers — every feature references these relationships.

### Step 4: Scheduling Foundation
**Files:** `app/Models/Schedule.php`, `app/Models/ClassSession.php`, `app/Models/Room.php`, `app/Models/Teacher.php`
Understand the two dimensions of time: weekly `schedules` (day-of-week + time slots) and concrete `class_sessions` (specific dates with attendance status).

### Step 5: Session Generation and Conflict Detection
**Files:** `app/Services/SessionGeneratorService.php`, `app/Services/ScheduleConflictDetector.php`, `app/Console/Commands/GenerateMonthlySessions.php`
The system's most important background process: generating sessions from schedules, handling holidays with replacement dates, and enforcing the max-4-sessions-per-month rule.

### Step 6: Attendance and Rescheduling
**Files:** `app/Http/Controllers/AbsensiController.php`, `app/Services/AttendanceService.php`, `app/Services/RescheduleService.php`
The operational heart — inline AJAX attendance recording with 10 statuses, each mapped to a specific honor code. RescheduleService handles creating replacement sessions when students cancel with sufficient notice.

### Step 7: Student Finance and Invoicing
**Files:** `app/Models/Invoice.php`, `app/Models/InvoiceItem.php`, `app/Models/Payment.php`, `app/Services/InvoiceService.php`
The billing lifecycle: auto-generated SPP per active enrollment, late fines, NOMINAL/PERCENT discounts, Kids Class Bundle 3-term installments, and 4 payment methods (CASH, TRANSFER, QRIS, DEBIT).

### Step 8: Teacher Honor System
**Files:** `app/Models/HonorSlip.php`, `app/Services/HonorCalculationService.php`, `app/Http/Controllers/HonorController.php`
Teacher compensation with 10 honor codes covering every attendance scenario. Slips aggregate base honor (auto), event honor, transport, and other honor. Status flows DRAFT → CALCULATED → PAID.

### Step 9: Student Lifecycle Management
**Files:** `app/Http/Controllers/StudentController.php`, `app/Services/StudentLifecycleService.php`, `app/Models/StudentStatusHistory.php`
The 6-status state machine: CALON → TRIAL → AKTIF ↔ CUTI → MUNDUR / SELESAI. Every transition triggers the correct side effects (enrollment updates, session cleanup, invoice generation).

### Step 10: Events, Concerts, and Exams
**Files:** `app/Http/Controllers/EventController.php`, `app/Models/Event.php`, `app/Services/EventHonorService.php`
Mini Concerts and exams with DRAFT → COMPLETED lifecycle. Exam results trigger automatic grade progression. Event completion injects Rp 250,000 honor for accompanying teachers.

### Step 11: Dashboard and Reporting
**Files:** `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/ReportController.php`, `routes/console.php`
Real-time P&L dashboard with role-based visibility. Financial reports with payment method breakdowns. Console route definitions schedule all cron tasks.

### Step 12: Teacher Portal
**Files:** `app/Http/Controllers/GuruController.php`, `resources/views/guru/`, `resources/js/app.js`
Teacher self-service: today's schedule, 2-week calendar, self-service attendance, progress report creation, honor slip viewing. Mobile-first layout with Alpine.js bottom navigation.

### Step 13: System Architecture Summary
**File:** `docs/srs/SRS-musik-kita-ops-2026-05-31.md`
The authoritative SRS document tying all components together — showing how the layered architecture serves a studio managing 1,200+ sessions/month across 9 rooms with 18 teachers.

---

## Key Concepts

### Naming Conventions — CRITICAL
- **Package code** (not name): `packages.code` — e.g., "REG-B-PNO-30"
- **Duration**: `duration_min` (not `duration_minutes`)
- **Student name**: `full_name` (not `name`)
- **Student code**: `student_code` (not `code`)
- **Class type enum** (always UPPERCASE): `REGULER`, `HOBBY`, `KIDS_CLASS`, `KIDS_CLASS_BUNDLE`
- **Student status enum** (Title Case): `Calon`, `Trial`, `Aktif`, `Cuti`, `Selesai`, `Mengundurkan Diri`
- **Columns DELETED**: `students.package_id`, `assigned_teacher_id`, `assigned_room_id` — use `$student->primaryEnrollment->package` instead

### Multi-Class Architecture
Students can have multiple enrollments (e.g., Piano Regular + Guitar Hobby). Each enrollment is independent: own schedule, own invoice, own attendance. `students.primary_enrollment_id` marks the primary enrollment for UI display. Invoice SPP is generated for ALL ACTIVE enrollments per BR-MK.4.

### Honor Calculation
Teachers are paid per session via the formula: `package_price × 50% / 4`. There are 10 honor codes covering every scenario:
- **H_REG** — Normal session (hadir/telat)
- **H_TRIAL** — Trial session (student attended)
- **TRIAL_NS** — Trial no-show (Rp 0)
- **H_VIDEO** — Video replacement
- **H_LIBUR** — National holiday (full pay)
- **H_HANGUS** — No-show/forfeit (full pay)
- **H_PENG** — Substitute teacher
- **H_KIDS** — Kids Class group
- **H_UJIAN** — Exam supervisor (Rp 250,000 flat)
- **H_IZIN** — Original session of reschedule (Rp 0 — paid via replacement)
- **H_SPLIT** — Split reschedule (half honor per part)

### Attendance Status Flow
9 session statuses: `SCHEDULED` → `HADIR`, `HADIR_TERLAMBAT`, `IZIN_RESCHEDULE`, `IZIN_VIDEO`, `HANGUS`, `LIBUR`, `DIGANTI`, `CANCELLED`. Reschedule rules: first cancellation with ≥5h notice per month grants replacement session. Second+ cancellation → video replacement. <5h notice → forfeit.

### Cuti (Leave)
Students can take paid leave (Rp 100,000) for max 1 month, extendable once (total 2 months max). During cuti: enrollment status → `ON_LEAVE`, no sessions generated, no SPP generated. On return: enrollment → `ACTIVE`, cuti dates cleared.

---

## Complexity Hotspots

These are the most complex files in the codebase — approach with care:

| File | Lines | Layer | Why Complex |
|---|---|---|---|
| `app/Http/Controllers/AbsensiController.php` | ~900 | API | 8+ attendance status transitions, AJAX responses, reschedule mini-modal, substitute teacher assignment |
| `app/Services/SessionGeneratorService.php` | ~600 | Service | Academic calendar rules, holiday replacement dates, week-5 logic, 4-session cap, honor code assignment |
| `routes/web.php` | ~450 | API | All 70+ routes with RBAC middleware, Guru route group, role-specific prefixes |
| `app/Http/Controllers/StudentController.php` | ~800 | API | Full CRUD + 6 lifecycle actions, multi-enrollment management, import wizard |
| `app/Services/InvoiceService.php` | ~550 | Service | SPP auto-generation, installment calculations, fine application, void logic |
| `app/Services/StudentLifecycleService.php` | ~450 | Service | 6-status state machine with all transition side effects |
| `app/Http/Controllers/GuruController.php` | ~676 | API | 19 methods for teacher portal: attendance, notes, progress reports, honor slips |
| `app/Services/AttendanceService.php` | ~400 | Service | 10 honor-code business rules, per-session honor calculation |
| `app/Http/Controllers/InvoiceController.php` | ~500 | API | Invoice CRUD, void, manual SPP generation, fine management |
| `app/Services/StudentImportService.php` | ~480 | Service | Two-phase Excel import with validation |

**Test files with high complexity:** `AbsensiControllerTest`, `RescheduleTest`, `SplitRescheduleTest`, `SessionGeneratorConflictTest`, `StudentImportServiceTest` — these reflect the corresponding production code complexity and are excellent learning resources for understanding edge cases.

---

## File Map by Module

### M01 — Master Data
| File | Purpose |
|---|---|
| `app/Http/Controllers/InstrumentController.php` | Instrument CRUD |
| `app/Http/Controllers/PackageController.php` | Package/pricing CRUD (Owner only for price changes) |
| `app/Http/Controllers/RoomController.php` | Room CRUD with supported_instruments JSON |
| `app/Http/Controllers/TeacherController.php` | Teacher CRUD with instrument matrix |
| `app/Http/Controllers/HolidayController.php` | Academic calendar with replacement dates |
| `app/Http/Controllers/InvoiceComponentController.php` | Invoice item catalog (Owner-managed) |
| `app/Models/Instrument.php`, `Package.php`, `Room.php`, `Teacher.php`, `Holiday.php` | Corresponding Eloquent models |

### M02 — Pendaftaran & Trial
| File | Purpose |
|---|---|
| `app/Http/Controllers/StudentController.php` | Student CRUD, lifecycle actions (trial, activate, skip-trial) |
| `app/Services/StudentLifecycleService.php` | State machine transitions |
| `app/Services/TrialManagementService.php` | Trial session creation, honor handling |
| `app/Models/Student.php`, `StudentStatusHistory.php` | Student data + lifecycle audit trail |

### M03 — Penjadwalan
| File | Purpose |
|---|---|
| `app/Http/Controllers/ScheduleController.php` | Weekly schedule CRUD |
| `app/Http/Controllers/SessionController.php` | Session listing, manual generation |
| `app/Http/Controllers/KalenderController.php` | Weekly calendar view |
| `app/Services/SessionGeneratorService.php` | Monthly session generation engine |
| `app/Services/ScheduleConflictDetector.php` | Teacher/room conflict detection |
| `app/Services/ManualSessionService.php` | Manual session creation |
| `app/Models/Schedule.php`, `ClassSession.php`, `Enrollment.php` | Scheduling data models |
| `app/Console/Commands/GenerateMonthlySessions.php` | Cron trigger |

### M04 — Absensi
| File | Purpose |
|---|---|
| `app/Http/Controllers/AbsensiController.php` | Daily attendance page with AJAX |
| `app/Services/AttendanceService.php` | Honor code assignment, attendance rules |
| `app/Services/RescheduleService.php` | Replacement session creation |
| `resources/views/absensi/_row.blade.php` | Inline attendance row with Alpine.js |
| `resources/views/absensi/index.blade.php` | Attendance dashboard |
| `resources/views/absensi/open-slots.blade.php` | Open slot board for pending reschedules |

### M05 — Keuangan Murid
| File | Purpose |
|---|---|
| `app/Http/Controllers/InvoiceController.php` | Invoice management |
| `app/Http/Controllers/PaymentController.php` | Payment recording, receipt generation |
| `app/Http/Controllers/DiscountController.php` | Invoice discount management |
| `app/Services/InvoiceService.php` | SPP generation, installment logic |
| `app/Services/DiscountService.php` | NOMINAL/PERCENT discount logic |
| `app/Services/InvoiceReminderService.php` | WhatsApp payment reminders |
| `app/Models/Invoice.php`, `InvoiceItem.php`, `Payment.php` | Finance data models |
| `app/Console/Commands/GenerateMonthlySpp.php` | Cron: SPP generation |
| `app/Console/Commands/ApplyLateFines.php` | Cron: daily fines |

### M06 — Honor Guru
| File | Purpose |
|---|---|
| `app/Http/Controllers/HonorController.php` | Honor slip management |
| `app/Services/HonorCalculationService.php` | Monthly honor calculation |
| `app/Models/HonorSlip.php`, `PayrollConfig.php` | Honor data models |
| `app/Console/Commands/CalculateHonor.php` | Cron: honor calculation |

### M07 — Pengeluaran & Kas
| File | Purpose |
|---|---|
| `app/Http/Controllers/ExpenseController.php` | Expense CRUD |
| `app/Http/Controllers/ExpenseCategoryController.php` | Expense category CRUD |
| `app/Models/Expense.php`, `ExpenseCategory.php` | Expense data models |

### M08 — Event (Mini Concert & Ujian)
| File | Purpose |
|---|---|
| `app/Http/Controllers/EventController.php` | Event management |
| `app/Services/EventHonorService.php` | Honor injection for accompanying teachers |
| `app/Models/Event.php`, `EventParticipant.php` | Event data models |

### M09 — Laporan & Notifikasi
| File | Purpose |
|---|---|
| `app/Http/Controllers/DashboardController.php` | P&L dashboard |
| `app/Http/Controllers/ReportController.php` | Financial reports |
| `app/Services/ScheduleReminderService.php` | WhatsApp schedule reminders |
| `app/Models/WhatsappMessageTemplate.php` | Template management |
| `app/Models/ScheduleReminderLog.php` | Reminder delivery log |

### M10 — Guru Portal
| File | Purpose |
|---|---|
| `app/Http/Controllers/GuruController.php` | Teacher portal (19 methods) |
| `resources/views/guru/dashboard.blade.php` | Mobile-first teacher dashboard |
| `resources/views/guru/jadwal.blade.php` | 2-week calendar view |
| `resources/views/guru/laporan-form.blade.php` | Progress report creation |
| `app/View/Components/GuruLayout.php` | Teacher portal layout |

### M11 — Laporan Progres Murid
| File | Purpose |
|---|---|
| `app/Services/ProgressReportService.php` | Progress report generation |
| `app/Services/ReportTemplateResolverService.php` | Template resolution by instrument |
| `app/Services/ProgressReportPdfService.php` | PDF generation |
| `app/Models/ReportTemplate.php`, `ProgressReport.php` | Report data models |

---

## Quick Reference — Common Patterns

### Accessing Student Data
```php
// CORRECT: via enrollment
$student->primaryEnrollment->package->price_per_month;
$student->primaryEnrollment->teacher->name;
$student->primaryEnrollment->room->code;

// WRONG — columns deleted in May 2026:
// $student->package_id
// $student->assigned_teacher_id
// $student->assigned_room_id
```

### Session Date Handling
```php
// ClassSession has NO 'date' cast on session_date
// Always use Carbon::parse()
Carbon::parse($session->session_date)->format('Y-m-d');
```

### RBAC Checks
```php
// In routes: middleware(['role:Owner'])
// In controllers: $request->user()->hasRole('Owner')
// In Blade: @role('Owner') ... @endrole
// 4 valid roles: Owner, Admin, Auditor, Guru
```

### Audit Logging
```php
use App\Models\AuditLog;
AuditLog::record('CREATE', Student::class, $student->id, $oldValues, $newValues);
```

---

## Getting Started — Development Workflow

1. **Start the dev server:** `composer run dev` (starts both `php artisan serve` and `npm run dev` via Vite)
2. **Run migrations:** `php artisan migrate` (NEVER `migrate:fresh` without explicit confirmation)
3. **Seed the database:** `php artisan db:seed` (creates default roles + Owner/Admin/Auditor accounts)
4. **Run tests:** `php artisan test` (uses SQLite in-memory — safe, no database impact)
5. **Build assets:** `npm run build` (production build for Tailwind CSS)

### Default Login Accounts
```
owner@musikkita.local   / password   → role Owner
admin@musikkita.local   / password   → role Admin
auditor@musikkita.local / password   → role Auditor
```
**Change these passwords before going live.**

### Key Artisan Commands
```bash
php artisan schedule:work       # Run scheduled tasks (local dev)
php artisan generate:sessions   # Manual session generation for next month
php artisan generate:spp        # Manual SPP invoice generation
php artisan honor:calculate     # Manual honor calculation
php artisan guru:create-accounts # Create teacher login accounts
```

---

*Last updated: 2026-06-09 · Based on commit `f4a0b02` · 626 files analyzed*
*To regenerate after major changes: run `/understand --full` then `/understand-onboard`*
