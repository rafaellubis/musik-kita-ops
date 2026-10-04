<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTeacherAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Admin']);
    }

    public function rules(): array
    {
        return [
            'attendance_status' => ['required', 'in:HADIR,TIDAK_HADIR'],
            'reason' => ['required_if:attendance_status,TIDAK_HADIR', 'nullable', 'string', 'max:1000'],
        ];
    }
}
