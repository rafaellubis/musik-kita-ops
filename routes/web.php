<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\HonorController;
use App\Http\Controllers\InstrumentController;
use App\Http\Controllers\InvoiceComponentController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PayrollConfigController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\KalenderController;
use App\Http\Controllers\ProgressReportController;
use App\Http\Controllers\ReportTemplateController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
untuk otomatis ke homepage
Route::get('/', function () {
    return view('welcome');
});
*/
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:Owner|Admin|Auditor'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
| Pembagian akses (sesuai CLAUDE.md):
|   - OWNER   : full akses (termasuk ubah harga, hapus master data)
|   - ADMIN   : operasional harian (TIDAK boleh ubah harga / hapus master data)
|   - AUDITOR : read-only seluruh data
|
| URUTAN GROUP itu PENTING: Laravel match route by registration order.
| Group dengan path statis (mis. /students/create) harus DIDAFTARKAN
| SEBELUM group dengan wildcard (mis. /students/{student}). Kalau dibalik,
| URL /students/create akan tertangkap oleh /students/{student} → 404.
|
| Karena itu kita daftarkan write group dulu (punya /students/create),
| baru read group di akhir (punya /students/{student}).
*/
Route::middleware('auth')->group(function () {

    // ============= Profil Pribadi =============
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /* ======================================================================
     | WRITE OPERASIONAL (Owner + Admin)
     | Master data yang aman diubah Admin sehari-hari.
     | DIDAFTARKAN DULUAN agar path statis (/students/create dll) tidak
     | tertangkap wildcard /students/{student} di read group.
     |====================================================================== */
    Route::middleware('role:Owner|Admin')->group(function () {

        // ===== Notifikasi =====
        // PENTING: route read-all HARUS sebelum {notification}/read agar tidak dibind sebagai ID
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->name('notifications.read');

        // Instrument
        Route::resource('instruments', InstrumentController::class)
            ->except(['index', 'show']);
        Route::post('instruments/{instrument}/toggle-active',
            [InstrumentController::class, 'toggleActive']
        )->name('instruments.toggle-active');

        // Teacher (+ matriks instrumen di-handle di controller)
        Route::resource('teachers', TeacherController::class)->except(['index', 'show']);

        // Holiday
        Route::resource('holidays', HolidayController::class)->except(['index', 'show']);

        // Room
        Route::resource('rooms', RoomController::class)->except(['index', 'show']);

        // ===== M02: Murid =====
        Route::resource('students', StudentController::class)->except(['index', 'show']);

        // M02 Lifecycle Actions — transisi status via StudentLifecycleService.
        // Semua POST. Form inline ada di halaman students/show.
        Route::post('students/{student}/lifecycle/start-trial',
            [StudentController::class, 'startTrial']
        )->name('students.start-trial');
        Route::post('students/{student}/lifecycle/convert-active',
            [StudentController::class, 'convertActive']
        )->name('students.convert-active');
        Route::post('students/{student}/lifecycle/skip-trial',
            [StudentController::class, 'skipTrial']
        )->name('students.skip-trial');
        Route::post('students/{student}/lifecycle/start-cuti',
            [StudentController::class, 'startCuti']
        )->name('students.start-cuti');
        Route::post('students/{student}/lifecycle/withdraw',
            [StudentController::class, 'withdraw']
        )->name('students.withdraw');
        Route::post('students/{student}/lifecycle/complete',
            [StudentController::class, 'complete']
        )->name('students.complete');
        Route::post('students/{student}/lifecycle/return-from-cuti',
            [StudentController::class, 'returnFromCuti']
        )->name('students.return-from-cuti');
        Route::post('students/{student}/lifecycle/reactivate',
            [StudentController::class, 'reactivate']
        )->name('students.reactivate');

        // ===== M03: Schedule mingguan tetap =====
        // Schedule selalu attach ke enrollment ACTIVE murid.
        Route::post('students/{student}/schedules',
            [ScheduleController::class, 'store']
        )->name('schedules.store');
        Route::patch('schedules/{schedule}',
            [ScheduleController::class, 'update']
        )->name('schedules.update');
        Route::delete('schedules/{schedule}',
            [ScheduleController::class, 'destroy']
        )->name('schedules.destroy');
        Route::post('schedules/{schedule}/toggle-active',
            [ScheduleController::class, 'toggleActive']
        )->name('schedules.toggle-active');

        // ===== Multi-Kelas: Manajemen enrollment per murid =====
        // Tambah kelas baru, jadikan utama, dan hentikan kelas
        Route::post('students/{student}/enrollments',
            [EnrollmentController::class, 'store']
        )->name('students.enrollments.store');
        Route::patch('students/{student}/enrollments/{enrollment}/primary',
            [EnrollmentController::class, 'setPrimary']
        )->name('students.enrollments.set-primary');
        Route::delete('students/{student}/enrollments/{enrollment}',
            [EnrollmentController::class, 'destroy']
        )->name('students.enrollments.destroy');

        // ===== M03: Generator sesi manual =====
        Route::post('sessions/generate',
            [SessionController::class, 'generate']
        )->name('sessions.generate');

        // M03: Edit jam, guru, ruang satu sesi (conflict detection)
        Route::patch('sessions/{classSession}',
            [SessionController::class, 'update']
        )->name('sessions.update');

        // M03: Hapus sesi SCHEDULED atau LIBUR
        Route::delete('sessions/{classSession}',
            [SessionController::class, 'destroy']
        )->name('sessions.destroy');

        // ===== M04: Open Slot Board — sesi IZIN_PENDING tanpa replacement =====
        // PENTING: route statis /absensi/open-slots harus SEBELUM wildcard /absensi/{classSession}
        Route::get('/absensi/open-slots',
            [AbsensiController::class, 'openSlotBoard']
        )->name('absensi.open-slots');
        Route::post('/absensi/open-slots/{session}/assign',
            [AbsensiController::class, 'assignOpenSlot']
        )->name('absensi.open-slots.assign');
        Route::post('/absensi/open-slots/{session}/schedule',
            [AbsensiController::class, 'scheduleReplacement']
        )->name('absensi.open-slots.schedule');

        // ===== M04: Absensi Harian — update inline per sesi via AJAX =====
        Route::patch('/absensi/{classSession}',
            [AbsensiController::class, 'update']
        )->name('absensi.update');

        // ===== M04: Split Reschedule — buat Part 1 dan Part 2 sesi pengganti =====
        // POST /absensi/{classSession}/split/1 atau /split/2
        // part parameter hanya terima nilai 1 atau 2 via where() constraint
        Route::post('/absensi/{classSession}/split/{part}',
            [AbsensiController::class, 'storeSplitPart']
        )->name('absensi.split')->where('part', '[12]');

        // ===== M05: Catat Pembayaran =====
        // Void payment di-protect role:Owner di group sensitif di bawah.
        Route::post('invoices/{invoice}/payments',
            [PaymentController::class, 'store']
        )->name('payments.store');

        // M05: Generator SPP & denda manual (analog cron)
        Route::post('invoices/generate-spp',
            [InvoiceController::class, 'generateSpp']
        )->name('invoices.generate-spp');
        Route::post('invoices/apply-fines',
            [InvoiceController::class, 'applyFines']
        )->name('invoices.apply-fines');
        Route::post('students/{student}/generate-bundle',
            [InvoiceController::class, 'generateBundle']
        )->name('invoices.generate-bundle');

        // M05: Generate invoice Final Project Kids Class (KIDS_FP)
        Route::post('students/{student}/generate-kids-fp',
            [InvoiceController::class, 'generateKidsFp']
        )->name('invoices.generate-kids-fp');

        // ===== M07: Pengeluaran — create/edit oleh Owner dan Admin =====
        Route::resource('expenses', ExpenseController::class)
            ->except(['index', 'show', 'destroy']);

    });

    /* ======================================================================
     | WRITE SENSITIF (Owner only)
     | - Paket: ubah harga = berdampak ke tagihan + honor
     | - PayrollConfig: ubah formula honor
     | - InvoiceComponent: ubah komponen tagihan
     | - Import murid dari Excel (sekali pakai, migrasi data)
     |====================================================================== */
    Route::middleware('role:Owner')->group(function () {

        // ===== User Management — kelola akun login Owner/Admin/Auditor/Guru =====
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Import murid dari Excel (Fase 1 — migrasi data, akses dari halaman profil)
        Route::get('/import',            [ImportController::class, 'index'])->name('import.index');
        Route::get('/import/template',   [ImportController::class, 'downloadTemplate'])->name('import.template');
        Route::post('/import/validate',  [ImportController::class, 'validate'])->name('import.validate');
        Route::post('/import/confirm',   [ImportController::class, 'confirm'])->name('import.confirm');
        Route::post('/import/cancel',    [ImportController::class, 'cancel'])->name('import.cancel');

        Route::resource('packages', PackageController::class)->except(['index', 'show']);

        Route::resource('payroll-configs', PayrollConfigController::class)
            ->parameters(['payroll-configs' => 'payrollConfig'])
            ->except(['index', 'show']);

        Route::resource('invoice-components', InvoiceComponentController::class)
            ->parameters(['invoice-components' => 'invoiceComponent'])
            ->except(['index', 'show']);

        // ===== M07: Pengeluaran — hapus hanya Owner =====
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])
            ->name('expenses.destroy');

        // ===== M07: Kategori pengeluaran — master data, hanya Owner =====
        Route::resource('expense-categories', ExpenseCategoryController::class)
            ->parameters(['expense-categories' => 'expenseCategory'])
            ->except(['index', 'show']);

        // BR-5.18: Void pembayaran hanya Owner.
        Route::post('payments/{payment}/void',
            [PaymentController::class, 'void']
        )->name('payments.void');

        // ===== Laporan Progres — Template Master Data (Owner only) =====
        // index + show didaftarkan juga di group read-only di bawah.
        Route::resource('report-templates', ReportTemplateController::class)
            ->parameters(['report-templates' => 'reportTemplate'])
            ->except(['index', 'show']);

        Route::post('report-templates/{reportTemplate}/sections',
            [ReportTemplateController::class, 'storeSection'])
            ->name('report-templates.sections.store');

        Route::delete('report-templates/{reportTemplate}/sections/{section}',
            [ReportTemplateController::class, 'destroySection'])
            ->name('report-templates.sections.destroy');

        Route::post('report-templates/{reportTemplate}/sections/{section}/items',
            [ReportTemplateController::class, 'storeItem'])
            ->name('report-templates.items.store');

        Route::delete('report-templates/{reportTemplate}/sections/{section}/items/{item}',
            [ReportTemplateController::class, 'destroyItem'])
            ->name('report-templates.items.destroy');


        // ===== M06: Honor Guru — aksi sensitif (Owner only) =====
        // Kalkulasi, edit komponen manual, dan tandai dibayar.
        // READ (index, show, print) di group read-only di bawah.
        Route::post('honors/calculate',
            [HonorController::class, 'calculate']
        )->name('honors.calculate');
        Route::get('honors/{honor}/edit',
            [HonorController::class, 'edit']
        )->name('honors.edit');
        Route::patch('honors/{honor}',
            [HonorController::class, 'update']
        )->name('honors.update');
        Route::post('honors/{honor}/mark-paid',
            [HonorController::class, 'markPaid']
        )->name('honors.mark-paid');

        // ===== M08: Event — write sensitif (Owner only) =====
        // Buat/edit event, input hasil ujian, tandai selesai, kelola slip honor.
        Route::resource('events', EventController::class)->except(['index', 'show', 'destroy']);
        Route::post('events/{event}/complete',
            [EventController::class, 'complete']
        )->name('events.complete');
        Route::patch('events/{event}/exam-results',
            [EventController::class, 'saveExamResults']
        )->name('events.exam-results');

    });

    /* ======================================================================
     | WRITE OPERASIONAL — Peserta event (Owner + Admin)
     | Admin boleh tambah/hapus peserta event.
     |====================================================================== */
    Route::middleware('role:Owner|Admin')->group(function () {
        Route::post('events/{event}/participants',
            [EventController::class, 'addParticipant']
        )->name('events.participants.store');
        Route::delete('event-participants/{participant}',
            [EventController::class, 'removeParticipant']
        )->name('event-participants.destroy');
        Route::patch('event-participants/{participant}/teacher',
            [EventController::class, 'updateParticipantTeacher']
        )->name('event-participants.update-teacher');
    });

    /* ======================================================================
     | WRITE OPERASIONAL — Item manual invoice (Owner + Admin)
     | Admin boleh tambah/hapus item manual, tapi tidak bisa void payment.
     |====================================================================== */
    Route::middleware('role:Owner|Admin')->group(function () {
        // Tambah item manual ke invoice
        Route::post('invoices/{invoice}/items',
            [InvoiceItemController::class, 'store']
        )->name('invoice-items.store');

        // Hapus item manual dari invoice
        Route::delete('invoice-items/{invoiceItem}',
            [InvoiceItemController::class, 'destroy']
        )->name('invoice-items.destroy');

        // Diskon per item invoice — beri/update dan hapus
        Route::post('invoice-items/{invoiceItem}/discount',
            [\App\Http\Controllers\DiscountController::class, 'store']
        )->name('invoice-items.discount.store');

        Route::delete('invoice-items/{invoiceItem}/discount',
            [\App\Http\Controllers\DiscountController::class, 'destroy']
        )->name('invoice-items.discount.destroy');
    });

    /* ======================================================================
     | READ-ONLY (Owner + Admin + Auditor)
     | DIDAFTARKAN PALING AKHIR karena route show pakai wildcard
     | /students/{student} — kalau didaftarkan dulu, URL statis seperti
     | /students/create akan jatuh ke route ini.
     |====================================================================== */
    Route::middleware('role:Owner|Admin|Auditor')->group(function () {
        Route::resource('instruments', InstrumentController::class)->only(['index']);
        Route::resource('packages', PackageController::class)->only(['index']);
        Route::resource('teachers', TeacherController::class)->only(['index']);
        Route::resource('holidays', HolidayController::class)->only(['index']);
        Route::resource('rooms', RoomController::class)->only(['index']);

        Route::resource('payroll-configs', PayrollConfigController::class)
            ->parameters(['payroll-configs' => 'payrollConfig'])
            ->only(['index']);

        Route::resource('invoice-components', InvoiceComponentController::class)
            ->parameters(['invoice-components' => 'invoiceComponent'])
            ->only(['index']);

        // Murid: Auditor boleh lihat detail murid
        Route::resource('students', StudentController::class)->only(['index', 'show']);

        // API internal — filter guru by instrumen (AJAX dari form lifecycle murid)
        // READ-ONLY: tidak ada write, aman diakses semua role termasuk Auditor
        Route::get('/api/teachers-by-instrument/{instrumentId}',
            [StudentController::class, 'teachersByInstrument']
        )->name('api.teachers-by-instrument');

        // ===== M03: List sesi (read-only, Auditor juga boleh) =====
        Route::get('sessions',
            [SessionController::class, 'index']
        )->name('sessions.index');

        // ===== M04: Absensi Harian — tampilan per tanggal (read + form inline) =====
        Route::get('/absensi',
            [AbsensiController::class, 'index']
        )->name('absensi.index');

        // ===== Kalender Jadwal Mingguan (read-only, semua role) =====
        Route::get('/kalender',
            [KalenderController::class, 'index']
        )->name('kalender.index');

        // ===== M07: Pengeluaran — read-only =====
        Route::resource('expenses', ExpenseController::class)->only(['index', 'show']);

        Route::resource('expense-categories', ExpenseCategoryController::class)
            ->parameters(['expense-categories' => 'expenseCategory'])
            ->only(['index']);

        // ===== M06: Honor Guru — read-only =====
        Route::get('honors',
            [HonorController::class, 'index']
        )->name('honors.index');
        // /honors/{honor}/print harus sebelum /honors/{honor} (biar tidak ditangkap show)
        Route::get('honors/{honor}/print',
            [HonorController::class, 'print']
        )->name('honors.print');
        Route::get('honors/{honor}',
            [HonorController::class, 'show']
        )->name('honors.show');

        // ===== M05: List & detail invoice (read-only) =====
        Route::get('invoices',
            [InvoiceController::class, 'index']
        )->name('invoices.index');
        // /invoices/{invoice}/print HARUS sebelum /invoices/{invoice} biar
        // tidak ditangkap show wildcard.
        Route::get('invoices/{invoice}/print',
            [InvoiceController::class, 'print']
        )->name('invoices.print');
        Route::get('invoices/{invoice}',
            [InvoiceController::class, 'show']
        )->name('invoices.show');

        // ===== M08: Event — read-only =====
        Route::resource('events', EventController::class)->only(['index', 'show']);

        // ===== Laporan Progres — Template read-only (Owner + Admin + Auditor) =====
        Route::resource('report-templates', ReportTemplateController::class)
            ->parameters(['report-templates' => 'reportTemplate'])
            ->only(['index', 'show']);

        // ===== Laporan Progres Murid — Admin/Owner/Auditor view laporan yang disubmit guru =====
        // /pdf HARUS sebelum /{progressReport} agar tidak ditangkap wildcard show.
        Route::get('progress-reports', [ProgressReportController::class, 'index'])->name('progress-reports.index');
        Route::get('progress-reports/{progressReport}/pdf', [ProgressReportController::class, 'pdf'])->name('progress-reports.pdf');
        Route::get('progress-reports/{progressReport}', [ProgressReportController::class, 'show'])->name('progress-reports.show');

        // ===== M09: Laporan statistik murid (read-only, semua role) =====
        Route::get('reports/students',
            [ReportController::class, 'students']
        )->name('reports.students');

        // ===== M05: Kuitansi cetak =====
        Route::get('payments/{payment}/receipt',
            [PaymentController::class, 'receipt']
        )->name('payments.receipt');
    });

    // ===== M09: Owner only =====
    Route::middleware('role:Owner')->group(function () {
        // Dashboard P&L dan Laporan Keuangan — data finansial sensitif
        Route::get('reports/finance',
            [ReportController::class, 'finance']
        )->name('reports.finance');

        // Audit Log
        Route::get('audit-logs',
            [AuditLogController::class, 'index']
        )->name('audit-logs.index');
    });
});

