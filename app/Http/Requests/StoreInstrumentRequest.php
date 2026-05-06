<?php
 
namespace App\Http\Requests;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreInstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Owner');
    }
 
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:instruments,code|regex:/^[A-Z_]+$/',
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'required|integer|min:0',
        ];
    }
 
    public function messages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode :input sudah dipakai instrumen lain.',
            'code.regex' => 'Kode hanya boleh huruf kapital dan underscore (mis. PIANO).',
            'name.required' => 'Nama instrumen wajib diisi.',
            'sort_order.required' => 'Urutan wajib diisi.',
        ];
    }
}
