<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi username ke lowercase sebelum validasi.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('username')) {
            $this->merge(['username' => strtolower($this->username)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'       => 'required|string|min:2|max:100',
            'username'   => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->route('user')),
            ],
            'email'      => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role'       => 'required|in:Owner,Admin,Auditor,Guru',
            'teacher_id' => 'required_if:role,Guru|nullable|exists:teachers,id',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required'          => 'Nama wajib diisi.',
            'name.min'               => 'Nama minimal 2 karakter.',
            'name.max'               => 'Nama maksimal 100 karakter.',
            'username.required'      => 'Username wajib diisi.',
            'username.min'           => 'Username minimal 3 karakter.',
            'username.max'           => 'Username maksimal 30 karakter.',
            'username.regex'         => 'Username hanya boleh huruf kecil, angka, titik, strip, dan underscore.',
            'username.unique'        => 'Username sudah digunakan oleh user lain.',
            'email.required'         => 'Email wajib diisi.',
            'email.unique'           => 'Email sudah digunakan oleh user lain.',
            'role.required'          => 'Role wajib dipilih.',
            'role.in'                => 'Role tidak valid.',
            'teacher_id.required_if' => 'Teacher wajib dipilih untuk role Guru.',
            'teacher_id.exists'      => 'Teacher tidak ditemukan.',
        ];
    }
}
