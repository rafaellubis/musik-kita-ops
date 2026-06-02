<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWhatsappMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Owner') ?? false;
    }

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string', 'max:50', 'unique:whatsapp_message_templates,code', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'name'       => ['required', 'string', 'max:100'],
            'body'       => ['required', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode template wajib diisi.',
            'code.unique'   => 'Kode sudah digunakan.',
            'code.regex'    => 'Kode hanya huruf besar, angka, dan underscore.',
            'name.required' => 'Nama template wajib diisi.',
            'body.required' => 'Isi pesan wajib diisi.',
        ];
    }
}
