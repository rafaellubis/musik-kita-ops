<?php

namespace App\Http\Requests;

use App\Services\WhatsAppService;
use Illuminate\Foundation\Http\FormRequest;

class SendWhatsAppTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Owner') ?? false;
    }

    public function rules(): array
    {
        return ['phone' => ['bail', 'required', 'string', 'max:30', function ($attribute, $value, $fail) {
            if (! app(WhatsAppService::class)->isValidPhone($value)) {
                $fail('Nomor WhatsApp tujuan uji tidak valid.');
            }
        }]];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Isi nomor WhatsApp tujuan uji.',
            'phone.string' => 'Nomor WhatsApp harus berupa teks.',
            'phone.max' => 'Nomor WhatsApp maksimal 30 karakter.',
        ];
    }
}
