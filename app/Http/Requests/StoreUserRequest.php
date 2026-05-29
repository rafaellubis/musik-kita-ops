<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Route sudah di-protect role:Owner
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
            'username'   => 'nullable|string|min:3|max:30|regex:/^[a-z0-9._-]+$/|unique:users,username',
            'email'      => 'required|email|unique:users,email',
            'role'       => 'required|in:Owner,Admin,Auditor,Guru',
            'password'   => 'required|string|min:8',
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
            'username.min'           => 'Username minimal 3 karakter.',
            'username.max'           => 'Username maksimal 30 karakter.',
            'username.regex'         => 'Username hanya boleh huruf kecil, angka, titik, strip, dan underscore.',
            'username.unique'        => 'Username sudah digunakan oleh user lain.',
            'email.required'         => 'Email wajib diisi.',
            'email.unique'           => 'Email sudah digunakan oleh user lain.',
            'role.required'          => 'Role wajib dipilih.',
            'role.in'                => 'Role tidak valid.',
            'password.required'      => 'Password wajib diisi.',
            'password.min'           => 'Password minimal 8 karakter.',
            'teacher_id.required_if' => 'Teacher wajib dipilih untuk role Guru.',
            'teacher_id.exists'      => 'Teacher tidak ditemukan.',
        ];
    }
}
