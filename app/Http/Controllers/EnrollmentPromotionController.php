<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentPromotionRequest;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\EnrollmentPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class EnrollmentPromotionController extends Controller
{
    public function preview(StoreEnrollmentPromotionRequest $request, Student $student, Enrollment $enrollment, EnrollmentPromotionService $service): JsonResponse
    {
        return response()->json($service->preview($enrollment, $request->validated()));
    }

    public function store(StoreEnrollmentPromotionRequest $request, Student $student, Enrollment $enrollment, EnrollmentPromotionService $service): RedirectResponse
    {
        $service->schedule($enrollment, $request->validated(), $request->user());
        return redirect()->to(route('students.show', $student).'#kelas')
            ->with('active_tab', 'kelas')
            ->with('success', 'Kenaikan level dijadwalkan. Sesi mendatang dan tagihan telah disesuaikan.');
    }
}
