<?php

namespace Database\Factories;

use App\Domain\Teams\Enums\TeamStatus;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Equipe '.fake()->unique()->numerify('###'),
            'code' => null,
            'status' => TeamStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => TeamStatus::Inactive]);
    }
}
