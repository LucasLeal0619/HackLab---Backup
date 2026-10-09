<?php

namespace App\Http\Resources;

use App\Models\JurorTeamAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JurorTeamAssignment
 */
class JurorAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'juror_id' => $this->juror_id,
            'team' => $this->whenLoaded('team', fn () => ['id' => $this->team->id, 'name' => $this->team->name, 'status' => $this->team->status?->value]),
            'status' => $this->status?->value,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'assigned_by_user_id' => $this->assigned_by_user_id,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_by_user_id' => $this->revoked_by_user_id,
        ];
    }
}
