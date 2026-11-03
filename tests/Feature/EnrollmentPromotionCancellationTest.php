<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\EnrollmentPromotion;
use App\Services\EnrollmentPromotionService;
use App\Services\SessionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesPromotionClass;
use Tests\TestCase;

class EnrollmentPromotionCancellationTest extends TestCase
{
    use RefreshDatabase, CreatesPromotionClass;

    public function test_cancellation_restores_original_sessions_and_allows_corrected_submission(): void
    {
        extract($this->promotionClass());
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $oldSessions = $source->classSessions()->orderBy('id')->get()->map->getAttributes()->all();
        $service = app(EnrollmentPromotionService::class);
        $promotion = $service->schedule($source, $data, $actor);
        $service->cancel($promotion, 'Salah memilih level tujuan.', $actor);

        $this->assertSame('CANCELLED', $promotion->fresh()->status);
        $this->assertNull($source->fresh()->end_date);
        $this->assertSame('ACTIVE', $source->fresh()->status);
        $this->assertSame($source->id, $student->fresh()->primary_enrollment_id);
        $this->assertSame($oldSessions, $source->classSessions()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(0, $promotion->successor->classSessions()->count());
        $this->assertFalse($promotion->successor->schedules()->where('is_active', true)->exists());
        $this->assertTrue(AuditLog::where('entity_type', 'EnrollmentPromotion')->where('entity_id', $promotion->id)
            ->where('action', 'UPDATE')->where('new_values->status', 'CANCELLED')->exists());
        $target->update(['grade' => 'L2', 'price_per_month' => 400000]);
        $corrected = $service->schedule($source->fresh(), $data, $actor);
        $this->assertNotSame($promotion->id, $corrected->id);
        $this->assertSame($corrected->id, $source->fresh()->promotion->id);
        $this->assertSame(2, EnrollmentPromotion::count());
    }

    public function test_used_successor_session_blocks_cancellation_without_partial_changes(): void
    {
        extract($this->promotionClass());
        $service = app(EnrollmentPromotionService::class);
        $promotion = $service->schedule($source, $data, $actor);
        $session = $promotion->successor->classSessions()->firstOrFail();
        $session->update(['status' => 'HADIR', 'honor_amount' => 46250]);
        try {
            $service->cancel($promotion, 'Salah memilih level.', $actor);
            $this->fail('Absensi tersimpan harus menghalangi pembatalan.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('promotion', $error->errors());
        }
        $this->assertSame('SCHEDULED', $promotion->fresh()->status);
        $this->assertSame('HADIR', $session->fresh()->status);
        $this->assertNotNull($source->fresh()->end_date);
    }

    public function test_applied_promotion_cannot_be_cancelled(): void
    {
        extract($this->promotionClass());
        $service = app(EnrollmentPromotionService::class);
        $promotion = $service->schedule($source, $data, $actor);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 00:01:00'));
        $service->applyDue();
        $this->expectException(ValidationException::class);
        $service->cancel($promotion, 'Salah input.', $actor);
    }

    public function test_cancellation_without_reason_requires_role_ownership_and_rejects_duplicate(): void
    {
        extract($this->promotionClass());
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $url = "/students/{$student->id}/promotions/{$promotion->id}/cancel";
        \Spatie\Permission\Models\Role::findOrCreate('Auditor', 'web');
        $actor->syncRoles(['Auditor']);
        $this->actingAs($actor)->postJson($url)->assertForbidden();
        $actor->syncRoles(['Owner']);
        $foreign = \App\Models\Student::factory()->create();
        $this->postJson("/students/{$foreign->id}/promotions/{$promotion->id}/cancel")->assertForbidden();
        \Spatie\Permission\Models\Role::findOrCreate('Admin', 'web');
        $actor->syncRoles(['Admin']);
        $this->post($url)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('CANCELLED', $promotion->fresh()->status);
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('promotion');
    }

    public function test_restored_old_slot_conflict_rolls_back_cancellation(): void
    {
        extract($this->promotionClass());
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $service = app(EnrollmentPromotionService::class);
        $promotion = $service->schedule($source, $data, $actor);
        $other = \App\Models\Enrollment::factory()->create(['teacher_id' => $source->teacher_id, 'effective_date' => '2026-11-01', 'status' => 'ACTIVE']);
        \App\Models\Schedule::factory()->for($other)->create(['day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '10:30', 'is_active' => true]);
        try {
            $service->cancel($promotion, 'Salah level', $actor);
            $this->fail('Slot lama tidak boleh mengambil slot kelas lain.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $this->assertSame('SCHEDULED', $promotion->fresh()->status);
        $this->assertNotNull($source->fresh()->end_date);
        $this->assertSame(4, $promotion->successor->classSessions()->count());
        $this->assertSame(0, $source->classSessions()->count());
    }

    public function test_holiday_pair_is_restored_with_original_ids_and_honor(): void
    {
        extract($this->promotionClass());
        \App\Models\Holiday::create(['date' => '2026-11-02', 'name' => 'Libur uji', 'type' => 'Nasional',
            'replacement_date' => '2026-11-30', 'is_honor_paid' => true, 'is_active' => true]);
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $original = $source->classSessions()->where('status', 'LIBUR')->firstOrFail();
        $replacement = $source->classSessions()->where('origin_session_id', $original->id)->firstOrFail();
        $service = app(EnrollmentPromotionService::class);
        $promotion = $service->schedule($source, $data, $actor);
        $service->cancel($promotion, 'Salah level', $actor);
        $this->assertSame($original->getAttributes(), ClassSession::findOrFail($original->id)->getAttributes());
        $this->assertSame($replacement->getAttributes(), ClassSession::findOrFail($replacement->id)->getAttributes());
    }

    public function test_paid_honor_blocks_cancellation(): void
    {
        extract($this->promotionClass());
        $service = app(EnrollmentPromotionService::class);
        $promotion = $service->schedule($source, $data, $actor);
        \App\Models\HonorSlip::factory()->create(['teacher_id' => $teacher->id, 'month' => 11, 'year' => 2026, 'status' => 'PAID']);
        try {
            $service->cancel($promotion, 'Salah level', $actor);
            $this->fail('Honor dibayar harus menghalangi pembatalan.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('promotion', $error->errors());
        }
        $this->assertSame('SCHEDULED', $promotion->fresh()->status);
        $this->assertNotNull($source->fresh()->end_date);
    }
}
