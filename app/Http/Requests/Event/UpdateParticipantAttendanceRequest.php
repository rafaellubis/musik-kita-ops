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

    public function messages(): array
    {
        return [
            'attendance_status.required' => 'Absensi murid wajib dipilih.',
            'attendance_status.in' => 'Absensi murid harus Hadir atau Tidak Hadir.',
            'reason.required' => 'Alasan wajib diisi.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
            'event_notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
