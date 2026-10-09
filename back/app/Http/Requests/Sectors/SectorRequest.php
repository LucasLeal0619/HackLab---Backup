<?php

namespace App\Http\Requests\Sectors;

use App\Models\Sector;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Criação (POST, aninhada no evento) e edição (PATCH) de setor. Nome único por evento, sem diferença de caixa.
 */
class SectorRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação (SectorPolicy).
     */
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('create', Sector::class)
            : $this->user()->can('update', $this->route('sector'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sector = $this->route('sector');
        $eventId = $sector?->event_id ?? $this->route('event')->id;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [
                $required, 'string', 'max:120',
                function (string $attribute, mixed $value, \Closure $fail) use ($eventId, $sector) {
                    $exists = Sector::query()
                        ->where('event_id', $eventId)
                        ->whereRaw('lower(name) = lower(?)', [$value])
                        ->when($sector, fn ($q) => $q->whereKeyNot($sector->id))
                        ->exists();

                    if ($exists) {
                        $fail('Já existe um setor com este nome neste evento.');
                    }
                },
            ],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
