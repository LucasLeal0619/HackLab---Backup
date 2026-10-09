<?php

namespace Database\Factories;

use App\Domain\Jurors\Enums\JurorStatus;
use App\Models\Event;
use App\Models\Juror;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Juror>
 *
 * Só o papel de jurado: não cria User nem atribuições.
 */
class JurorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'person_id' => Person::factory(),
            'company_id' => null,
            'status' => JurorStatus::Active,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => JurorStatus::Inactive]);
    }
}
