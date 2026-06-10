<?php

namespace App\Http\Controllers;

use App\Models\StaffPayrollItem;
use App\Models\StaffPayrollSlip;
use App\Services\StaffPayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Manajemen slip gaji karyawan non-guru (M12).
 */
class StaffPayrollController extends Controller
{
    public function __construct(
        private readonly StaffPayrollService $service
    ) {}

    public function index(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = StaffPayrollSlip::query()
            ->join('employees', 'staff_payroll_slips.employee_id', '=', 'employees.id')
            ->select('staff_payroll_slips.*')
            ->with('employee')
            ->forMonth($year, $month)
            ->orderBy('status')
            ->orderBy('employees.full_name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $slips = $query->paginate(50)->withQueryString();

        $stats = StaffPayrollSlip::forMonth($year, $month)
            ->selectRaw('status, COUNT(*) as cnt, SUM(net_salary) as sum_total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $slipEmployeeIds = StaffPayrollSlip::forMonth($year, $month)->pluck('employee_id');
        $missingCount    = \App\Models\Employee::active()
            ->whereNotIn('id', $slipEmployeeIds)
            ->count();

        $monthName = Carbon::create($year, $month, 1)->locale('id')->translatedFormat('F Y');

        return view('staff-payrolls.index', compact(
            'slips', 'stats', 'missingCount',
            'year', 'month', 'monthName'
        ));
    }

    public function show(StaffPayrollSlip $staffPayroll)
    {
        $staffPayroll->load('employee.user', 'items', 'paidBy', 'createdBy', 'expense');

        $monthName = Carbon::create($staffPayroll->year, $staffPayroll->month, 1)
            ->locale('id')->translatedFormat('F Y');

        $itemCodes = StaffPayrollItem::CODE_LABELS;

        return view('staff-payrolls.show', compact('staffPayroll', 'monthName', 'itemCodes'));
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'year'  => 'required|integer|min:2024|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $report = $this->service->generateAllSlips(
            $data['year'],
            $data['month'],
            auth()->id()
        );

        $monthName = Carbon::create($data['year'], $data['month'], 1)
            ->locale('id')->translatedFormat('F Y');

        return redirect()->route('staff-payrolls.index', [
            'year' => $data['year'], 'month' => $data['month'],
        ])->with('success', sprintf(
            'Generate slip gaji %s selesai: %d baru, %d di-update, %d skip (sudah PAID).',
            $monthName,
            $report['created'],
            $report['updated'],
            $report['skipped'],
        ));
    }

    public function storeItem(Request $request, StaffPayrollSlip $staffPayroll)
    {
        if ($staffPayroll->isLocked()) {
            return back()->with('error', 'Slip sudah PAID dan tidak bisa diubah.');
        }

        $data = $request->validate([
            'item_type'   => 'required|in:ALLOWANCE,OVERTIME,DEDUCTION',
            'item_code'   => 'required|string|max:30',
            'description' => 'required|string|max:255',
            'amount'      => 'required|integer|min:1|max:999999999',
        ], [
            'item_type.required'   => 'Tipe komponen wajib dipilih.',
            'item_code.required'   => 'Kode komponen wajib dipilih.',
            'description.required' => 'Keterangan wajib diisi.',
            'amount.required'      => 'Nominal wajib diisi.',
            'amount.min'           => 'Nominal harus lebih dari 0.',
        ]);

        try {
            $this->service->addItem($staffPayroll, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Komponen gaji berhasil ditambahkan.');
    }

    public function destroyItem(StaffPayrollSlip $staffPayroll, StaffPayrollItem $item)
    {
        if ($item->staff_payroll_slip_id !== $staffPayroll->id) {
            abort(404);
        }

        try {
            $this->service->deleteItem($item);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Komponen gaji berhasil dihapus.');
    }

    public function markPaid(StaffPayrollSlip $staffPayroll)
    {
        try {
            $this->service->markPaid($staffPayroll, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('staff-payrolls.show', $staffPayroll)
                ->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()->route('staff-payrolls.show', $staffPayroll)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('staff-payrolls.show', $staffPayroll)
            ->with('success', 'Slip gaji ' . $staffPayroll->slip_number . ' ditandai DIBAYAR dan pengeluaran tercatat.');
    }

    public function voidPaid(StaffPayrollSlip $staffPayroll)
    {
        try {
            $this->service->voidPaid($staffPayroll);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('staff-payrolls.show', $staffPayroll)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('staff-payrolls.show', $staffPayroll)
            ->with('success', 'Pembayaran slip ' . $staffPayroll->slip_number . ' di-void. Slip kembali ke status Terhitung.');
    }

    public function print(StaffPayrollSlip $staffPayroll)
    {
        $staffPayroll->load('employee', 'items', 'paidBy');

        $monthName = Carbon::create($staffPayroll->year, $staffPayroll->month, 1)
            ->locale('id')->translatedFormat('F Y');

        return view('staff-payrolls.print', compact('staffPayroll', 'monthName'));
    }
}
