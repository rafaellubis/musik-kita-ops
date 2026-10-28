<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckWhatsAppConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Owner') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
