# Finance Report PDF Print — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuat standalone print view `/reports/finance/print` yang menghasilkan cetakan PDF laporan keuangan profesional multi-halaman (P&L, Honor Guru, Gaji Staff, Pengeluaran Operasional, Petty Cash).

**Architecture:** Ekstrak semua query di `ReportController::finance()` ke private method `buildFinanceData()`, tambah method `financePrint()` yang reuse data yang sama, render ke standalone Blade view baru tanpa `x-app-layout`. View cetak auto-trigger `window.print()` on load.

**Tech Stack:** Laravel 11, PHP 8.3, Blade standalone HTML (tanpa Tailwind), CSS @media print, Google Fonts CDN (Playfair Display + Hanken Grotesk), CSS `@page` counter untuk nomor halaman.

**Spec:** `docs/superpowers/specs/2026-07-02-finance-report-print-design.md`

## Global Constraints

- PHP 8.3, Laravel 11 — tidak ada perubahan versi
- Middleware route: `role:Owner` (konsisten dengan route `reports.finance` yang sudah ada)
- Komentar kode: Bahasa Indonesia
- Logo: `asset('images/logo-musikkita-light-mode.PNG')` — jangan hardcode path
- Warna brand: primer `#4A1F0A`, aksen `#5DB890`, muted `#7A3818`
- Font: Playfair Display (heading) + Hanken Grotesk (body) via Google Fonts CDN
- Honor/gaji: hanya berstatus `PAID` yang masuk P&L (konsisten dengan logika existing)
- Tidak ada perubahan ke slip honor guru, slip gaji staff, atau invoice
- Testing WAJIB menggunakan SQLite `:memory:` — jangan jalankan `migrate:fresh`

---

