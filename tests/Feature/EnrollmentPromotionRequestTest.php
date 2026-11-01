<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\EnrollmentPromotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesPromotionClass;
use Tests\TestCase;

class EnrollmentPromotionRequestTest extends TestCase
{
    use RefreshDatabase, CreatesPromotionClass;

    public function test_owner_can_preview_and_submit_but_auditor_and_foreign_enrollment_cannot(): void
    {
        extract($this->promotionClass());
        $base = "/students/{$student->id}/enrollments/{$source->id}";
        $this->actingAs($actor)->postJson($base.'/promotion-preview', $data)->assertOk()->assertJsonPath('new_price', 370000);
        $this->assertSame(0, EnrollmentPromotion::count());
        Role::findOrCreate('Auditor', 'web');
        $auditor = User::factory()->create()->assignRole('Auditor');
        $this->actingAs($auditor)->postJson($base.'/promotions', $data)->assertForbidden();
        $foreign = Enrollment::factory()->create();
        $this->actingAs($actor)->postJson("/students/{$student->id}/enrollments/{$foreign->id}/promotions", $data)->assertForbidden();
        $this->actingAs($actor)->post($base.'/promotions', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, EnrollmentPromotion::count());
    }

    public function test_preview_date_cannot_silently_move_after_month_boundary(): void
    {
        extract($this->promotionClass());
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 00:01:00'));
        $this->actingAs($actor)->postJson("/students/{$student->id}/enrollments/{$source->id}/promotions", $data)
            ->assertUnprocessable()->assertJsonValidationErrors('effective_date');
        $this->assertSame(0, EnrollmentPromotion::count());
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('additionalRoles')]
    public function test_write_access_is_enforced_for_additional_roles(?string $role, int $status): void
    {
        extract($this->promotionClass());
        if ($role) {
            Role::findOrCreate($role, 'web');
            $actor->syncRoles([$role]);
            $this->actingAs($actor);
        }
        $this->postJson("/students/{$student->id}/enrollments/{$source->id}/promotions", $data)->assertStatus($status);
        $this->assertSame($role === 'Admin' ? 1 : 0, EnrollmentPromotion::count());
    }

    public static function additionalRoles(): array
    {
        return [['Admin', 302], ['Guru', 403], [null, 401]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTargets')]
    public function test_ineligible_master_data_cannot_be_used_for_promotion(string $case): void
    {
        extract($this->promotionClass());
        match ($case) {
            'grade' => $target->update(['grade' => 'L2']),
            'instrument' => $target->update(['instrument_id' => \App\Models\Instrument::factory()->create()->id]),
            'package' => $target->update(['is_active' => false]),
            'teacher' => $teacher->update(['is_active' => false]),
            'teacher_instrument' => $teacher->instruments()->detach(),
            'room' => $room->update(['supported_instruments' => []]),
        };
        $this->actingAs($actor)->postJson("/students/{$student->id}/enrollments/{$source->id}/promotions", $data)->assertUnprocessable();
        $this->assertSame(0, EnrollmentPromotion::count());
        $this->assertNull($source->fresh()->end_date);
    }

    public static function invalidTargets(): array
    {
        return [['grade'], ['instrument'], ['package'], ['teacher'], ['teacher_instrument'], ['room']];
    }
}
