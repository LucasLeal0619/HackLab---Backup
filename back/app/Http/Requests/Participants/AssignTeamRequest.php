<?php

namespace App\Http\Requests\Participants;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Coloca o participante na equipe informada (adiciona ou move).
 */
class AssignTeamRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        // Equipe inexistente ou de outro evento cai na validação (422).
        return $this->user()->can('manageMembers', Team::query()->find($this->input('team_id')) ?? new Team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_id' => [
                'required', 'integer',
                Rule::exists('teams', 'id')->where('event_id', $this->route('participant')->event_id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_id.exists' => 'Equipe inexistente ou de outro evento.',
        ];
    }
}
