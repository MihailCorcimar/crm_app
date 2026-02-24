<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => Entity::query()->inRandomOrder()->value('id')
                ?? Entity::factory()->create()->id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->optional()->lastName(),
            'role_id' => ContactRole::query()->inRandomOrder()->value('id')
                ?? ContactRole::query()->create(['name' => 'Sales'])->id,
            'phone' => $this->faker->optional()->phoneNumber(),
            'mobile' => $this->faker->optional()->phoneNumber(),
            'email' => $this->faker->optional()->safeEmail(),
            'gdpr_consent' => $this->faker->boolean(),
            'notes' => $this->faker->optional()->sentence(),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
