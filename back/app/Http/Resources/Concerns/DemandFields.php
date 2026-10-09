<?php

namespace App\Http\Resources\Concerns;

use App\Http\Resources\DemandInteractionResource;

/**
 * Campos comuns de pendência e ocorrência no JSON da API.
 */
trait DemandFields
{
    /**
     * @return array<string, mixed>
     */
    protected function demandFields(): array
    {
        $sector = fn ($s) => $s === null ? null : ['id' => $s->id, 'name' => $s->name, 'active' => $s->active];

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'event_id' => $this->event_id,
            'title' => $this->title,
            'description' => $this->description,
            'origin_sector' => $this->whenLoaded('originSector', fn () => $sector($this->originSector)),
            'responsible_sector' => $this->whenLoaded('responsibleSector', fn () => $sector($this->responsibleSector)),
            'involved_sectors' => $this->whenLoaded('involvedSectors', fn () => $this->involvedSectors->map($sector)->values()),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser === null ? null : [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->person?->full_name,
                'email' => $this->assignedUser->email,
            ]),
            'priority' => $this->priority?->value,
            'status' => $this->status?->value,
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->person?->full_name,
            ]),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'interactions' => DemandInteractionResource::collection($this->whenLoaded('interactions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
