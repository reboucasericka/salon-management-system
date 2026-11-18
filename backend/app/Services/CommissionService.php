<?php

namespace App\Services;

use App\Enums\PayableStatus;
use App\Enums\PayableType;
use App\Models\Appointment;
use App\Models\Payable;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras de comissão (auditoria legado 2026-08-11):
 *
 * Legado real:
 * - DefaultCommission no Professional = campo usado em UI e no cálculo de ganhos
 *   (PublicProfessionalProfile: Service.Price * DefaultCommission/100).
 * - CommissionPercentage no Professional = campo morto (nunca gravado nos forms).
 * - ProfessionalService.Commission = exibido no Index, mas Edit NÃO gravava valor
 *   (estrutura incompleta).
 * - Service.Commission = campo sem uso em cálculo.
 * - Payable type Commission = criação MANUAL; relatório lia Payables.
 * - Sem geração automática ao concluir Sale/Appointment.
 *
 * Laravel (migração):
 * - professionals.commission_percentage ≡ DefaultCommission
 * - professional_service.commission_percentage ≡ melhoria (pivot usável)
 * - Precedência: pivot (incl. 0) → professional → 0; null no pivot = fallback
 * - Base: coalesce(appointment.total_price, service.price)
 * - Payable automático: NÃO
 * - Geração MANUAL via POST /commissions/generate com idempotência por appointment_id
 */
class CommissionService
{
    public function __construct(private readonly PayableService $payableService) {}

    /**
     * Percentagem efetiva, ou null se o profissional não oferece o serviço.
     */
    public function resolveCommissionPercentage(Professional $professional, Service $service): ?string
    {
        $pivot = $professional->services()
            ->where('services.id', $service->id)
            ->first()?->pivot;

        if ($pivot === null) {
            return null;
        }

        if ($pivot->commission_percentage !== null) {
            return number_format((float) $pivot->commission_percentage, 2, '.', '');
        }

        return number_format((float) ($professional->commission_percentage ?? 0), 2, '.', '');
    }

    public function calculateCommissionAmount(float|string $baseAmount, float|string $percentage): string
    {
        $base = round((float) $baseAmount, 2);
        $pct = round((float) $percentage, 2);

        if ($base < 0) {
            throw ValidationException::withMessages([
                'base_amount' => 'A base de cálculo não pode ser negativa.',
            ]);
        }

        if ($pct < 0 || $pct > 100) {
            throw ValidationException::withMessages([
                'commission_percentage' => 'A percentagem de comissão deve estar entre 0 e 100.',
            ]);
        }

        return number_format(round($base * ($pct / 100), 2), 2, '.', '');
    }

    /**
     * @return array{
     *   applicable: bool,
     *   base_amount: string|null,
     *   percentage: string|null,
     *   commission_amount: string|null,
     *   source: string|null
     * }|null
     */
    public function calculateForAppointment(Appointment $appointment): ?array
    {
        if ($appointment->status === Appointment::STATUS_CANCELED) {
            return null;
        }

        $appointment->loadMissing(['professional', 'service']);

        if (! $appointment->professional || ! $appointment->service) {
            return null;
        }

        $percentage = $this->resolveCommissionPercentage(
            $appointment->professional,
            $appointment->service,
        );

        if ($percentage === null) {
            return [
                'applicable' => false,
                'base_amount' => null,
                'percentage' => null,
                'commission_amount' => null,
                'source' => 'not_associated',
            ];
        }

        $base = $appointment->total_price !== null
            ? (float) $appointment->total_price
            : (float) $appointment->service->price;

        $pivotValue = $appointment->professional->services()
            ->where('services.id', $appointment->service->id)
            ->first()?->pivot?->commission_percentage;

        $source = $pivotValue !== null ? 'professional_service' : 'professional';

        return [
            'applicable' => true,
            'base_amount' => number_format($base, 2, '.', ''),
            'percentage' => $percentage,
            'commission_amount' => $this->calculateCommissionAmount($base, $percentage),
            'source' => $source,
        ];
    }