## Task 1: Refactor Controller + Tambah Route

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/FinancePrintTest.php`

**Interfaces:**
- Produces: `private function buildFinanceData(int $year, int $month): array`
- Produces: `public function financePrint(Request $request)` → `view('reports.finance-print', $data)`
- Produces: named route `reports.finance.print`

- [ ] **Step 1: Tulis test untuk route `financePrint()`**

  Buat `tests/Feature/FinancePrintTest.php`:

  ```php
  <?php

  namespace Tests\Feature;

  use App\Models\User;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Spatie\Permission\Models\Role;
  use Tests\TestCase;

  class FinancePrintTest extends TestCase
  {
      use RefreshDatabase;

      /** @test */
      public function owner_bisa_akses_finance_print(): void
      {
          Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
          $owner = User::factory()->create();
          $owner->assignRole('Owner');

          $response = $this->actingAs($owner)
              ->get('/reports/finance/print?year=2026&month=1');

          $response->assertStatus(200);
          $response->assertViewIs('reports.finance-print');
          $response->assertSee('Laporan Keuangan');
      }

      /** @test */
      public function admin_tidak_bisa_akses_finance_print(): void
      {
          Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
          Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
          $admin = User::factory()->create();
          $admin->assignRole('Admin');

          $response = $this->actingAs($admin)
              ->get('/reports/finance/print?year=2026&month=1');

          $response->assertStatus(403);
      }

      /** @test */
      public function guest_redirect_ke_login(): void
      {
          $response = $this->get('/reports/finance/print');
          $response->assertRedirect('/login');
      }
  }
  ```

- [ ] **Step 2: Jalankan test — verifikasi FAIL**

  ```bash
  php artisan test tests/Feature/FinancePrintTest.php --env=testing
  ```
  Expected: FAIL — route belum ada

- [ ] **Step 3: Refactor `ReportController.php`**

  Ganti seluruh isi `app/Http/Controllers/ReportController.php`:

  ```php
  <?php

  namespace App\Http\Controllers;

  use App\Models\Expense;
  use App\Models\HonorSlip;
  use App\Models\PettyCashExpense;
  use App\Models\PettyCashTopup;
  use App\Models\StaffPayrollSlip;
  use App\Models\Payment;
  use App\Models\Student;
  use Carbon\Carbon;
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\DB;

  /**
   * Laporan keuangan dan operasional (M09).
   *
   * Route:
   *   GET /reports/finance       -> laporan P&L bulanan (tampilan web)
   *   GET /reports/finance/print -> standalone print view untuk cetak PDF
   *   GET /reports/students      -> laporan statistik murid
   */
  class ReportController extends Controller
  {
      /**
       * Laporan keuangan bulanan: tampilan web biasa.
       */
      public function finance(Request $request)
      {
          $year  = (int) $request->get('year', now()->year);
          $month = (int) $request->get('month', now()->month);

          return view('reports.finance', $this->buildFinanceData($year, $month));
      }

      /**
       * Laporan keuangan: standalone print view untuk cetak PDF profesional.
       */
      public function financePrint(Request $request)
      {
          $year  = (int) $request->get('year', now()->year);
          $month = (int) $request->get('month', now()->month);

          return view('reports.finance-print', $this->buildFinanceData($year, $month));
      }

      /**
       * Kumpulkan semua data keuangan untuk bulan & tahun tertentu.
       * Digunakan bersama oleh finance() dan financePrint() — tidak ada duplikasi query.
       */
      private function buildFinanceData(int $year, int $month): array
      {
          // ===== REVENUE — breakdown per jenis item =====
          $revenueByType = DB::table('payments')
              ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
              ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
              ->whereNull('payments.voided_at')
              ->whereYear('payments.payment_date', $year)
              ->whereMonth('payments.payment_date', $month)
              ->select(
                  'invoice_items.item_code',
                  DB::raw('COUNT(DISTINCT invoices.id) as invoice_count'),
                  DB::raw('SUM(invoice_items.amount) as total')
              )
              ->groupBy('invoice_items.item_code')
              ->orderBy('total', 'desc')
              ->get();

          // Total bayar bulan ini (semua metode, tidak void)
          $totalRevenue = Payment::whereNull('voided_at')
              ->whereYear('payment_date', $year)
              ->whereMonth('payment_date', $month)
              ->sum('amount');

          // Breakdown per metode bayar (CASH, TRANSFER, QRIS, DEBIT)
          $revenueByMethod = Payment::whereNull('voided_at')
              ->whereYear('payment_date', $year)
              ->whereMonth('payment_date', $month)
              ->selectRaw('method, SUM(amount) as total')
              ->groupBy('method')
              ->pluck('total', 'method');

          // ===== HONOR GURU =====
          $honorSlips = HonorSlip::where('year', $year)
              ->where('month', $month)
              ->with('teacher:id,name')
              ->orderBy('total_honor', 'desc')
              ->get();
          $totalHonor = $honorSlips->sum('total_honor');
          $honorPaid  = $honorSlips->where('status', HonorSlip::STATUS_PAID)->sum('total_honor');

          // ===== GAJI STAFF (M12) =====
          $staffPayrollSlips = StaffPayrollSlip::where('year', $year)
              ->where('month', $month)
              ->with('employee:id,full_name,position')
              ->orderBy('net_salary', 'desc')
              ->get();
          $totalStaffPayroll = $staffPayrollSlips->sum('net_salary');
          $staffPayrollPaid  = $staffPayrollSlips
              ->where('status', StaffPayrollSlip::STATUS_PAID)
              ->sum('net_salary');

          // ===== PETTY CASH =====
          $totalPettyCashTopup = (int) PettyCashTopup::forMonth($year, $month)->sum('amount');
          $pettyCashExpenses = PettyCashExpense::forMonth($year, $month)
              ->with('category')
              ->orderByDesc('expense_date')
              ->get();
          $totalPettyCashExpense = (int) $pettyCashExpenses->sum('amount');

          $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();
          $saldoPettyCashAkhir = (int) PettyCashTopup::whereDate('topup_date', '<=', $monthEnd)->sum('amount')
              - (int) PettyCashExpense::whereDate('expense_date', '<=', $monthEnd)->sum('amount');

          // ===== PENGELUARAN OPERASIONAL — TRANSFER saja, exclude GAJI_STAFF =====
          $expenseByCategory = Expense::forMonth($year, $month)
              ->where('expenses.payment_method', Expense::METHOD_TRANSFER)
              ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
              ->where('expense_categories.code', '!=', 'GAJI_STAFF')
              ->select(
                  'expense_categories.name as cat_name',
                  'expense_categories.code as cat_code',
                  DB::raw('SUM(expenses.amount) as total'),
                  DB::raw('COUNT(*) as cnt')
              )
              ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.code')
              ->orderBy('total', 'desc')
              ->get();
          $totalPengeluaranOperasional = (int) $expenseByCategory->sum('total');

          // ===== LABA / RUGI BERSIH =====
          $labaBersih = $totalRevenue
              - $honorPaid
              - $staffPayrollPaid
              - $totalPengeluaranOperasional
              - $totalPettyCashTopup;

          $monthName = Carbon::create($year, $month, 1)->format('F Y');

          return compact(
              'year', 'month', 'monthName',
              'revenueByType', 'totalRevenue', 'revenueByMethod',
              'honorSlips', 'totalHonor', 'honorPaid',
              'staffPayrollSlips', 'totalStaffPayroll', 'staffPayrollPaid',
              'expenseByCategory', 'totalPengeluaranOperasional',
              'totalPettyCashTopup', 'pettyCashExpenses', 'totalPettyCashExpense', 'saldoPettyCashAkhir',
              'labaBersih',
          );
      }

      /**
       * Laporan statistik murid: distribusi status, instrumen, enrollment.
       */
      public function students(Request $request)
      {
          $year  = (int) $request->get('year', now()->year);
          $month = (int) $request->get('month', now()->month);

          $byStatus = Student::selectRaw('status, COUNT(*) as total')
              ->groupBy('status')
              ->orderBy('total', 'desc')
              ->pluck('total', 'status')
              ->toArray();

          $byInstrument = DB::table('students')
              ->join('enrollments', function ($j) {
                  $j->on('students.id', '=', 'enrollments.student_id')
                    ->where('enrollments.status', 'ACTIVE');
              })
              ->join('packages', 'enrollments.package_id', '=', 'packages.id')
              ->join('instruments', 'packages.instrument_id', '=', 'instruments.id')
              ->where('students.status', 'Aktif')
              ->select('instruments.name as instr_name', DB::raw('COUNT(DISTINCT students.id) as total'))
              ->groupBy('instruments.id', 'instruments.name')
              ->orderBy('total', 'desc')
              ->get();

          $muridBaru = Student::where('active_since', '>=', Carbon::create($year, $month, 1)->startOfMonth())
              ->where('active_since', '<=', Carbon::create($year, $month, 1)->endOfMonth())
              ->count();

          $muridMundur = Student::whereIn('status', ['Mengundurkan Diri', 'Selesai'])
              ->whereYear('updated_at', $year)
              ->whereMonth('updated_at', $month)
              ->count();

          $monthName = Carbon::create($year, $month, 1)->format('F Y');

          return view('reports.students', compact(
              'year', 'month', 'monthName',
              'byStatus', 'byInstrument',
              'muridBaru', 'muridMundur',
          ));
      }
  }
  ```

- [ ] **Step 4: Tambah route di `routes/web.php`**

  Temukan blok `// ===== M09: Owner only =====` (sekitar baris 616). Tambah route `finance.print` setelah route `finance`:

  ```php
  // ===== M09: Owner only =====
  Route::middleware('role:Owner')->group(function () {
      Route::get('reports/finance',
          [ReportController::class, 'finance']
      )->name('reports.finance');

      // Cetak PDF laporan keuangan — standalone print view
      Route::get('reports/finance/print',
          [ReportController::class, 'financePrint']
      )->name('reports.finance.print');

      Route::get('audit-logs',
          [AuditLogController::class, 'index']
      )->name('audit-logs.index');
  });
  ```

