<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Student;
use App\Services\FonnteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendInvoiceReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Owner', 'Admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_ids'   => ['required', 'array', 'min:1', 'max:30'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'template_id'   => ['nullable', 'integer', 'exists:whatsapp_message_templates,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'Pilih minimal satu murid.',
            'student_ids.min'      => 'Pilih minimal satu murid.',
            'student_ids.max'      => 'Maksimal 30 murid per pengiriman.',
            'student_ids.*.exists' => 'Murid tidak valid.',
            'template_id.exists'   => 'Template pesan tidak ditemukan.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $fonnte = app(FonnteService::class);
            $ids = $this->input('student_ids', []);

            foreach ($ids as $studentId) {
                $student = Student::find($studentId);
                if (! $student) {
                    continue;
                }

                // Valid jika no ortu valid, ATAU jika ortu tidak valid tapi no murid valid
                $hasValidParent = $fonnte->isValidPhone($student->parent_phone);
                $hasValidStudent = $fonnte->isValidPhone($student->phone);

                if (! $hasValidParent && ! $hasValidStudent) {
                    $v->errors()->add(
                        'student_ids',
                        "Murid {$student->full_name} tidak punya nomor HP ortu maupun nomor HP murid sendiri yang valid untuk WA.",
                    );
                    continue;
                }

                $hasUnpaid = Invoice::query()
                    ->unpaid()
                    ->where('student_id', $studentId)
                    ->exists();

                if (! $hasUnpaid) {
                    $v->errors()->add(
                        'student_ids',
                        "Murid {$student->full_name} tidak memiliki tagihan belum lunas.",
                    );
                }
            }
        });
    }
}

