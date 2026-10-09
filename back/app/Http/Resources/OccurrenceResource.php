<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\DemandFields;
use App\Models\Occurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Occurrence
 */
class OccurrenceResource extends JsonResource
{
    use DemandFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->demandFields() + [
            'category' => $this->category?->value,
            'event_day' => $this->whenLoaded('eventDay', fn () => $this->eventDay === null ? null : [
                'id' => $this->eventDay->id,
                'day_number' => $this->eventDay->day_number,
                'label' => $this->eventDay->label,
            ]),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'location' => $this->location,
            'team' => $this->whenLoaded('team', fn () => $this->team === null ? null : ['id' => $this->team->id, 'name' => $this->team->name]),
            'notes' => $this->notes,
            'resolution' => $this->resolution,
            'generated_tasks' => $this->whenLoaded('generatedTasks', fn () => $this->generatedTasks->map(fn ($task) => [
                'id' => $task->id,
                'reference' => $task->reference,
                'status' => $task->status?->value,
            ])->values()),
        ];
    }
}
