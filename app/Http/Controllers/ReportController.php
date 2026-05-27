<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\HonorSlip;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Laporan keuangan dan operasional (M09).
 *
 * Route:
 *   GET /reports/finance  -> laporan P&L bulanan (printable)
 *   GET /reports/students -> laporan statistik murid
 */
class ReportController extends Controller
{
    /**
     * Laporan keuangan bulanan: Revenue, Honor, Pengeluaran, Laba/Rugi.
     */
    public function finance(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

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

        // ===== PENGELUARAN — breakdown per kategori =====
        $expenseByCategory = Expense::forMonth($year, $month)
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select(
                'expense_categories.name as cat_name',
                'expense_categories.code as cat_code',
                DB::raw('SUM(expenses.amount) as total'),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.code')
            ->orderBy('total', 'desc')
            ->get();
        $totalPengeluaran = $expenseByCategory->sum('total');

        // ===== LABA / RUGI =====
        // Laba = Revenue - Honor - Pengeluaran lainnya
        $labaBersih = $totalRevenue - $totalHonor - $totalPengeluaran;

        // ===== INVOICE OVERVIEW =====
        $invoiceStats = Invoice::where('year', $year)->where('month', $month)
            ->selectRaw('status, COUNT(*) as cnt, SUM(total_amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $monthName = Carbon::create($year, $month, 1)->format('F Y');

        // Daftar bulan tersedia (untuk dropdown navigasi)
        $availableMonths = Invoice::selectRaw('DISTINCT year, month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return view('reports.finance', compact(
            'year', 'month', 'monthName',
            'revenueByType', 'totalRevenue', 'revenueByMethod',
            'honorSlips', 'totalHonor', 'honorPaid',
            'expenseByCategory', 'totalPengeluaran',
            'labaBersih',
            'invoiceStats',
            'availableMonths',
        ));
    }

    /**
     * Laporan statistik murid: distribusi status, instrumen, enrollment.
     */
    public function students(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        // Distribusi per status
        $byStatus = Student::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('total', 'desc')
            ->pluck('total', 'status')
            ->toArray();

        // Distribusi murid Aktif per instrumen
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

        // Murid masuk bulan ini (status pernah jadi Aktif di bulan ini)
        $muridBaru = Student::where('active_since', '>=', Carbon::create($year, $month, 1)->startOfMonth())
            ->where('active_since', '<=', Carbon::create($year, $month, 1)->endOfMonth())
            ->count();

        // Murid mundur bulan ini
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
