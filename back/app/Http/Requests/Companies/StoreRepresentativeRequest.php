<?php

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Representante a partir de uma pessoa existente (person_id) ou cadastrando a pessoa junto (person).
 */
class StoreRepresentativeRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageRepresentatives', $this->route('company'));
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
                Rule::unique('company_representatives', 'person_id')->where('company_id', $this->route('company')->id),
            ],
            'person' => ['required_without:person_id', 'nullable', 'array'],
            'person.full_name' => ['required_with:person', 'string', 'max:255'],
            'person.email' => ['nullable', 'string', 'email', 'max:255'],
            'person.phone' => ['nullable', 'string', 'max:32'],
            'person.document' => ['nullable', 'string', 'max:32'],
            'title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_id.unique' => 'Esta pessoa já é representante desta empresa.',
        ];
    }
}
