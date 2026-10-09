<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationCriterion>
 */
class EvaluationCriterionFactory extends Factory
{
    protected $model = EvaluationCriterion::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Critério '.fake()->unique()->numerify('####'),
            'description' => null,
            'min_score' => 0,
            'max_score' => 10,
            'weight' => 1,
            'sort_order' => fake()->unique()->numberBetween(1, 900),
            'active' => true,
        ];
    }
}
