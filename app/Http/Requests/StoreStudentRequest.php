<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
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

            // Status selalu Calon saat create — dikunci via hidden field di form
            'status'              => 'nullable|in:Calon',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'           => 'Nama lengkap wajib diisi.',
            'full_name.max'                => 'Nama lengkap maksimal 100 karakter.',
            'gender.required'              => 'Jenis kelamin wajib dipilih.',
            'gender.in'                    => 'Jenis kelamin harus L atau P.',
            'birth_date.before_or_equal'   => 'Tanggal lahir tidak boleh di masa depan.',
            'email.email'                  => 'Format email tidak valid.',
            'phone.regex'                  => 'Nomor HP hanya boleh angka, +, -, spasi, dan kurung.',
            'parent_email.email'           => 'Format email orang tua tidak valid.',
            'parent_phone.regex'           => 'Nomor HP orang tua hanya boleh angka.',
            'status.in'                    => 'Status tidak valid.',
        ];
    }

}