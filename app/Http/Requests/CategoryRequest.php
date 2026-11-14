<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category?->id)],
            // Demandé à la création seulement : c'est le préfixe des références des produits.
            'code' => [$category ? 'nullable' : 'required', 'string', 'regex:/^[A-Z]{3,4}$/', Rule::unique('categories', 'code')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Le code doit compter 3 ou 4 lettres, sans chiffre ni accent.'];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
        }
    }
}
