<?php

namespace App\Domain\Classes;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\Event;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

/**
 * Turmas. Turma inativa continua existindo (histórico), mas não recebe novos participantes.
 */
class ClassService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string}  $data
     */
    public function create(Event $event, array $data): SchoolClass
    {
        return DB::transaction(function () use ($event, $data) {
            $class = SchoolClass::query()->create($data + ['event_id' => $event->id, 'active' => true]);

            $this->audit->record(
                AuditAction::CLASS_CREATED,
                'classes',
                "Turma {$class->name} criada no evento {$event->name}.",
                entity: $class,
                after: $this->snapshot($class),
            );

            return $class;
        });
    }

    /**
     * @param  array{name?: string}  $data
     */
    public function update(SchoolClass $class, array $data): SchoolClass
    {
        return DB::transaction(function () use ($class, $data) {
            $before = $this->snapshot($class);
            $class->fill($data);

            if (! $class->isDirty()) {
                return $class;
            }

            $class->save();

            $this->audit->record(
                AuditAction::CLASS_UPDATED,
                'classes',
                "Turma {$class->name} alterada.",
                entity: $class,
                before: $before,
                after: $this->snapshot($class),
            );

            return $class;
        });
    }

    public function changeStatus(SchoolClass $class, bool $active): SchoolClass
    {
        return DB::transaction(function () use ($class, $active) {
            if ($class->active === $active) {
                return $class;
            }

            $before = $this->snapshot($class);
            $class->forceFill(['active' => $active])->save();

            $this->audit->record(
                $active ? AuditAction::CLASS_ACTIVATED : AuditAction::CLASS_INACTIVATED,
                'classes',
                $active ? "Turma {$class->name} ativada." : "Turma {$class->name} inativada.",
                entity: $class,
                before: $before,
                after: $this->snapshot($class),
            );

            return $class;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(SchoolClass $class): array
    {
        return $class->only(['event_id', 'name', 'active']);
    }
}
