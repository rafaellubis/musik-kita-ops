<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\EnrollmentPromotion;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentPromotionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentPromotionTransitionTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\CreatesPromotionClass;

    public function test_pending_successor_is_selected_by_period_and_primary_changes_only_when_due(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31 12:00:00'));
        $student = Student::factory()->create(['status' => 'Aktif']);
        $source = Enrollment::factory()->for($student)->create([
            'status' => 'ACTIVE', 'is_primary' => true,
            'effective_date' => '2026-01-01', 'end_date' => '2027-01-01',
        ]);
        $student->update(['primary_enrollment_id' => $source->id]);
        $successor = Enrollment::factory()->for($student)->create([
            'status' => 'INACTIVE', 'effective_date' => '2027-01-01', 'is_primary' => false,
        ]);
        $other = Enrollment::factory()->for($student)->create(['status' => 'ACTIVE', 'effective_date' => '2026-01-01']);
        $inactive = Enrollment::factory()->for($student)->create(['status' => 'INACTIVE']);
        EnrollmentPromotion::create([
            'student_id' => $student->id, 'source_enrollment_id' => $source->id,
            'successor_enrollment_id' => $successor->id, 'effective_date' => '2027-01-01',
            'evaluation_note' => 'Evaluasi guru: siap naik level.', 'created_by' => User::factory()->create()->id,
            'status' => 'SCHEDULED', 'snapshot' => [], 'invoice_adjustments' => [],
        ]);
        $service = app(EnrollmentPromotionService::class);
        $this->assertSame(0, $service->applyDue());
        $this->assertSame($source->id, $student->fresh()->primary_enrollment_id);
        $ids = Enrollment::forPeriod(Carbon::parse('2027-01-01'), Carbon::parse('2027-02-01'))->pluck('id')->all();
        $this->assertContains($successor->id, $ids);
        $this->assertNotContains($source->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->travelTo(Carbon::parse('2027-01-01 00:01:00'));
        $this->assertSame(1, $service->applyDue());
        $this->assertSame(0, $service->applyDue());
        $this->assertSame($successor->id, $student->fresh()->primary_enrollment_id);
        $this->assertSame('COMPLETED', $source->fresh()->status);
        $this->assertSame('ACTIVE', $successor->fresh()->status);
        $this->assertSame('ACTIVE', $other->fresh()->status);
        $this->assertSame('Aktif', $student->fresh()->status);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('fallbacks')]
    public function test_missed_scheduler_is_recovered_by_each_entrypoint(string $entrypoint): void
    {
        extract($this->promotionClass());
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $this->travelTo(Carbon::parse('2026-11-01 08:00:00'));
        if ($entrypoint === 'web') {
            $this->actingAs($actor)->get(route('students.show', $student))->assertOk();
        } elseif ($entrypoint === 'sessions') {
            app(\App\Services\SessionGeneratorService::class)->generateForMonth(2026, 11);
        } else {
            app(\App\Services\InvoiceService::class)->generateMonthlySPP(2026, 11);
        }
        $this->assertSame($promotion->successor_enrollment_id, $student->fresh()->primary_enrollment_id);
        $this->assertSame('APPLIED', $promotion->fresh()->status);
        $this->assertSame('COMPLETED', $source->fresh()->status);
    }

    public static function fallbacks(): array
    {
        return [['web'], ['sessions'], ['invoices']];
    }
}
