<?php

namespace App\Http\Middleware;

use App\Services\EnrollmentPromotionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncEnrollmentPromotions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            app(EnrollmentPromotionService::class)->applyDue();
        }
        return $next($request);
    }
}
