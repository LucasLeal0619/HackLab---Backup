<?php

namespace App\Http\Resources;

use App\Models\Challenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Challenge
 *
 * "team" vem da relação inversa (teams.challenge_id).
 */
class ChallengeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'company' => $this->whenLoaded('company', fn () => $this->company === null ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'type' => $this->company->type?->value,
                'status' => $this->company->status?->value,
            ]),
            'title' => $this->title,
            'problem' => $this->problem,
            'objective' => $this->objective,
            'requirements' => $this->requirements,
            'restrictions' => $this->restrictions,
            'expected_outcome' => $this->expected_outcome,
            'notes' => $this->notes,
            'status' => $this->status?->value,
            'team' => $this->whenLoaded('team', fn () => $this->team === null ? null : [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'code' => $this->team->code,
                'status' => $this->team->status?->value,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
