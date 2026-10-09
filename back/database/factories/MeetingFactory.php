<?php

namespace Database\Factories;

use App\Domain\Meetings\Enums\MeetingStatus;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 *
 * Padrão: reunião geral. Use forSector() para reunião setorial (mesmo evento do setor).
 */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'sector_id' => null,
            'title' => 'Reunião '.fake()->words(2, true),
            'description' => null,
            'scheduled_at' => now()->addDays(3),
            'location' => null,
            'status' => MeetingStatus::Scheduled,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function forSector(Sector $sector): static
    {
        return $this->state(fn () => ['event_id' => $sector->event_id, 'sector_id' => $sector->id]);
    }
}
