<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentTransferRequest;
use App\Models\{Enrollment, Student};
use App\Services\EnrollmentTransferService;
use Illuminate\Http\{JsonResponse, RedirectResponse};

class EnrollmentTransferController extends Controller
{
    public function preview(StoreEnrollmentTransferRequest $request, Student $student, Enrollment $enrollment, EnrollmentTransferService $service): JsonResponse
    {
        return response()->json($service->preview($enrollment, $request->validated()));
    }

    public function store(StoreEnrollmentTransferRequest $request, Student $student, Enrollment $enrollment, EnrollmentTransferService $service): RedirectResponse
    {
        $transfer = $service->schedule($enrollment, $request->validated(), $request->user());
        return redirect()->route('students.show', $student)->with('active_tab', 'kelas')->with('success',
            $transfer->status === 'APPLIED' ? 'Kelas berhasil dialihkan ke guru baru.' : 'Pengalihan guru berhasil dijadwalkan.');
    }
}
