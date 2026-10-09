<?php

namespace Tests\Feature\Demands;

use App\Domain\Tasks\TaskService;
use App\Models\Event;
use App\Models\Occurrence;
use App\Models\Sector;
use App\Models\Task;
use App\Models\TaskInteraction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

/**
 * Garantias no PostgreSQL e regras cruzadas com usuários e setores.
 */
class DemandIntegrityTest extends DatabaseTestCase
{
    use DemandScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDemandScenario();
    }

    private function failsInDatabase(callable $statement): void
    {
        try {
            DB::transaction($statement);
            $this->fail('O banco deveria ter recusado a operação.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_database_rejects_cross_event_relations(): void
    {
        $foreign = Sector::factory()->for(Event::factory())->create();
        $task = Task::factory()->between($this->sectorA)->create();
        $occurrence = Occurrence::factory()->between($this->sectorA)->create();
        $foreignOccurrence = Occurrence::factory()->between($foreign)->create();

        $this->failsInDatabase(fn () => DB::table('tasks')->where('id', $task->id)->update(['responsible_sector_id' => $foreign->id]));
        $this->failsInDatabase(fn () => DB::table('occurrences')->where('id', $occurrence->id)->update(['responsible_sector_id' => $foreign->id]));
        $this->failsInDatabase(fn () => DB::table('task_sectors')->insert(['task_id' => $task->id, 'sector_id' => $foreign->id, 'event_id' => $this->event->id]));
        $this->failsInDatabase(fn () => DB::table('occurrence_sectors')->insert(['occurrence_id' => $occurrence->id, 'sector_id' => $foreign->id, 'event_id' => $foreign->event_id]));
        $this->failsInDatabase(fn () => Task::factory()->between($this->sectorA)->create(['source_occurrence_id' => $foreignOccurrence->id]));
    }

    public function test_reference_origin_and_status_values_are_protected(): void
    {
        $task = Task::factory()->between($this->sectorA, $this->sectorB)->create();

        $this->failsInDatabase(fn () => DB::table('tasks')->where('id', $task->id)->update(['reference' => 'PEN-0999']));
        $this->failsInDatabase(fn () => DB::table('tasks')->where('id', $task->id)->update(['origin_sector_id' => $this->sectorB->id]));
        $this->failsInDatabase(fn () => DB::table('tasks')->where('id', $task->id)->update(['status' => 'DONE']));
        $this->failsInDatabase(fn () => DB::table('tasks')->where('id', $task->id)->update(['priority' => 'CRITICAL']));
        $this->failsInDatabase(fn () => DB::table('tasks')->where('id', $task->id)->update(['status' => 'COMPLETED'])); // sem resolved_at
    }

    public function test_history_is_append_only_in_model_and_database(): void
    {
        $task = Task::factory()->between($this->sectorA)->create();
        $interaction = app(TaskService::class)->comment($task, $this->adminUser, 'Comentário');

        $this->failsInDatabase(fn () => DB::table('task_interactions')->where('id', $interaction->id)->update(['message' => 'editado']));
        $this->failsInDatabase(fn () => DB::table('task_interactions')->where('id', $interaction->id)->delete());

        $this->expectException(LogicException::class);
        TaskInteraction::query()->findOrFail($interaction->id)->update(['message' => 'editado']);
    }

    public function test_database_keeps_assignee_coherent_with_responsible_sector(): void
    {
        $task = Task::factory()->between($this->sectorA)->create(['assigned_user_id' => $this->manager('A')->id]);

        $this->failsInDatabase(fn () => DB::table('tasks')->where('id', $task->id)->update(['assigned_user_id' => $this->manager('B')->id]));
        $this->failsInDatabase(fn () => DB::table('tasks')->where('id', $task->id)->update(['responsible_sector_id' => $this->sectorB->id]));
        $this->failsInDatabase(fn () => DB::table('users')->where('id', $this->manager('A')->id)->update(['sector_id' => $this->sectorB->id]));
        $this->failsInDatabase(fn () => DB::table('users')->where('id', $this->manager('A')->id)->update(['status' => 'INACTIVE']));
    }

    public function test_user_with_open_assignment_cannot_be_moved_or_inactivated_until_released(): void
    {
        $task = Task::factory()->between($this->sectorA)->create(['assigned_user_id' => $this->editor('A')->id]);
        $editor = $this->editor('A');

        $this->actingAs($this->adminUser)->patchJson("/api/v1/users/{$editor->id}/sector", ['sector_id' => $this->sectorB->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['sector_id']);
        $this->actingAs($this->adminUser)->patchJson("/api/v1/users/{$editor->id}/status", ['status' => 'INACTIVE'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->actingAs($this->adminUser)->patchJson("/api/v1/users/{$editor->id}/role", ['role' => 'CONSULTANT'])
            ->assertUnprocessable()->assertJsonValidationErrors(['role']);

        $this->actingAs($this->adminUser)->postJson("/api/v1/tasks/{$task->id}/complete")->assertOk();

        $this->actingAs($this->adminUser)->patchJson("/api/v1/users/{$editor->id}/sector", ['sector_id' => $this->sectorB->id])->assertOk();
    }

    public function test_reopening_clears_an_assignee_that_is_no_longer_coherent(): void
    {
        $task = Task::factory()->between($this->sectorA)->create(['assigned_user_id' => $this->editor('A')->id]);
        $this->actingAs($this->adminUser)->postJson("/api/v1/tasks/{$task->id}/complete")->assertOk();
        $this->actingAs($this->adminUser)->patchJson("/api/v1/users/{$this->editor('A')->id}/sector", ['sector_id' => $this->sectorB->id])->assertOk();

        $this->actingAs($this->adminUser)->postJson("/api/v1/tasks/{$task->id}/reopen")->assertOk()->assertJsonPath('data.assigned_user', null);
        $this->assertSame($this->editor('A')->id, $task->interactions()->where('type', 'REOPENED')->sole()->metadata['cleared_assigned_user_id']);
    }

    public function test_sector_responsible_for_open_demand_cannot_be_inactivated(): void
    {
        $empty = fn (Sector $sector) => $sector->users()->update(['status' => 'INACTIVE']);
        $empty($this->sectorB);
        $empty($this->sectorC);

        $task = Task::factory()->between($this->sectorA, $this->sectorB)->create();
        $task->involvedSectors()->attach($this->sectorC->id, ['event_id' => $this->event->id]);

        $this->actingAs($this->adminUser)->patchJson("/api/v1/sectors/{$this->sectorB->id}/status", ['active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['active']);

        // Ser só envolvido (histórico) não bloqueia.
        $this->actingAs($this->adminUser)->patchJson("/api/v1/sectors/{$this->sectorC->id}/status", ['active' => false])->assertOk();

        $this->actingAs($this->adminUser)->postJson("/api/v1/tasks/{$task->id}/complete")->assertOk();
        $this->actingAs($this->adminUser)->patchJson("/api/v1/sectors/{$this->sectorB->id}/status", ['active' => false])->assertOk();
    }

    public function test_open_occurrence_also_blocks_sector_inactivation(): void
    {
        $this->sectorD->users()->update(['status' => 'INACTIVE']);
        Occurrence::factory()->between($this->sectorA, $this->sectorD)->create();

        $this->actingAs($this->adminUser)->patchJson("/api/v1/sectors/{$this->sectorD->id}/status", ['active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['active']);
    }
}
