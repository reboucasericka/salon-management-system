<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'status' => ['nullable', 'string', 'max:32'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'financial_status' => ['nullable', 'string', Rule::in(['calculated', 'payable_pending', 'paid'])],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'format' => ['nullable', 'string', Rule::in(['json', 'csv', 'pdf'])],
        ];
    }
}
