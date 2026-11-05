<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewInvoiceReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_ids' => ['sometimes', 'required', 'array', 'min:1', 'prohibits:resend_log_id'],
            'invoice_ids.*' => ['required', 'integer', 'distinct'],
            'resend_log_id' => ['sometimes', 'required', 'integer', 'min:1', 'prohibits:invoice_ids'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_ids.*' => 'Pilih daftar invoice yang valid dan tidak kosong.',
            'invoice_ids.*.*' => 'Setiap invoice harus berupa ID yang unik dan valid.',
            'resend_log_id.*' => 'Pilih riwayat kirim ulang yang valid tanpa pilihan invoice tambahan.',
        ];
    }
}
