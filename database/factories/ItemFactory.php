<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('ITM-####')),
            'description' => $this->faker->sentence(3),
            'unit' => 'un',
            'price' => $this->faker->randomFloat(2, 0, 10000),
            'vat' => 23.00,
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
