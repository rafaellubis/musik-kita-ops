<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class ReopenEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Owner');
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Alasan membuka kembali event wajib diisi.'];
    }
}
