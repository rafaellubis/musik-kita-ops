<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoidInvoiceRequest;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * List & detail invoice (M05).
 *
 * Read invoice + generator SPP/denda. Void invoice (Owner|Admin) &
 * void pembayaran (Owner only) lewat method terpisah.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = Invoice::query()
            ->with('student', 'items')
            ->forMonth($year, $month);

        if ($request->filled('status')) {
            if ($request->status === 'overdue') {
                $query->whereIn('status', ['UNPAID', 'PARTIAL'])
                      ->whereDate('due_date', '<', now());
            } else {
                $query->where('status', $request->status);
            }
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
            'voidedBy',
        ]);

        // Sibling invoices untuk panel progress cicilan Kids Bundle (BR-10.10).
        // Hanya di-query jika invoice ini adalah bagian dari cicilan (installment_group_id ada).
        $siblings = $invoice->installment_group_id
            ? Invoice::where('installment_group_id', $invoice->installment_group_id)
                ->orderBy('installment_number')
                ->get(['id', 'installment_number', 'total_amount', 'paid_amount', 'status', 'due_date'])
            : collect();

        // Katalog item manual yang aktif — untuk dropdown tambah item
        $catalogItems = \App\Models\InvoiceComponent::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'default_price']);

        return view('invoices.show', compact('invoice', 'catalogItems', 'siblings'));
    }

    /**
     * Void invoice. Owner atau Admin — middleware role:Owner|Admin di route.
     * Row tidak dihapus; status → VOID + audit log.
     */
    public function void(VoidInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $oldValues = $invoice->only([
            'invoice_number', 'status', 'total_amount', 'student_id', 'enrollment_id',
        ]);

        try {
            $invoice = $this->invoiceService->voidInvoice(
                $invoice,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(
            action: AuditLog::ACTION_VOID,
            entity: $invoice,
            entityLabel: $invoice->invoice_number,
            oldValues: $oldValues,
            newValues: [
                'status'        => Invoice::STATUS_VOID,
                'voided_at'     => $invoice->voided_at?->toDateTimeString(),
                'voided_by'     => $invoice->voided_by,
                'voided_reason' => $invoice->voided_reason,
            ],
            notes: 'Alasan: ' . $request->validated('reason'),
        );

        return back()->with('success', sprintf(
            'Invoice %s berhasil di-void.',
            $invoice->invoice_number,
        ));
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
     * Generate 3 invoice cicilan untuk murid KIDS_CLASS_BUNDLE yang diimport.
     * Hanya bisa dipanggil sekali — ditolak jika invoice cicilan sudah ada.
     */
    public function generateBundle(Request $request, Student $student, InvoiceService $service): RedirectResponse
    {
        abort_if($student->status !== 'Aktif', 422, 'Murid harus berstatus Aktif.');

        $enrollment = $student->primaryEnrollment;
        abort_if(
            !$enrollment || $enrollment->package?->class_type !== 'KIDS_CLASS_BUNDLE',
            422,
            'Kelas utama murid bukan Kids Class Bundle.'
        );

        $alreadyExists = Invoice::where('enrollment_id', $enrollment->id)
            ->where('payment_mode', Invoice::MODE_INSTALLMENT)
            ->notVoid()
            ->exists();
        abort_if($alreadyExists, 422, 'Invoice cicilan sudah pernah dibuat untuk murid ini.');

        $data = $request->validate([
            'program_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2024-01-01', 'before_or_equal:2030-12-31'],
        ], [
            'program_start_date.required'         => 'Tanggal mulai program wajib diisi.',
            'program_start_date.date_format'      => 'Format tanggal harus YYYY-MM-DD (contoh: 2026-03-01).',
            'program_start_date.after_or_equal'   => 'Tanggal mulai program tidak boleh sebelum 2024.',
            'program_start_date.before_or_equal'  => 'Tanggal mulai program tidak boleh setelah 2030.',
        ]);

        $service->createKidsBundleInstallments(
            student:    $student,
            enrollment: $enrollment,
            startDate:  Carbon::parse($data['program_start_date']),
        );

        AuditLog::record(
            action:      AuditLog::ACTION_CREATE,
            entity:      $student,
            entityLabel: "Generate cicilan bundle – {$student->full_name}",
            newValues:   ['program_start_date' => $data['program_start_date']],
        );

        return redirect()
            ->route('students.show', $student)
            ->with('success', '3 invoice cicilan Kids Bundle berhasil dibuat.');
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

    /**
     * Generate invoice Final Project Kids Class (KIDS_FP).
     * Hanya untuk murid KIDS_CLASS yang belum punya invoice KIDS_FP.
     */
    public function generateKidsFp(Student $student): RedirectResponse
    {
        // Guard 1: hanya untuk murid KIDS_CLASS
        $enrollment = $student->primaryEnrollment;
        if (! $enrollment || $enrollment->package->class_type !== 'KIDS_CLASS') {
            abort(403, 'Fitur ini hanya untuk murid Kids Class.');
        }

        // Guard 2: cegah double generate
        $sudahAda = InvoiceItem::whereHas('invoice', fn ($q) =>
            $q->where('student_id', $student->id)->notVoid()
        )->where('item_code', 'KIDS_FP')->exists();

        if ($sudahAda) {
            return redirect()->route('students.show', $student)
                ->with('error', 'Invoice Final Project untuk murid ini sudah pernah dibuat.');
        }

        // Generate invoice via InvoiceService
        $invoice = $this->invoiceService->createOneOff(
            student:      $student,
            items:        [[
                'code'        => 'KIDS_FP',
                'description' => 'Final Project Kids Class',
                'amount'      => InvoiceService::FEE_KIDS_FP,
            ]],
            classType:    'KIDS_CLASS',
            enrollmentId: $enrollment->id,
        );

        AuditLog::record(
            AuditLog::ACTION_CREATE,
            $invoice,
            "Invoice KIDS_FP — {$student->full_name}",
            null,
            ['student_id' => $student->id, 'amount' => InvoiceService::FEE_KIDS_FP],
        );

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice Final Project {$student->full_name} berhasil dibuat.");
    }
}
