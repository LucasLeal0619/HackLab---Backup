<?php

namespace App\Http\Requests\Jurors;

use App\Domain\Companies\Enums\CompanyStatus;
use App\Models\Juror;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Jurado a partir de uma Person existente (ex.: um representante de empresa) ou cadastrando a pessoa junto.
 * Não cria conta de acesso.
 */
class StoreJurorRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Juror::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventId = $this->route('event')->id;

        return [
            'person_id' => [
                'required_without:person', 'prohibits:person', 'integer',
                Rule::exists('people', 'id'),
                Rule::unique('jurors', 'person_id')->where('event_id', $eventId),
            ],
            'person' => ['required_without:person_id', 'nullable', 'array'],
            'person.full_name' => ['required_with:person', 'string', 'max:255'],
            'person.email' => ['nullable', 'string', 'email', 'max:255'],
            'person.phone' => ['nullable', 'string', 'max:32'],
            'person.document' => ['nullable', 'string', 'max:32'],
            'company_id' => [
                'nullable', 'integer',
                Rule::exists('companies', 'id')->where('event_id', $eventId)->whereNot('status', CompanyStatus::Inactive->value),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_id.unique' => 'Esta pessoa já é jurado(a) deste evento.',
            'company_id.exists' => 'Empresa inexistente, inativa ou de outro evento.',
        ];
    }
}
