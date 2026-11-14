<?php

namespace App\Http\Requests;

class DirectReceiptRequest extends LinesRequest
{
    public function rules(): array
    {
        return $this->lineRules(withCost: true) + [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'delivery_note_number' => ['nullable', 'string', 'max:60'],
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
