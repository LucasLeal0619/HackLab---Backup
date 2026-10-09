<?php

namespace App\Http\Requests\Sectors;

use Illuminate\Foundation\Http\FormRequest;

class ChangeSectorStatusRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação (SectorPolicy::changeStatus).
     */
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('sector'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}
