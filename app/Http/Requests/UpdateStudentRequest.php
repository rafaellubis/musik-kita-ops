<?php

namespace App\Http\Requests;

use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ============= IDENTITAS =============
            'full_name'           => 'required|string|max:100',
            'nickname'            => 'nullable|string|max:30',
            'gender'              => 'required|in:L,P',
            'birth_date'          => 'nullable|date|before_or_equal:today',

            // ============= KONTAK =============
            'phone'               => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'email'               => 'nullable|email|max:100',
            'address'             => 'nullable|string',
            'notes'               => 'nullable|string',

            // ============= PARENT =============
            'parent_name'         => 'nullable|string|max:100',
            'parent_phone'        => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'parent_email'        => 'nullable|email|max:100',
            'parent_relationship' => 'nullable|in:Ayah,Ibu,Wali',

            // ============= STATUS BELAJAR =============
            // CATATAN: 'status' TIDAK ada di sini — immutable via form edit
            // Status hanya berubah lewat lifecycle action (Sesi 6-8)
            'package_id'          => 'nullable|exists:packages,id',
            'assigned_teacher_id' => 'nullable|exists:teachers,id',
            'assigned_room_id'    => 'nullable|exists:rooms,id',
            'preferred_day'       => 'nullable|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'preferred_time'      => 'nullable|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'           => 'Nama lengkap wajib diisi.',
            'gender.required'              => 'Jenis kelamin wajib dipilih.',
            'gender.in'                    => 'Jenis kelamin harus L atau P.',
            'birth_date.before_or_equal'   => 'Tanggal lahir tidak boleh di masa depan.',
            'email.email'                  => 'Format email tidak valid.',
            'phone.regex'                  => 'Nomor HP hanya boleh angka, +, -, spasi, dan kurung.',
            'parent_email.email'           => 'Format email orang tua tidak valid.',
            'parent_phone.regex'           => 'Nomor HP orang tua hanya boleh angka.',
            'package_id.exists'            => 'Paket yang dipilih tidak valid.',
            'assigned_teacher_id.exists'   => 'Guru yang dipilih tidak valid.',
            'assigned_room_id.exists'      => 'Ruangan yang dipilih tidak valid.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            // Ambil current student dari route
            $studentId = $this->route('student');
            if (!$studentId) return;

            $currentStudent = \App\Models\Student::find($studentId);
            if (!$currentStudent) return;

            // Rule 1: Kalau status saat ini Aktif, package tidak boleh dikosongkan
            if ($currentStudent->status === 'Aktif' && !$this->package_id) {
                $validator->errors()->add(
                    'package_id',
                    'Paket tidak boleh kosong untuk murid Aktif.'
                );
            }

            // Rule 2: Kalau status saat ini Aktif, teacher tidak boleh dikosongkan
            if ($currentStudent->status === 'Aktif' && !$this->assigned_teacher_id) {
                $validator->errors()->add(
                    'assigned_teacher_id',
                    'Guru tidak boleh kosong untuk murid Aktif.'
                );
            }

            // Rule 3: Kids Class — validasi umur
            // ⚠ Pakai enum value yang BENAR: KIDS_CLASS / KIDS_CLASS_BUNDLE
            if ($this->package_id) {
                $package = Package::find($this->package_id);
                $isKidsClass = $package && in_array(
                    $package->class_type,
                    ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE']
                );

                if ($isKidsClass && $this->birth_date) {
                    $age = \Carbon\Carbon::parse($this->birth_date)->age;
                    if ($age < 4 || $age >= 5) {
                        $validator->errors()->add(
                            'birth_date',
                            'Kids Class hanya untuk anak usia 4 sampai kurang dari 5 tahun.'
                        );
                    }
                }

                if ($isKidsClass && !$this->parent_name) {
                    $validator->errors()->add(
                        'parent_name',
                        'Nama orang tua wajib untuk Kids Class.'
                    );
                }
            }
        });
    }
}