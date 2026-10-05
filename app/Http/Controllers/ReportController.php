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
        // Satu sumber basis kas: setiap payment valid masuk tepat sekali pada payment_date.
        $validPayments = Payment::whereNull('voided_at')
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->with(['invoice:id,event_id', 'invoice.items:id,invoice_id,item_code'])
            ->get(['id', 'invoice_id', 'amount', 'method', 'payment_date']);

        $revenueByType = $validPayments
            ->groupBy(function (Payment $payment): string {
                $codes = $payment->invoice->items
                    ->where('item_code', '!=', 'DISKON')
                    ->pluck('item_code')
                    ->unique()
                    ->values();

                return $codes->count() === 1 ? $codes->first() : 'GABUNGAN';
            })
            ->map(fn ($payments, $itemCode) => (object) [
                'item_code' => $itemCode,
                'invoice_count' => $payments->pluck('invoice_id')->unique()->count(),
                'total' => (int) $payments->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();

        $totalRevenue = (int) $validPayments->sum('amount');

        // Breakdown per metode bayar (CASH, TRANSFER, QRIS, DEBIT)
        $revenueByMethod = $validPayments
            ->groupBy('method')
            ->map(fn ($payments) => (int) $payments->sum('amount'));

        // ===== HONOR GURU =====
        $honorSlips = HonorSlip::where('year', $year)
            ->where('month', $month)
            ->with('teacher:id,name')
            ->orderBy('total_honor', 'desc')
            ->get();
        $totalHonor = $honorSlips->sum('total_honor');
        $honorPaid  = $honorSlips->where('status', HonorSlip::STATUS_PAID)->sum('total_honor');

        // Guru aktif tanpa slip bulan ini
        $activeTeachers = \App\Models\Teacher::where('is_active', true)->pluck('name', 'id');
        $teachersWithSlip = $honorSlips->pluck('teacher_id')->toArray();
        $teachersWithoutSlip = $activeTeachers->reject(fn($name, $id) => in_array($id, $teachersWithSlip))->values()->toArray();

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
            'honorSlips', 'totalHonor', 'honorPaid', 'teachersWithoutSlip',
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

        $muridTrial = Student::whereYear('trial_date', $year)
            ->whereMonth('trial_date', $month)
            ->count();

        $muridMundur = Student::whereIn('status', ['Mengundurkan Diri', 'Selesai'])
            ->whereYear('updated_at', $year)
            ->whereMonth('updated_at', $month)
            ->count();

        $monthName = Carbon::create($year, $month, 1)->format('F Y');

        return view('reports.students', compact(
            'year', 'month', 'monthName',
            'byStatus', 'byInstrument',
            'muridBaru', 'muridTrial', 'muridMundur',
        ));
    }
}
