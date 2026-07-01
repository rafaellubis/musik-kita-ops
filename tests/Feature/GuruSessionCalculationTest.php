<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruSessionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Teacher $teacher1;
    private Teacher $teacher2;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        
        $this->teacher1 = Teacher::factory()->create();
        $this->teacher2 = Teacher::factory()->create();
    }

    public function test_scopes_calculate_honored_sessions_correctly(): void
    {
        $today = today()->toDateString();

        // 1. Sesi biasa HADIR -> terhitung untuk teacher1
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_HADIR,
            'honor_code' => 'H_REG',
            'honor_amount' => 42500,
        ]);

        // 2. Sesi IZIN_RESCHEDULE -> tidak terhitung untuk teacher1
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_IZIN_RESCHEDULE,
            'honor_code' => 'H_IZIN',
            'honor_amount' => 0,
        ]);

        // 3. Sesi DIGANTI (substitute_teacher_id = teacher2) - belum dikonfirmasi (honor_code null)
        // -> tidak terhitung untuk teacher1 maupun teacher2
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'substitute_teacher_id' => $this->teacher2->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_DIGANTI,
            'honor_code' => null,
            'honor_amount' => null,
        ]);

        // 4. Sesi DIGANTI (substitute_teacher_id = teacher2) - sudah dikonfirmasi (honor_code filled)
        // -> terhitung untuk teacher2, TIDAK untuk teacher1
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'substitute_teacher_id' => $this->teacher2->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_DIGANTI,
            'honor_code' => 'H_PENG',
            'honor_amount' => 42500,
        ]);

        // 5. Sesi LIBUR nasional berbayar -> terhitung untuk teacher1
        ClassSession::factory()->create([
            'teacher_id' => $this->teacher1->id,
            'session_date' => $today,
            'status' => ClassSession::STATUS_LIBUR,
            'honor_code' => 'H_LIBUR',
            'honor_amount' => 42500,
        ]);

        // Test scope honoredForTeacher & honoredStatus
        $count1 = ClassSession::honoredForTeacher($this->teacher1->id)->honoredStatus()->count();
        $count2 = ClassSession::honoredForTeacher($this->teacher2->id)->honoredStatus()->count();

        $this->assertEquals(2, $count1, 'Teacher 1 harus memiliki 2 sesi berbayar (Hadir, Libur)');
        $this->assertEquals(1, $count2, 'Teacher 2 harus memiliki 1 sesi berbayar (Pengganti terkonfirmasi)');
    }
}
