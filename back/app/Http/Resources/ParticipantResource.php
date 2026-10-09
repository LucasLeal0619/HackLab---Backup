<?php

namespace App\Http\Resources;

use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Participant
 */
class ParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'full_name' => $this->person->full_name,
                'email' => $this->person->email,
                'phone' => $this->person->phone,
            ]),
            'class' => $this->whenLoaded('schoolClass', fn () => $this->schoolClass === null ? null : [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
                'active' => $this->schoolClass->active,
            ]),
            'team' => $this->whenLoaded('activeMembership', fn () => $this->activeMembership === null ? null : [
                'id' => $this->activeMembership->team_id,
                'name' => $this->activeMembership->team?->name,
                'joined_at' => $this->activeMembership->joined_at?->toIso8601String(),
            ]),
            'team_history' => TeamMemberResource::collection($this->whenLoaded('memberships')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
