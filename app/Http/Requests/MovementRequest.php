<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sortie interne ou ajustement manuel, avec motif obligatoire. */
class MovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('movements.manage');
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['internal_use', 'adjustment_in', 'adjustment_out'])],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['kind' => 'type de mouvement'];
    }

    public function messages(): array
    {
        return ['product_id.required' => 'Choisissez un produit.'];
    }
}
