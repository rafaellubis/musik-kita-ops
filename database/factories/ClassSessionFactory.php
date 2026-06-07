<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model ClassSession — digunakan di test unit/feature.
 *
 * Catatan: schedule_id dan enrollment_id nullable di tabel (sesi trial
 * belum punya enrollment). Di test yang butuh relasi lengkap, override
 * via createTestSession() helper di masing-masing test class.
 *
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    protected $model = ClassSession::class;

    public function definition(): array
    {
        return [
            'schedule_id'           => null,
            'enrollment_id'         => null,
            'student_id'            => Student::factory(),
            'teacher_id'            => Teacher::factory(),
            'substitute_teacher_id' => null,
            'session_date'          => today(),
            'start_time'            => '10:00:00',
            'end_time'              => '10:30:00',
            'room_id'               => null,
            'status'                => ClassSession::STATUS_SCHEDULED,
            'late_minutes'          => null,
            'notes'                 => null,
            'honor_code'            => null,
            'honor_amount'          => null,
            'session_type'          => ClassSession::TYPE_REGULAR,
            'attribution_month'     => null,
            'attribution_year'      => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ClassSession $session) {
            if ($session->session_date && $session->attribution_month === null) {
                $date = \Carbon\Carbon::parse($session->session_date);
                $session->attribution_month = $date->month;
                $session->attribution_year  = $date->year;
            }
        });
    }
}
