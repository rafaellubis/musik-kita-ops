<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventHonorSlipController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HonorController;
use App\Http\Controllers\InstrumentController;
use App\Http\Controllers\InvoiceComponentController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PayrollConfigController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
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

        // API internal — filter guru by instrumen (dipanggil AJAX dari form Murid)
        Route::get('/api/teachers-by-instrument/{instrumentId}',
            [StudentController::class, 'teachersByInstrument']
        )->name('api.teachers-by-instrument');

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

        // ===== M03: Generator sesi manual =====
        Route::post('sessions/generate',
            [SessionController::class, 'generate']
        )->name('sessions.generate');

        // ===== M04: Absensi per sesi =====
        Route::get('sessions/{session}/attendance',
            [AttendanceController::class, 'edit']
        )->name('attendance.edit');
        Route::patch('sessions/{session}/attendance',
            [AttendanceController::class, 'update']
        )->name('attendance.update');

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
    });

    /* ======================================================================
     | WRITE SENSITIF (Owner only)
     | - Paket: ubah harga = berdampak ke tagihan + honor
     | - PayrollConfig: ubah formula honor
     | - InvoiceComponent: ubah komponen tagihan
     |====================================================================== */
    Route::middleware('role:Owner')->group(function () {

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

        // ===== M07: Pengeluaran — Owner bisa hapus, Admin bisa create/edit =====
        // Hapus expense + CRUD kategori di group Owner-only di bawah.
        Route::resource('expenses', ExpenseController::class)
            ->except(['index', 'show', 'destroy']);

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

        // Slip honor event
        Route::post('events/{event}/honor-slips',
            [EventHonorSlipController::class, 'store']
        )->name('event-honor-slips.store');
        Route::get('event-honor-slips/{eventHonorSlip}/edit',
            [EventHonorSlipController::class, 'edit']
        )->name('event-honor-slips.edit');
        Route::patch('event-honor-slips/{eventHonorSlip}',
            [EventHonorSlipController::class, 'update']
        )->name('event-honor-slips.update');
        Route::post('event-honor-slips/{eventHonorSlip}/mark-paid',
            [EventHonorSlipController::class, 'markPaid']
        )->name('event-honor-slips.mark-paid');
        Route::delete('event-honor-slips/{eventHonorSlip}',
            [EventHonorSlipController::class, 'destroy']
        )->name('event-honor-slips.destroy');
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

        // ===== M03: List sesi (read-only, Auditor juga boleh) =====
        Route::get('sessions',
            [SessionController::class, 'index']
        )->name('sessions.index');

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
        // print harus sebelum show agar tidak ditangkap wildcard
        Route::get('event-honor-slips/{eventHonorSlip}/print',
            [EventHonorSlipController::class, 'print']
        )->name('event-honor-slips.print');

        // ===== M09: Laporan (read-only, semua role) =====
        Route::get('reports/finance',
            [ReportController::class, 'finance']
        )->name('reports.finance');
        Route::get('reports/students',
            [ReportController::class, 'students']
        )->name('reports.students');

        // ===== M05: Kuitansi cetak =====
        Route::get('payments/{payment}/receipt',
            [PaymentController::class, 'receipt']
        )->name('payments.receipt');
    });

    // ===== M09: Audit Log (Owner only) =====
    Route::middleware('role:Owner')->group(function () {
        Route::get('audit-logs',
            [AuditLogController::class, 'index']
        )->name('audit-logs.index');
    });
});

require __DIR__.'/auth.php';
