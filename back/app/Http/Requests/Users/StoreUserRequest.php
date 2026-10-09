<?php

namespace App\Http\Requests\Users;

use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Cria a conta para uma pessoa existente (person_id) ou cadastra a pessoa junto (person).
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização no controller (UserPolicy).
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
        ];
    }
}