- [ ] **Step 5: Jalankan test — verifikasi PASS**

  ```bash
  php artisan test tests/Feature/FinancePrintTest.php --env=testing
  ```
  Expected: 3 test PASS. Jika gagal karena view belum ada, buat file kosong sementara:
  `touch resources/views/reports/finance-print.blade.php`

- [ ] **Step 6: Commit**

  ```bash
  git add app/Http/Controllers/ReportController.php routes/web.php tests/Feature/FinancePrintTest.php
  git commit -m "[M09] Refactor: ekstrak buildFinanceData(), tambah route finance.print"
  ```

---

## Task 2: Buat Standalone Print View + Update Tombol

**Files:**
- Create: `resources/views/reports/finance-print.blade.php`
- Modify: `resources/views/reports/finance.blade.php` (ubah tombol cetak, baris 25-28)

**Interfaces:**
- Consumes dari Task 1: variabel dari `buildFinanceData()` → `year`, `month`, `monthName`, `revenueByType`, `totalRevenue`, `revenueByMethod`, `honorSlips`, `totalHonor`, `honorPaid`, `staffPayrollSlips`, `totalStaffPayroll`, `staffPayrollPaid`, `expenseByCategory`, `totalPengeluaranOperasional`, `totalPettyCashTopup`, `pettyCashExpenses`, `totalPettyCashExpense`, `saldoPettyCashAkhir`, `labaBersih`
- Consumes dari Task 1: route name `reports.finance.print`

