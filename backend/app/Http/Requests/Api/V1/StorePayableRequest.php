<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PayableType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayableRequest extends FormRequest
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
            'due_date' => ['required', 'date'],
            'type' => ['required', Rule::enum(PayableType::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
        ];
    }
}
