<?php

namespace App\Http\Requests\Occurrences;

use App\Domain\Occurrences\Enums\OccurrenceCategory;
use Illuminate\Validation\Rule;

/**
 * Campos próprios da ocorrência (dia, equipe, local...), compartilhados entre criação e edição.
 */
final class OccurrenceRules
{
    /**
     * @return array<string, mixed>
     */
    public static function details(int $eventId, bool $creating): array
    {
        $optional = $creating ? 'nullable' : 'sometimes';

        return [
            'category' => [$creating ? 'required' : 'sometimes', Rule::enum(OccurrenceCategory::class)],
            'event_day_id' => [$optional, 'nullable', 'integer', Rule::exists('event_days', 'id')->where('event_id', $eventId)],
            'occurred_at' => [$optional, 'nullable', 'date'],
            'location' => [$optional, 'nullable', 'string', 'max:255'],
            'team_id' => [$optional, 'nullable', 'integer', Rule::exists('teams', 'id')->where('event_id', $eventId)],
            'notes' => [$optional, 'nullable', 'string', 'max:10000'],
            'resolution' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'event_day_id.exists' => 'Dia inexistente ou de outro evento.',
            'team_id.exists' => 'Equipe inexistente ou de outro evento.',
            'resolution.prohibited' => 'A solução é registrada ao resolver (POST /occurrences/{occurrence}/resolve).',
        ];
    }
}
