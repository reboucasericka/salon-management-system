<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncProfessionalServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'services' => ['sometimes', 'array'],
            'services.*.service_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'services.*.commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasIds = $this->exists('service_ids');
            $hasServices = $this->exists('services');

            if (! $hasIds && ! $hasServices) {
                $validator->errors()->add(
                    'service_ids',
                    'Informe service_ids (legado) ou services (com comissão).',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'service_ids.*.exists' => 'Um ou mais serviços são inválidos ou estão inativos.',
            'service_ids.*.distinct' => 'A lista de serviços não pode conter duplicados.',
            'services.*.service_id.exists' => 'Um ou mais serviços são inválidos ou estão inativos.',
            'services.*.service_id.distinct' => 'A lista de serviços não pode conter duplicados.',
            'services.*.commission_percentage.min' => 'A comissão deve ser no mínimo 0%.',
            'services.*.commission_percentage.max' => 'A comissão deve ser no máximo 100%.',
        ];
    }

    /**
     * @return list<int>|list<array{service_id:int, commission_percentage?:mixed}>
     */
    public function syncPayload(): array
    {
        if ($this->exists('services')) {
            return $this->validated('services') ?? [];
        }

        return $this->validated('service_ids') ?? [];
    }
}
