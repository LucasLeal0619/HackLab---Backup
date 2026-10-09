<?php

namespace Tests\Feature\Evaluations;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\EvaluationCriterion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\JuryScenario;
use Tests\DatabaseTestCase;

class EvaluationCriteriaTest extends DatabaseTestCase
{
    use JuryScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildJuryScenario();
    }

    private function url(): string
    {
        return "/api/v1/events/{$this->event->id}/evaluation-criteria";
    }

    private function lockByDraft(): void
    {
        $this->assign($this->jurorA, [$this->team1]);
        $this->actingAs($this->userA)->putJson("/api/v1/juror-assignments/{$this->assignmentOf($this->jurorA, $this->team1)->id}/evaluation", ['comments' => 'Início'])->assertOk();
    }

    public function test_admin_creates_numeric_criteria_with_automatic_order(): void
    {
        $this->actingAs($this->adminUser)->postJson($this->url(), ['name' => 'Impacto', 'min_score' => 0, 'max_score' => 100, 'weight' => 1.5])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.max_score', 100)
            ->assertJsonPath('data.weight', 1.5)
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::EVALUATION_CRITERION_CREATED]);
        $this->actingAs($this->adminUser)->getJson($this->url())->assertJsonCount(3, 'data')->assertJsonPath('meta.locked', false);
    }

    public function test_invalid_range_weight_order_and_duplicate_name(): void
    {
        $base = ['name' => 'Novo', 'min_score' => 0, 'max_score' => 10, 'weight' => 1];

        $this->actingAs($this->adminUser)->postJson($this->url(), array_replace($base, ['max_score' => 0]))->assertUnprocessable()->assertJsonValidationErrors(['max_score']);
        $this->actingAs($this->adminUser)->postJson($this->url(), array_replace($base, ['weight' => 0]))->assertUnprocessable()->assertJsonValidationErrors(['weight']);
        $this->actingAs($this->adminUser)->postJson($this->url(), array_replace($base, ['sort_order' => 0]))->assertUnprocessable()->assertJsonValidationErrors(['sort_order']);
        $this->actingAs($this->adminUser)->postJson($this->url(), array_replace($base, ['name' => ' inovação ']))->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->actingAs($this->adminUser)->patchJson("/api/v1/evaluation-criteria/{$this->c1->id}", ['min_score' => 10])->assertUnprocessable()->assertJsonValidationErrors(['max_score']);

        $this->expectException(QueryException::class);
        DB::table('evaluation_criteria')->where('id', $this->c1->id)->update(['weight' => -1]);
    }

    public function test_criteria_lock_after_the_first_evaluation_even_draft(): void
    {
        $this->lockByDraft();

        $this->actingAs($this->adminUser)->getJson($this->url())->assertJsonPath('meta.locked', true);
        $this->actingAs($this->adminUser)->postJson($this->url(), ['name' => 'Tarde demais', 'min_score' => 0, 'max_score' => 10, 'weight' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['criteria']);

        foreach (['min_score' => 1, 'max_score' => 20, 'weight' => 5, 'sort_order' => 9] as $field => $value) {
            $this->actingAs($this->adminUser)->patchJson("/api/v1/evaluation-criteria/{$this->c1->id}", [$field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors(['criteria']);
        }

        $this->actingAs($this->adminUser)->patchJson("/api/v1/evaluation-criteria/{$this->c1->id}/status", ['active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['criteria']);

        $this->assertSame('3.000', $this->c1->fresh()->weight);
    }

    public function test_name_and_description_can_still_be_corrected_after_lock(): void
    {
        $this->lockByDraft();

        $this->actingAs($this->adminUser)->patchJson("/api/v1/evaluation-criteria/{$this->c1->id}", ['name' => 'Inovação e Originalidade', 'description' => 'Grau de novidade.'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Inovação e Originalidade')
            ->assertJsonPath('data.weight', 3);
    }

    public function test_status_toggle_before_lock(): void
    {
        $this->actingAs($this->adminUser)->patchJson("/api/v1/evaluation-criteria/{$this->c2->id}/status", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::EVALUATION_CRITERION_STATUS_CHANGED]);
        $this->actingAs($this->adminUser)->patchJson("/api/v1/evaluation-criteria/{$this->c2->id}", ['active' => true])->assertUnprocessable()->assertJsonValidationErrors(['active']);
    }

    public function test_who_sees_and_manages_criteria(): void
    {
        $this->actingAs($this->userA)->getJson($this->url())->assertOk();
        $this->actingAs($this->userA)->postJson($this->url(), ['name' => 'X', 'min_score' => 0, 'max_score' => 1, 'weight' => 1])->assertForbidden();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant, RoleCode::Voter] as $role) {
            $this->actingAs($this->userWithRole($role))->getJson($this->url())->assertForbidden();
        }

        $this->assertSame(2, EvaluationCriterion::query()->count());
    }
}
