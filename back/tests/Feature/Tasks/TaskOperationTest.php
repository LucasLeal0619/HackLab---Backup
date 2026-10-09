<?php

namespace Tests\Feature\Tasks;

use App\Domain\Audit\AuditAction;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Task;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

/**
 * Pendência de origem A, responsável B, envolvido C.
 */
class TaskOperationTest extends DatabaseTestCase
{
    use DemandScenario;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDemandScenario();

        $this->task = Task::factory()->between($this->sectorA, $this->sectorB)->create();
        $this->task->involvedSectors()->attach($this->sectorC->id, ['event_id' => $this->event->id]);
    }

    private function patchTask($user, array $data)
    {
        return $this->actingAs($user)->patchJson("/api/v1/tasks/{$this->task->id}", $data);
    }

    private function types(): array
    {
        return $this->task->interactions()->pluck('type')->map->value->all();
    }

    public function test_responsible_manager_changes_structure_with_history_and_a_single_audit_log(): void
    {
        $this->patchTask($this->manager('B'), [
            'priority' => 'HIGH',
            'due_at' => '2026-11-12T18:00:00-03:00',
            'assigned_user_id' => $this->editor('B')->id,
            'involved_sector_ids' => [$this->sectorD->id],
            'title' => 'Novo título',
        ])->assertOk()
            ->assertJsonPath('data.priority', 'HIGH')
            ->assertJsonPath('data.assigned_user.id', $this->editor('B')->id)
            ->assertJsonPath('data.involved_sectors.0.id', $this->sectorD->id)
            ->assertJsonPath('data.title', 'Novo título');

        $this->assertEqualsCanonicalizing(
            ['PRIORITY_CHANGED', 'DUE_CHANGED', 'ASSIGNEE_CHANGED', 'UPDATED', 'SECTOR_ADDED', 'SECTOR_REMOVED'],
            $this->types(),
        );
        $this->assertSame(1, AuditLog::query()->where('entity_type', 'Task')->where('entity_id', (string) $this->task->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TASK_UPDATED, 'entity_id' => (string) $this->task->id]);
    }

    public function test_manager_of_origin_or_involved_sector_only_follows_and_comments(): void
    {
        foreach (['A', 'C'] as $key) {
            $user = $this->manager($key);
            $this->patchTask($user, ['priority' => 'HIGH'])->assertForbidden();
            $this->patchTask($user, ['status' => 'IN_PROGRESS'])->assertForbidden();
            $this->actingAs($user)->postJson("/api/v1/tasks/{$this->task->id}/complete")->assertForbidden();
            $this->actingAs($user)->postJson("/api/v1/tasks/{$this->task->id}/forward", ['sector_id' => $this->sectorD->id, 'reason' => 'x'])->assertForbidden();
            $this->actingAs($user)->postJson("/api/v1/tasks/{$this->task->id}/comments", ['message' => 'Acompanhando'])->assertCreated();
        }

        $this->assertSame(TaskStatus::Pending, $this->task->fresh()->status);
    }

    public function test_responsible_editor_has_reduced_operation(): void
    {
        $editor = $this->editor('B');

        $this->patchTask($editor, ['status' => 'IN_PROGRESS'])->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TASK_STATUS_CHANGED, 'entity_id' => (string) $this->task->id]);

        foreach (['priority' => 'HIGH', 'due_at' => '2026-11-12', 'assigned_user_id' => $editor->id, 'involved_sector_ids' => [], 'title' => 'x'] as $field => $value) {
            $this->patchTask($editor, [$field => $value])->assertForbidden();
        }

        $this->actingAs($editor)->postJson("/api/v1/tasks/{$this->task->id}/forward", ['sector_id' => $this->sectorD->id, 'reason' => 'x'])->assertForbidden();

        $this->actingAs($editor)->postJson("/api/v1/tasks/{$this->task->id}/complete")->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->actingAs($editor)->postJson("/api/v1/tasks/{$this->task->id}/reopen")->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
    }

    public function test_complete_and_reopen_manage_resolved_at(): void
    {
        $this->actingAs($this->manager('B'))->postJson("/api/v1/tasks/{$this->task->id}/complete", ['message' => 'Feito'])->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED');
        $this->assertNotNull($this->task->fresh()->resolved_at);

        $this->actingAs($this->manager('B'))->postJson("/api/v1/tasks/{$this->task->id}/reopen", ['message' => 'Faltou uma bancada'])->assertOk()
            ->assertJsonPath('data.status', 'IN_PROGRESS')
            ->assertJsonPath('data.resolved_at', null);

        $this->assertSame(['COMPLETED', 'REOPENED'], $this->types());
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TASK_COMPLETED]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TASK_REOPENED]);
    }

    public function test_repeated_or_invalid_status_operations_change_nothing(): void
    {
        $manager = $this->manager('B');

        $this->actingAs($manager)->postJson("/api/v1/tasks/{$this->task->id}/reopen")->assertUnprocessable();
        $this->actingAs($manager)->postJson("/api/v1/tasks/{$this->task->id}/complete")->assertOk();
        $this->actingAs($manager)->postJson("/api/v1/tasks/{$this->task->id}/complete")->assertUnprocessable();
        $this->patchTask($manager, ['status' => 'PENDING'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->patchTask($manager, ['status' => 'COMPLETED'])->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::TASK_COMPLETED)->count());
        $this->assertSame(['COMPLETED'], $this->types());
    }

    public function test_origin_responsible_and_source_cannot_be_changed_by_patch(): void
    {
        $this->patchTask($this->adminUser, ['responsible_sector_id' => $this->sectorD->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.responsible_sector_id.0', 'Para mudar o setor responsável, use o encaminhamento.');
        $this->patchTask($this->adminUser, ['origin_sector_id' => $this->sectorD->id])->assertUnprocessable();
        $this->patchTask($this->adminUser, ['reference' => 'PEN-1'])->assertUnprocessable();
    }

    public function test_comments_do_not_create_audit_logs_and_history_is_not_audit(): void
    {
        $this->actingAs($this->manager('A'))->postJson("/api/v1/tasks/{$this->task->id}/comments", ['message' => 'Comentário longo'])->assertCreated()
            ->assertJsonPath('data.type', 'COMMENT')
            ->assertJsonPath('data.message', 'Comentário longo')
            ->assertJsonPath('data.user.id', $this->manager('A')->id);

        $this->assertSame(0, AuditLog::query()->where('entity_type', 'Task')->count());

        $this->actingAs($this->manager('B'))->patchJson("/api/v1/tasks/{$this->task->id}", ['priority' => 'LOW'])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('entity_type', 'Task')->count());
        $this->assertSame(['COMMENT', 'PRIORITY_CHANGED'], $this->types());
    }

    public function test_no_delete_route(): void
    {
        $this->actingAs($this->adminUser)->deleteJson("/api/v1/tasks/{$this->task->id}")->assertStatus(405);
    }
}
