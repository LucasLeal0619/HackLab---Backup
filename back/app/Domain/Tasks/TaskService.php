<?php

namespace App\Domain\Tasks;

use App\Domain\Audit\AuditAction;
use App\Domain\Demands\Contracts\Demand;
use App\Domain\Demands\DemandService;
use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Demands\Enums\InteractionType;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Occurrence;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pendências: algo que precisa ser feito.
 */
class TaskService extends DemandService
{
    public const RELATIONS = [
        'originSector', 'responsibleSector', 'involvedSectors', 'assignedUser.person', 'creator.person', 'sourceOccurrence',
    ];

    protected function auditPrefix(): string
    {
        return 'TASK';
    }

    protected function module(): string
    {
        return 'tasks';
    }

    protected function noun(): string
    {
        return 'Pendência';
    }

    protected function newQuery(): Builder
    {
        return Task::query();
    }

    protected function relations(): array
    {
        return self::RELATIONS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($event, $actor, $data) {
            /** @var Task $task */
            $task = $this->createDemand($event, $actor, $data, $this->defaults());

            $this->audit->record(
                AuditAction::TASK_CREATED,
                'tasks',
                "Pendência {$task->reference} criada.",
                entity: $task,
                after: $this->snapshot($task),
            );

            return $task->load(self::RELATIONS);
        });
    }

    /**
     * Pendência gerada por ocorrência. Sem log próprio: quem chama registra OCCURRENCE_TASK_GENERATED.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromOccurrence(Occurrence $occurrence, User $actor, array $data): Task
    {
        /** @var Task $task */
        $task = $this->createDemand(
            $occurrence->event,
            $actor,
            $data + ['source_occurrence_id' => $occurrence->id],
            $this->defaults(),
            ['source_occurrence_id' => $occurrence->id, 'source_occurrence_reference' => $occurrence->reference],
        );

        return $task;
    }

    public function complete(Task $task, User $actor, ?string $message = null): Task
    {
        return DB::transaction(function () use ($task, $actor, $message) {
            /** @var Task $task */
            $task = $this->lock($task);

            if ($task->isClosed()) {
                throw ValidationException::withMessages(['status' => 'A pendência já está concluída.']);
            }

            $before = $this->snapshot($task);
            $task->forceFill(['status' => TaskStatus::Completed, 'resolved_at' => now()])->save();

            $this->interact($task, $actor, InteractionType::Completed, $message, ['from' => $before['status']]);
            $this->audit->record(AuditAction::TASK_COMPLETED, 'tasks', "Pendência {$task->reference} concluída.", entity: $task, before: $before, after: $this->snapshot($task));

            return $task->load(self::RELATIONS);
        });
    }

    /**
     * Reabre para IN_PROGRESS e limpa resolved_at. Responsável individual que deixou de ser
     * coerente com o setor responsável é limpo (registrado no histórico).
     */
    public function reopen(Task $task, User $actor, ?string $reason = null): Task
    {
        return DB::transaction(function () use ($task, $actor, $reason) {
            /** @var Task $task */
            $task = $this->lock($task);

            if (! $task->isClosed()) {
                throw ValidationException::withMessages(['status' => 'Só pendências concluídas são reabertas.']);
            }

            $before = $this->snapshot($task);
            $staleAssignee = $this->isAssignable($task->assigned_user_id, $task->responsible_sector_id) ? null : $task->assigned_user_id;

            $task->forceFill([
                'status' => TaskStatus::InProgress,
                'resolved_at' => null,
                'assigned_user_id' => $staleAssignee === null ? $task->assigned_user_id : null,
            ])->save();

            $this->interact($task, $actor, InteractionType::Reopened, $reason, array_filter(['cleared_assigned_user_id' => $staleAssignee]));
            $this->audit->record(AuditAction::TASK_REOPENED, 'tasks', "Pendência {$task->reference} reaberta.", entity: $task, before: $before, after: $this->snapshot($task));

            return $task->load(self::RELATIONS);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return ['status' => TaskStatus::Pending->value, 'priority' => DemandPriority::Medium->value];
    }

    public function snapshot(Model&Demand $demand): array
    {
        /** @var Task $demand */
        return $this->baseSnapshot($demand) + [
            'due_at' => $demand->due_at?->toIso8601String(),
            'source_occurrence_id' => $demand->source_occurrence_id,
        ];
    }
}
