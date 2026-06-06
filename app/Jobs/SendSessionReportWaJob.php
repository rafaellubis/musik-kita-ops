<?php

namespace App\Jobs;

use App\Models\ClassSession;
use App\Services\SessionReportWaService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSessionReportWaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $classSessionId,
        public string $noteUpdatedAtIso,
    ) {}

    public function handle(SessionReportWaService $service): void
    {
        $session = ClassSession::query()
            ->with([
                'student',
                'teacher',
                'substituteTeacher',
                'enrollment.package.instrument',
                'teacherNote',
            ])
            ->find($this->classSessionId);

        if (! $session || ! $session->teacherNote) {
            return;
        }

        $snapshot = Carbon::parse($this->noteUpdatedAtIso);
        $service->sendForSession($session, $snapshot);
    }
}
