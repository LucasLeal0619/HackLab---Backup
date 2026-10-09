<?php

namespace App\Http\Requests\Demands;

/**
 * { "sector_id": 5, "reason": "..." } — motivo obrigatório; destino ativo do mesmo evento.
 */
class ForwardRequest extends DemandRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('forward', $this->demand());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sector_id' => ['required', 'integer', $this->activeSector()],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
