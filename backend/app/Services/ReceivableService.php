<?php

namespace App\Services;

use App\Enums\ReceivableStatus;
use App\Models\Receivable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivableService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Receivable
    {
        unset($data['created_by'], $data['received_by'], $data['status'], $data['received_at']);

        $data['status'] = ReceivableStatus::Pending->value;
        $data['received_at'] = null;
        $data['received_by'] = null;

        if ($actor !== null) {
            $data['created_by'] = $actor->id;
        }

        return Receivable::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Receivable $receivable, array $data): Receivable
    {
        unset($data['created_by'], $data['received_by'], $data['status'], $data['received_at']);

        $receivable->update($data);

        return $receivable->fresh(['client', 'professional', 'service', 'sale', 'creator', 'receiver']);
    }

    public function markAsReceived(Receivable $receivable, ?User $actor = null, ?string $receivedAt = null): Receivable
    {
        if ($receivable->isReceived()) {
            throw ValidationException::withMessages([
                'receivable' => 'Esta conta a receber já está marcada como recebida.',
            ]);
        }

        $receivable->update([
            'status' => ReceivableStatus::Received,
            'received_at' => $receivedAt ?? now()->toDateString(),
            'received_by' => $actor?->id,
        ]);

        return $receivable->fresh(['client', 'professional', 'service', 'sale', 'creator', 'receiver']);
    }

    public function delete(Receivable $receivable): void
    {
        // Legado permite eliminar pendentes e recebidas. Mantemos a mesma regra.
        DB::transaction(fn () => $receivable->delete());
    }
}
