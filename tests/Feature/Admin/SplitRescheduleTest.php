<?php

namespace Tests\Feature\Admin;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SplitRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner',  'guard_name' => 'web']);
    }

    private function makeOriginalSession(array $override = []): ClassSession
    {
        $teacher    = Teacher::factory()->create(['name' => 'Guru Test', 'is_active' => true]);
        $student    = Student::factory()->create();
        $package    = Package::factory()->create([
            'duration_min'    => 30,
            'price_per_month' => 340000,
            'is_active'       => true,
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create(array_merge([
            'teacher_id'       => $teacher->id,
            'student_id'       => $student->id,
            'enrollment_id'    => $enrollment->id,
            'session_date'     => '2026-05-20',
            'start_time'       => '10:00:00',
            'end_time'         => '10:30:00',
            'status'           => ClassSession::STATUS_SCHEDULED,
            'session_sequence' => 3,
        ], $override));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    /** @test */
    public function session_label_split_part1(): void
    {
        $original = $this->makeOriginalSession();
        $part1 = ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'session_sequence'  => 3,
            'split_part'        => 1,
            'session_date'      => '2026-06-05',
        ]);
        $part1->load('originSession');

        $this->assertEquals(
            'Bagian 1/2 — Reschedule dari Sesi ke-3 Bulan Mei 2026',
            $part1->getSessionLabel()
        );
    }

    /** @test */
    public function session_label_split_part2(): void
    {
        $original = $this->makeOriginalSession();
        $part2 = ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'session_sequence'  => 3,
            'split_part'        => 2,
            'session_date'      => '2026-06-12',
        ]);
        $part2->load('originSession');

        $this->assertEquals(
            'Bagian 2/2 — Reschedule dari Sesi ke-3 Bulan Mei 2026',
            $part2->getSessionLabel()
        );
    }
}
