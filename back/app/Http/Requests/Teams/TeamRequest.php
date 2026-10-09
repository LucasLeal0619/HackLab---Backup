<?php

namespace App\Http\Requests\Teams;

use App\Domain\Teams\Enums\TeamStatus;
use App\Models\Team;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação (POST, aninhada no evento) e edição (PATCH) de equipe. Nome e código únicos por evento.
 */
class TeamRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('create', Team::class)
            : $this->user()->can('update', $this->route('team'));
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'code'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:120', $this->uniqueInEvent('name', 'Já existe uma equipe com este nome neste evento.')],
            'code' => ['sometimes', 'nullable', 'string', 'max:32', $this->uniqueInEvent('code', 'Já existe uma equipe com este código neste evento.')],
            'status' => $this->isMethod('post') ? ['prohibited'] : ['sometimes', Rule::enum(TeamStatus::class)],
            // O desafio da equipe só muda por PATCH /challenges/{challenge}/team.
            'challenge_id' => ['prohibited'],
        ];
    }

    private function uniqueInEvent(string $column, string $message): Closure
    {
        $team = $this->route('team');
        $eventId = $team?->event_id ?? $this->route('event')->id;

        return function (string $attribute, mixed $value, Closure $fail) use ($column, $message, $eventId, $team) {
            if ($value === null || $value === '') {
                return;
            }

            $exists = Team::query()
                ->where('event_id', $eventId)
                ->whereRaw("lower({$column}) = lower(?)", [$value])
                ->when($team, fn ($q) => $q->whereKeyNot($team->id))
                ->exists();

            if ($exists) {
                $fail($message);
            }
        };
    }
}