// ===== GURU ROUTES =====
Route::middleware(['auth', 'verified', 'role:Guru'])
    ->prefix('guru')
    ->name('guru.')
    ->group(function () {
        Route::get('/dashboard',                     [GuruController::class, 'dashboard'])->name('dashboard');
        Route::get('/jadwal',                        [GuruController::class, 'jadwal'])->name('jadwal');
        Route::get('/honor',                         [GuruController::class, 'honor'])->name('honor');
        Route::get('/honor/{honorSlip}',             [GuruController::class, 'honorShow'])->name('honor.show');
        Route::patch('/sesi/{classSession}/absensi', [GuruController::class, 'updateAbsensi'])->name('absensi.update');
        Route::get('/profil',                        [GuruController::class, 'profil'])->name('profil');
        Route::get('/sesi-pending',                  [GuruController::class, 'sesiPending'])->name('sesi-pending.index');
        Route::post('/sesi-pending/{session}/suggest', [GuruController::class, 'suggestDate'])->name('sesi-pending.suggest');

        // ===== Laporan Progres Murid =====
        Route::get('/laporan',                       [GuruController::class, 'laporan'])->name('laporan.index');
        Route::post('/laporan',                      [GuruController::class, 'laporanStore'])->name('laporan.store');
        Route::get('/laporan/{progressReport}/edit', [GuruController::class, 'laporanEdit'])->name('laporan.edit');
        Route::put('/laporan/{progressReport}',      [GuruController::class, 'laporanUpdate'])->name('laporan.update');
    });

require __DIR__.'/auth.php';
