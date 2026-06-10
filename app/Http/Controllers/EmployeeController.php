<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\StaffPayrollSlip;
use App\Models\User;
use App\Services\StaffPayrollService;
use Illuminate\Http\Request;

/**
 * Master data karyawan non-guru (M12).
 * Write: Owner only. Read: Owner|Admin|Auditor.
 */
class EmployeeController extends Controller
{
    public function __construct(
        private readonly StaffPayrollService $payrollService
    ) {}

    public function index()
    {
        $employees = Employee::with('user')
            ->withCount('payrollSlips')
            ->orderBy('full_name')
            ->get();

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        $users = User::role(['Owner', 'Admin'])
            ->whereDoesntHave('employee')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('employees.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name'           => 'required|string|max:100',
            'position'            => 'required|string|max:100',
            'user_id'             => 'nullable|exists:users,id|unique:employees,user_id',
            'base_salary'         => 'required|integer|min:0|max:999999999',
            'bank_name'           => 'nullable|string|max:50',
            'bank_account'        => 'nullable|string|max:30',
            'bank_account_holder' => 'nullable|string|max:100',
            'joined_date'         => 'nullable|date',
            'notes'               => 'nullable|string',
        ], [
            'full_name.required'   => 'Nama lengkap wajib diisi.',
            'position.required'    => 'Posisi/jabatan wajib diisi.',
            'base_salary.required' => 'Gaji pokok wajib diisi.',
            'base_salary.min'      => 'Gaji pokok tidak boleh negatif.',
            'user_id.unique'       => 'User ini sudah terhubung ke karyawan lain.',
        ]);

        $validated['employee_code'] = $this->payrollService->generateEmployeeCode();
        $validated['is_active']   = $request->has('is_active');

        Employee::create($validated);

        return redirect()->route('employees.index')
            ->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(Employee $employee)
    {
        $users = User::role(['Owner', 'Admin'])
            ->where(function ($q) use ($employee) {
                $q->whereDoesntHave('employee')
                  ->orWhere('id', $employee->user_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('employees.edit', compact('employee', 'users'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'full_name'           => 'required|string|max:100',
            'position'            => 'required|string|max:100',
            'user_id'             => 'nullable|exists:users,id|unique:employees,user_id,' . $employee->id,
            'base_salary'         => 'required|integer|min:0|max:999999999',
            'bank_name'           => 'nullable|string|max:50',
            'bank_account'        => 'nullable|string|max:30',
            'bank_account_holder' => 'nullable|string|max:100',
            'joined_date'         => 'nullable|date',
            'notes'               => 'nullable|string',
        ], [
            'full_name.required'   => 'Nama lengkap wajib diisi.',
            'position.required'    => 'Posisi/jabatan wajib diisi.',
            'base_salary.required' => 'Gaji pokok wajib diisi.',
            'user_id.unique'       => 'User ini sudah terhubung ke karyawan lain.',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if ($employee->payrollSlips()->where('status', StaffPayrollSlip::STATUS_PAID)->exists()
            && !$validated['is_active']
            && $employee->is_active) {
            return back()
                ->with('error', 'Karyawan dengan historis slip PAID tidak bisa dinonaktifkan lewat hapus — nonaktifkan saja.')
                ->withInput();
        }

        $employee->update($validated);

        return redirect()->route('employees.index')
            ->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee)
    {
        if ($employee->payrollSlips()->exists()) {
            return back()->with('error', 'Karyawan dengan historis slip gaji tidak bisa dihapus. Nonaktifkan saja.');
        }

        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Karyawan berhasil dihapus.');
    }
}
