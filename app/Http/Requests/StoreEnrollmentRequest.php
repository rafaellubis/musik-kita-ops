<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form tambah kelas baru untuk murid (multi-kelas).
 * Dipakai oleh EnrollmentController@store.
 */
class StoreEnrollmentRequest extends FormRequest
{
    /**
     * Hanya Owner dan Admin yang boleh menambah kelas.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Owner', 'Admin']);
    }

    /**
     * Aturan validasi form tambah kelas.
     */
    public function rules(): array
    {
        return [
            'package_id'                => ['required', 'exists:packages,id'],
            'teacher_id'                => ['required', 'exists:teachers,id'],
            'room_id'                   => ['required', 'exists:rooms,id'],
            'day_of_week'               => ['required', 'integer', 'between:0,6'],
            'start_time'                => ['required', 'date_format:H:i'],
            'effective_date'            => ['required', 'date'],
            'jadikan_utama'             => ['sometimes', 'boolean'],
            'new_primary_enrollment_id' => ['sometimes', 'nullable', 'exists:enrollments,id'],
        ];
    }

    /**
     * Pesan error validasi dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'package_id.required'           => 'Paket wajib dipilih.',
            'teacher_id.required'           => 'Guru wajib dipilih.',
            'room_id.required'              => 'Ruangan wajib dipilih.',
            'day_of_week.required'          => 'Hari wajib dipilih.',
            'start_time.required'           => 'Jam mulai wajib diisi.',
            'effective_date.required' => 'Tanggal mulai efektif wajib diisi.',
        ];
    }
}
