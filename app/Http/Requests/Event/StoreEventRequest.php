<?php

namespace App\Http\Requests\Event;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in([Event::TYPE_MINI_CONCERT])],
            'event_date' => ['required', 'date'],
            'payment_due_date' => ['required', 'date', 'before_or_equal:event_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama event wajib diisi.',
            'type.in' => 'Fase ini hanya mendukung Mini Concert.',
            'event_date.required' => 'Tanggal event wajib diisi.',
            'payment_due_date.required' => 'Batas pembayaran wajib diisi.',
            'payment_due_date.before_or_equal' => 'Batas pembayaran tidak boleh melewati tanggal event.',
        ];
    }
}
