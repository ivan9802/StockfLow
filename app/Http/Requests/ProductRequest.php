<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('catalog.manage');
    }

    public function rules(): array
    {
        $image = config('stockflow.product_image');
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            // Laissé vide, le code-barres est généré (EAN-13 interne).
            'barcode' => ['nullable', 'string', 'regex:/^[0-9]{8,13}$/', Rule::unique('products', 'barcode')->ignore($product?->id)],
            'purchase_price' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'selling_price' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'alert_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
            'image' => [
                'nullable', 'file', 'image', 'mimes:'.implode(',', $image['mimes']), 'max:'.$image['max_kilobytes'],
                // Le contenu doit se décoder comme une image : un fichier renommé en .png ne passe pas.
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value instanceof UploadedFile && @getimagesize($value->getRealPath()) === false) {
                        $fail('Le fichier envoyé n\'est pas une image lisible.');
                    }
                },
            ],
            'remove_image' => ['nullable', 'boolean'],
            'reorder_points' => ['nullable', 'array'],
            'reorder_points.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'barcode.regex' => 'Le code-barres doit contenir de 8 à 13 chiffres.',
            'image.max' => 'L\'image ne doit pas dépasser 2 Mo.',
            'image.mimes' => 'L\'image doit être au format JPEG, PNG ou WebP.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            // Les montants saisis avec des espaces (« 12 500 ») sont acceptés.
            'purchase_price' => $this->digits('purchase_price'),
            'selling_price' => $this->digits('selling_price'),
            // Seuls les emplacements connus sont retenus.
            'reorder_points' => collect($this->input('reorder_points', []))
                ->only(Warehouse::pluck('id')->all())->all(),
        ]);
    }

    private function digits(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $value) : $value;
    }
}
