<?php

namespace App\Http\Requests\Occurrences;

use App\Http\Requests\Demands\DemandRequest;
use App\Models\Task;

/**
 * Pendência gerada a partir da ocorrência. Gestor: origem = próprio setor. Administrador escolhe.
 * O setor responsável é sempre informado explicitamente.
 */
class GenerateTaskRequest extends DemandRequest
{
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user?->sector_id !== null && ! $this->has('origin_sector_id')) {
            $this->merge(['origin_sector_id' => $user->sector_id]);
        }
    }

    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('generateTask', $this->demand())
            && $this->user()->can('create', [
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
