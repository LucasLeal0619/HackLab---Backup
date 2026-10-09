<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventDay>
 */
class EventDayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'day_number' => 1,
            'date' => fn (array $attributes) => Event::query()->find($attributes['event_id'])?->start_date?->toDateString() ?? now()->toDateString(),
            'label' => 'Dia 1',
            'start_time' => null,
            'end_time' => null,
        ];
    }
}
