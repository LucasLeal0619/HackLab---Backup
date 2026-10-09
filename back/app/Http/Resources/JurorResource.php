<?php

namespace App\Http\Resources;

use App\Models\Juror;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Juror
 *
 * "account" é derivado do User da mesma Person (não é coluna de jurors).
 */
class JurorResource extends JsonResource
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
            'company' => $this->whenLoaded('company', fn () => $this->company === null ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'status' => $this->company->status?->value,
            ]),
            'account' => $this->when($this->resource->relationLoaded('account'), fn () => [
                'has_account' => $this->account !== null,
                'account_status' => $this->account?->status?->value,
                'account_role' => $this->account?->role?->code?->value,
                'access_ready' => $this->resource->accessReady(),
            ]),
            'active_assignments_count' => $this->whenCounted('activeAssignments'),
            'assignments' => JurorAssignmentResource::collection($this->whenLoaded('assignments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
