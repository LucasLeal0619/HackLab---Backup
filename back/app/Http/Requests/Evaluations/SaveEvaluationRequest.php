<?php

namespace App\Http\Requests\Evaluations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Notas do jurado (rascunho ou envio). score null remove a nota daquele critério (só no rascunho).
 */
class SaveEvaluationRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('evaluate', $this->route('assignment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comments' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'scores' => ['sometimes', 'array'],
            'scores.*.criterion_id' => ['required', 'integer', 'distinct'],
            'scores.*.score' => ['present', 'nullable', 'numeric'],
            'scores.*.comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
