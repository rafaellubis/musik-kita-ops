<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendInvoiceReminderPanelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'preview_token' => ['required', 'uuid'],
            'message_body' => ['bail', 'required', 'string', 'max:5000', function ($attribute, $value, $fail) {
                if (preg_match('/\S/u', $value) !== 1) $fail('Pesan tidak boleh kosong.');
            }],
            'confirm_repeat' => ['sometimes', 'boolean'],
            'phone' => ['prohibited'], 'sent_by' => ['prohibited'], 'invoice_ids' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'preview_token.*' => 'Preview tidak valid. Buat ulang preview sebelum mengirim.',
            'message_body.required' => 'Pesan wajib diisi.',
            'message_body.string' => 'Pesan harus berupa teks.',
            'message_body.max' => 'Pesan maksimal 5.000 karakter.',
            'confirm_repeat.boolean' => 'Konfirmasi kirim ulang tidak valid.',
            'prohibited' => 'Tujuan, pengirim, dan pilihan invoice ditentukan dari preview server.',
        ];
    }
}
