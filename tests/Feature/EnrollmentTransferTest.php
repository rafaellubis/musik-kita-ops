<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Enrollment, ProgressReport};
use App\Services\{EnrollmentTransferService, SessionGeneratorService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTransferTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\CreatesPromotionClass;

    private function transferClass(): array
    {
        $context = $this->promotionClass();
        $context['data']['reason'] = 'Pengalihan tanggung jawab mengajar.';
        unset($context['data']['package_id'], $context['data']['evaluation_note']);
        return $context;
    }

    public function test_future_transfer_preserves_class_and_switches_only_when_due(): void
    {
        extract($this->transferClass());
        $oldTeacher = $source->teacher_id;
        $service = app(EnrollmentTransferService::class);
        $transfer = $service->schedule($source, $data, $actor);
        $this->assertSame($oldTeacher, $source->fresh()->teacher_id);
        $this->assertSame($oldTeacher, $source->fresh()->teachingAssignmentOn(Carbon::parse('2026-10-31'))['teacher_id']);
        $this->assertSame($teacher->id, $source->fresh()->teachingAssignmentOn(Carbon::parse('2026-11-01'))['teacher_id']);
        $this->assertSame(0, $service->applyDue());
        $this->travelTo(Carbon::parse('2026-11-01 00:01'));
        $this->assertSame(1, $service->applyDue());
        $this->assertSame(0, $service->applyDue());
        $this->assertSame($teacher->id, $source->fresh()->teacher_id);
        $this->assertSame($package->id, $source->fresh()->package_id);
        $this->assertSame($source->id, $student->fresh()->primary_enrollment_id);
        $this->assertSame('APPLIED', $transfer->fresh()->status);
        $this->assertSame(1, AuditLog::where('entity_type', 'EnrollmentTransfer')->where('action', 'UPDATE')->count());
    }

    public function test_generator_uses_future_assignment_without_duplicate_sessions(): void
    {
        extract($this->transferClass());
        app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $generator = app(SessionGeneratorService::class);
        $generator->generateForMonth(2026, 11);
        $first = $source->classSessions()->orderBy('session_date')->get();
        $this->assertSame(['2026-11-03', '2026-11-10', '2026-11-17', '2026-11-24'], $first->pluck('session_date')->all());
        $this->assertSame([$teacher->id], $first->pluck('teacher_id')->unique()->values()->all());
        $generator->generateForMonth(2026, 11);
        $this->assertSame($first->pluck('id')->all(), $source->classSessions()->orderBy('session_date')->pluck('id')->all());
    }

    public function test_report_with_assessment_moves_to_new_teacher_without_losing_content(): void
    {
        extract($this->transferClass());
        $template = \App\Models\ReportTemplate::create(['instrument_id' => $package->instrument_id, 'name' => 'Template', 'is_active' => true]);
        $report = ProgressReport::create(['enrollment_id' => $source->id, 'student_id' => $student->id,
            'teacher_id' => $source->teacher_id, 'report_template_id' => $template->id, 'year' => 2026, 'month' => 11, 'status' => 'SUBMITTED',
            'submitted_at' => now(), 'rating_teknik' => 4, 'summary_notes' => 'Penilaian guru asal.']);
        $before = $report->getAttributes();
        app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $this->travelTo(Carbon::parse('2026-11-01 00:01'));
        app(EnrollmentTransferService::class)->applyDue();
        $after = $report->fresh();
        $this->assertSame($teacher->id, $after->teacher_id);
        foreach (['rating_teknik', 'summary_notes', 'status', 'submitted_at'] as $field) {
            $this->assertEquals($before[$field], $after->getRawOriginal($field));
        }
    }

    public function test_midmonth_transfer_preserves_completed_sessions_and_remaining_rights(): void
    {
        extract($this->transferClass());
        $generator = app(SessionGeneratorService::class);
        $generator->generateForMonth(2026, 10);
        $past = $source->classSessions()->where('session_date', '<', '2026-10-20')->get();
        foreach ($past as $session) { $session->update(['status' => 'HADIR', 'honor_code' => 'H_REG', 'honor_amount' => 42500]); }
        $before = $past->map->getAttributes()->all();
        $data['effective_date'] = '2026-10-21';
        app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $generator->generateForMonth(2026, 10);
        $this->assertSame($before, $source->classSessions()->whereIn('id', $past->pluck('id'))->get()->map->getAttributes()->all());
        $future = $source->classSessions()->whereDate('session_date', '>=', '2026-10-21')->get();
        $this->assertSame(['2026-10-27'], $future->pluck('session_date')->all());
        $this->assertSame([$teacher->id], $future->pluck('teacher_id')->all());
        $this->assertSame([4], $future->pluck('session_sequence')->all());
    }

    public function test_scheduled_transfer_reserves_target_and_prevents_schedule_mutation(): void
    {
        extract($this->transferClass());
        app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $conflicts = app(\App\Services\ScheduleConflictDetector::class)->findTeacherConflicts($teacher->id, 2, '11:00:00', '11:30:00');
        $this->assertContains($source->id, $conflicts->pluck('enrollment_id')->all());
        $schedule = $source->schedules()->first();
        $this->actingAs($actor)->patch(route('schedules.update', [$student, $schedule]), [
            'day_of_week' => 3, 'start_time' => '12:00', 'end_time' => '12:30',
        ])->assertSessionHasErrors('enrollment_id');
        $this->assertSame(1, $schedule->fresh()->day_of_week);
    }

    public function test_stale_preview_does_not_replace_newly_added_session_note(): void
    {
        extract($this->transferClass());
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $service = app(EnrollmentTransferService::class);
        $preview = $service->preview($source, $data);
        $session = $source->classSessions()->first();
        \App\Models\SessionTeacherNote::create(['class_session_id' => $session->id, 'teacher_id' => $source->teacher_id, 'notes' => 'Catatan baru']);
        try {
            $service->schedule($source, $data + ['fingerprint' => $preview['fingerprint']], $actor);
            $this->fail('Pratinjau basi harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('fingerprint', $error->errors());
        }
        $this->assertDatabaseHas('session_teacher_notes', ['class_session_id' => $session->id, 'notes' => 'Catatan baru']);
        $this->assertSame($source->teacher_id, $source->fresh()->teacher_id);
    }

    public function test_holiday_honor_moves_between_unpaid_slips_and_preserves_manual_components(): void
    {
        extract($this->transferClass());
        \App\Models\Holiday::create(['date' => '2026-11-02', 'name' => 'Libur nasional', 'type' => 'Nasional', 'is_active' => true, 'is_honor_paid' => true]);
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $honor = app(\App\Services\HonorCalculationService::class);
        $honor->calculateForTeacher($source->teacher, 2026, 11, $actor->id);
        $honor->calculateForTeacher($teacher, 2026, 11, $actor->id);
        $oldSlip = \App\Models\HonorSlip::where('teacher_id', $source->teacher_id)->firstOrFail();
        $oldSlip->update(['transport_honor' => 10000, 'other_honor' => 5000, 'other_honor_note' => 'Bonus']);
        $this->assertSame(42500, $oldSlip->base_honor);
        app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $this->assertSame(0, $oldSlip->fresh()->base_honor);
        $this->assertSame(10000, $oldSlip->fresh()->transport_honor);
        $this->assertSame(5000, $oldSlip->fresh()->other_honor);
        $this->assertSame('Bonus', $oldSlip->fresh()->other_honor_note);
    }

    public function test_paid_holiday_honor_blocks_transfer_without_changing_sessions(): void
    {
        extract($this->transferClass());
        \App\Models\Holiday::create(['date' => '2026-11-02', 'name' => 'Libur nasional', 'type' => 'Nasional', 'is_active' => true, 'is_honor_paid' => true]);
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        app(\App\Services\HonorCalculationService::class)->calculateForTeacher($source->teacher, 2026, 11, $actor->id);
        \App\Models\HonorSlip::where('teacher_id', $source->teacher_id)->update(['status' => 'PAID']);
        $before = $source->classSessions()->get()->map->getAttributes()->all();
        try {
            app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
            $this->fail('Honor dibayar harus mengunci perubahan.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('sessions', $error->errors());
        }
        $this->assertSame($before, $source->classSessions()->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('enrollment_transfers', 0);
    }

    public function test_leave_and_holiday_replacement_follow_new_assignment(): void
    {
        extract($this->transferClass());
        \App\Models\Holiday::create(['date' => '2026-11-03', 'name' => 'Libur', 'type' => 'Nasional',
            'replacement_date' => '2026-11-29', 'is_active' => true, 'is_honor_paid' => true]);
        $source->leaves()->create(['cuti_from' => '2026-11-10', 'cuti_until' => '2026-11-10', 'reason' => 'Cuti']);
        app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $this->assertDatabaseMissing('class_sessions', ['enrollment_id' => $source->id, 'session_date' => '2026-11-10']);
        $holiday = $source->classSessions()->where('session_date', '2026-11-03')->firstOrFail();
        $replacement = $source->classSessions()->where('session_date', '2026-11-29')->firstOrFail();
        $this->assertSame('LIBUR', $holiday->status);
        $this->assertSame(0, $holiday->honor_amount);
        $this->assertSame($teacher->id, $replacement->teacher_id);
        $this->assertSame($holiday->id, $replacement->origin_session_id);
        $this->assertSame(1, $replacement->session_sequence);
    }

    public function test_due_command_transfers_report_and_old_teacher_cannot_create_new_period(): void
    {
        extract($this->transferClass());
        \Spatie\Permission\Models\Role::findOrCreate('Guru', 'web');
        $oldUser = \App\Models\User::factory()->create()->assignRole('Guru');
        $newUser = \App\Models\User::factory()->create()->assignRole('Guru');
        $source->teacher->update(['user_id' => $oldUser->id]);
        $teacher->update(['user_id' => $newUser->id]);
        app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $this->travelTo(Carbon::parse('2026-11-01 00:01'));
        $this->artisan('enrollments:apply-transfers')->assertSuccessful();
        $this->artisan('enrollments:apply-transfers')->assertSuccessful();
        $this->assertSame($teacher->id, $source->fresh()->teacher_id);
        $this->actingAs($oldUser)->post(route('guru.laporan.store'), [
            'enrollment_id' => $source->id, 'month' => 11, 'year' => 2026,
        ])->assertForbidden();
        $this->actingAs($newUser)->post(route('guru.laporan.store'), [
            'enrollment_id' => $source->id, 'month' => 10, 'year' => 2026,
        ])->assertForbidden();
    }

    public function test_resource_disabled_before_due_date_keeps_transfer_pending(): void
    {
        extract($this->transferClass());
        $transfer = app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $teacher->update(['is_active' => false]);
        $this->travelTo(Carbon::parse('2026-11-01 00:01'));
        $this->assertSame(0, app(EnrollmentTransferService::class)->applyDue());
        $this->assertSame('SCHEDULED', $transfer->fresh()->status);
        $this->assertNotEmpty($transfer->fresh()->blocked_reason);
        $this->assertNotSame($teacher->id, $source->fresh()->teacher_id);
    }

    public function test_transfer_endpoints_enforce_roles_ownership_and_preview_confirmation(): void
    {
        extract($this->transferClass());
        $url = '/students/'.$student->id.'/enrollments/'.$source->id;
        $response = $this->actingAs($actor)->postJson($url.'/transfer-preview', $data)->assertOk();
        $this->actingAs($actor)->post($url.'/transfers', $data + ['fingerprint' => $response->json('fingerprint')])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('enrollment_transfers', ['enrollment_id' => $source->id, 'target_teacher_id' => $teacher->id]);
        foreach (['Auditor', 'Guru'] as $role) {
            \Spatie\Permission\Models\Role::findOrCreate($role, 'web');
            $user = \App\Models\User::factory()->create()->assignRole($role);
            $this->actingAs($user)->postJson($url.'/transfer-preview', $data)->assertForbidden();
        }
        $otherStudent = \App\Models\Student::factory()->create();
        $this->actingAs($actor)->postJson('/students/'.$otherStudent->id.'/enrollments/'.$source->id.'/transfer-preview', $data)->assertForbidden();
    }

    public function test_target_paid_slip_blocks_new_holiday_honor(): void
    {
        extract($this->transferClass());
        \App\Models\Holiday::create(['date' => '2026-11-03', 'name' => 'Libur', 'type' => 'Nasional', 'is_active' => true, 'is_honor_paid' => true]);
        app(\App\Services\HonorCalculationService::class)->calculateForTeacher($teacher, 2026, 11, $actor->id);
        \App\Models\HonorSlip::where('teacher_id', $teacher->id)->update(['status' => 'PAID']);
        try {
            app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
            $this->fail('Honor baru tidak boleh masuk slip yang sudah dibayar.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('sessions', $error->errors());
        }
        $this->assertDatabaseCount('enrollment_transfers', 0);
    }

    public function test_protected_holiday_replacement_keeps_its_origin(): void
    {
        extract($this->transferClass());
        \App\Models\Holiday::create(['date' => '2026-11-02', 'name' => 'Libur', 'type' => 'Nasional',
            'replacement_date' => '2026-11-29', 'is_active' => true, 'is_honor_paid' => true]);
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $origin = $source->classSessions()->where('status', 'LIBUR')->firstOrFail();
        $child = $source->classSessions()->where('origin_session_id', $origin->id)->firstOrFail();
        \App\Models\SessionTeacherNote::create(['class_session_id' => $child->id, 'teacher_id' => $source->teacher_id, 'notes' => 'Catatan pengganti']);
        try {
            app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('sessions', $error->errors());
        }
        $this->assertSame($origin->id, $child->fresh()->origin_session_id);
        $this->assertDatabaseHas('class_sessions', ['id' => $origin->id, 'status' => 'LIBUR']);
    }

    public function test_other_class_cannot_take_student_reserved_transfer_slot(): void
    {
        extract($this->transferClass());
        app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $other = Enrollment::factory()->for($student)->create(['status' => 'ACTIVE', 'effective_date' => '2026-01-01']);
        $schedule = \App\Models\Schedule::factory()->for($other)->create(['is_active' => true, 'day_of_week' => 4, 'start_time' => '15:00', 'end_time' => '15:30', 'room_id' => null]);
        $this->actingAs($actor)->patch(route('schedules.update', [$student, $schedule]), [
            'day_of_week' => 2, 'start_time' => '11:00', 'end_time' => '11:30',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(4, $schedule->fresh()->day_of_week);
    }

    public function test_duo_transfer_can_share_one_generated_partner_slot(): void
    {
        extract($this->transferClass());
        $package->update(['class_type' => 'DUO']);
        $room->update(['capacity' => 2]);
        $partner = Enrollment::factory()->create(['package_id' => $package->id, 'teacher_id' => $teacher->id, 'status' => 'ACTIVE', 'effective_date' => '2026-01-01']);
        $partner->student->update(['status' => 'Aktif']);
        \App\Models\Schedule::factory()->for($partner)->create(['day_of_week' => 2, 'start_time' => '11:00:00', 'end_time' => '11:30:00', 'room_id' => $room->id, 'is_active' => true]);
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $transfer = app(EnrollmentTransferService::class)->schedule($source, $data, $actor);
        $this->assertSame('SCHEDULED', $transfer->status);
        $this->assertDatabaseHas('class_sessions', ['enrollment_id' => $source->id, 'teacher_id' => $teacher->id, 'session_date' => '2026-11-03']);
    }
}
