<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item do histórico contextual (pendência ou ocorrência).
 */
class DemandInteractionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->person?->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
