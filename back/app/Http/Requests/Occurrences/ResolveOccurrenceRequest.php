<?php

namespace App\Http\Requests\Occurrences;

use App\Http\Requests\Demands\DemandRequest;

class ResolveOccurrenceRequest extends DemandRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('resolution'))) {
            $this->merge(['resolution' => trim($this->input('resolution'))]);
        }
    }

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
            'resolution' => ['required', 'string', 'max:10000'],
        ];
    }
}
