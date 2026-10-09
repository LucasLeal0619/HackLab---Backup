<?php

namespace Database\Factories;

use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Occurrences\Enums\OccurrenceCategory;
use App\Domain\Occurrences\Enums\OccurrenceStatus;
use App\Models\Event;
use App\Models\Occurrence;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Occurrence>
 *
 * Padrão: origem = responsável = um setor novo do evento. Use between() para escolher os setores.
 */
class OccurrenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'title' => 'Ocorrência '.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(),
            'origin_sector_id' => fn (array $a) => Sector::factory()->create(['event_id' => $a['event_id']])->id,
            'responsible_sector_id' => fn (array $a) => $a['origin_sector_id'],
            'assigned_user_id' => null,
            'priority' => DemandPriority::Medium,
            'status' => OccurrenceStatus::Open,
            'created_by_user_id' => User::factory(),
            'category' => OccurrenceCategory::Other,
        ];
    }

    /**
     * A referência (PEN/OCO) é gerada pelo default do banco: recarrega para tê-la no model.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Occurrence $model) => $model->refresh());
    }

    /**
     * Origem e responsável informados (do mesmo evento).
     */
    public function between(Sector $origin, ?Sector $responsible = null): static
    {
        return $this->state(fn () => [
            'event_id' => $origin->event_id,
            'origin_sector_id' => $origin->id,
            'responsible_sector_id' => ($responsible ?? $origin)->id,
        ]);
    }
}
