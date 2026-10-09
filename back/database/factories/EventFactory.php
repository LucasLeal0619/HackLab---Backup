<?php

namespace Database\Factories;

use App\Domain\Events\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 month', '+3 months');

        return [
            'name' => 'Hackathon '.fake()->unique()->numerify('####'),
            'description' => null,
            'location' => fake()->city(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+2 days')->format('Y-m-d'),
            'status' => EventStatus::Planned,
        ];
    }
}
