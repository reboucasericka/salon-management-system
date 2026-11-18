<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Indique o email.',
            'email.email' => 'Indique um email válido.',
            'token.required' => 'O token de redefinição é obrigatório.',
            'password.required' => 'Indique a nova palavra-passe.',
            'password.min' => 'A nova palavra-passe deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da nova palavra-passe não coincide.',
        ];
    }
}
