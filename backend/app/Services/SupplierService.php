<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Supplier
    {
        $data['is_active'] = $data['is_active'] ?? true;

        return Supplier::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        if ($supplier->products()->exists()) {
            throw ValidationException::withMessages([
                'supplier' => 'Este fornecedor possui produtos associados e não pode ser eliminado. Reatribua os produtos ou desative o fornecedor.',
            ]);
        }

        DB::transaction(fn () => $supplier->delete());
    }

    public function setActive(Supplier $supplier, bool $isActive): Supplier
    {
        $supplier->update(['is_active' => $isActive]);

        return $supplier->fresh();
    }
}
