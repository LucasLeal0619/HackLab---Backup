<?php

namespace Tests\Feature\Occurrences;

use App\Domain\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\Occurrence;
use App\Models\Task;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

class OccurrenceTaskGenerationTest extends DatabaseTestCase
{
    use DemandScenario;

    private Occurrence $occurrence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDemandScenario();

        $this->occurrence = Occurrence::factory()->between($this->sectorA, $this->sectorB)->create();
    }

    private function generate($user, array $payload = [])
    {
        return $this->actingAs($user)->postJson("/api/v1/occurrences/{$this->occurrence->id}/tasks", array_replace([
            'title' => 'Comprar extensões',
            'description' => 'Para as bancadas do bloco 2.',
            'responsible_sector_id' => $this->sectorC->id,
        ], $payload));
    }

    public function test_admin_generates_a_task_with_history_on_both_sides_and_a_single_audit_log(): void
    {
        $response = $this->generate($this->adminUser, ['origin_sector_id' => $this->sectorB->id])->assertCreated()
            ->assertJsonPath('data.origin_sector.id', $this->sectorB->id)
            ->assertJsonPath('data.responsible_sector.id', $this->sectorC->id)
            ->assertJsonPath('data.source_occurrence.id', $this->occurrence->id);

        $task = Task::query()->findOrFail($response->json('data.id'));
        $this->assertSame('CREATED', $task->interactions()->sole()->type->value);
        $this->assertSame($this->occurrence->reference, $task->interactions()->sole()->metadata['source_occurrence_reference']);

        $generated = $this->occurrence->interactions()->where('type', 'TASK_GENERATED')->sole();
        $this->assertSame($task->reference, $generated->metadata['task_reference']);

        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::OCCURRENCE_TASK_GENERATED)->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::TASK_CREATED)->count());
    }

    public function test_one_occurrence_can_generate_several_tasks(): void
    {
        $this->generate($this->adminUser, ['origin_sector_id' => $this->sectorA->id])->assertCreated();
        $this->generate($this->adminUser, ['origin_sector_id' => $this->sectorA->id, 'responsible_sector_id' => $this->sectorD->id])->assertCreated();

        $this->assertSame(2, Task::query()->where('source_occurrence_id', $this->occurrence->id)->count());
        $this->actingAs($this->adminUser)->getJson("/api/v1/occurrences/{$this->occurrence->id}")->assertJsonCount(2, 'data.generated_tasks');
        $this->actingAs($this->adminUser)->getJson("/api/v1/events/{$this->event->id}/tasks?source_occurrence_id={$this->occurrence->id}")->assertJsonCount(2, 'data');
    }

    public function test_related_manager_generates_with_own_sector_as_origin(): void
    {
        $this->generate($this->manager('A'))->assertCreated()->assertJsonPath('data.origin_sector.id', $this->sectorA->id);
        $this->generate($this->manager('B'))->assertCreated()->assertJsonPath('data.origin_sector.id', $this->sectorB->id);

        $this->generate($this->manager('A'), ['origin_sector_id' => $this->sectorB->id])->assertForbidden();
    }

    public function test_editor_consultant_and_unrelated_manager_cannot_generate(): void
    {
        $this->generate($this->editor('B'))->assertForbidden();
        $this->generate($this->consultant)->assertForbidden();
        $this->generate($this->manager('D'))->assertForbidden();

        $this->assertSame(0, Task::query()->count());
    }

    public function test_source_occurrence_cannot_change_after_creation(): void
    {
        $taskId = $this->generate($this->adminUser, ['origin_sector_id' => $this->sectorA->id])->json('data.id');
        $other = Occurrence::factory()->between($this->sectorA)->create();

        $this->actingAs($this->adminUser)->patchJson("/api/v1/tasks/{$taskId}", ['source_occurrence_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['source_occurrence_id']);

        $this->expectException(QueryException::class);
        DB::table('tasks')->where('id', $taskId)->update(['source_occurrence_id' => $other->id]);
    }
}
