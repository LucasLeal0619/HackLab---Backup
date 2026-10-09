<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\DemandFields;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    use DemandFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->demandFields() + [
            'due_at' => $this->due_at?->toIso8601String(),
            'overdue' => $this->isOverdue(),
            'source_occurrence' => $this->whenLoaded('sourceOccurrence', fn () => $this->sourceOccurrence === null ? null : [
                'id' => $this->sourceOccurrence->id,
                'reference' => $this->sourceOccurrence->reference,
            ]),
        ];
    }
}
