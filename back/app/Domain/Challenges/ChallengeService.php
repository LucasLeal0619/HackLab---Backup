<?php

namespace App\Domain\Challenges;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Models\Challenge;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Desafios e distribuição para equipes (teams.challenge_id, 1:1).
 *
 * Invariantes garantidas aqui (o banco garante valores válidos, mesmo evento e unicidade):
 * - DISTRIBUTED e IN_DEVELOPMENT exigem equipe;
 * - equipe vinculada só com DISTRIBUTED, IN_DEVELOPMENT ou FINISHED;
 * - em IN_DEVELOPMENT/FINISHED a equipe não é trocada nem retirada sem antes mudar o status.
 */
class ChallengeService
{
    private const CONTENT_FIELDS = [
        'company_id', 'title', 'problem', 'objective', 'requirements', 'restrictions', 'expected_outcome', 'notes',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, array $data): Challenge
    {
        return DB::transaction(function () use ($event, $data) {
            $challenge = Challenge::query()->create($data + [
                'event_id' => $event->id,
                'status' => ChallengeStatus::Draft->value,
            ]);

            $this->audit->record(
                AuditAction::CHALLENGE_CREATED,
                'challenges',
                "Desafio \"{$challenge->title}\" cadastrado no evento {$event->name}.",
                entity: $challenge,
                after: $this->snapshot($challenge),
            );

            return $challenge->load('company', 'team');
        });
    }

    /**
     * Conteúdo e empresa. Status e equipe têm operações próprias.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Challenge $challenge, array $data): Challenge
    {
        return DB::transaction(function () use ($challenge, $data) {
            $before = $this->snapshot($challenge);
            $challenge->fill($data);

            if (! $challenge->isDirty()) {
                return $challenge->load('company', 'team');
            }

            $challenge->save();

            $this->audit->record(
                AuditAction::CHALLENGE_UPDATED,
                'challenges',
                "Desafio \"{$challenge->title}\" alterado.",
                entity: $challenge,
                before: $before,
                after: $this->snapshot($challenge),
            );

            return $challenge->load('company', 'team');
        });
    }

    public function changeStatus(Challenge $challenge, ChallengeStatus $status): Challenge
    {
        return DB::transaction(function () use ($challenge, $status) {
            $challenge = $this->lock($challenge);

            if ($challenge->status === $status) {
                return $challenge->load('company', 'team');
            }

            $team = $challenge->team;

            if ($status->requiresTeam() && $team === null) {
                throw ValidationException::withMessages([
                    'status' => "O status {$status->value} exige uma equipe. Distribua o desafio antes.",
                ]);
            }

            if ($team !== null && ! $status->allowsTeam()) {
                throw ValidationException::withMessages([
                    'status' => "O desafio está com a equipe {$team->name}. Retire a equipe antes de voltar para {$status->value}.",
                ]);
            }

            $before = $this->snapshot($challenge);
            $challenge->forceFill(['status' => $status])->save();

            $this->audit->record(
                AuditAction::CHALLENGE_STATUS_CHANGED,
                'challenges',
                "Status do desafio \"{$challenge->title}\" alterado de {$before['status']} para {$status->value}.",
                entity: $challenge,
                before: $before,
                after: $this->snapshot($challenge),
            );

            return $challenge->load('company', 'team');
        });
    }

    /**
     * Distribui (APPROVED → DISTRIBUTED), move entre equipes ou retira (DISTRIBUTED → APPROVED).
     * Sempre uma transação e um único log semântico.
     */
    public function assignTeam(Challenge $challenge, ?Team $target): Challenge
    {
        return DB::transaction(function () use ($challenge, $target) {
            $challenge = $this->lock($challenge);
            $current = $challenge->team;

            if ($target === null) {
                return $current === null ? $challenge->load('company', 'team') : $this->unassign($challenge, $current);
            }

            if ($current?->id === $target->id) {
                return $challenge->load('company', 'team');
            }

            $target = Team::query()->lockForUpdate()->findOrFail($target->id);
            $this->ensureTeamCanReceive($challenge, $target);

            return $current === null
                ? $this->distribute($challenge, $target)
                : $this->move($challenge, Team::query()->lockForUpdate()->findOrFail($current->id), $target);
        });
    }

