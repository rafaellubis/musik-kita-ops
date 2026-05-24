<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi input untuk endpoint POST /absensi/{session}/split/{part}.
 * Dipakai untuk membuat Part 1 dan Part 2 dari split reschedule.
 *
 * Endpoint ini dipanggil 2x setelah admin memilih skenario split:
 * 1. POST /absensi/{session}/split/1 — buat Part 1 (sesi awal dipotong)
 * 2. POST /absensi/{session}/split/2 — buat Part 2 (sesi baru pengganti)
 *
 * Kedua part berbagi field input yang sama: tanggal, jam, dan ruangan pengganti.
 */
class StoreSplitPartRequest extends FormRequest
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
     * Aturan validasi untuk input split reschedule.
     *
     * replacement_date: Tanggal sesi pengganti (format YYYY-MM-DD)
     * replacement_time: Jam mulai pengganti (format HH:MM)
     * replacement_room_id: Ruangan pengganti (opsional, bisa pakai ruangan awal)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'replacement_date'    => ['required', 'date', 'date_format:Y-m-d'],
            'replacement_time'    => ['required', 'date_format:H:i'],
            'replacement_room_id' => ['nullable', 'exists:rooms,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'replacement_date.required'    => 'Tanggal sesi pengganti wajib diisi.',
            'replacement_date.date'        => 'Format tanggal tidak valid.',
            'replacement_date.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'replacement_time.required'    => 'Jam mulai pengganti wajib diisi.',
            'replacement_time.date_format' => 'Format jam harus HH:MM.',
            'replacement_room_id.exists'   => 'Ruangan tidak ditemukan.',
        ];
    }
}
