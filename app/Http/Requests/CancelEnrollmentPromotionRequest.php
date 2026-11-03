<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelEnrollmentPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin'])
            && $this->route('promotion')->student_id === $this->route('student')->id;
    }

    public function rules(): array
    {
        return [];
    }

}
