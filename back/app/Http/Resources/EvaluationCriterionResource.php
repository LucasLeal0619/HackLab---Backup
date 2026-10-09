<?php

namespace App\Http\Resources;

use App\Models\EvaluationCriterion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EvaluationCriterion
 */
class EvaluationCriterionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'description' => $this->description,
            'min_score' => (float) $this->min_score,
            'max_score' => (float) $this->max_score,
            'weight' => (float) $this->weight,
            'sort_order' => $this->sort_order,
            'active' => $this->active,
        ];
    }
}
