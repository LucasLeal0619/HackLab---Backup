<?php

namespace Database\Factories;

use App\Domain\Participants\Enums\ParticipantStatus;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Person;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'person_id' => Person::factory(),
            'class_id' => null,
            'status' => ParticipantStatus::Available,
            'notes' => null,
        ];
    }

    /**
     * Participante na turma informada (mesmo evento da turma).
     */
    public function inClass(SchoolClass $class): static
    {
        return $this->state(fn () => ['event_id' => $class->event_id, 'class_id' => $class->id]);
    }

    public function status(ParticipantStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
