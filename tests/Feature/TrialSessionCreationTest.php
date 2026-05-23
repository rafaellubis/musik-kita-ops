<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialSessionCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** mulaiTrial harus membuat ClassSession dengan enrollment_id = NULL */
    public function test_mulaiTrial_membuat_class_session(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $trialAt = now()->addDay()->setTime(10, 0, 0);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => $trialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'enrollment_id' => null,
            'session_date'  => $trialAt->toDateString(),
            'start_time'    => $trialAt->format('H:i:s'),
            'end_time'      => $trialAt->copy()->addMinutes(30)->format('H:i:s'),
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);
    }

    /** Durasi trial selalu 30 menit untuk semua tipe paket (BR-1.3) */
    public function test_mulaiTrial_durasi_sesi_30_menit(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $trialAt = now()->addDay()->setTime(14, 30, 0);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => $trialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'student_id' => $student->id,
            'start_time' => $trialAt->format('H:i:s'),
            'end_time'   => $trialAt->copy()->addMinutes(30)->format('H:i:s'),
        ]);
    }

    /** room_id tersimpan saat admin memilih ruangan */
    public function test_mulaiTrial_menyimpan_room_id(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $room    = Room::factory()->create();
        $trialAt = now()->addDay()->setTime(9, 0, 0);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => $trialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'assigned_room_id'    => $room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'student_id' => $student->id,
            'room_id'    => $room->id,
        ]);
    }

    /** Guru trial wajib diisi — request tanpa guru dikembalikan dengan error validasi */
    public function test_mulaiTrial_wajib_isi_guru(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date' => now()->addDay()->format('Y-m-d\TH:i'),
                // tidak ada assigned_teacher_id
            ])
            ->assertSessionHasErrors(['assigned_teacher_id']);

        // Pastikan tidak ada ClassSession yang terbuat
        $this->assertDatabaseCount('class_sessions', 0);
    }

    /** Service-level guard: mulaiTrial() tanpa assigned_teacher_id lempar InvalidArgumentException */
    public function test_mulaiTrial_tanpa_teacher_lempar_exception(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('assigned_teacher_id wajib diisi');

        $lifecycle = new \App\Services\StudentLifecycleService(
            new \App\Services\InvoiceService()
        );
        $lifecycle->mulaiTrial($student, [
            'trial_date' => now()->addDay()->format('Y-m-d\TH:i'),
            // tidak ada assigned_teacher_id
        ]);
    }
}
