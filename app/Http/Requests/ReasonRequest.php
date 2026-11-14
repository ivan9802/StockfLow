<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Motif d'une annulation, d'un solde ou d'un renvoi. Le droit est vérifié par la route. */
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Indiquez un motif.', 'reason.min' => 'Le motif doit compter au moins 5 caractères.'];
    }
}
