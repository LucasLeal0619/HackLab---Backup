<?php

namespace App\Http\Requests\Meetings;

use App\Domain\Meetings\Enums\MeetingStatus;
use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação (POST, aninhada no evento) e edição (PATCH) de reunião.
 * O setor, se informado, precisa ser ativo e do mesmo evento (também garantido por FK composta).
 */
class MeetingRequest extends FormRequest
{
    /**
     * Autorização antes da validação: sem permissão, 403 sem detalhes de validação (MeetingPolicy).
     */
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? $this->user()->can('create', [Meeting::class, $this->filled('sector_id') ? (int) $this->input('sector_id') : null])
            : $this->user()->can('update', $this->route('meeting'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventId = $this->route('meeting')?->event_id ?? $this->route('event')->id;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'sector_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('sectors', 'id')->where('event_id', $eventId)->where('active', true),
            ],
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'scheduled_at' => [$required, 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => $this->isMethod('post') ? ['prohibited'] : ['sometimes', Rule::enum(MeetingStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sector_id.exists' => 'Setor inexistente, inativo ou de outro evento.',
        ];
    }
}
