<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsappMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Owner') ?? false;
    }

    public function rules(): array
    {
        $template = $this->route('whatsappMessageTemplate');

        $rules = [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('whatsapp_message_templates', 'code')->ignore($template?->id),
            ],
            'name'       => ['required', 'string', 'max:100'],
            'body'       => ['required', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];

        if ($template instanceof \App\Models\WhatsappMessageTemplate
            && $template->isSessionReportTemplate()) {
            foreach (['rating_5', 'rating_4', 'rating_3', 'rating_2', 'rating_1', 'default'] as $key) {
                $rules["encouragement_lines.{$key}"] = ['required', 'string', 'max:500'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode sudah digunakan template lain.',
            'code.regex'  => 'Kode hanya huruf besar, angka, dan underscore.',
            'body.required' => 'Isi pesan wajib diisi.',
            'encouragement_lines.*.required' => 'Pesan semangat wajib diisi.',
            'encouragement_lines.*.max'      => 'Pesan semangat maksimal 500 karakter.',
        ];
    }
}
