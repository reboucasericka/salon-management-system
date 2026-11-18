<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'sale_id' => ['nullable', 'integer', 'exists:sales,id'],
        ];
    }
}
