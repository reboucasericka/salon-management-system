<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Indique a palavra-passe atual.',
            'password.required' => 'Indique a nova palavra-passe.',
            'password.min' => 'A nova palavra-passe deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da nova palavra-passe não coincide.',
        ];
    }
}