    private function distribute(Challenge $challenge, Team $target): Challenge
    {
        if ($challenge->status !== ChallengeStatus::Approved) {
            throw ValidationException::withMessages([
                'team_id' => "Só desafios aprovados são distribuídos (status atual: {$challenge->status->value}).",
            ]);
        }

        $before = $this->assignmentSnapshot($challenge, null);
        $this->link($target, $challenge);
        $challenge->forceFill(['status' => ChallengeStatus::Distributed])->save();

        $this->audit->record(
            AuditAction::CHALLENGE_TEAM_ASSIGNED,
            'challenges',
            "Desafio \"{$challenge->title}\" distribuído para a equipe {$target->name}.",
            entity: $challenge,
            before: $before,
            after: $this->assignmentSnapshot($challenge, $target),
        );

        return $challenge->load('company', 'team');
    }

    private function move(Challenge $challenge, Team $from, Team $target): Challenge
    {
        $this->ensureTeamNotLocked($challenge);

        $before = $this->assignmentSnapshot($challenge, $from);
        $from->forceFill(['challenge_id' => null])->save();
        $this->link($target, $challenge);

        $this->audit->record(
            AuditAction::CHALLENGE_TEAM_CHANGED,
            'challenges',
            "Desafio \"{$challenge->title}\" movido da equipe {$from->name} para {$target->name}.",
            entity: $challenge,
            before: $before,
            after: $this->assignmentSnapshot($challenge, $target),
        );

        return $challenge->load('company', 'team');
    }

    private function unassign(Challenge $challenge, Team $current): Challenge
    {
        $this->ensureTeamNotLocked($challenge);

        $before = $this->assignmentSnapshot($challenge, $current);
        Team::query()->lockForUpdate()->findOrFail($current->id)->forceFill(['challenge_id' => null])->save();

        if ($challenge->status === ChallengeStatus::Distributed) {
            $challenge->forceFill(['status' => ChallengeStatus::Approved])->save();
        }

        $this->audit->record(
            AuditAction::CHALLENGE_TEAM_UNASSIGNED,
            'challenges',
            "Desafio \"{$challenge->title}\" retirado da equipe {$current->name}.",
            entity: $challenge,
            before: $before,
            after: $this->assignmentSnapshot($challenge, null),
        );

        return $challenge->load('company', 'team');
    }

    private function ensureTeamCanReceive(Challenge $challenge, Team $target): void
    {
        if ($target->event_id !== $challenge->event_id) {
            throw ValidationException::withMessages(['team_id' => 'A equipe é de outro evento.']);
        }

        if (! $target->isActive()) {
            throw ValidationException::withMessages(['team_id' => 'Equipe inativa não recebe desafio.']);
        }

        if ($target->challenge_id !== null) {
            throw ValidationException::withMessages([
                'team_id' => 'A equipe já está com outro desafio. Retire-o antes de distribuir este.',
            ]);
        }
    }

    private function ensureTeamNotLocked(Challenge $challenge): void
    {
        if ($challenge->status->locksTeam()) {
            throw ValidationException::withMessages([
                'team_id' => "Desafio {$challenge->status->value}: mude o status para uma etapa que permita redistribuição antes de trocar ou retirar a equipe.",
            ]);
        }
    }

    private function link(Team $team, Challenge $challenge): void
    {
        try {
            $team->forceFill(['challenge_id' => $challenge->id])->save();
        } catch (UniqueConstraintViolationException) {
            // Corrida entre requisições: o unique de teams.challenge_id barrou o segundo vínculo.
            throw ValidationException::withMessages(['team_id' => 'O desafio já está vinculado a outra equipe.']);
        }
    }

    private function lock(Challenge $challenge): Challenge
    {
        return Challenge::query()->with('team')->lockForUpdate()->findOrFail($challenge->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentSnapshot(Challenge $challenge, ?Team $team): array
    {
        return [
            'status' => $challenge->status?->value,
            'team_id' => $team?->id,
            'team_name' => $team?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Challenge $challenge): array
    {
        return $challenge->only(['event_id', ...self::CONTENT_FIELDS]) + ['status' => $challenge->status?->value];
    }
}
