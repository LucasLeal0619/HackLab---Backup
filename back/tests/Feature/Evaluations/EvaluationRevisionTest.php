<?php

namespace Tests\Feature\Evaluations;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Evaluation;
use Tests\Concerns\JuryScenario;
use Tests\DatabaseTestCase;

class EvaluationRevisionTest extends DatabaseTestCase
{
    use JuryScenario;

    private Evaluation $evaluation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildJuryScenario();
        $this->assign($this->jurorA, [$this->team1]);

        $this->actingAs($this->userA)->postJson("/api/v1/juror-assignments/{$this->assignmentOf($this->jurorA, $this->team1)->id}/evaluation/submit", [
            'scores' => $this->fullScores(8, 4), 'comments' => 'Original',
        ])->assertOk();
        $this->evaluation = Evaluation::query()->sole();
    }

    private function requestRevision($user, array $payload = ['reason' => 'O critério Viabilidade foi preenchido incorretamente.'])
    {
        return $this->actingAs($user)->postJson("/api/v1/evaluations/{$this->evaluation->id}/request-revision", $payload);
    }

    public function test_only_admin_requests_revision_and_reason_is_required(): void
    {
        foreach ([RoleCode::Manager, RoleCode::Consultant, RoleCode::Editor, RoleCode::Voter] as $role) {
            $this->requestRevision($this->userWithRole($role))->assertForbidden();
        }
        $this->requestRevision($this->userA)->assertForbidden();

        $this->requestRevision($this->adminUser, ['reason' => '  '])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->requestRevision($this->adminUser)->assertOk()
            ->assertJsonPath('data.status', 'REVISION_REQUESTED')
            ->assertJsonPath('data.revision.reason', 'O critério Viabilidade foi preenchido incorretamente.')
            ->assertJsonPath('data.revision.requested_by_user_id', $this->adminUser->id);
    }

    public function test_juror_corrects_and_resubmits_and_the_reason_is_preserved(): void
    {
        $firstSubmission = $this->evaluation->submitted_at;
        $this->requestRevision($this->adminUser)->assertOk();
        $assignment = $this->assignmentOf($this->jurorA, $this->team1);

        $this->actingAs($this->userA)->putJson("/api/v1/juror-assignments/{$assignment->id}/evaluation", ['scores' => [['criterion_id' => $this->c2->id, 'score' => 2]]])->assertOk()
            ->assertJsonPath('data.status', 'REVISION_REQUESTED');

        $this->travel(5)->minutes();
        $this->actingAs($this->userA)->postJson("/api/v1/juror-assignments/{$assignment->id}/evaluation/submit")->assertOk()
            ->assertJsonPath('data.status', 'SUBMITTED')
            ->assertJsonPath('data.revision.reason', 'O critério Viabilidade foi preenchido incorretamente.');

        $fresh = $this->evaluation->fresh();
        $this->assertTrue($fresh->submitted_at->gt($firstSubmission));
        $this->assertSame([AuditAction::EVALUATION_SUBMITTED, AuditAction::EVALUATION_REVISION_REQUESTED, AuditAction::EVALUATION_RESUBMITTED],
            AuditLog::query()->where('entity_type', 'Evaluation')->orderBy('id')->pluck('action')->all());

        // Travou de novo.
        $this->actingAs($this->userA)->putJson("/api/v1/juror-assignments/{$assignment->id}/evaluation", ['comments' => 'x'])->assertUnprocessable();
    }

    public function test_admin_never_edits_scores(): void
    {
        $assignment = $this->assignmentOf($this->jurorA, $this->team1);
        $this->requestRevision($this->adminUser)->assertOk();

        $this->actingAs($this->adminUser)->putJson("/api/v1/juror-assignments/{$assignment->id}/evaluation", ['scores' => $this->fullScores(1, 1)])->assertForbidden();
        $this->actingAs($this->adminUser)->patchJson("/api/v1/evaluations/{$this->evaluation->id}", ['comments' => 'x'])->assertStatus(405);

        $this->assertSame('8.00', $this->evaluation->scores()->where('criterion_id', $this->c1->id)->value('score'));
    }

    public function test_revision_only_for_submitted_and_active_assignment(): void
    {
        $this->requestRevision($this->adminUser)->assertOk();
        $this->requestRevision($this->adminUser)->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::EVALUATION_REVISION_REQUESTED)->count());

        $this->actingAs($this->userA)->postJson("/api/v1/juror-assignments/{$this->assignmentOf($this->jurorA, $this->team1)->id}/evaluation/submit")->assertOk();
        $this->assign($this->jurorA, []); // revoga

        $this->requestRevision($this->adminUser)->assertUnprocessable()->assertJsonPath('errors.status.0', 'A atribuição está revogada: o jurado não poderia corrigir.');
    }

    public function test_admin_sees_all_scores_and_juror_sees_own(): void
    {
        $this->actingAs($this->adminUser)->getJson("/api/v1/evaluations/{$this->evaluation->id}")->assertOk()
            ->assertJsonPath('data.comments', 'Original')
            ->assertJsonCount(2, 'data.scores')
            ->assertJsonPath('meta.assignment_status', 'ACTIVE');

        $this->actingAs($this->adminUser)->getJson("/api/v1/events/{$this->event->id}/evaluations")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.percent', 78);
    }
}
