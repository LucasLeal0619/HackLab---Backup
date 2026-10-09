<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Enums\RoleCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeUserRoleRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação (UserPolicy::changeRole).
     */
    public function authorize(): bool
    {
        return $this->user()->can('changeRole', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(RoleCode::class)],
            // Opcional ao trocar entre Gestor e Editor (mantém o setor atual); obrigatório para virar Gestor/Editor sem setor.
            'sector_id' => [
                'nullable', 'integer',
                Rule::prohibitedIf(fn () => ! in_array($this->input('role'), RoleCode::sectorRoleValues(), true)),
                Rule::exists('sectors', 'id')->where('active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sector_id.required' => 'Gestor e Editor precisam de um setor.',
            'sector_id.prohibited' => 'Só Gestor e Editor têm vínculo setorial.',
            'sector_id.exists' => 'Setor inexistente ou inativo.',
        ];
    }
}
