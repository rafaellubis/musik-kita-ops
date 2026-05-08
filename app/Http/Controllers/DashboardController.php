<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\HonorSlip;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard P&L real-time (M09).
 *
 * Menampilkan ringkasan keuangan + operasional bulan ini:
 *   - Revenue (pembayaran murid) vs Pengeluaran vs Laba/Rugi
 *   - Petty cash saldo
 *   - Statistik murid (per status)
 *   - Aging piutang (0-10 / 11-30 / 31+ hari)
 *   - 10 invoice terlama yang belum lunas (early-warning)
 *   - Slip honor belum dibayar
 */
class DashboardController extends Controller
{
    public function index()
    {
        $year  = (int) now()->year;
        $month = (int) now()->month;
        $today = now()->startOfDay();

        // ===== REVENUE =====
        // Semua pembayaran valid (tidak void) bulan ini
        $revenueBulan = Payment::whereNull('voided_at')
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->sum('amount');

        // Breakdown per metode
        $revenueCash     = Payment::whereNull('voided_at')
            ->where('method', 'CASH')
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->sum('amount');
        $revenueTransfer = $revenueBulan - $revenueCash;

        // ===== PENGELUARAN =====
        $pengeluaranBulan = Expense::forMonth($year, $month)->sum('amount');
        $pengeluaranCash  = Expense::forMonth($year, $month)->cash()->sum('amount');

        // ===== LABA / RUGI =====
        $labaBulan = $revenueBulan - $pengeluaranBulan;

        // ===== PETTY CASH (saldo kas fisik hari ini) =====
        // Semua penerimaan CASH dari murid sampai hari ini
        $kasmasukTotal = Payment::whereNull('voided_at')
            ->where('method', 'CASH')
            ->whereDate('payment_date', '<=', $today)
            ->sum('amount');
        $kaskeluarTotal = Expense::cash()
            ->whereDate('expense_date', '<=', $today)
            ->sum('amount');
        $saldoKas = $kasmasukTotal - $kaskeluarTotal;

        // ===== STATISTIK MURID =====
        $muridStats = Student::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $muridAktif = $muridStats['Aktif'] ?? 0;
        $muridTrial = $muridStats['Trial'] ?? 0;
        $muridCuti  = $muridStats['Cuti'] ?? 0;
        $muridCalon = $muridStats['Calon'] ?? 0;
        $muridTotal = array_sum($muridStats);

        // ===== AGING PIUTANG =====
        // Kelompokkan invoice belum lunas berdasarkan umur piutang
        $invoicesUnpaid = Invoice::whereIn('status', ['UNPAID', 'PARTIAL'])
            ->with('student:id,full_name,student_code')
            ->get(['id', 'invoice_number', 'student_id', 'total_amount', 'paid_amount', 'due_date', 'issued_at']);

        $aging = ['current' => 0, 'late1_30' => 0, 'late31' => 0];
        $agingCount = ['current' => 0, 'late1_30' => 0, 'late31' => 0];

        foreach ($invoicesUnpaid as $inv) {
            $sisa = $inv->total_amount - $inv->paid_amount;
            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date) : null;
            $daysLate = $dueDate ? max(0, $today->diffInDays($dueDate, false) * -1) : 0;

            if ($daysLate <= 0) {
                $aging['current'] += $sisa;
                $agingCount['current']++;
            } elseif ($daysLate <= 30) {
                $aging['late1_30'] += $sisa;
                $agingCount['late1_30']++;
            } else {
                $aging['late31'] += $sisa;
                $agingCount['late31']++;
            }
        }
        $totalPiutang = array_sum($aging);

        // 10 invoice terlama yang belum lunas (early-warning tunggakan)
        $invoiceTerlama = Invoice::whereIn('status', ['UNPAID', 'PARTIAL'])
            ->with('student:id,full_name,student_code')
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        // ===== SLIP HONOR BELUM DIBAYAR =====
        $honorBelumBayar = HonorSlip::where('status', '!=', HonorSlip::STATUS_PAID)
            ->where('status', HonorSlip::STATUS_CALCULATED)
            ->with('teacher:id,name')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        $monthName = Carbon::create($year, $month, 1)->translatedFormat('F Y');

        return view('dashboard', compact(
            'year', 'month', 'monthName',
            'revenueBulan', 'revenueCash', 'revenueTransfer',
            'pengeluaranBulan', 'pengeluaranCash',
            'labaBulan',
            'saldoKas', 'kasmasukTotal', 'kaskeluarTotal',
            'muridAktif', 'muridTrial', 'muridCuti', 'muridCalon', 'muridTotal',
            'aging', 'agingCount', 'totalPiutang',
            'invoiceTerlama',
            'honorBelumBayar',
        ));
    }
}
