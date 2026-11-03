<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\EnrollmentPromotion;
use App\Services\EnrollmentPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPromotionClass;
use Tests\TestCase;

class EnrollmentPromotionRetryHistoryTest extends TestCase
{
    use RefreshDatabase, CreatesPromotionClass;

    public function test_cancelled_promotion_allows_retry_and_relation_ignores_newer_cancelled_history(): void
    {
        extract($this->promotionClass());
        $service = app(EnrollmentPromotionService::class);
        $cancelled = $service->schedule($source, $data, $actor);
        $service->cancel($cancelled, 'Koreksi level tujuan.', $actor);
        $this->assertNull($source->fresh()->promotion);
        $this->assertFalse($source->promotion()->exists());

        $retry = $service->schedule($source->fresh(), $data, $actor);
        $this->assertSame($retry->id, $source->fresh()->promotion->id);
        $this->assertSame($retry->id, Enrollment::with('promotion')->findOrFail($source->id)->promotion->id);

        $laterCancelled = $cancelled->fresh()->replicate();
        $laterCancelled->successor_enrollment_id = Enrollment::factory()->for($student)->create(['status' => 'INACTIVE'])->id;
        $laterCancelled->save();
        $this->assertSame($retry->id, $source->fresh()->promotion->id);
        $this->assertSame($retry->id, Enrollment::with('promotion')->findOrFail($source->id)->promotion->id);
        $this->assertSame(3, EnrollmentPromotion::where('source_enrollment_id', $source->id)->count());
    }

    public function test_schema_rollback_refuses_to_discard_duplicate_source_history(): void
    {
        extract($this->promotionClass());
        $service = app(EnrollmentPromotionService::class);
        $cancelled = $service->schedule($source, $data, $actor);
        $service->cancel($cancelled, 'Koreksi level tujuan.', $actor);
        $service->schedule($source->fresh(), $data, $actor);
        $migration = require database_path('migrations/2026_11_03_000001_allow_cancelled_enrollment_promotions.php');

        try {
            $migration->down();
            $this->fail('Riwayat berulang harus menghalangi rollback migrasi.');
        } catch (\RuntimeException $error) {
            $this->assertSame(\RuntimeException::class, $error::class);
            $this->assertSame(2, EnrollmentPromotion::where('source_enrollment_id', $source->id)->count());
        }
    }

    public function test_cancelled_successor_is_not_a_completed_class_but_promotion_history_is_retained(): void
    {
        extract($this->promotionClass());
        $service = app(EnrollmentPromotionService::class);
        $cancelled = $service->schedule($source, $data, $actor);
        $service->cancel($cancelled, 'Koreksi level tujuan.', $actor);
        $completed = Enrollment::factory()->for($student)->create(['status' => 'COMPLETED', 'end_date' => '2026-09-01']);

        $this->actingAs($actor)->get(route('students.show', $student))->assertOk()
            ->assertViewHas('historyEnrollments', fn ($classes) =>
                $classes->contains('id', $completed->id)
                && ! $classes->contains('id', $cancelled->successor_enrollment_id))
            ->assertViewHas('promotions', fn ($promotions) =>
                $promotions->contains(fn ($promotion) => $promotion->id === $cancelled->id && $promotion->status === 'CANCELLED'));
    }
}
