<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Http\Requests\Demands\DemandRequest;
use Illuminate\Validation\Rule;

/**
 * Edição da pendência. Conteúdo/prioridade/prazo/atribuição/envolvidos exigem gestão do setor responsável;
 * status (PENDING/IN_PROGRESS) exige operação. Concluir, encaminhar e reabrir têm rotas próprias.
 */
class UpdateTaskRequest extends DemandRequest
{
    public const STRUCTURAL = ['title', 'description', 'priority', 'due_at', 'assigned_user_id', 'involved_sector_ids'];

    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->authorizeUpdate(self::STRUCTURAL);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'priority' => ['sometimes', Rule::enum(DemandPriority::class)],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer'],
            'involved_sector_ids' => ['sometimes', 'nullable', 'array'],
            'involved_sector_ids.*' => ['integer', 'distinct', $this->activeSector()],
            'status' => ['sometimes', Rule::in([TaskStatus::Pending->value, TaskStatus::InProgress->value])],
        ] + $this->immutableRules();
    }

    public function messages(): array
    {
        return parent::messages() + [
            'status.in' => 'Pelo PATCH o status vai entre PENDING e IN_PROGRESS. Use /complete e /reopen.',
        ];
    }
}
