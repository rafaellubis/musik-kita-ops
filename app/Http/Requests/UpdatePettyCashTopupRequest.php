<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi edit isi saldo petty cash (Owner only via route middleware).
 */
class UpdatePettyCashTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'        => ['required', 'integer', 'min:1'],
            'topup_date'    => ['required', 'date', 'before_or_equal:today'],
            'description'   => ['required', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'receipt_image' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'            => 'Nominal isi saldo wajib diisi.',
            'amount.min'                 => 'Nominal isi saldo minimal Rp 1.',
            'topup_date.required'        => 'Tanggal isi saldo wajib diisi.',
            'topup_date.before_or_equal' => 'Tanggal isi saldo tidak boleh di masa depan.',
            'description.required'       => 'Keterangan wajib diisi.',
            'receipt_image.image'        => 'File harus berupa gambar (JPG/PNG).',
            'receipt_image.max'          => 'Ukuran foto maksimal 2 MB.',
        ];
    }
}
