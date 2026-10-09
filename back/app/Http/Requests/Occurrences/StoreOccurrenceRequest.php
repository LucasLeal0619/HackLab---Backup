<?php

namespace App\Http\Requests\Occurrences;

use App\Http\Requests\Demands\DemandRequest;
use App\Models\Occurrence;

class StoreOccurrenceRequest extends DemandRequest
{
    protected function prepareForValidation(): void
    {
        $this->applySectorDefaults('occurrences.route');
    }

    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [
            Occurrence::class,
            $this->intOrNull('origin_sector_id'),
            $this->intOrNull('responsible_sector_id'),
            $this->intOrNull('assigned_user_id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->creationRules() + OccurrenceRules::details($this->eventId(), creating: true);
    }

    public function messages(): array
    {
        return parent::messages() + OccurrenceRules::messages();
    }
}
