<?php

namespace App\Http\Requests;

class TransferRequest extends LinesRequest
{
    public function rules(): array
    {
        return $this->lineRules() + [
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['to_warehouse_id.different' => 'L\'emplacement d\'arrivée doit être différent de celui de départ.'];
    }
}