    /**
     * Pré-visualização sob demanda a partir de appointments concluídos.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function previewFromCompletedAppointments(
        ?string $from = null,
        ?string $to = null,
        ?int $professionalId = null,
        ?string $financialStatus = null,
    ): Collection {
        $query = Appointment::query()
            ->with(['professional', 'service'])
            ->where('status', Appointment::STATUS_COMPLETED)
            ->when($professionalId, fn ($q) => $q->where('professional_id', $professionalId))
            ->when($from, fn ($q) => $q->whereDate('start_time', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('start_time', '<=', $to))
            ->orderByDesc('start_time');

        $rows = $query->get()
            ->map(function (Appointment $appointment) {
                $calc = $this->calculateForAppointment($appointment);

                if ($calc === null || ! ($calc['applicable'] ?? false)) {
                    return null;
                }

                return [
                    'origin' => 'appointment',
                    'origin_id' => $appointment->id,
                    'date' => $appointment->start_time?->toIso8601String(),
                    'professional_id' => $appointment->professional_id,
                    'professional' => $appointment->professional ? [
                        'id' => $appointment->professional->id,
                        'name' => $appointment->professional->name,
                    ] : null,
                    'service_id' => $appointment->service_id,
                    'service' => $appointment->service ? [
                        'id' => $appointment->service->id,
                        'name' => $appointment->service->name,
                    ] : null,
                    'base_amount' => $calc['base_amount'],
                    'percentage' => $calc['percentage'],
                    'commission_amount' => $calc['commission_amount'],
                    'source' => $calc['source'],
                    'financial_status' => 'calculated',
                    'payable_id' => null,
                ];
            })
            ->filter()
            ->values();

        $appointmentIds = $rows->pluck('origin_id')->all();

        if ($appointmentIds === []) {
            return $rows;
        }

        $payables = Payable::query()
            ->where('type', PayableType::Commission)
            ->whereIn('appointment_id', $appointmentIds)
            ->get()
            ->keyBy('appointment_id');

        $rows = $rows->map(function (array $row) use ($payables) {
            /** @var Payable|null $payable */
            $payable = $payables->get($row['origin_id']);

            if ($payable === null) {
                $row['financial_status'] = 'calculated';
                $row['payable_id'] = null;

                return $row;
            }

            $row['payable_id'] = $payable->id;
            $row['financial_status'] = $payable->isPaid() ? 'paid' : 'payable_pending';

            return $row;
        });

        if ($financialStatus !== null && $financialStatus !== '') {
            $rows = $rows->filter(fn (array $row) => $row['financial_status'] === $financialStatus)->values();
        }

        return $rows;
    }

    /**
     * Geração MANUAL de Payables (alinhado ao legado: sem automação ao concluir).
     * Snapshot: amount do Payable; percentagem/base ficam em notes.
     *
     * @param  list<int>|null  $appointmentIds
     * @return array{created: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     */
    public function generatePayables(
        ?User $actor,
        ?array $appointmentIds = null,
        ?string $from = null,
        ?string $to = null,
        ?int $professionalId = null,
    ): array {
        $created = [];
        $skipped = [];

        if ($appointmentIds !== null) {
            $requested = Appointment::query()
                ->whereIn('id', $appointmentIds)
                ->get(['id', 'status']);

            foreach ($requested as $appointment) {
                if ($appointment->status !== Appointment::STATUS_COMPLETED) {
                    $skipped[] = [
                        'appointment_id' => $appointment->id,
                        'reason' => $appointment->status === Appointment::STATUS_CANCELED
                            ? 'canceled'
                            : 'not_completed',
                    ];
                }
            }
        }

        $query = Appointment::query()
            ->with(['professional', 'service'])
            ->where('status', Appointment::STATUS_COMPLETED)
            ->when($appointmentIds !== null, fn ($q) => $q->whereIn('id', $appointmentIds))
            ->when($professionalId, fn ($q) => $q->where('professional_id', $professionalId))
            ->when($from, fn ($q) => $q->whereDate('start_time', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('start_time', '<=', $to));

        DB::transaction(function () use ($query, $actor, &$created, &$skipped) {
            foreach ($query->lockForUpdate()->get() as $appointment) {
                $calc = $this->calculateForAppointment($appointment);

                if ($calc === null || ! ($calc['applicable'] ?? false)) {
                    $skipped[] = [
                        'appointment_id' => $appointment->id,
                        'reason' => 'not_applicable',
                    ];

                    continue;
                }

                if ((float) $calc['commission_amount'] <= 0) {
                    $skipped[] = [
                        'appointment_id' => $appointment->id,
                        'reason' => 'zero_amount',
                    ];

                    continue;
                }

                $existing = Payable::query()
                    ->where('type', PayableType::Commission)
                    ->where('appointment_id', $appointment->id)
                    ->first();

                if ($existing !== null) {
                    $skipped[] = [
                        'appointment_id' => $appointment->id,
                        'reason' => 'already_exists',
                        'payable_id' => $existing->id,
                    ];

                    continue;
                }

                $serviceName = $appointment->service?->name ?? 'Serviço';
                $description = mb_substr(
                    "Comissão · {$serviceName} · Agendamento #{$appointment->id}",
                    0,
                    200,
                );

                $payable = $this->payableService->create([
                    'description' => $description,
                    'amount' => $calc['commission_amount'],
                    'due_date' => now()->toDateString(),
                    'type' => PayableType::Commission->value,
                    'professional_id' => $appointment->professional_id,
                    'appointment_id' => $appointment->id,
                    'notes' => sprintf(
                        'Snapshot: base=%s; percentagem=%s%%; origem=%s',
                        $calc['base_amount'],
                        $calc['percentage'],
                        $calc['source'],
                    ),
                ], $actor);

                $created[] = [
                    'appointment_id' => $appointment->id,
                    'payable_id' => $payable->id,
                    'amount' => $payable->amount,
                ];
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
}
