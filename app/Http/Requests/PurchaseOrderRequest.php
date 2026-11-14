<?php

namespace App\Http\Requests;

class PurchaseOrderRequest extends LinesRequest
{
    public function rules(): array
    {
        return $this->lineRules(withCost: true) + [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function orderData(): array
    {
        return $this->safe()->only(['supplier_id', 'warehouse_id', 'expected_at', 'notes']) + ['lines' => $this->lines()];
    }
}
