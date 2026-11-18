<?php

namespace Database\Factories;

use App\Enums\PayableStatus;
use App\Enums\PayableType;
use App\Models\Payable;
use App\Models\Professional;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payable>
 *
 * Uso exclusivo em testes. Não há seeder de contas a pagar inventadas.
 */
class PayableFactory extends Factory
{
    protected $model = Payable::class;

    public function definition(): array
    {
        return [
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 5, 500),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'paid_at' => null,
            'status' => PayableStatus::Pending,
            'type' => PayableType::Expense,
            'notes' => fake()->optional()->sentence(),
            'supplier_id' => null,
            'professional_id' => null,
            'created_by' => null,
            'paid_by' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PayableStatus::Paid,
            'paid_at' => now()->toDateString(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => PayableStatus::Pending,
            'due_date' => now()->subDays(3)->toDateString(),
            'paid_at' => null,
        ]);
    }

    public function forSupplier(?Supplier $supplier = null): static
    {
        return $this->state(fn () => [
            'type' => PayableType::Supplier,
            'supplier_id' => $supplier?->id ?? Supplier::factory(),
            'professional_id' => null,
        ]);
    }

    public function forCommission(?Professional $professional = null): static
    {
        return $this->state(fn () => [
            'type' => PayableType::Commission,
            'professional_id' => $professional?->id ?? Professional::factory(),
            'supplier_id' => null,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by' => $user->id,
        ]);
    }
}
