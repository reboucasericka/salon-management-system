<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 *
 * Uso exclusivo em testes. Não há seeder de fornecedores inventados.
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'phone' => fake()->numerify('+351 9## ### ###'),
            'email' => fake()->unique()->companyEmail(),
            'tax_id' => fake()->numerify('#########'),
            'address' => fake()->optional()->streetAddress(),
            'delivery_time_days' => fake()->optional()->numberBetween(1, 30),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
