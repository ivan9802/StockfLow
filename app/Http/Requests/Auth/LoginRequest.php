<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }

    /** Clé du limiteur de tentatives : l'adresse essayée et l'adresse IP du poste. */
    public function throttleKey(): string
    {
        return 'login|'.Str::transliterate(Str::lower($this->string('email'))).'|'.$this->ip();
    }
}
