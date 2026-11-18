<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'string', 'max:200'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'payment_method' => ['sometimes', Rule::enum(PaymentMethod::class)],
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'professional_id' => ['sometimes', 'integer', 'exists:professionals,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],
        ];
    }
}
