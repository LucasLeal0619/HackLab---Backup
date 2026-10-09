<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação (POST) e edição (PATCH) de dia do evento. Número e data são únicos no evento.
 */
class EventDayRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação (EventPolicy::update).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventId = $this->route('event')->id;
        $dayId = $this->route('day')?->id;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'day_number' => [
                $required, 'integer', 'min:1', 'max:365',
                Rule::unique('event_days', 'day_number')->where('event_id', $eventId)->ignore($dayId),
            ],
            'date' => [
                $required, 'date_format:Y-m-d',
                Rule::unique('event_days', 'date')->where('event_id', $eventId)->ignore($dayId),
            ],
            'label' => [$required, 'string', 'max:120'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i', 'after:start_time'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'day_number.unique' => 'Já existe um dia com este número neste evento.',
            'date.unique' => 'Já existe um dia com esta data neste evento.',
        ];
    }
}
