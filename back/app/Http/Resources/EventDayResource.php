<?php

namespace App\Http\Resources;

use App\Models\EventDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventDay
 */
class EventDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'day_number' => $this->day_number,
            'date' => $this->date?->toDateString(),
            'label' => $this->label,
            'start_time' => $this->start_time === null ? null : substr($this->start_time, 0, 5),
            'end_time' => $this->end_time === null ? null : substr($this->end_time, 0, 5),
        ];
    }
}
