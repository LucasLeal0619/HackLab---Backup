<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
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
            'legal_name' => $this->legal_name,
            'document' => $this->document,
            'segment' => $this->segment,
            'type' => $this->type?->value,
            'description' => $this->description,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'status' => $this->status?->value,
            'active_representatives_count' => $this->whenCounted('activeRepresentatives'),
            'challenges_count' => $this->whenCounted('challenges'),
            'representatives' => CompanyRepresentativeResource::collection($this->whenLoaded('representatives')),
            'challenges' => $this->whenLoaded('challenges', fn () => $this->challenges->map(fn ($challenge) => [
                'id' => $challenge->id,
                'title' => $challenge->title,
                'status' => $challenge->status?->value,
                'team' => $challenge->team === null ? null : ['id' => $challenge->team->id, 'name' => $challenge->team->name],
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
