<?php

namespace App\Http\Requests;

use App\Services\PettyCashService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi pengeluaran petty cash + cek saldo tersedia.
 */
class StorePettyCashExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC Owner|Admin ditangani middleware route
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount'              => ['required', 'integer', 'min:1'],
            'description'         => ['required', 'string', 'max:255'],
            'expense_date'        => ['required', 'date', 'before_or_equal:today'],
            'notes'               => ['nullable', 'string'],
            'receipt_image'       => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Kategori pengeluaran wajib dipilih.',
            'expense_category_id.exists'   => 'Kategori pengeluaran tidak valid.',
            'amount.required'              => 'Nominal pengeluaran wajib diisi.',
            'amount.integer'               => 'Nominal pengeluaran harus berupa angka bulat.',
            'amount.min'                   => 'Nominal pengeluaran minimal Rp 1.',
            'description.required'         => 'Keterangan wajib diisi.',
            'description.string'           => 'Keterangan harus berupa teks.',
            'description.max'              => 'Keterangan maksimal 255 karakter.',
            'expense_date.required'        => 'Tanggal pengeluaran wajib diisi.',
            'expense_date.date'            => 'Format tanggal pengeluaran tidak valid.',
            'expense_date.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'receipt_image.image'          => 'Bukti pembayaran harus berupa gambar.',
            'receipt_image.max'            => 'Bukti pembayaran maksimal 2 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $amount = (int) $this->input('amount', 0);
            $saldo  = app(PettyCashService::class)->getCurrentBalance();

            if ($amount > $saldo) {
                $validator->errors()->add(
                    'amount',
                    'Saldo petty cash tidak cukup. Tersedia: Rp ' . number_format($saldo, 0, ',', '.')
                );
            }
        });
    }
}
