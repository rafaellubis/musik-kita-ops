<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
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
        $year    = (int) now()->year;
        $month   = (int) now()->month;
        $today   = now()->startOfDay();
        $isOwner = auth()->user()->hasRole('Owner');

        // ===== STATISTIK MURID (semua role) =====
        $muridStats = Student::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $muridAktif = $muridStats['Aktif'] ?? 0;
        $muridTrial = $muridStats['Trial'] ?? 0;
        $muridCuti  = $muridStats['Cuti'] ?? 0;
        $muridCalon = $muridStats['Calon'] ?? 0;
        $muridTotal = array_sum($muridStats);

        // ===== P&L (Owner only) =====
        $revenueBulan = $revenueCash = $revenueTransfer = 0;
        $pengeluaranBulan = $pengeluaranCash = 0;
        $labaBulan = 0;
        $revenueChart   = [];   // data area chart 6 bulan
        $instrumenChart = [];   // data donut distribusi instrumen

        if ($isOwner) {
            $revenueBulan = Payment::whereNull('voided_at')
                ->whereYear('payment_date', $year)
                ->whereMonth('payment_date', $month)
                ->sum('amount');
            $revenueCash = Payment::whereNull('voided_at')
                ->where('method', 'CASH')
                ->whereYear('payment_date', $year)
                ->whereMonth('payment_date', $month)
                ->sum('amount');
            $revenueTransfer  = $revenueBulan - $revenueCash;
            $pengeluaranBulan = Expense::forMonth($year, $month)->sum('amount');
            $pengeluaranCash  = Expense::forMonth($year, $month)->cash()->sum('amount');
            $labaBulan        = $revenueBulan - $pengeluaranBulan;

            // Area chart: pemasukan vs honor 6 bulan terakhir
            for ($i = 5; $i >= 0; $i--) {
                $d = now()->subMonths($i);
                $revenueChart[] = [
                    'label'     => $d->translatedFormat('M Y'),
                    'pemasukan' => (int) Payment::whereNull('voided_at')
                                       ->whereYear('payment_date', $d->year)
                                       ->whereMonth('payment_date', $d->month)
                                       ->sum('amount'),
                    'honor'     => (int) HonorSlip::where('year', $d->year)
                                       ->where('month', $d->month)
                                       ->sum('total_honor'),
                    'pengeluaran' => (int) Expense::forMonth($d->year, $d->month)->sum('amount'),
                ];
            }

            // Donut: distribusi murid aktif per instrumen
            $instrumenChart = DB::table('students')
                ->join('enrollments', function ($j) {
                    $j->on('students.id', '=', 'enrollments.student_id')
                      ->where('enrollments.status', 'ACTIVE');
                })
                ->join('packages', 'enrollments.package_id', '=', 'packages.id')
                ->join('instruments', 'packages.instrument_id', '=', 'instruments.id')
                ->where('students.status', 'Aktif')
                ->select('instruments.name as name', DB::raw('COUNT(DISTINCT students.id) as total'))
                ->groupBy('instruments.id', 'instruments.name')
                ->orderBy('total', 'desc')
                ->get();
        }

        // ===== PETTY CASH, AGING, INVOICE TERLAMA, HONOR (semua role) =====
        $kasmasukTotal = Payment::whereNull('voided_at')
            ->where('method', 'CASH')
            ->whereDate('payment_date', '<=', $today)
            ->sum('amount');
        $kaskeluarTotal = Expense::cash()
            ->whereDate('expense_date', '<=', $today)
            ->sum('amount');
        $saldoKas = $kasmasukTotal - $kaskeluarTotal;

        $aging      = ['current' => 0, 'late1_30' => 0, 'late31' => 0];
        $agingCount = ['current' => 0, 'late1_30' => 0, 'late31' => 0];

        $invoicesUnpaid = Invoice::whereIn('status', ['UNPAID', 'PARTIAL'])
            ->get(['id', 'total_amount', 'paid_amount', 'due_date']);

        foreach ($invoicesUnpaid as $inv) {
            $sisa     = $inv->total_amount - $inv->paid_amount;
            $dueDate  = $inv->due_date ? Carbon::parse($inv->due_date) : null;
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

        $invoiceTerlama = Invoice::whereIn('status', ['UNPAID', 'PARTIAL'])
            ->with('student:id,full_name,student_code')
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $honorBelumBayar = HonorSlip::where('status', HonorSlip::STATUS_CALCULATED)
            ->with('teacher:id,name')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Bar chart: absensi mingguan bulan ini (semua role)
        $attendanceChart = [];
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = Carbon::create($year, $month, 1)->endOfMonth();
        for ($w = 0; $w < 4; $w++) {
            $wStart = $monthStart->copy()->addDays($w * 7);
            $wEnd   = $monthStart->copy()->addDays($w * 7 + 6)->min($monthEnd);
            $attendanceChart[] = [
                'label'  => 'Mg ' . ($w + 1),
                'hadir'  => ClassSession::whereBetween('session_date', [$wStart, $wEnd])
                                ->whereIn('status', ['HADIR', 'HADIR_TERLAMBAT'])->count(),
                'izin'   => ClassSession::whereBetween('session_date', [$wStart, $wEnd])
                                ->whereIn('status', ['IZIN_RESCHEDULE', 'IZIN_VIDEO'])->count(),
                'hangus' => ClassSession::whereBetween('session_date', [$wStart, $wEnd])
                                ->where('status', 'HANGUS')->count(),
            ];
        }

        $monthName = Carbon::create($year, $month, 1)->translatedFormat('F Y');

        return view('dashboard', compact(
            'year', 'month', 'monthName', 'isOwner',
            'revenueBulan', 'revenueCash', 'revenueTransfer',
            'pengeluaranBulan', 'pengeluaranCash',
            'labaBulan',
            'saldoKas', 'kasmasukTotal', 'kaskeluarTotal',
            'muridAktif', 'muridTrial', 'muridCuti', 'muridCalon', 'muridTotal',
            'aging', 'agingCount', 'totalPiutang',
            'invoiceTerlama',
            'honorBelumBayar',
            'revenueChart', 'instrumenChart', 'attendanceChart',
        ));
    }
}
