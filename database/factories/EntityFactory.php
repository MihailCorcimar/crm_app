<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    protected $model = Entity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['customer', 'supplier', 'both']),
            'tax_id' => $this->faker->unique()->numerify('#########'),
            'name' => $this->faker->company(),
            'address' => $this->faker->optional()->streetAddress(),
            'postal_code' => $this->faker->optional()->postcode(),
            'city' => $this->faker->optional()->city(),
            'country_id' => Country::query()->inRandomOrder()->value('id')
                ?? Country::query()->create(['code' => 'PT', 'name' => 'Portugal'])->id,
            'phone' => $this->faker->optional()->phoneNumber(),
            'mobile' => $this->faker->optional()->phoneNumber(),
            'website' => $this->faker->optional()->url(),
            'email' => $this->faker->optional()->safeEmail(),
            'gdpr_consent' => $this->faker->boolean(),
            'notes' => $this->faker->optional()->sentence(),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
