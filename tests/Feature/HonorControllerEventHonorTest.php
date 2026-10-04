<?php

namespace Tests\Feature;

use App\Models\EventHonorEntry;
use App\Models\HonorSlip;
use App\Models\User;
use App\Services\EventLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesEventHonorScenario;
use Tests\TestCase;

class HonorControllerEventHonorTest extends TestCase
{
    use RefreshDatabase, CreatesEventHonorScenario;

    private User $owner;
    private HonorSlip $slip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 20));
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->owner = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->owner);
        [$event] = $this->readyEvent($this->owner);
        app(EventLifecycleService::class)->complete($event, $this->owner);
        $this->slip = HonorSlip::sole();
        $this->slip->update(['status' => 'CALCULATED']);
    }

    public function test_owner_can_edit_manual_components_without_event_fields(): void
    {
        $this->patch(route('honors.update', $this->slip), [
            'transport_honor' => 10000, 'other_honor' => 5000, 'other_honor_note' => 'Bonus',
        ])->assertRedirect(route('honors.show', $this->slip))->assertSessionHas('success');
        $this->assertSame(42500, $this->slip->fresh()->event_honor);
        $this->assertSame('Konser KITA Juni', $this->slip->fresh()->event_honor_note);
        $this->assertSame(57500, $this->slip->fresh()->total_honor);
    }

    #[DataProvider('eventTampering')]
    public function test_manual_event_honor_tampering_is_rejected(array $tampered): void
    {
        $this->patchJson(route('honors.update', $this->slip), $tampered + [
            'transport_honor' => 10000, 'other_honor' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors(array_keys($tampered));
        $this->assertSame(42500, $this->slip->fresh()->event_honor);
        $this->assertSame(0, $this->slip->fresh()->transport_honor);
    }

    public static function eventTampering(): array
    {
        return [[['event_honor' => 250000]], [['event_honor' => 0]], [['event_honor_note' => 'Manual']], [['event_honor_note' => '']]];
    }

    public function test_other_honor_still_requires_a_note(): void
    {
        $this->patch(route('honors.update', $this->slip), [
            'transport_honor' => 0, 'other_honor' => 5000, 'other_honor_note' => '',
        ])->assertSessionHasErrors('other_honor_note');
        $this->assertSame(0, $this->slip->fresh()->other_honor);
    }

    public function test_paid_slip_and_non_owner_remain_locked(): void
    {
        $this->slip->update(['status' => 'PAID']);
        $this->patch(route('honors.update', $this->slip), ['transport_honor' => 10000, 'other_honor' => 0])
            ->assertRedirect(route('honors.show', $this->slip))->assertSessionHas('error');
        foreach (['Admin', 'Auditor', 'Guru'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->patchJson(route('honors.update', $this->slip), ['transport_honor' => 10000, 'other_honor' => 0])->assertForbidden();
        }
        $this->assertSame(0, $this->slip->fresh()->transport_honor);
    }

    public function test_owner_edit_show_print_and_guru_show_display_automatic_participant_breakdown(): void
    {
        $entry = EventHonorEntry::sole();
        foreach (['honors.edit', 'honors.show', 'honors.print'] as $route) {
            $this->get(route($route, $this->slip))->assertOk()->assertSee('Honor Event (Otomatis)')
                ->assertSee('Konser KITA Juni')->assertSee($entry->participant->student->full_name)
                ->assertSee($entry->snapshot_package_code)->assertSee('42.500')
                ->assertDontSee('name="event_honor"', false)->assertDontSee('name="event_honor_note"', false);
        }
        $guru = User::factory()->create()->assignRole('Guru');
        $this->slip->teacher->update(['user_id' => $guru->id]);
        $this->actingAs($guru)->get(route('guru.honor.show', $this->slip))->assertOk()
            ->assertSee('Honor Event (Otomatis)')->assertSee('Konser KITA Juni')
            ->assertSee($entry->participant->student->full_name)->assertSee($entry->snapshot_package_code)->assertSee('42.500');
    }

    public function test_reversed_entries_do_not_appear_as_payable_details(): void
    {
        $event = EventHonorEntry::sole()->event;
        app(EventLifecycleService::class)->reopen($event, $this->owner, 'Koreksi');
        $this->get(route('honors.show', $this->slip))->assertOk()->assertDontSee('Konser KITA Juni');
        $this->get(route('honors.print', $this->slip))->assertOk()->assertDontSee('Konser KITA Juni');
    }
}
