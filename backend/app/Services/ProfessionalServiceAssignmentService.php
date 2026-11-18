<?php

namespace App\Services;

use App\Models\Professional;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfessionalServiceAssignmentService
{
    public function listFor(Professional $professional): Collection
    {
        return $professional->services()
            ->with('serviceCategory')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int>|list<array{service_id:int, commission_percentage?:float|string|null}>  $services
     */
    public function sync(Professional $professional, array $services): Professional
    {
        $normalized = $this->normalizeSyncPayload($professional, $services);

        $uniqueIds = array_map('intval', array_keys($normalized));

        $activeIds = Service::query()
            ->whereIn('id', $uniqueIds)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $sortedUnique = $uniqueIds;
        sort($sortedUnique);
        $sortedActive = $activeIds;
        sort($sortedActive);

        if ($sortedUnique !== $sortedActive) {
            throw ValidationException::withMessages([
                'service_ids' => 'Um ou mais serviços são inválidos ou estão inativos.',
            ]);
        }

        DB::transaction(function () use ($professional, $normalized) {
            $professional->services()->sync($normalized);
        });

        return $professional->load([
            'services' => fn ($query) => $query->with('serviceCategory')->orderBy('name'),
        ]);
    }

    /**
     * Aceita payload legado `service_ids: [1,2]` ou novo
     * `services: [{service_id, commission_percentage}]`.
     *
     * No formato legado, preserva comissões já existentes no pivot.
     *
     * @param  list<int>|list<array{service_id:int, commission_percentage?:mixed}>  $services
     * @return array<int, array{commission_percentage: float|null}>
     */
    private function normalizeSyncPayload(Professional $professional, array $services): array
    {
        if ($services === []) {
            return [];
        }

        $first = $services[0] ?? null;
        $isLegacyIds = array_is_list($services) && (
            $services === []
            || is_int($first)
            || (is_string($first) && ctype_digit($first))
        );

        if ($isLegacyIds) {
            $ids = array_values(array_unique(array_map('intval', $services)));
            $existing = $professional->services()
                ->whereIn('services.id', $ids)
                ->get()
                ->mapWithKeys(fn (Service $service) => [
                    $service->id => $service->pivot?->commission_percentage,
                ]);

            $sync = [];
            foreach ($ids as $id) {
                $sync[$id] = [
                    'commission_percentage' => $existing->get($id),
                ];
            }

            return $sync;
        }

        $sync = [];
        foreach ($services as $item) {
            if (! is_array($item) || ! isset($item['service_id'])) {
                throw ValidationException::withMessages([
                    'services' => 'Cada item deve incluir service_id.',
                ]);
            }

            $serviceId = (int) $item['service_id'];
            $percentage = array_key_exists('commission_percentage', $item)
                ? $item['commission_percentage']
                : null;

            if ($percentage === '' || $percentage === false) {
                $percentage = null;
            }

            if ($percentage !== null) {
                $percentage = round((float) $percentage, 2);
            }

            $sync[$serviceId] = [
                'commission_percentage' => $percentage,
            ];
        }

        return $sync;
    }

    public function ensureOffersService(Professional $professional, int $serviceId): void
    {
        $offers = $professional->services()
            ->where('services.id', $serviceId)
            ->where('services.is_active', true)
            ->exists();

        if (! $offers) {
            throw ValidationException::withMessages([
                'professional_id' => 'O profissional selecionado não executa este serviço.',
            ]);
        }
    }

    public function activeProfessionalsForService(Service $service): Collection
    {
        if (! $service->is_active) {
            return collect();
        }

        return $service->professionals()
            ->where('professionals.is_active', true)
            ->orderBy('professionals.name')
            ->get();
    }
}
