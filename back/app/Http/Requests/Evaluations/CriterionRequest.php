<?php

namespace App\Http\Requests\Evaluations;

use App\Models\EvaluationCriterion;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Criação (POST) e edição (PATCH) de critério numérico. Travamento após avaliações: EvaluationCriterionService.
 */
class CriterionRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('create', EvaluationCriterion::class)
            : $this->user()->can('update', $this->route('criterion'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->isMethod('post');
        $required = $post ? 'required' : 'sometimes';
        $criterion = $this->route('criterion');
        $eventId = $criterion?->event_id ?? $this->route('event')->id;

        return [
            'name' => [
                $required, 'string', 'max:120',
                function (string $attribute, mixed $value, Closure $fail) use ($eventId, $criterion) {
                    $exists = EvaluationCriterion::query()->where('event_id', $eventId)
                        ->whereRaw('lower(name) = lower(?)', [$value])
                        ->when($criterion, fn ($q) => $q->whereKeyNot($criterion->id))
                        ->exists();

                    if ($exists) {
                        $fail('Já existe um critério com este nome neste evento.');
                    }
                },
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'min_score' => [$required, 'numeric', 'min:0', 'max:999999'],
            'max_score' => array_filter([$required, 'numeric', 'max:999999', $post ? 'gt:min_score' : null]),
            'weight' => [$required, 'numeric', 'gt:0', 'max:9999'],
            'sort_order' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'active' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_score.gt' => 'A nota máxima precisa ser maior que a mínima.',
            'weight.gt' => 'O peso precisa ser maior que zero.',
            'active.prohibited' => 'Use PATCH /evaluation-criteria/{criterion}/status para ativar/inativar.',
        ];
    }
}
