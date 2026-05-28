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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'       => 'required|string|min:2|max:100',
            'email'      => [
                'required',
                'email',
                // Ignore email unik validation untuk user yang sedang di-edit
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
            'email.required'         => 'Email wajib diisi.',
            'email.unique'           => 'Email sudah digunakan oleh user lain.',
            'role.required'          => 'Role wajib dipilih.',
            'role.in'                => 'Role tidak valid.',
            'teacher_id.required_if' => 'Teacher wajib dipilih untuk role Guru.',
            'teacher_id.exists'      => 'Teacher tidak ditemukan.',
        ];
    }
}
