<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi isi saldo petty cash (Owner only via route middleware).
 */
class StorePettyCashTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC Owner ditangani middleware route
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'      => ['required', 'integer', 'min:1'],
            'topup_date'  => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'           => 'Nominal isi saldo wajib diisi.',
            'amount.integer'            => 'Nominal isi saldo harus berupa angka bulat.',
            'amount.min'                => 'Nominal isi saldo minimal Rp 1.',
            'topup_date.required'       => 'Tanggal isi saldo wajib diisi.',
            'topup_date.date'           => 'Format tanggal isi saldo tidak valid.',
            'topup_date.before_or_equal' => 'Tanggal isi saldo tidak boleh di masa depan.',
            'description.required'      => 'Keterangan wajib diisi.',
            'description.string'        => 'Keterangan harus berupa teks.',
            'description.max'           => 'Keterangan maksimal 255 karakter.',
        ];
    }
}
