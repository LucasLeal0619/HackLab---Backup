<?php

namespace App\Domain\Occurrences;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Demands\Contracts\Demand;
use App\Domain\Demands\DemandService;
use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Demands\Enums\InteractionType;
use App\Domain\Occurrences\Enums\OccurrenceStatus;
use App\Domain\Tasks\TaskService;
use App\Models\Event;
use App\Models\Occurrence;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ocorrências: algo que aconteceu.
 */
class OccurrenceService extends DemandService
{
    public const RELATIONS = [
        'originSector', 'responsibleSector', 'involvedSectors', 'assignedUser.person', 'creator.person',
        'eventDay', 'team', 'generatedTasks',
    ];

    public function __construct(AuditLogger $audit, private readonly TaskService $tasks)
    {
        parent::__construct($audit);
    }

    protected function auditPrefix(): string
    {
        return 'OCCURRENCE';
    }

    protected function module(): string
    {
        return 'occurrences';
    }

    protected function noun(): string
    {
        return 'Ocorrência';
    }

    protected function newQuery(): Builder
    {
        return Occurrence::query();
    }

    protected function relations(): array
    {
        return self::RELATIONS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, User $actor, array $data): Occurrence
    {
        return DB::transaction(function () use ($event, $actor, $data) {
            /** @var Occurrence $occurrence */
            $occurrence = $this->createDemand($event, $actor, $data, [
                'status' => OccurrenceStatus::Open->value,
                'priority' => DemandPriority::Medium->value,
            ]);

            $this->audit->record(
                AuditAction::OCCURRENCE_CREATED,
                'occurrences',
                "Ocorrência {$occurrence->reference} registrada.",
                entity: $occurrence,
                after: $this->snapshot($occurrence),
            );

            return $occurrence->load(self::RELATIONS);
        });
    }

    /**
     * Resolve com solução obrigatória. Uma solução anterior (de resolução passada) fica no histórico.
     */
    public function resolve(Occurrence $occurrence, User $actor, string $resolution): Occurrence
    {
        return DB::transaction(function () use ($occurrence, $actor, $resolution) {
            /** @var Occurrence $occurrence */
            $occurrence = $this->lock($occurrence);

            if ($occurrence->isClosed()) {
                throw ValidationException::withMessages(['status' => 'A ocorrência já está resolvida.']);
            }

            $before = $this->snapshot($occurrence);
            $occurrence->forceFill([
                'status' => OccurrenceStatus::Resolved,
                'resolved_at' => now(),
                'resolution' => $resolution,
            ])->save();

            $this->interact($occurrence, $actor, InteractionType::Resolved, $resolution, array_filter([
                'from' => $before['status'],
                'previous_resolution' => $before['resolution'],
            ]));
            $this->audit->record(AuditAction::OCCURRENCE_RESOLVED, 'occurrences', "Ocorrência {$occurrence->reference} resolvida.", entity: $occurrence, before: $before, after: $this->snapshot($occurrence));

            return $occurrence->load(self::RELATIONS);
        });
    }

    /**
     * Reabre para OPEN, limpa resolved_at e mantém a última solução (não apaga histórico).
     */
    public function reopen(Occurrence $occurrence, User $actor, ?string $reason = null): Occurrence
    {
        return DB::transaction(function () use ($occurrence, $actor, $reason) {
            /** @var Occurrence $occurrence */
            $occurrence = $this->lock($occurrence);

            if (! $occurrence->isClosed()) {
                throw ValidationException::withMessages(['status' => 'Só ocorrências resolvidas são reabertas.']);
            }

            $before = $this->snapshot($occurrence);
            $staleAssignee = $this->isAssignable($occurrence->assigned_user_id, $occurrence->responsible_sector_id) ? null : $occurrence->assigned_user_id;

            $occurrence->forceFill([
                'status' => OccurrenceStatus::Open,
                'resolved_at' => null,
                'assigned_user_id' => $staleAssignee === null ? $occurrence->assigned_user_id : null,
            ])->save();

            $this->interact($occurrence, $actor, InteractionType::Reopened, $reason, array_filter(['cleared_assigned_user_id' => $staleAssignee]));
            $this->audit->record(AuditAction::OCCURRENCE_REOPENED, 'occurrences', "Ocorrência {$occurrence->reference} reaberta.", entity: $occurrence, before: $before, after: $this->snapshot($occurrence));

            return $occurrence->load(self::RELATIONS);
        });
    }

    /**
     * Gera uma pendência vinculada. Uma transação, histórico nas duas pontas e um único log.
     *
     * @param  array<string, mixed>  $data
     */
    public function generateTask(Occurrence $occurrence, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($occurrence, $actor, $data) {
            /** @var Occurrence $occurrence */
            $occurrence = $this->lock($occurrence);
            $task = $this->tasks->createFromOccurrence($occurrence, $actor, $data);

            $this->interact($occurrence, $actor, InteractionType::TaskGenerated, null, [
                'task_id' => $task->id,
                'task_reference' => $task->reference,
                'responsible_sector_id' => $task->responsible_sector_id,
            ]);

            $this->audit->record(
                AuditAction::OCCURRENCE_TASK_GENERATED,
                'occurrences',
                "Ocorrência {$occurrence->reference} gerou a pendência {$task->reference}.",
                entity: $occurrence,
                after: ['task' => $this->tasks->snapshot($task)],
            );

            return $task->load(TaskService::RELATIONS);
        });
    }

    public function snapshot(Model&Demand $demand): array
    {
        /** @var Occurrence $demand */
        return $this->baseSnapshot($demand) + [
            'category' => $demand->category?->value,
            'event_day_id' => $demand->event_day_id,
            'occurred_at' => $demand->occurred_at?->toIso8601String(),
            'location' => $demand->location,
            'team_id' => $demand->team_id,
            'notes' => $demand->notes,
            'resolution' => $demand->resolution,
        ];
    }
}
