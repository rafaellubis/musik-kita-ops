<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * List & detail invoice (M05).
 *
 * Read-only — semua aksi tulis (catat pembayaran, void) lewat
 * PaymentController. Generator SPP lewat console / button di list.
 */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = Invoice::query()
            ->with('student', 'items')
            ->forMonth($year, $month);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', "%{$term}%")
                  ->orWhereHas('student', fn ($qs) =>
                      $qs->where('full_name', 'like', "%{$term}%")
                         ->orWhere('student_code', 'like', "%{$term}%"));
            });
        }

        $invoices = $query->latest('issued_at')->paginate(50)->withQueryString();

        // Stats per status untuk bulan terpilih
        $stats = Invoice::forMonth($year, $month)
            ->selectRaw('status, COUNT(*) as cnt, SUM(total_amount) as sum_total, SUM(paid_amount) as sum_paid')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Aging: invoice UNPAID/PARTIAL yang sudah lewat due_date
        $overdueCount = Invoice::unpaid()
            ->whereDate('due_date', '<', now())
            ->count();

        // Untuk dropdown filter
        $studentIds = Invoice::forMonth($year, $month)->distinct()->pluck('student_id');
        $students = Student::whereIn('id', $studentIds)
            ->orderBy('full_name')
            ->get(['id', 'student_code', 'full_name']);

        return view('invoices.index', compact(
            'invoices', 'stats', 'students',
            'year', 'month', 'overdueCount'
        ));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'student',
            // Hanya item induk (bukan item DISKON) + eager load diskon tiap item
            'items' => fn ($q) => $q->whereNull('parent_item_id')
                                    ->with(['discountItem', 'addedBy']),
            'payments' => fn ($q) => $q->latest('payment_date'),
            'payments.createdBy',
            'payments.voidedBy',
        ]);

        // Katalog item manual yang aktif — untuk dropdown tambah item
        $catalogItems = \App\Models\InvoiceComponent::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'default_price']);

        return view('invoices.show', compact('invoice', 'catalogItems'));
    }

    /**
     * Trigger manual generate SPP bulanan dari UI.
     * Idempotent — invoice yang sudah ada tidak duplikat.
     */
    public function generateSpp(Request $request, InvoiceService $service)
    {
        $data = $request->validate([
            'year'  => 'required|integer|min:2024|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $report = $service->generateMonthlySPP($data['year'], $data['month']);
        $monthName = Carbon::create($data['year'], $data['month'], 1)->format('F Y');

        return redirect()->route('invoices.index', [
            'year' => $data['year'], 'month' => $data['month'],
        ])->with('success', sprintf(
            'Generate SPP %s: %d invoice baru, %d sudah ada (skip).',
            $monthName, $report['created'], $report['skipped'],
        ));
    }

    /**
     * Trigger manual apply denda harian dari UI.
     */
    public function applyFines(Request $request, InvoiceService $service)
    {
        $data = $request->validate([
            'year'  => 'required|integer|min:2024|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $report = $service->applyLateFinesForMonth($data['year'], $data['month']);
        $monthName = Carbon::create($data['year'], $data['month'], 1)->format('F Y');

        return redirect()->route('invoices.index', [
            'year' => $data['year'], 'month' => $data['month'],
        ])->with('success', sprintf(
            'Apply denda %s: %d invoice unpaid diproses, %d item denda dibuat/update.',
            $monthName, $report['processed'], $report['updated'],
        ));
    }

    /**
     * Halaman A4 untuk dicetak (Ctrl+P → save PDF / cetak fisik).
     * Layout minimalist tanpa nav. CSS @media print untuk auto-hide tombol.
     */
    public function print(Invoice $invoice)
    {
        $invoice->load([
            'student.primaryEnrollment.package.instrument',
            'items' => fn ($q) => $q->whereNull('parent_item_id')->with('discountItem'),
            'validPayments',
        ]);

        return view('invoices.print', compact('invoice'));
    }
}
