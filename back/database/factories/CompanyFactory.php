<?php

namespace Database\Factories;

use App\Domain\Companies\Enums\CompanyStatus;
use App\Domain\Companies\Enums\CompanyType;
use App\Models\Company;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Empresa '.fake()->unique()->numerify('####'),
            'legal_name' => null,
            'document' => null,
            'segment' => null,
            'type' => CompanyType::Participant,
            'description' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'status' => CompanyStatus::Confirmed,
        ];
    }

    public function status(CompanyStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