- [ ] **Step 1: Buat `resources/views/reports/finance-print.blade.php`**

  Lihat konten lengkap di bagian lampiran plan (Part 2). File ini adalah standalone HTML (tidak extend layout apapun).

- [ ] **Step 2: Update tombol di `finance.blade.php`**

  Temukan di sekitar baris 25–28:
  ```html
  <button onclick="window.print()"
          class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm no-print">
      Cetak PDF
  </button>
  ```

  Ganti dengan:
  ```html
  <a href="{{ route('reports.finance.print', ['year' => $year, 'month' => $month]) }}"
     target="_blank"
     class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm no-print inline-block">
      Cetak PDF
  </a>
  ```

- [ ] **Step 3: Test manual di browser**

  1. Jalankan `php artisan serve` atau akses via Laragon
  2. Login sebagai Owner (`owner@musikkita.local / password`)
  3. Buka `/reports/finance`
  4. Klik "Cetak PDF" → tab baru terbuka di `/reports/finance/print`
  5. Dialog print browser muncul otomatis
  6. Verifikasi di Print Preview:
     - ✅ Halaman 1: Header logo, P&L summary, metode penerimaan
     - ✅ Halaman 2: Tabel honor guru (jika ada data bulan ini)
     - ✅ Halaman 3: Gaji staff + pengeluaran operasional
     - ✅ Halaman 4: Petty cash ringkasan + transaksi
     - ✅ Toolbar (tombol cetak/kembali) tidak tampil di preview cetak
     - ✅ Angka keuangan terformat benar (Rp 1.234.567)
     - ✅ Warna hijau untuk laba, merah untuk rugi/beban

- [ ] **Step 4: Jalankan test suite**

  ```bash
  php artisan test tests/Feature/FinancePrintTest.php --env=testing
  ```
  Expected: 3 test PASS

- [ ] **Step 5: Commit final**

  ```bash
  git add resources/views/reports/finance-print.blade.php resources/views/reports/finance.blade.php
  git commit -m "[M09] Finance report: standalone PDF print view profesional multi-halaman"
  ```

---

## Self-Review

**Spec coverage:**
- ✅ Route `GET /reports/finance/print` → Task 1 Step 4
- ✅ `buildFinanceData()` private method → Task 1 Step 3
- ✅ Halaman 1 P&L (pendapatan, beban, laba/rugi, metode) → Task 2 Step 1
- ✅ Halaman 2 honor guru → Task 2 Step 1
- ✅ Halaman 3 gaji staff + pengeluaran → Task 2 Step 1
- ✅ Halaman 4 petty cash → Task 2 Step 1
- ✅ Tombol "Cetak PDF" → link route baru → Task 2 Step 2
- ✅ Middleware `role:Owner` → Task 1 Step 4
- ✅ Auto-print on load → Task 2 Step 1 (script di akhir HTML)
- ✅ Slip honor/gaji/invoice tidak disentuh — dikonfirmasi tidak ada di tasks

**Placeholder scan:** tidak ada TBD/TODO/implement later

**Type consistency:**
- `buildFinanceData()` return `compact(...)` → semua variabel konsisten di view ✅
- Route name `reports.finance.print` konsisten di `web.php` dan `finance.blade.php` ✅

