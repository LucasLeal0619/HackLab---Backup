<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyRepresentative;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyRepresentative>
 */
class CompanyRepresentativeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'person_id' => Person::factory(),
            'title' => null,
            'notes' => null,
            'is_primary' => false,
            'active' => true,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
