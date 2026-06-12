<?php

namespace App\Http\Requests;

use App\Models\PettyCashExpense;
use App\Services\PettyCashService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi edit pengeluaran petty cash + cek saldo tersedia.
 */
class UpdatePettyCashExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount'              => ['required', 'integer', 'min:1'],
            'description'         => ['required', 'string', 'max:255'],
            'expense_date'        => ['required', 'date', 'before_or_equal:today'],
            'notes'               => ['nullable', 'string', 'max:1000'],
            'receipt_image'       => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Kategori pengeluaran wajib dipilih.',
            'amount.required'              => 'Nominal pengeluaran wajib diisi.',
            'amount.min'                   => 'Nominal pengeluaran minimal Rp 1.',
            'description.required'         => 'Keterangan wajib diisi.',
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

            /** @var PettyCashExpense $expense */
            $expense   = $this->route('expense');
            $newAmount = (int) $this->input('amount', 0);
            $available = app(PettyCashService::class)->getCurrentBalance() + $expense->amount;

            if ($newAmount > $available) {
                $validator->errors()->add(
                    'amount',
                    'Saldo petty cash tidak cukup. Tersedia: Rp ' . number_format($available, 0, ',', '.')
                );
            }
        });
    }
}
