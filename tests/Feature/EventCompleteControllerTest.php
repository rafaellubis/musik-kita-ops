<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\HonorSlip;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventCompleteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles yang dibutuhkan Spatie Permission
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        return User::factory()->create()->assignRole('Owner');
    }

    public function test_complete_event_injects_honor_and_shows_flash(): void
    {
        $owner   = $this->ownerUser();
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create();

        $event = Event::factory()->create([
            'status'     => Event::STATUS_DRAFT,
            'event_date' => '2026-06-15',
            'name'       => 'Konser KITA Juni 2026',
        ]);

        EventParticipant::factory()->create([
            'event_id'                => $event->id,
            'student_id'              => $student->id,
            'accompanying_teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($owner)
            ->post(route('events.complete', $event));

        $response->assertRedirect(route('events.show', $event));

        $this->assertEquals(Event::STATUS_COMPLETED, $event->fresh()->status);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)->first();
        $this->assertNotNull($slip);
        $this->assertEquals(250000, $slip->event_honor);
    }

    public function test_complete_already_completed_event_returns_error(): void
    {
        $owner = $this->ownerUser();
        $event = Event::factory()->create(['status' => Event::STATUS_COMPLETED]);

        $response = $this->actingAs($owner)
            ->post(route('events.complete', $event));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
