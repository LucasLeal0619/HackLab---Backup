<?php

namespace Tests\Feature\Tasks;

use App\Domain\Users\Enums\RoleCode;
use App\Models\Task;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

/**
 * Pendência de origem A, responsável B, envolvido C. Setor D não tem relação.
 */
class TaskVisibilityTest extends DatabaseTestCase
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

    private function listed($user): array
    {
        return collect($this->actingAs($user)->getJson("/api/v1/events/{$this->event->id}/tasks")->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_origin_responsible_and_involved_sectors_see_and_comment(): void
    {
        foreach (['A', 'B', 'C'] as $key) {
            foreach ([$this->manager($key), $this->editor($key)] as $user) {
                $this->actingAs($user)->getJson("/api/v1/tasks/{$this->task->id}")->assertOk();
                $this->actingAs($user)->postJson("/api/v1/tasks/{$this->task->id}/comments", ['message' => "Oi do setor {$key}"])->assertCreated();
                $this->assertSame([$this->task->id], $this->listed($user));
            }
        }
    }

    public function test_unrelated_sector_gets_403_and_does_not_see_it_in_the_list(): void
    {
        foreach ([$this->manager('D'), $this->editor('D')] as $user) {
            $this->actingAs($user)->getJson("/api/v1/tasks/{$this->task->id}")->assertForbidden();
            $this->actingAs($user)->postJson("/api/v1/tasks/{$this->task->id}/comments", ['message' => 'x'])->assertForbidden();
            $this->assertSame([], $this->listed($user));
        }
    }

    public function test_admin_and_consultant_see_everything(): void
    {
        Task::factory()->between($this->sectorD)->create();

        $this->assertCount(2, $this->listed($this->adminUser));
        $this->assertCount(2, $this->listed($this->consultant));
    }

    public function test_consultant_comments_but_has_no_operational_power(): void
    {
        $this->actingAs($this->consultant)->postJson("/api/v1/tasks/{$this->task->id}/comments", ['message' => 'Sugestão'])->assertCreated();
        $this->actingAs($this->consultant)->patchJson("/api/v1/tasks/{$this->task->id}", ['status' => 'IN_PROGRESS'])->assertForbidden();
        $this->actingAs($this->consultant)->patchJson("/api/v1/tasks/{$this->task->id}", ['priority' => 'HIGH'])->assertForbidden();
        $this->actingAs($this->consultant)->postJson("/api/v1/tasks/{$this->task->id}/forward", ['sector_id' => $this->sectorD->id, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($this->consultant)->postJson("/api/v1/tasks/{$this->task->id}/complete")->assertForbidden();
        $this->actingAs($this->consultant)->postJson("/api/v1/events/{$this->event->id}/tasks", [
            'title' => 'x', 'description' => 'x', 'origin_sector_id' => $this->sectorA->id, 'responsible_sector_id' => $this->sectorA->id,
        ])->assertForbidden();
    }

    public function test_juror_and_voter_have_no_access(): void
    {
        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson("/api/v1/events/{$this->event->id}/tasks")->assertForbidden();
            $this->actingAs($user)->getJson("/api/v1/tasks/{$this->task->id}")->assertForbidden();
        }
    }

    public function test_show_returns_abilities_for_the_viewer(): void
    {
        $this->actingAs($this->manager('B'))->getJson("/api/v1/tasks/{$this->task->id}")
            ->assertJsonPath('meta.abilities', ['comment' => true, 'change_status' => true, 'update_structure' => true, 'forward' => true]);

        $this->actingAs($this->manager('A'))->getJson("/api/v1/tasks/{$this->task->id}")
            ->assertJsonPath('meta.abilities', ['comment' => true, 'change_status' => false, 'update_structure' => false, 'forward' => false]);

        $this->actingAs($this->editor('B'))->getJson("/api/v1/tasks/{$this->task->id}")
            ->assertJsonPath('meta.abilities', ['comment' => true, 'change_status' => true, 'update_structure' => false, 'forward' => false]);
    }
}
