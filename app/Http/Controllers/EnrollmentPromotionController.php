<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentPromotionRequest;
use App\Http\Requests\CancelEnrollmentPromotionRequest;
use App\Models\EnrollmentPromotion;
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

    public function cancel(CancelEnrollmentPromotionRequest $request, Student $student, EnrollmentPromotion $promotion, EnrollmentPromotionService $service): RedirectResponse
    {
        $service->cancel($promotion, 'Pembatalan kenaikan level.', $request->user());
        return redirect()->to(route('students.show', $student).'#kelas')
            ->with('active_tab', 'kelas')
            ->with('success', 'Kenaikan dibatalkan. Kelas dan tagihan dipulihkan; Anda dapat mengajukan kenaikan yang benar.');
    }
}
