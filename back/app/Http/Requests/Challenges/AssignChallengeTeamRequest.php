<?php

namespace App\Http\Requests\Challenges;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * { "team_id": 12 } distribui ou move; { "team_id": null } retira.
 */
class AssignChallengeTeamRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('assignTeam', $this->route('challenge'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_id' => [
                'present', 'nullable', 'integer',
                Rule::exists('teams', 'id')->where('event_id', $this->route('challenge')->event_id),
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
            'team_id.present' => 'Informe team_id (use null para retirar a equipe).',
        ];
    }
}
