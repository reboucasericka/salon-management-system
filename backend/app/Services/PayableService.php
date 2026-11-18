<?php

namespace App\Services;

use App\Enums\PayableStatus;
use App\Enums\PayableType;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayableService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Payable
    {
        unset($data['created_by'], $data['paid_by'], $data['status'], $data['paid_at']);

        $data['status'] = PayableStatus::Pending->value;
        $data['paid_at'] = null;
        $data['paid_by'] = null;
        $data['type'] = $data['type'] ?? PayableType::Expense->value;

        if ($actor !== null) {
            $data['created_by'] = $actor->id;
        }

        return Payable::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Payable $payable, array $data): Payable
    {
        unset($data['created_by'], $data['paid_by'], $data['status'], $data['paid_at']);

        $payable->update($data);

        return $payable->fresh(['supplier', 'professional', 'creator', 'payer']);
    }

    public function markAsPaid(Payable $payable, ?User $actor = null, ?string $paidAt = null): Payable
    {
        if ($payable->isPaid()) {
            throw ValidationException::withMessages([
                'payable' => 'Esta conta a pagar já está marcada como paga.',
            ]);
        }

        $payable->update([
            'status' => PayableStatus::Paid,
            'paid_at' => $paidAt ?? now()->toDateString(),
            'paid_by' => $actor?->id,
        ]);

        return $payable->fresh(['supplier', 'professional', 'creator', 'payer']);
    }

    public function delete(Payable $payable): void
    {
        // Legado permite eliminar pendentes e pagas. Mantemos a mesma regra.
        DB::transaction(fn () => $payable->delete());
    }
}
