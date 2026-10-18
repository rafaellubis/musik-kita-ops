<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 12, 20)->startOfDay());
        foreach (['Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->actingAs(User::factory()->create()->assignRole('Admin'));
    }

    public function test_draft_forms_disable_grade_exam_and_include_payment_deadline(): void
    {
        $event = Event::factory()->create();
        foreach ([route('events.create'), route('events.edit', $event)] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('value="UJIAN_GRADE" disabled', false)
                ->assertSee('name="payment_due_date"', false)
                ->assertSee('Ujian Kenaikan Grade — segera hadir')
                ->assertDontSee('MINI_CONCERT_UJIAN');
        }
    }

    public function test_index_distinguishes_all_statuses_and_admin_can_create(): void
    {
        foreach (['DRAFT', 'PUBLISHED', 'COMPLETED', 'CANCELLED'] as $status) {
            Event::factory()->create(['status' => $status]);
        }
        $this->get(route('events.index'))->assertOk()->assertSee('Buat Event')
            ->assertSee('Dipublikasikan')->assertSee('Dibatalkan')->assertSee('Selesai')->assertSee('Draft');
    }

    public function test_index_filters_events_and_renders_operational_summaries(): void
    {
        $event = Event::factory()->published()->create([
            'name' => 'Konser Operasional 2026',
            'type' => Event::TYPE_MINI_CONCERT,
            'event_date' => '2026-12-20',
        ]);
        $ready = EventTeacherAssignment::factory()->hadir()->create(['event_id' => $event->id]);
        $pending = EventTeacherAssignment::factory()->create(['event_id' => $event->id]);
        $first = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'active_teacher_assignment_id' => $ready->id,
            'attendance_status' => EventParticipant::ATTENDANCE_HADIR,
        ]);
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $first->student_id,
            'enrollment_id' => Enrollment::factory()->create(['student_id' => $first->student_id])->id,
            'active_teacher_assignment_id' => $ready->id,
            'attendance_status' => EventParticipant::ATTENDANCE_PENDING,
        ]);
        $third = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'active_teacher_assignment_id' => $pending->id,
            'attendance_status' => EventParticipant::ATTENDANCE_PENDING,
        ]);
        EventParticipant::factory()->cancelled()->create(['event_id' => $event->id]);

        Invoice::factory()->create([
            'event_id' => $event->id,
            'student_id' => $first->student_id,
            'status' => Invoice::STATUS_PAID,
        ]);
        Invoice::factory()->create([
            'event_id' => $event->id,
            'student_id' => $third->student_id,
            'status' => Invoice::STATUS_PARTIAL,
        ]);
        Invoice::factory()->create([
            'event_id' => $event->id,
            'status' => Invoice::STATUS_VOID,
        ]);

        Event::factory()->published()->create(['name' => 'Konser Tahun Lain', 'event_date' => '2025-12-20']);
        Event::factory()->create(['name' => 'Konser Draft Tersaring', 'event_date' => '2026-12-20']);
        Event::factory()->published()->create([
            'name' => 'Ujian Tersaring',
            'type' => Event::TYPE_UJIAN_GRADE,
            'event_date' => '2026-12-20',
        ]);

        $this->get(route('events.index', [
            'year' => 2026,
            'status' => Event::STATUS_PUBLISHED,
            'type' => Event::TYPE_MINI_CONCERT,
        ]))->assertOk()
            ->assertSee('name="year"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="type"', false)
            ->assertSee('Konser Operasional 2026')
            ->assertSee('3 enrollment · 2 murid')
            ->assertSee('1/2 guru siap')
            ->assertSee('1/3 absensi')
            ->assertSee('1/2 invoice lunas')
            ->assertDontSee('Konser Tahun Lain')
            ->assertDontSee('Konser Draft Tersaring')
            ->assertDontSee('Ujian Tersaring');
    }

    public function test_detail_groups_enrollments_by_student_and_has_four_accessible_tabs(): void
    {
        $event = Event::factory()->create();
        $first = EventParticipant::factory()->create(['event_id' => $event->id]);
        EventParticipant::factory()->create(['event_id' => $event->id, 'student_id' => $first->student_id,
            'enrollment_id' => Enrollment::factory()->create(['student_id' => $first->student_id])->id,
            'snapshot_package_code' => 'HOBBY-GITAR-45', 'snapshot_instrument_name' => 'Gitar']);
        $response = $this->get(route('events.show', $event))->assertOk();
        $this->assertSame(4, substr_count($response->getContent(), 'role="tab"'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-student-group="'.$first->student_id.'"'));
        $response->assertSeeInOrder(['Ringkasan', 'Peserta', 'Kehadiran Guru', 'Keuangan'])
            ->assertSee('role="tablist"', false)->assertSee('aria-selected="true"', false)
            ->assertSee('aria-controls="event-panel-summary"', false)
            ->assertSee(':tabindex="tab === \'summary\' ? 0 : -1"', false)
            ->assertSee('@keydown.home.prevent', false)
            ->assertSee('role="tabpanel"', false)
            ->assertSee('role="tabpanel" aria-labelledby="event-tab-summary" tabindex="0"', false)
            ->assertSee('REG-PIANO-BASIC')->assertSee('HOBBY-GITAR-45')
            ->assertSee('name="enrollment_ids[]"', false)->assertSee('Publikasikan Event')
            ->assertSee('Event masih Draft.');
    }

    public function test_published_teacher_card_shows_confirmation_progress_and_replacement_controls(): void
    {
        [$event, $assignment, $participant] = $this->publishedParticipant();
        $assignment->update(['attendance_status' => 'HADIR', 'confirmed_at' => now(), 'confirmed_by' => auth()->id()]);
        $this->get(route('events.show', $event))->assertOk()
            ->assertSee($assignment->teacher->name)->assertSee('Progres absensi murid')
            ->assertSee('0 dari 1 absensi murid')
            ->assertSee('Dikonfirmasi oleh')->assertSee(auth()->user()->name)
            ->assertSee(route('event-participants.attendance', $participant), false)
            ->assertSee(route('event-participants.reassign', $participant), false)
            ->assertSee('name="reason"', false)->assertSee('Selesaikan absensi seluruh peserta aktif.')
            ->assertSee('aria-label="Tandai '.$participant->student->full_name.' hadir"', false)
            ->assertSee('aria-label="Tandai '.$participant->student->full_name.' tidak hadir"', false)
            ->assertDontSee('Simpan Absensi')
            ->assertDontSee('action="'.route('events.complete', $event).'"', false)
            ->assertDontSee(route('events.edit', $event), false);

        $viewEvent = $this->get(route('events.show', $event))->viewData('event');
        $this->assertTrue(
            $viewEvent->participants->first()->activeTeacherAssignment->relationLoaded('confirmedBy'),
        );
    }

    public function test_teacher_cards_confirm_in_one_click_and_expose_student_detail_panel(): void
    {
        [$event, $assignment, $participant] = $this->publishedParticipant();

        $response = $this->get(route('events.show', $event))->assertOk();

        // Konfirmasi satu klik: tombol submit langsung membawa nilainya, tanpa radio + tombol simpan terpisah.
        $response->assertSee('name="attendance_status" value="HADIR"', false)
            ->assertSee('name="attendance_status" value="TIDAK_HADIR"', false)
            ->assertDontSee('type="radio" name="attendance_status"', false)
            ->assertDontSee('Simpan Konfirmasi')
            // Satu panel detail (slide-over) per guru, memuat murid yang didampingi.
            ->assertSee('data-teacher-detail="'.$assignment->id.'"', false)
            ->assertSee('Detail (1)')
            ->assertSee('Murid didampingi '.$assignment->teacher->name);

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'data-teacher-detail="'));
        // Satu kartu per guru. Penanda tombol absensi tidak dipakai karena markupnya sama dengan form absensi murid.
        $this->assertSame(1, substr_count($html, 'name="assignment_id" value="'.$assignment->id.'"'));
    }

    public function test_confirmed_teacher_card_shows_reason_text_and_cancel_instead_of_reason_field(): void
    {
        [$event, $assignment, $participant] = $this->publishedParticipant();
        // Payload persis seperti yang dikirim tombol di kartu guru.
        $kirim = fn (string $status, ?string $reason = null) => $this->from(route('events.show', $event))
            ->patch(route('event-teacher-assignments.attendance', $assignment), [
                'attendance_status' => $status, 'reason' => $reason,
                'event_ui_tab' => 'teacher-attendance', 'assignment_id' => $assignment->id,
            ])->assertRedirect(route('events.show', $event));

        // Tombol "Hadir": kartu hanya menyisakan Batalkan, field alasan tidak dirender.
        $kirim('HADIR');
        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('name="attendance_status" value="PENDING"', false)
            ->assertSee('>Batalkan</button>', false)
            ->assertDontSee('id="teacher-reason-'.$assignment->id.'"', false)
            ->assertDontSee('Alasan tidak hadir:');

        // Tombol "Batalkan": kartu kembali seperti semula (Hadir + Tidak hadir + field alasan).
        $kirim('PENDING');
        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('Belum dikonfirmasi')
            ->assertSee('name="attendance_status" value="HADIR"', false)
            ->assertSee('id="teacher-reason-'.$assignment->id.'"', false);

        // Tombol "Simpan Tidak Hadir": alasan tampil sebagai teks, bukan field input.
        $kirim('TIDAK_HADIR', 'Sedang sakit');
        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('name="attendance_status" value="PENDING"', false)
            ->assertSee('>Batalkan</button>', false)
            ->assertSee('Alasan tidak hadir:')
            ->assertSee('Sedang sakit')
            ->assertDontSee('id="teacher-reason-'.$assignment->id.'"', false);

        // Absensi murid sudah tercatat: Batalkan dinonaktifkan + alasan reset ditampilkan.
        $kirim('HADIR');
        $this->from(route('events.show', $event))->patch(route('event-participants.attendance', $participant), [
            'attendance_status' => 'HADIR', 'event_notes' => null, 'event_ui_tab' => 'teacher-attendance',
        ])->assertRedirect(route('events.show', $event));
        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('Reset absensi murid terlebih dahulu sebelum membatalkan kehadiran guru.')
            ->assertSee('name="attendance_status" value="PENDING" disabled', false);
    }

    public function test_failed_participant_attendance_reopens_the_detail_panel(): void
    {
        [$event, $assignment, $participant] = $this->publishedParticipant();
        $assignment->update(['attendance_status' => 'HADIR']);

        $this->from(route('events.show', $event))->patch(route('event-participants.attendance', $participant), [
            'attendance_status' => 'HADIR',
            'event_notes' => str_repeat('a', 1001),
            'event_ui_tab' => 'teacher-attendance',
            'participant_id' => $participant->id,
        ])->assertRedirect(route('events.show', $event))->assertSessionHasErrors('event_notes');

        // Panel detail guru yang bersangkutan dibuka lagi lewat konfigurasi Alpine (initial), lalu error tampil di baris murid.
        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('initial: '.$assignment->id, false)
            ->assertSee('name="participant_id" value="'.$participant->id.'"', false)
            ->assertSee('Catatan maksimal 1000 karakter.');
    }

    public function test_absent_teacher_reason_is_visible_and_student_attendance_is_locked(): void
    {
        [$event, $assignment, $participant] = $this->publishedParticipant();
        $assignment->update(['attendance_status' => 'TIDAK_HADIR', 'absence_reason' => 'Sedang sakit']);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Sedang sakit')
            ->assertSee('Guru pendamping harus Hadir sebelum absensi murid diisi.')
            ->assertDontSee('action="'.route('event-participants.attendance', $participant).'"', false);
    }

    public function test_missing_teacher_absence_reason_reopens_the_form_and_retains_input(): void
    {
        [$event, $assignment] = $this->publishedParticipant();

        $this->from(route('events.show', $event))->patch(route('event-teacher-assignments.attendance', $assignment), [
            'attendance_status' => 'TIDAK_HADIR',
            'reason' => '',
            'event_ui_tab' => 'teacher-attendance',
            'assignment_id' => $assignment->id,
        ])->assertRedirect(route('events.show', $event))->assertSessionHasErrors('reason');

        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('x-data="{ tab: \'teacher-attendance\' }"', false)
            ->assertDontSee('sessionStorage.getItem', false)
            ->assertSee('id="event-validation-errors" role="alert"', false)
            ->assertSee('Alasan wajib diisi saat guru tidak hadir.')
            ->assertSee('x-data="{ absen: true }"', false)
            ->assertSee('name="attendance_status" value="TIDAK_HADIR"', false)
            ->assertSee('name="reason"', false)
            ->assertSee('name="assignment_id" value="'.$assignment->id.'"', false);
    }

    public function test_recorded_attendance_requires_reset_before_replacement(): void
    {
        [$event, $assignment, $participant] = $this->publishedParticipant();
        $assignment->update(['attendance_status' => 'HADIR']);
        $participant->update(['attendance_status' => 'HADIR', 'event_notes' => 'Lancar']);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Lancar')
            ->assertSee(route('event-participants.attendance.reset', $participant), false)
            ->assertDontSee('action="'.route('event-participants.reassign', $participant).'"', false)
            ->assertSee('Reset absensi sebelum mengganti guru.');
    }

    public function test_recorded_attendance_can_be_reset_after_teacher_is_corrected_absent(): void
    {
        [$event, $assignment, $participant] = $this->publishedParticipant();
        $assignment->update([
            'attendance_status' => 'TIDAK_HADIR',
            'absence_reason' => 'Koreksi konfirmasi',
        ]);
        $participant->update(['attendance_status' => 'HADIR', 'event_notes' => 'Sudah dicatat']);

        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('action="'.route('event-participants.attendance.reset', $participant).'"', false)
            ->assertDontSee('action="'.route('event-participants.attendance', $participant).'"', false)
            ->assertDontSee('action="'.route('event-participants.reassign', $participant).'"', false)
            ->assertSee('Reset absensi sebelum mengganti guru.');
    }

    public function test_historical_teacher_confirmation_keeps_reschedule_locked_after_reassignment(): void
    {
        [$event, $historicalAssignment, $participant] = $this->publishedParticipant();
        $historicalAssignment->update([
            'attendance_status' => 'HADIR',
            'confirmed_at' => now(),
            'confirmed_by' => auth()->id(),
        ]);
        $currentAssignment = EventTeacherAssignment::factory()->create(['event_id' => $event->id]);
        $participant->update(['active_teacher_assignment_id' => $currentAssignment->id]);

        $this->get(route('events.show', $event))->assertOk()
            ->assertDontSee('action="'.route('events.reschedule', $event).'"', false)
            ->assertSee('Jadwal terkunci karena konfirmasi guru atau absensi murid sudah dimulai.');
    }

    public function test_future_event_explains_date_lock_and_allows_reschedule_before_attendance(): void
    {
        [$event, $assignment] = $this->publishedParticipant();
        $event->update(['event_date' => '2026-12-21']);
        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('Absensi dibuka mulai tanggal event.')
            ->assertSee(route('events.reschedule', $event), false)
            ->assertDontSee('action="'.route('event-teacher-assignments.attendance', $assignment).'"', false);
    }

    public function test_auditor_sees_operational_information_without_event_mutation_forms(): void
    {
        [$event, $assignment, $participant] = $this->publishedParticipant();
        $this->actingAs(User::factory()->create()->assignRole('Auditor'));
        foreach (['DRAFT', 'PUBLISHED', 'COMPLETED', 'CANCELLED'] as $status) {
            $event->update(['status' => $status]);
            $response = $this->get(route('events.show', $event))->assertOk()
                ->assertSee('Kehadiran Guru')->assertSee($participant->student->full_name)
                ->assertSee($assignment->teacher->name)->assertDontSee('Publikasikan Event')
                ->assertDontSee('Tambah Peserta')->assertDontSee('Tandai Selesai');
            preg_match_all('/<form[^>]*action="([^"]+)"/', $response->getContent(), $forms);
            $this->assertSame([route('logout')], $forms[1]);
        }
        $this->get(route('events.index'))->assertOk()->assertDontSee('Buat Event');
    }

    public function test_closed_events_hide_mutations_and_finance_accepts_default_summary(): void
    {
        [$event, $assignment] = $this->publishedParticipant();
        foreach (['COMPLETED', 'CANCELLED'] as $status) {
            $event->update(['status' => $status]);
            $this->get(route('events.show', $event))->assertOk()->assertSee('Pendapatan diterima')
                ->assertSee('Rp 0')->assertDontSee('Tambah Peserta')
                ->assertDontSee('action="'.route('event-teacher-assignments.attendance', $assignment).'"', false)
                ->assertSee('Detail (1)')
                ->assertDontSee('Batalkan Event');
        }
    }

    private function publishedParticipant(): array
    {
        $event = Event::factory()->published()->create(['event_date' => '2026-12-20']);
        $assignment = EventTeacherAssignment::factory()->create(['event_id' => $event->id]);
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id, 'active_teacher_assignment_id' => $assignment->id,
        ]);

        return [$event, $assignment, $participant];
    }
}
