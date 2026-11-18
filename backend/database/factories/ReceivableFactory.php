<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\ReceivableStatus;
use App\Models\Client;
use App\Models\Professional;
use App\Models\Receivable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receivable>
 *
 * Uso exclusivo em testes. Não há seeder de contas a receber inventadas.
 */
class ReceivableFactory extends Factory
{
    protected $model = Receivable::class;

    public function definition(): array
    {
        return [
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 10, 400),
            'received_at' => null,
            'status' => ReceivableStatus::Pending,
            'payment_method' => PaymentMethod::Cash,
            'client_id' => Client::query()->create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->numerify('9########'),
                'is_active' => true,
            ])->id,
            'professional_id' => Professional::factory(),
            'service_id' => null,
            'sale_id' => null,
            'created_by' => null,
            'received_by' => null,
        ];
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => ReceivableStatus::Received,
            'received_at' => now()->toDateString(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => ReceivableStatus::Pending,
            'received_at' => null,
            'created_at' => now()->subDays(Receivable::OVERDUE_AFTER_DAYS + 5),
            'updated_at' => now()->subDays(Receivable::OVERDUE_AFTER_DAYS + 5),
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn () => [
            'client_id' => $client->id,
        ]);
    }

    public function forProfessional(Professional $professional): static
    {
        return $this->state(fn () => [
            'professional_id' => $professional->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by' => $user->id,
        ]);
    }
}
