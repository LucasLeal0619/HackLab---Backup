<?php

namespace Database\Factories;

use App\Domain\People\Enums\PersonStatus;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'document' => null,
            'status' => PersonStatus::Active,
        ];
    }
}
