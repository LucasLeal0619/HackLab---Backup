<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'status' => $this->status?->value,
            'role' => $this->whenLoaded('role', fn () => [
                'code' => $this->role->code->value,
                'name' => $this->role->name,
            ]),
            'sector' => $this->whenLoaded('sector', fn () => $this->sector === null ? null : [
                'id' => $this->sector->id,
                'event_id' => $this->sector->event_id,
                'name' => $this->sector->name,
            ]),
            'person' => PersonResource::make($this->whenLoaded('person')),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
