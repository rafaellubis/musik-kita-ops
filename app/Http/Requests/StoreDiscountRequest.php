<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi role sudah dijaga oleh middleware route (role:Owner|Admin)
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_type'   => ['required', 'in:NOMINAL,PERCENT'],
            'discount_value'  => ['required', 'integer', 'min:1'],
            'discount_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_type.required'   => 'Tipe diskon wajib dipilih.',
            'discount_type.in'         => 'Tipe diskon harus NOMINAL atau PERCENT.',
            'discount_value.required'  => 'Nilai diskon wajib diisi.',
            'discount_value.integer'   => 'Nilai diskon harus berupa angka bulat.',
            'discount_value.min'       => 'Nilai diskon minimal 1.',
            'discount_reason.required' => 'Alasan diskon wajib diisi.',
            'discount_reason.min'      => 'Alasan diskon minimal 3 karakter.',
            'discount_reason.max'      => 'Alasan diskon maksimal 500 karakter.',
        ];
    }
}
