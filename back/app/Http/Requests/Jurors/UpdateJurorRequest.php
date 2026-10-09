<?php

namespace App\Http\Requests\Jurors;

use App\Domain\Companies\Enums\CompanyStatus;
use App\Models\Company;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJurorRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('juror'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $juror = $this->route('juror');

        return [
            'company_id' => [
                'sometimes', 'nullable', 'integer',
                // Nova empresa: mesmo evento e não inativa. Manter a atual (mesmo inativa) é permitido.
                function (string $attribute, mixed $value, Closure $fail) use ($juror) {
                    if ($value === null || (int) $value === $juror->company_id) {
                        return;
                    }

                    $valid = Company::query()->whereKey($value)->where('event_id', $juror->event_id)
                        ->where('status', '!=', CompanyStatus::Inactive->value)->exists();

                    if (! $valid) {
                        $fail('Empresa inexistente, inativa ou de outro evento.');
                    }
                },
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'person_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
