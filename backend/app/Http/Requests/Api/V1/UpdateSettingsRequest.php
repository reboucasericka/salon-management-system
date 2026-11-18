<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $validTimezones = timezone_identifiers_list();

        return [
            'salon_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64', Rule::in($validTimezones)],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:100'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reminder_24h_enabled' => ['sometimes', 'boolean'],
            'reminder_2h_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'timezone.in' => 'Indique uma timezone IANA válida (ex.: Europe/Lisbon).',
            'currency.size' => 'A moeda deve ter 3 letras (ex.: EUR).',
            'currency.regex' => 'A moeda deve estar em maiúsculas (ex.: EUR).',
            'contact_email.email' => 'Indique um email de contacto válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge([
                'currency' => strtoupper(trim($this->input('currency'))),
            ]);
        }

        if ($this->has('timezone') && $this->input('timezone') === '') {
            $this->merge(['timezone' => null]);
        }
    }
}
