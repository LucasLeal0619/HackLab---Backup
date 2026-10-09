<?php

namespace App\Domain\Teams;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Participants\Enums\ParticipantStatus;
use App\Domain\Teams\Enums\TeamStatus;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Equipes e composição. Vínculos nunca são apagados: encerrar = active=false + left_at.
 *
 * Formação automática (equilíbrio entre turmas) ainda não existe; quando existir, usa
 * addMember/moveMember daqui para manter as mesmas garantias.
 */
class TeamService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, code?: ?string}  $data
     */
    public function create(Event $event, array $data): Team
    {
        return DB::transaction(function () use ($event, $data) {
            $team = Team::query()->create($data + ['event_id' => $event->id, 'status' => TeamStatus::Active]);

            $this->audit->record(
                AuditAction::TEAM_CREATED,
                'teams',
                "Equipe {$team->name} criada no evento {$event->name}.",
                entity: $team,
                after: $this->snapshot($team),
            );

            return $team->loadCount('activeMemberships');
        });
    }

    /**
     * @param  array{name?: string, code?: ?string, status?: string}  $data
     */
    public function update(Team $team, array $data): Team
    {
        return DB::transaction(function () use ($team, $data) {
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);
            $before = $this->snapshot($team);
            $team->fill($data);

            if (! $team->isDirty()) {
                return $team->loadCount('activeMemberships');
            }

            if ($team->isDirty('status') && $team->status === TeamStatus::Inactive) {
                $members = $team->activeMemberships()->count();

                if ($members > 0) {
                    throw ValidationException::withMessages([
                        'status' => "A equipe tem {$members} membro(s) ativo(s). Remova ou mova os membros antes de inativar.",
                    ]);
                }
            }

            $team->save();

            $this->audit->record(
                AuditAction::TEAM_UPDATED,
                'teams',
                "Equipe {$team->name} alterada.",
                entity: $team,
                before: $before,
                after: $this->snapshot($team),
            );

            return $team->loadCount('activeMemberships');
        });
    }

    /**
     * Coloca o participante numa equipe: adiciona se ele não tem equipe, move se tem outra.
     */
    public function assign(Participant $participant, Team $team): TeamMember
    {
        return DB::transaction(function () use ($participant, $team) {
            $participant = $this->lockParticipant($participant);
            $current = $participant->activeMembership;

            if ($current?->team_id === $team->id) {
                return $current->load('team');
            }

            return $current === null
                ? $this->add($participant, $team)
                : $this->move($participant, $current, $team);
        });
    }

    /**
     * Adiciona participante sem equipe. Se ele já tem equipe ativa, recusa (use assign para mover).
     */
    public function addMember(Team $team, Participant $participant): TeamMember
    {
        return DB::transaction(function () use ($team, $participant) {
            $participant = $this->lockParticipant($participant);
            $current = $participant->activeMembership;

            if ($current !== null) {
                throw ValidationException::withMessages([
                    'participant_id' => $current->team_id === $team->id
                        ? 'O participante já é membro desta equipe.'
                        : "O participante já está na equipe {$current->team->name}. Use a movimentação de equipe.",
                ]);
            }

            return $this->add($participant, $team);
        });
    }

    /**
     * Encerra o vínculo ativo do participante com a equipe, preservando o histórico.
     */
    public function removeMember(Team $team, Participant $participant): TeamMember
    {
        return DB::transaction(function () use ($team, $participant) {
            $participant = $this->lockParticipant($participant);
            $current = $participant->activeMembership;

            if ($current === null || $current->team_id !== $team->id) {
                throw ValidationException::withMessages([
                    'participant_id' => 'O participante não é membro ativo desta equipe.',
                ]);
            }

            $this->close($current);

            $this->audit->record(
                AuditAction::PARTICIPANT_REMOVED_FROM_TEAM,
                'teams',
                "{$participant->person->full_name} removido(a) da equipe {$team->name}.",
                entity: $participant,
                before: $this->membershipSnapshot($current, $team),
                after: ['team_id' => null],
            );

            return $current;
        });
    }

    private function add(Participant $participant, Team $team): TeamMember
    {
        $this->ensureCanJoin($participant, $team);
        $membership = $this->open($participant, $team);

        $this->audit->record(
            AuditAction::PARTICIPANT_ADDED_TO_TEAM,
            'teams',
            "{$participant->person->full_name} adicionado(a) à equipe {$team->name}.",
            entity: $participant,
            before: ['team_id' => null],
            after: $this->membershipSnapshot($membership, $team),
        );

        return $membership->load('team');
    }

    private function move(Participant $participant, TeamMember $current, Team $target): TeamMember
    {
        $this->ensureCanJoin($participant, $target);
        $from = $current->team;

        $this->close($current);
        $membership = $this->open($participant, $target);

        $this->audit->record(
            AuditAction::PARTICIPANT_TEAM_CHANGED,
            'teams',
            "{$participant->person->full_name} movido(a) da equipe {$from->name} para {$target->name}.",
            entity: $participant,
            before: $this->membershipSnapshot($current, $from),
            after: $this->membershipSnapshot($membership, $target),
        );

        return $membership->load('team');
    }

    private function ensureCanJoin(Participant $participant, Team $team): void
    {
        if ($team->event_id !== $participant->event_id) {
            throw ValidationException::withMessages(['team_id' => 'A equipe é de outro evento.']);
        }

        if (! $team->isActive()) {
            throw ValidationException::withMessages(['team_id' => 'A equipe está inativa.']);
        }

        if ($participant->status !== ParticipantStatus::Available) {
            throw ValidationException::withMessages([
                'participant_id' => "Só participantes disponíveis entram em equipe (status atual: {$participant->status->value}).",
            ]);
        }
    }

    private function open(Participant $participant, Team $team): TeamMember
    {
        try {
            return TeamMember::query()->create([
                'event_id' => $participant->event_id,
                'team_id' => $team->id,
                'participant_id' => $participant->id,
                'joined_at' => now(),
                'active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Corrida entre duas requisições: o índice único parcial do banco barrou o segundo vínculo ativo.
            throw ValidationException::withMessages(['participant_id' => 'O participante já está em uma equipe ativa.']);
        }
    }

    private function close(TeamMember $membership): void
    {
        $membership->forceFill(['active' => false, 'left_at' => now()])->save();
    }

    private function lockParticipant(Participant $participant): Participant
    {
        return Participant::query()
            ->with(['person', 'activeMembership.team'])
            ->lockForUpdate()
            ->findOrFail($participant->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipSnapshot(TeamMember $membership, Team $team): array
    {
        return [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'membership_id' => $membership->id,
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'left_at' => $membership->left_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Team $team): array
    {
        return [
            'event_id' => $team->event_id,
            'name' => $team->name,
            'code' => $team->code,
            'status' => $team->status?->value,
        ];
    }
}
