<?php

namespace App\Http\Requests\Participants;

use App\Domain\Participants\Enums\ParticipantStatus;
use App\Models\SchoolClass;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParticipantRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('participant'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $participant = $this->route('participant');

        return [
            'class_id' => [
                'sometimes', 'nullable', 'integer',
                // Nova turma precisa ser ativa e do mesmo evento; manter a turma atual (mesmo inativa) é permitido.
                function (string $attribute, mixed $value, Closure $fail) use ($participant) {
                    if ($value === null || (int) $value === $participant->class_id) {
                        return;
                    }

                    $valid = SchoolClass::query()
                        ->whereKey($value)
                        ->where('event_id', $participant->event_id)
                        ->where('active', true)
                        ->exists();

                    if (! $valid) {
                        $fail('Turma inexistente, inativa ou de outro evento.');
                    }
                },
            ],
            'status' => ['sometimes', 'required', Rule::enum(ParticipantStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
