<?php

namespace App\Http\Requests\Tasks;

use App\Http\Requests\Demands\DemandRequest;
use App\Models\Task;

class StoreTaskRequest extends DemandRequest
{
    protected function prepareForValidation(): void
    {
        $this->applySectorDefaults('tasks.route');
    }

    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [
            Task::class,
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
        return $this->creationRules() + [
            'due_at' => ['sometimes', 'nullable', 'date'],
            'source_occurrence_id' => ['prohibited'],
        ];
    }
}
