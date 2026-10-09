<?php

namespace App\Http\Requests\Companies;

use App\Domain\Companies\Enums\CompanyStatus;
use App\Domain\Companies\Enums\CompanyType;
use App\Models\Company;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação (POST, aninhada no evento) e edição (PATCH) de empresa.
 * Nome único por evento sem diferença de caixa; documento normalizado e único por evento.
 * Status só na criação (DRAFT ou CONFIRMED); depois, pela rota /status.
 */
class CompanyRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('create', Company::class)
            : $this->user()->can('update', $this->route('company'));
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'legal_name', 'segment', 'website'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if ($this->exists('document')) {
            $this->merge(['document' => Company::normalizeDocument($this->input('document'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->isMethod('post');

        return [
            'name' => [$post ? 'required' : 'sometimes', 'string', 'max:160', $this->uniqueInEvent('lower(name) = lower(?)', 'Já existe uma empresa com este nome neste evento.')],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'document' => ['sometimes', 'nullable', 'string', 'max:32', $this->uniqueInEvent('document = ?', 'Já existe uma empresa com este documento neste evento.')],
            'segment' => ['sometimes', 'nullable', 'string', 'max:120'],
            'type' => [$post ? 'required' : 'sometimes', Rule::enum(CompanyType::class)],
            'description' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => $post
                ? ['sometimes', Rule::in([CompanyStatus::Draft->value, CompanyStatus::Confirmed->value])]
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.prohibited' => 'Use PATCH /companies/{company}/status para mudar o status.',
        ];
    }

    private function uniqueInEvent(string $whereSql, string $message): Closure
    {
        $company = $this->route('company');
        $eventId = $company?->event_id ?? $this->route('event')->id;

        return function (string $attribute, mixed $value, Closure $fail) use ($whereSql, $message, $eventId, $company) {
            if ($value === null || $value === '') {
                return;
            }

            $exists = Company::query()
                ->where('event_id', $eventId)
                ->whereRaw($whereSql, [$value])
                ->when($company, fn ($q) => $q->whereKeyNot($company->id))
                ->exists();

            if ($exists) {
                $fail($message);
            }
        };
    }
}
