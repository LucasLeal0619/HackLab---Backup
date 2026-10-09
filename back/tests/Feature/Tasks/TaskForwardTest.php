<?php

namespace Tests\Feature\Tasks;

use App\Domain\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Sector;
use App\Models\Task;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

class TaskForwardTest extends DatabaseTestCase
{
    use DemandScenario;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDemandScenario();

        // Origem A, responsável B (com responsável individual), envolvido C.
        $this->task = Task::factory()->between($this->sectorA, $this->sectorB)->create(['assigned_user_id' => $this->editor('B')->id]);
        $this->task->involvedSectors()->attach($this->sectorC->id, ['event_id' => $this->event->id]);
    }

    private function forward($user, int $sectorId, string $reason = 'A instalação depende de outro setor.')
    {
        return $this->actingAs($user)->postJson("/api/v1/tasks/{$this->task->id}/forward", ['sector_id' => $sectorId, 'reason' => $reason]);
    }

    public function test_forward_keeps_previous_responsible_as_involved_and_clears_assignee(): void
    {
        $this->forward($this->manager('B'), $this->sectorC->id)->assertOk()
            ->assertJsonPath('data.responsible_sector.id', $this->sectorC->id)
            ->assertJsonPath('data.origin_sector.id', $this->sectorA->id)
            ->assertJsonPath('data.assigned_user', null);

        $involved = $this->task->fresh()->involvedSectors->pluck('id')->all();
        $this->assertSame([$this->sectorB->id], $involved); // B entrou, C (novo responsável) saiu

        $interaction = $this->task->interactions()->where('type', 'FORWARDED')->sole();
        $this->assertSame('A instalação depende de outro setor.', $interaction->message);
        $this->assertSame($this->editor('B')->id, $interaction->metadata['previous_assigned_user_id']);

        $this->assertSame(1, AuditLog::query()->where('entity_type', 'Task')->where('entity_id', (string) $this->task->id)->count());
        $log = AuditLog::query()->where('action', AuditAction::TASK_FORWARDED)->sole();
        $this->assertSame($this->sectorB->id, $log->before_data['responsible_sector_id']);
        $this->assertSame($this->sectorC->id, $log->after_data['responsible_sector_id']);
        $this->assertNull($log->after_data['assigned_user_id']);
    }

    public function test_previous_responsible_keeps_following_but_loses_control(): void
    {
        $this->forward($this->manager('B'), $this->sectorD->id)->assertOk();

        $this->actingAs($this->manager('B'))->getJson("/api/v1/tasks/{$this->task->id}")->assertOk();
        $this->actingAs($this->manager('B'))->postJson("/api/v1/tasks/{$this->task->id}/comments", ['message' => 'Seguimos acompanhando'])->assertCreated();
        $this->actingAs($this->manager('B'))->patchJson("/api/v1/tasks/{$this->task->id}", ['priority' => 'HIGH'])->assertForbidden();
        $this->forward($this->manager('B'), $this->sectorB->id)->assertForbidden();

        $this->actingAs($this->manager('D'))->patchJson("/api/v1/tasks/{$this->task->id}", ['priority' => 'HIGH'])->assertOk();
    }

    public function test_forwarding_away_from_origin_does_not_duplicate_origin_as_involved(): void
    {
        $task = Task::factory()->between($this->sectorA)->create();

        $this->actingAs($this->manager('A'))->postJson("/api/v1/tasks/{$task->id}/forward", ['sector_id' => $this->sectorB->id, 'reason' => 'x'])->assertOk();

        $this->assertSame([], $task->fresh()->involvedSectors->pluck('id')->all());
        $this->actingAs($this->manager('A'))->getJson("/api/v1/tasks/{$task->id}")->assertOk(); // origem continua vendo
    }

    public function test_who_can_forward(): void
    {
        $this->forward($this->editor('B'), $this->sectorD->id)->assertForbidden();
        $this->forward($this->consultant, $this->sectorD->id)->assertForbidden();
        $this->forward($this->manager('A'), $this->sectorD->id)->assertForbidden(); // só origem
        $this->forward($this->manager('C'), $this->sectorD->id)->assertForbidden(); // só envolvido
        $this->forward($this->adminUser, $this->sectorD->id)->assertOk();
    }

    public function test_forward_validations_change_nothing(): void
    {
        $inactive = Sector::factory()->for($this->event)->inactive()->create();
        $foreign = Sector::factory()->for(Event::factory())->create();

        $this->actingAs($this->manager('B'))->postJson("/api/v1/tasks/{$this->task->id}/forward", ['sector_id' => $this->sectorC->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->forward($this->manager('B'), $inactive->id)->assertUnprocessable()->assertJsonValidationErrors(['sector_id']);
        $this->forward($this->manager('B'), $foreign->id)->assertUnprocessable()->assertJsonValidationErrors(['sector_id']);
        $this->forward($this->manager('B'), $this->sectorB->id)->assertUnprocessable()->assertJsonPath('errors.sector_id.0', 'O setor informado já é o responsável.');

        $this->actingAs($this->manager('B'))->postJson("/api/v1/tasks/{$this->task->id}/complete")->assertOk();
        $this->forward($this->manager('B'), $this->sectorC->id)->assertUnprocessable();

        $this->assertSame($this->sectorB->id, $this->task->fresh()->responsible_sector_id);
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::TASK_FORWARDED)->count());
    }
}
