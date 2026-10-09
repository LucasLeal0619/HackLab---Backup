<?php

namespace Database\Factories;

use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Models\Challenge;
use App\Models\Company;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 *
 * Padrão: desafio institucional (sem empresa) em DRAFT. Use forCompany() para desafio de empresa.
 */
class ChallengeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'company_id' => null,
            'title' => 'Desafio '.fake()->unique()->numerify('####'),
            'problem' => fake()->sentence(),
            'status' => ChallengeStatus::Draft,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['event_id' => $company->event_id, 'company_id' => $company->id]);
    }

    public function status(ChallengeStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
