<?php

namespace App\Http\Requests;

use App\Models\ClassSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAbsensiRequest extends FormRequest
{
    /**
     * Semua user yang sudah lolos middleware role:Owner|Admin diizinkan.
     * Otorisasi sudah dihandle di route middleware, jadi cukup return true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk update absensi satu sesi.
     *
     * Status yang valid adalah semua status kecuali LIBUR dan SCHEDULED —
     * LIBUR diblok di controller (BR-4.10), SCHEDULED bukan input valid dari admin.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    ClassSession::STATUS_HADIR,
                    ClassSession::STATUS_HADIR_TERLAMBAT,
                    ClassSession::STATUS_HANGUS,
                    ClassSession::STATUS_IZIN_RESCHEDULE,
                    ClassSession::STATUS_IZIN_VIDEO,
                    ClassSession::STATUS_DIGANTI,
                    ClassSession::STATUS_CANCELLED,
                ]),
            ],
            // Menit terlambat — wajib jika status HADIR_TERLAMBAT
            'late_minutes' => [
                'required_if:status,' . ClassSession::STATUS_HADIR_TERLAMBAT,
                'nullable', 'integer', 'min:1', 'max:60',
            ],
            // Guru pengganti — wajib jika status DIGANTI
            'substitute_teacher_id' => [
                'required_if:status,' . ClassSession::STATUS_DIGANTI,
                'nullable', 'exists:teachers,id',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            // Tanggal pengganti — wajib jika status IZIN_RESCHEDULE
            'replacement_date' => [
                'required_if:status,' . ClassSession::STATUS_IZIN_RESCHEDULE,
                'nullable', 'date', 'date_format:Y-m-d',
            ],
            // Jam mulai pengganti — wajib jika status IZIN_RESCHEDULE
            'replacement_time' => [
                'required_if:status,' . ClassSession::STATUS_IZIN_RESCHEDULE,
                'nullable', 'date_format:H:i',
            ],
            // Ruangan pengganti — opsional
            'replacement_room_id' => [
                'nullable', 'exists:rooms,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required'                   => 'Status absensi wajib diisi.',
            'status.in'                         => 'Status tidak valid.',
            'late_minutes.required_if'          => 'Jumlah menit terlambat wajib diisi.',
            'late_minutes.min'                  => 'Minimal terlambat 1 menit.',
            'late_minutes.max'                  => 'Maksimal 60 menit.',
            'substitute_teacher_id.required_if' => 'Guru pengganti wajib dipilih.',
            'substitute_teacher_id.exists'      => 'Guru pengganti tidak ditemukan.',
            'replacement_date.required_if'      => 'Tanggal pengganti wajib diisi.',
            'replacement_date.date_format'      => 'Format tanggal harus YYYY-MM-DD.',
            'replacement_time.required_if'      => 'Jam mulai pengganti wajib diisi.',
            'replacement_time.date_format'      => 'Format jam harus HH:MM.',
            'replacement_room_id.exists'        => 'Ruangan tidak ditemukan.',
        ];
    }
}
