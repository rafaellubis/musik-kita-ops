<?php

namespace App\Http\Requests;

use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        // Nama → Title Case (Capitalize Each Word) agar konsisten di DB
        foreach (['full_name', 'nickname', 'parent_name'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = ucwords(strtolower(trim($this->input($field))));
            }
        }

        // Nomor HP → awalan +62 (08xxx → +628xxx, 8xxx → +628xxx)
        foreach (['phone', 'parent_phone'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = $this->normalizePhone($this->input($field));
            }
        }

        $this->merge($merge);
    }

    private function normalizePhone(string $phone): string
    {
        // Hapus spasi, tanda kurung, strip — pertahankan + saja
        $clean = preg_replace('/[^0-9+]/', '', trim($phone));

        if (empty($clean)) return $phone;

        if (str_starts_with($clean, '+62')) return $clean;
        if (str_starts_with($clean, '62'))  return '+' . $clean;
        if (str_starts_with($clean, '0'))   return '+62' . substr($clean, 1);

        return '+62' . $clean; // "8xxxx" tanpa awalan
    }

    public function rules(): array
    {
        return [
            // ============= IDENTITAS =============
            'full_name'           => 'required|string|max:100',
            'nickname' => [
                'nullable', 'string', 'max:30',
                Rule::unique('students', 'nickname')->ignore($this->route('student')),
            ],
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

            // CATATAN: 'status' TIDAK ada di sini — immutable via form edit
            // Status hanya berubah lewat lifecycle action (Sesi 6-8)
            // package, teacher, room dikelola via tab Kelas (enrollments) — bukan form edit
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
            'nickname.unique'              => 'Nama panggilan sudah dipakai murid lain.',
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

            // Validasi package/teacher/room tidak diperlukan di sini —
            // sekarang dikelola via tab Kelas (enrollment) di halaman detail murid.
        });
    }
}