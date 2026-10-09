<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Cria a conta para uma pessoa existente (person_id) ou cadastra a pessoa junto (person).
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação (UserPolicy::create).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'person_id' => [
                'required_without:person', 'prohibits:person', 'integer',
                Rule::exists('people', 'id'),
                Rule::unique('users', 'person_id'),
            ],
            'person' => ['required_without:person_id', 'nullable', 'array'],
            'person.full_name' => ['required_with:person', 'string', 'max:255'],
            'person.phone' => ['nullable', 'string', 'max:32'],
            'person.document' => ['nullable', 'string', 'max:32'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::enum(RoleCode::class)],
            'sector_id' => [
                'nullable', 'integer',
                Rule::requiredIf(fn () => in_array($this->input('role'), RoleCode::sectorRoleValues(), true)),
                Rule::prohibitedIf(fn () => ! in_array($this->input('role'), RoleCode::sectorRoleValues(), true)),
                Rule::exists('sectors', 'id')->where('active', true),
            ],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_id.unique' => 'Esta pessoa já possui uma conta.',
            'email.unique' => 'Já existe uma conta com este e-mail.',
            'sector_id.required' => 'Gestor e Editor precisam de um setor.',
            'sector_id.prohibited' => 'Só Gestor e Editor têm vínculo setorial.',
            'sector_id.exists' => 'Setor inexistente ou inativo.',
        ];
    }
}
