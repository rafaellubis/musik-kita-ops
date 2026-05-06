<?php
 
namespace App\Http\Requests;
 
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
 
class UpdateInstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Owner');
    }
 
    public function rules(): array
    {
        $instrumentId = $this->route('instrument');
 
        return [
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z_]+$/',
                Rule::unique('instruments', 'code')->ignore($instrumentId),
            ],
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'required|integer|min:0',
        ];
    }
}
