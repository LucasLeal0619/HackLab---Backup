<?php

namespace App\Http\Resources;

use App\Models\CompanyRepresentative;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyRepresentative
 */
class CompanyRepresentativeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->id,
                'full_name' => $this->person->full_name,
                'email' => $this->person->email,
                'phone' => $this->person->phone,
            ]),
            'title' => $this->title,
            'notes' => $this->notes,
            'is_primary' => $this->is_primary,
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
