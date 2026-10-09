<?php

namespace App\Http\Requests\Occurrences;

use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Occurrences\Enums\OccurrenceStatus;
use App\Http\Requests\Demands\DemandRequest;
use Illuminate\Validation\Rule;

/**
 * Edição da ocorrência. Campos de conteúdo/estrutura exigem gestão do setor responsável;
 * status (OPEN/IN_PROGRESS) exige operação. Resolver, encaminhar e reabrir têm rotas próprias.
 */
class UpdateOccurrenceRequest extends DemandRequest
{
    public const STRUCTURAL = [
        'title', 'description', 'category', 'priority', 'assigned_user_id', 'involved_sector_ids',
        'event_day_id', 'occurred_at', 'location', 'team_id', 'notes',
    ];

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
            'assigned_user_id' => ['sometimes', 'nullable', 'integer'],
            'involved_sector_ids' => ['sometimes', 'nullable', 'array'],
            'involved_sector_ids.*' => ['integer', 'distinct', $this->activeSector()],
            'status' => ['sometimes', Rule::in([OccurrenceStatus::Open->value, OccurrenceStatus::InProgress->value])],
        ] + OccurrenceRules::details($this->eventId(), creating: false) + $this->immutableRules();
    }

    public function messages(): array
    {
        return parent::messages() + OccurrenceRules::messages() + [
            'status.in' => 'Pelo PATCH o status vai entre OPEN e IN_PROGRESS. Use /resolve e /reopen.',
        ];
    }
}
