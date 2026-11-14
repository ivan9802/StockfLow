<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{8,25}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Le téléphone ne doit contenir que des chiffres et des espaces, par exemple +225 07 08 09 10 11.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
