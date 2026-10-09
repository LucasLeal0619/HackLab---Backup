<?php

namespace Tests\Feature\Demands;

use App\Domain\Audit\AuditAction;
use App\Domain\Occurrences\OccurrenceService;
use App\Domain\Tasks\TaskService;
use App\Models\AuditLog;
use App\Models\Occurrence;
use App\Models\Task;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

/**
 * Operações críticas releem a demanda com lock antes de decidir: uma cópia desatualizada
 * (como a de uma segunda requisição simultânea) não produz estado contraditório.
 */
class DemandConcurrencyTest extends DatabaseTestCase
{
    use DemandScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDemandScenario();
    }

    private function rejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('A operação deveria ser recusada.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_stale_copy_cannot_complete_twice_or_forward_a_completed_task(): void
    {
        $service = app(TaskService::class);
        $task = Task::factory()->between($this->sectorA)->create();
        $stale = Task::query()->findOrFail($task->id); // segunda "requisição" leu antes

        $service->complete($task, $this->adminUser);

        $this->rejects(fn () => $service->complete($stale, $this->adminUser));
        $this->rejects(fn () => $service->forward($stale, $this->sectorB->id, 'x', $this->adminUser));

        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::TASK_COMPLETED)->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::TASK_FORWARDED)->count());
        $this->assertSame($this->sectorA->id, $task->fresh()->responsible_sector_id);
    }

    public function test_stale_copy_cannot_forward_again_to_the_new_responsible(): void
    {
        $service = app(TaskService::class);
        $task = Task::factory()->between($this->sectorA)->create();
        $stale = Task::query()->findOrFail($task->id);

        $service->forward($task, $this->sectorB->id, 'primeiro', $this->adminUser);
        $this->rejects(fn () => $service->forward($stale, $this->sectorB->id, 'segundo', $this->adminUser));

        $this->assertSame(1, $task->interactions()->where('type', 'FORWARDED')->count());
    }

    public function test_stale_copy_cannot_resolve_twice(): void
    {
        $service = app(OccurrenceService::class);
        $occurrence = Occurrence::factory()->between($this->sectorA)->create();
        $stale = Occurrence::query()->findOrFail($occurrence->id);

        $service->resolve($occurrence, $this->adminUser, 'Primeira solução');
        $this->rejects(fn () => $service->resolve($stale, $this->adminUser, 'Solução concorrente'));

        $this->assertSame('Primeira solução', $occurrence->fresh()->resolution);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::OCCURRENCE_RESOLVED)->count());
    }

    public function test_assignee_change_is_revalidated_against_current_responsible(): void
    {
        $service = app(TaskService::class);
        $task = Task::factory()->between($this->sectorA)->create();
        $stale = Task::query()->findOrFail($task->id);

        $service->forward($task, $this->sectorB->id, 'x', $this->adminUser);

        // A cópia antiga ainda "acha" que o responsável é A; o service relê e recusa.
        $this->rejects(fn () => $service->update($stale, ['assigned_user_id' => $this->manager('A')->id], $this->adminUser));
        $this->assertNull($task->fresh()->assigned_user_id);
    }
}
