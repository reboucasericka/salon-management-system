<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => strtolower(trim($this->input('email'))),
            ]);
        }

        if ($this->has('first_name') && is_string($this->input('first_name'))) {
            $this->merge([
                'first_name' => trim($this->input('first_name')),
            ]);
        }

        if ($this->has('last_name') && is_string($this->input('last_name'))) {
            $this->merge([
                'last_name' => trim($this->input('last_name')),
            ]);
        }

        if ($this->has('phone') && is_string($this->input('phone'))) {
            $phone = trim($this->input('phone'));
            $this->merge([
                'phone' => $phone === '' ? null : $phone,
            ]);
        }
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
