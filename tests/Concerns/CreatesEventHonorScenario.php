<?php

namespace Tests\Concerns;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\InvoiceComponent;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EventAttendanceService;
use App\Services\EventLifecycleService;
use App\Services\EventParticipantService;

trait CreatesEventHonorScenario
{
    /** Jalankan persiapan nyata agar tautan invoice dan snapshot sama seperti operasional. */
    private function readyEvent(User $actor, int $count = 1, ?Teacher $teacher = null, string $name = 'Konser KITA Juni'): array
    {
        InvoiceComponent::firstOrCreate(['code' => 'MC'], [
            'name' => 'Mini Concert', 'default_price' => 295000, 'is_active' => true,
        ]);
        $event = Event::factory()->create([
            'name' => $name, 'event_date' => '2026-06-15', 'payment_due_date' => '2026-06-10',
        ]);
        $teacher ??= Teacher::factory()->create();
        $participants = collect();
        for ($i = 0; $i < $count; $i++) {
            $enrollment = Enrollment::factory()->create(['teacher_id' => $teacher->id]);
            $participants->push(app(EventParticipantService::class)
                ->addEnrollments($event, $enrollment->student, [$enrollment->id], $actor)->first());
        }
        app(EventLifecycleService::class)->publish($event, $actor);
        app(EventAttendanceService::class)->confirmTeacher($participants->first()->activeTeacherAssignment, 'HADIR', $actor, null);
        foreach ($participants as $participant) {
            app(EventAttendanceService::class)->recordParticipant($participant, 'HADIR', null, $actor);
        }

        return [$event->fresh(), $participants->map->fresh()];
    }
}
