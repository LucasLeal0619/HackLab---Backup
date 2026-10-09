<?php

namespace App\Http\Requests\Challenges;

use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Domain\Companies\Enums\CompanyStatus;
use App\Models\Challenge;
use App\Models\Company;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação (POST, aninhada no evento) e edição (PATCH) de desafio.
 * A empresa, quando informada, precisa ser do mesmo evento e não estar inativa.
 * Status só na criação (até APPROVED); depois, pela rota /status. Equipe, só pela rota /team.
 */
class ChallengeRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('create', Challenge::class)
            : $this->user()->can('update', $this->route('challenge'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->isMethod('post');
        $challenge = $this->route('challenge');
        $eventId = $challenge?->event_id ?? $this->route('event')->id;

        return [
            'company_id' => [
                'sometimes', 'nullable', 'integer',
                // Nova empresa precisa ser do mesmo evento e não inativa; manter a atual (mesmo inativa) é permitido.
                function (string $attribute, mixed $value, Closure $fail) use ($challenge, $eventId) {
                    if ($value === null || ($challenge !== null && (int) $value === $challenge->company_id)) {
                        return;
                    }

                    $valid = Company::query()
                        ->whereKey($value)
                        ->where('event_id', $eventId)
                        ->where('status', '!=', CompanyStatus::Inactive->value)
                        ->exists();

                    if (! $valid) {
                        $fail('Empresa inexistente, inativa ou de outro evento.');
                    }
                },
            ],
            'title' => [$post ? 'required' : 'sometimes', 'string', 'max:255'],
            'problem' => [$post ? 'required' : 'sometimes', 'string'],
            'objective' => ['sometimes', 'nullable', 'string'],
            'requirements' => ['sometimes', 'nullable', 'string'],
            'restrictions' => ['sometimes', 'nullable', 'string'],
            'expected_outcome' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => $post ? ['sometimes', Rule::in(ChallengeStatus::creatableValues())] : ['prohibited'],
            'team_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.prohibited' => 'Use PATCH /challenges/{challenge}/status para mudar o status.',
            'status.in' => 'Na criação, o status vai de DRAFT a APPROVED. Distribuição é feita pela rota /team.',
            'team_id.prohibited' => 'Use PATCH /challenges/{challenge}/team para distribuir o desafio.',
        ];
    }
}
