<?php

namespace App\Domain\Participants;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Participants\Enums\ParticipantStatus;
use App\Domain\People\PersonService;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Participantes do evento. Mudar status não altera vínculo com equipe (ação explícita no TeamService).
 */
class ParticipantService
{
    private const RELATIONS = ['person', 'schoolClass', 'activeMembership.team'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PersonService $people,
    ) {}

    /**
     * @param  array{
     *     person_id?: int,
     *     person?: array{full_name: string, email?: ?string, phone?: ?string, document?: ?string},
     *     class_id?: ?int,
     *     status?: string,
     *     notes?: ?string,
     * }  $data
     */
    public function create(Event $event, array $data): Participant
    {
        return DB::transaction(function () use ($event, $data) {
            $person = isset($data['person_id'])
                ? Person::query()->findOrFail($data['person_id'])
                : $this->people->create($data['person']);

            $participant = Participant::query()->create([
                'event_id' => $event->id,
                'person_id' => $person->id,
                'class_id' => $data['class_id'] ?? null,
                'status' => $data['status'] ?? ParticipantStatus::Available->value,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->record(
                AuditAction::PARTICIPANT_CREATED,
                'participants',
                "{$person->full_name} cadastrado(a) como participante do evento {$event->name}.",
                entity: $participant,
                after: $this->snapshot($participant),
            );

            return $participant->load(self::RELATIONS);
        });
    }

    /**
     * Turma, status e observações. Uma operação = um log (STATUS_CHANGED quando o status muda).
     *
     * @param  array{class_id?: ?int, status?: string, notes?: ?string}  $data
     */
    public function update(Participant $participant, array $data): Participant
    {
        return DB::transaction(function () use ($participant, $data) {
            $before = $this->snapshot($participant);
            $participant->fill($data);

            if (! $participant->isDirty()) {
                return $participant->load(self::RELATIONS);
            }

            $statusChanged = $participant->isDirty('status');
            $participant->save();
            $name = $participant->person->full_name;

            $this->audit->record(
                $statusChanged ? AuditAction::PARTICIPANT_STATUS_CHANGED : AuditAction::PARTICIPANT_UPDATED,
                'participants',
                $statusChanged
                    ? "Status de {$name} alterado de {$before['status']} para {$participant->status->value}."
                    : "Participante {$name} alterado(a).",
                entity: $participant,
                before: $before,
                after: $this->snapshot($participant),
            );

            return $participant->load(self::RELATIONS);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Participant $participant): array
    {
        return [
            'event_id' => $participant->event_id,
            'person_id' => $participant->person_id,
            'class_id' => $participant->class_id,
            'status' => $participant->status?->value,
            'notes' => $participant->notes,
        ];
    }
}
