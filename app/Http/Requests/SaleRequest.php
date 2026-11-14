<?php

namespace App\Http\Requests;

class SaleRequest extends LinesRequest
{
    public function rules(): array
    {
        return $this->lineRules() + [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            // Jeton à usage unique du formulaire : un double envoi ne crée pas deux ventes.
            'form_token' => ['required', 'string', 'size:40'],
        ];
    }
}
