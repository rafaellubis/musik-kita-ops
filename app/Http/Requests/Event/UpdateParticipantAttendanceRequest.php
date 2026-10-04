<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParticipantAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Admin']);
    }

    public function rules(): array
    {
        if ($this->routeIs('event-participants.attendance.reset')) {
            return ['reason' => ['required', 'string', 'max:1000']];
        }

        return [
            'attendance_status' => ['required', 'in:HADIR,TIDAK_HADIR'],
            'event_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
