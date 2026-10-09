<?php

namespace App\Http\Requests\Classes;

use App\Models\SchoolClass;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Criação (POST, aninhada no evento) e edição (PATCH) de turma. Nome único por evento, sem diferença de caixa.
 */
class ClassRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('create', SchoolClass::class)
            : $this->user()->can('update', $this->route('class'));
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
        $class = $this->route('class');
        $eventId = $class?->event_id ?? $this->route('event')->id;

        return [
            'name' => [
                $this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:120',
                function (string $attribute, mixed $value, Closure $fail) use ($eventId, $class) {
                    $exists = SchoolClass::query()
                        ->where('event_id', $eventId)
                        ->whereRaw('lower(name) = lower(?)', [$value])
                        ->when($class, fn ($q) => $q->whereKeyNot($class->id))
                        ->exists();

                    if ($exists) {
                        $fail('Já existe uma turma com este nome neste evento.');
                    }
                },
            ],
        ];
    }
}
