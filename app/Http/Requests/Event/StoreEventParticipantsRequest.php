<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'enrollment_ids' => ['nullable', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'distinct', 'exists:enrollments,id'],
            'reason' => [Rule::requiredIf(fn () => $this->route('event')?->isPublished()), 'nullable', 'string', 'max:1000'],
        ];
    }
}
