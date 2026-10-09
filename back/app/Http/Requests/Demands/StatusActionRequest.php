<?php

namespace App\Http\Requests\Demands;

/**
 * Concluir e reabrir (com observação/motivo opcional). Resolver ocorrência tem request próprio.
 */
class StatusActionRequest extends DemandRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->demand());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
