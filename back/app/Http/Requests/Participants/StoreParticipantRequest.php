<?php

namespace App\Http\Requests\Participants;

use App\Domain\Participants\Enums\ParticipantStatus;
use App\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Participante a partir de uma pessoa existente (person_id) ou cadastrando a pessoa junto (person).
 */
class StoreParticipantRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Participant::class);
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
                Rule::unique('participants', 'person_id')->where('event_id', $eventId),
            ],
            'person' => ['required_without:person_id', 'nullable', 'array'],
            'person.full_name' => ['required_with:person', 'string', 'max:255'],
            'person.email' => ['nullable', 'string', 'email', 'max:255'],
            'person.phone' => ['nullable', 'string', 'max:32'],
            'person.document' => ['nullable', 'string', 'max:32'],
            'class_id' => [
                'nullable', 'integer',
                Rule::exists('classes', 'id')->where('event_id', $eventId)->where('active', true),
            ],
            'status' => ['sometimes', Rule::enum(ParticipantStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_id.unique' => 'Esta pessoa já é participante deste evento.',
            'class_id.exists' => 'Turma inexistente, inativa ou de outro evento.',
        ];
    }
}
