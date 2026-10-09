<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeUserSectorRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação (UserPolicy::changeSector).
     */
    public function authorize(): bool
    {
        return $this->user()->can('changeSector', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sector_id' => ['required', 'integer', Rule::exists('sectors', 'id')->where('active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sector_id.exists' => 'Setor inexistente ou inativo.',
        ];
    }
}
