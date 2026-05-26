<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model EventParticipant — digunakan di test unit/feature.
 * Menghasilkan data peserta event yang valid sesuai skema tabel event_participants.
 *
 * @extends Factory<EventParticipant>
 */
class EventParticipantFactory extends Factory
{
    protected $model = EventParticipant::class;

    public function definition(): array
    {
        return [
            'event_id'                => Event::factory(),
            'student_id'              => Student::factory(),
            'enrollment_id'           => null,
            'accompanying_teacher_id' => null,
            'participation_type'      => EventParticipant::TYPE_TAMPIL_SAJA,
            'fee_amount'              => EventParticipant::FEE_TAMPIL_SAJA,
            'invoice_id'              => null,
            'invoice_item_id'         => null,
            'exam_result'             => null,
            'grade_before'            => null,
            'grade_after'             => null,
            'exam_notes'              => null,
        ];
    }
}
