<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruEventPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;

    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guru', 'Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->guruUser = User::factory()->create(['name' => 'Guru Portal']);
        $this->guruUser->assignRole('Guru');
        $this->teacher = Teacher::factory()->create([
            'name' => 'Guru Portal',
            'user_id' => $this->guruUser->id,
        ]);
    }

    public function test_guru_only_sees_published_or_completed_events_assigned_to_them(): void
    {
        [$published, $assignment] = $this->assignedEvent('Konser Terbit', Event::STATUS_PUBLISHED, today()->addWeek());
        $this->participant($published, $assignment, 'Murid Terbit');

        [$completed, $completedAssignment] = $this->assignedEvent('Konser Selesai', Event::STATUS_COMPLETED, today()->subWeek());
        $this->participant($completed, $completedAssignment, 'Murid Riwayat', EventParticipant::ATTENDANCE_HADIR);

        [$draft, $draftAssignment] = $this->assignedEvent('Konser Draft', Event::STATUS_DRAFT, today()->addWeek());
        $this->participant($draft, $draftAssignment, 'Murid Draft');

        [$cancelled, $cancelledAssignment] = $this->assignedEvent('Konser Batal', Event::STATUS_CANCELLED, today()->addWeek());
        $this->participant($cancelled, $cancelledAssignment, 'Murid Batal');

        $otherTeacher = Teacher::factory()->create();
        $foreign = Event::factory()->published()->create(['name' => 'Konser Guru Lain']);
        $foreignAssignment = EventTeacherAssignment::factory()->create([
            'event_id' => $foreign->id,
            'teacher_id' => $otherTeacher->id,
        ]);
        $this->participant($foreign, $foreignAssignment, 'Murid Guru Lain');

        $response = $this->actingAs($this->guruUser)->get(route('guru.events.index'));

        $response->assertOk()
            ->assertSee('Event Mendatang')
            ->assertSee('Perlu Absensi')
            ->assertSee('Riwayat Selesai')
            ->assertSee('Konser Terbit')
            ->assertSee('Konser Selesai')
            ->assertSee('1 kartu belum lengkap')
            ->assertDontSee('Konser Draft')
            ->assertDontSee('Konser Batal')
            ->assertDontSee('Konser Guru Lain');
    }

    public function test_cards_are_visible_but_locked_before_teacher_is_confirmed_hadir(): void
    {
        [$event, $assignment] = $this->assignedEvent('Konser Hari Ini', Event::STATUS_PUBLISHED, today());
        $participant = $this->participant($event, $assignment, 'Murid Tetap Terlihat');

        $response = $this->actingAs($this->guruUser)->get(route('guru.events.show', $event));

        $response->assertOk()
            ->assertSee('Murid Tetap Terlihat')
            ->assertSee('Menunggu konfirmasi kehadiran guru oleh Admin.')
            ->assertDontSee('action="'.route('guru.events.attendance.update', $participant).'"', false);
    }

    public function test_cards_are_locked_before_event_date(): void
    {
        [$event, $assignment] = $this->assignedEvent('Konser Besok', Event::STATUS_PUBLISHED, today()->addDay(), true);
        $participant = $this->participant($event, $assignment, 'Murid Besok');

        $response = $this->actingAs($this->guruUser)->get(route('guru.events.show', $event));

        $response->assertOk()
            ->assertSee('Absensi dibuka mulai tanggal event.')
            ->assertDontSee('action="'.route('guru.events.attendance.update', $participant).'"', false);
    }

    public function test_present_teacher_can_record_hadir_tidak_hadir_and_optional_notes(): void
    {
        [$event, $assignment] = $this->assignedEvent('Konser Berjalan', Event::STATUS_PUBLISHED, today(), true);
        $participant = $this->participant($event, $assignment, 'Murid Aktif');

        $this->actingAs($this->guruUser)
            ->patch(route('guru.events.attendance.update', $participant), [
                'attendance_status' => EventParticipant::ATTENDANCE_HADIR,
                'event_notes' => 'Tampil percaya diri.',
                'teacher_id' => Teacher::factory()->create()->id,
            ])
            ->assertRedirect(route('guru.events.show', $event));

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'attendance_status' => EventParticipant::ATTENDANCE_HADIR,
            'event_notes' => 'Tampil percaya diri.',
            'attendance_updated_by' => $this->guruUser->id,
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.events.attendance.update', $participant), [
                'attendance_status' => EventParticipant::ATTENDANCE_TIDAK_HADIR,
                'event_notes' => null,
            ])
            ->assertRedirect(route('guru.events.show', $event));

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'attendance_status' => EventParticipant::ATTENDANCE_TIDAK_HADIR,
            'event_notes' => null,
        ]);
        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_stale_html_form_redirects_with_domain_message_and_preserved_input(): void
    {
        [$event, $assignment] = $this->assignedEvent('Konser Berubah', Event::STATUS_PUBLISHED, today(), true);
        $participant = $this->participant($event, $assignment, 'Murid Stale');

        $this->actingAs($this->guruUser)
            ->get(route('guru.events.show', $event))
            ->assertOk()
            ->assertSee(route('guru.events.attendance.update', $participant), false);

        // Admin mengubah konfirmasi guru setelah form dibuka oleh guru.
        $assignment->update([
            'attendance_status' => EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR,
            'confirmed_at' => now(),
        ]);

        $this->from(route('guru.events.show', $event))
            ->patch(route('guru.events.attendance.update', $participant), [
                'participant_id' => $participant->id,
                'attendance_status' => EventParticipant::ATTENDANCE_HADIR,
                'event_notes' => 'Catatan dari form yang sudah stale.',
            ])
            ->assertRedirect(route('guru.events.show', $event))
            ->assertSessionHas('error', 'Kehadiran murid hanya dapat diisi setelah guru pendamping HADIR.')
            ->assertSessionHasInput('attendance_status', EventParticipant::ATTENDANCE_HADIR)
            ->assertSessionHasInput('event_notes', 'Catatan dari form yang sudah stale.');

        $this->actingAs($this->guruUser)
            ->get(route('guru.events.show', $event))
            ->assertOk()
            ->assertSee('Kehadiran murid hanya dapat diisi setelah guru pendamping HADIR.');

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'attendance_status' => EventParticipant::ATTENDANCE_PENDING,
            'event_notes' => null,
        ]);
    }

    public function test_guru_cannot_view_or_mutate_another_teachers_participant_by_id(): void
    {
        [$event, $ownAssignment] = $this->assignedEvent('Konser Bersama', Event::STATUS_PUBLISHED, today(), true);
        $this->participant($event, $ownAssignment, 'Murid Sendiri');

        $otherTeacher = Teacher::factory()->create();
        $foreignAssignment = EventTeacherAssignment::factory()->hadir()->create([
            'event_id' => $event->id,
            'teacher_id' => $otherTeacher->id,
        ]);
        $foreignParticipant = $this->participant($event, $foreignAssignment, 'Murid Guru Lain');

        $this->actingAs($this->guruUser)
            ->get(route('guru.events.show', $event))
            ->assertOk()
            ->assertSee('Murid Sendiri')
            ->assertDontSee('Murid Guru Lain');

        $this->actingAs($this->guruUser)
            ->patch(route('guru.events.attendance.update', $foreignParticipant), [
                'attendance_status' => EventParticipant::ATTENDANCE_HADIR,
            ])
            ->assertNotFound();

        $foreignEvent = Event::factory()->published()->create();
        $foreignOnlyAssignment = EventTeacherAssignment::factory()->create([
            'event_id' => $foreignEvent->id,
            'teacher_id' => $otherTeacher->id,
        ]);
        $this->participant($foreignEvent, $foreignOnlyAssignment, 'Murid Event Asing');

        $this->actingAs($this->guruUser)
            ->get(route('guru.events.show', $foreignEvent))
            ->assertNotFound();
    }

    public function test_completed_event_is_read_only(): void
    {
        [$event, $assignment] = $this->assignedEvent('Konser Terkunci', Event::STATUS_COMPLETED, today()->subDay(), true);
        $participant = $this->participant($event, $assignment, 'Murid Selesai', EventParticipant::ATTENDANCE_HADIR);

        $response = $this->actingAs($this->guruUser)->get(route('guru.events.show', $event));

        $response->assertOk()
            ->assertSee('Murid Selesai')
            ->assertSee('Event sudah selesai. Absensi hanya dapat dilihat.')
            ->assertDontSee('action="'.route('guru.events.attendance.update', $participant).'"', false);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.events.attendance.update', $participant), [
                'attendance_status' => EventParticipant::ATTENDANCE_TIDAK_HADIR,
            ])
            ->assertRedirect(route('guru.events.show', $event))
            ->assertSessionHas('error', 'Absensi hanya dapat diubah saat event PUBLISHED.');

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'attendance_status' => EventParticipant::ATTENDANCE_HADIR,
        ]);
    }

    public function test_detail_shows_assignment_honor_event_notes_and_last_actor(): void
    {
        [$event, $assignment] = $this->assignedEvent('Konser Detail', Event::STATUS_PUBLISHED, today(), true);
        $participant = $this->participant($event, $assignment, 'Murid Detail', EventParticipant::ATTENDANCE_HADIR, [
            'snapshot_package_code' => 'REG-PIANO-BASIC',
            'snapshot_instrument_name' => 'Piano',
            'snapshot_honor_amount' => 42500,
            'event_notes' => 'Catatan khusus event.',
            'attendance_updated_at' => now(),
            'attendance_updated_by' => $this->guruUser->id,
        ]);

        $this->actingAs($this->guruUser)
            ->get(route('guru.events.show', $event))
            ->assertOk()
            ->assertSee('Guru hadir')
            ->assertSee('Rp 42.500')
            ->assertSee('REG-PIANO-BASIC')
            ->assertSee('Piano')
            ->assertSee('Catatan khusus event.')
            ->assertSee('Guru Portal')
            ->assertSee(route('guru.events.attendance.update', $participant), false);
    }

    public function test_inactive_mobile_event_label_uses_contrasting_white_text(): void
    {
        $html = $this->actingAs($this->guruUser)
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<span class="text-\[10px\] font-semibold text-white\/50">\s*Event\s*<\/span>/',
            $html,
        );
    }

    /** @return array{Event, EventTeacherAssignment} */
    private function assignedEvent(string $name, string $status, $date, bool $teacherHadir = false): array
    {
        $event = Event::factory()->create([
            'name' => $name,
            'status' => $status,
            'event_date' => $date,
            'published_at' => in_array($status, [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED], true) ? now() : null,
            'completed_at' => $status === Event::STATUS_COMPLETED ? now() : null,
            'cancelled_at' => $status === Event::STATUS_CANCELLED ? now() : null,
            'cancellation_reason' => $status === Event::STATUS_CANCELLED ? 'Dibatalkan.' : null,
        ]);
        $factory = $teacherHadir
            ? EventTeacherAssignment::factory()->hadir()
            : EventTeacherAssignment::factory();
        $assignment = $factory->create([
            'event_id' => $event->id,
            'teacher_id' => $this->teacher->id,
        ]);

        return [$event, $assignment];
    }

    private function participant(
        Event $event,
        EventTeacherAssignment $assignment,
        string $studentName,
        string $attendance = EventParticipant::ATTENDANCE_PENDING,
        array $attributes = [],
    ): EventParticipant {
        $enrollment = Enrollment::factory()->create();
        $enrollment->student->update(['full_name' => $studentName]);

        return EventParticipant::factory()->create($attributes + [
            'event_id' => $event->id,
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'active_teacher_assignment_id' => $assignment->id,
            'attendance_status' => $attendance,
        ]);
    }
}
