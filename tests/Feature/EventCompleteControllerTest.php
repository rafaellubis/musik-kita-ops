<?php

namespace Tests\Feature;

use App\Models\HonorSlip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesEventHonorScenario;
use Tests\TestCase;

class EventCompleteControllerTest extends TestCase
{
    use RefreshDatabase, CreatesEventHonorScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 20));
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_owner_and_admin_complete_published_events_with_flash_and_safe_retry(): void
    {
        foreach (['Owner', 'Admin'] as $role) {
            $actor = User::factory()->create()->assignRole($role);
            [$event] = $this->readyEvent($actor);
            $this->actingAs($actor)->post(route('events.complete', $event))
                ->assertRedirect(route('events.show', $event))->assertSessionHas('success');
            $this->post(route('events.complete', $event))->assertRedirect()->assertSessionHas('success');
            $this->assertSame('COMPLETED', $event->fresh()->status);
            $this->assertSame(42500, $event->honorEntries()->sole()->honorSlip->event_honor);
        }
        $this->assertDatabaseCount('event_honor_entries', 2);
    }

    public function test_auditor_and_guru_cannot_complete_events(): void
    {
        [$event] = $this->readyEvent(User::factory()->create()->assignRole('Owner'));
        foreach (['Auditor', 'Guru'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->postJson(route('events.complete', $event))->assertForbidden();
        }
        $this->assertSame('PUBLISHED', $event->fresh()->status);
        $this->assertDatabaseCount('event_honor_entries', 0);
    }

    public function test_event_summary_wires_complete_and_owner_reopen_controls(): void
    {
        $actor = User::factory()->create()->assignRole('Owner');
        [$event] = $this->readyEvent($actor);
        $this->actingAs($actor)->get(route('events.show', $event))->assertOk()
            ->assertSee('action="'.route('events.complete', $event).'"', false);
        $this->post(route('events.complete', $event))->assertRedirect();
        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('action="'.route('events.reopen', $event).'"', false)->assertSee('name="reason"', false);
        $this->actingAs(User::factory()->create()->assignRole('Admin'))->get(route('events.show', $event))->assertOk()
            ->assertDontSee('action="'.route('events.reopen', $event).'"', false);
    }
}
