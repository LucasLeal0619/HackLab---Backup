<?php

namespace Tests\Feature\Evaluations;

use App\Domain\Audit\AuditAction;
use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Domain\Jurors\Enums\JurorStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\Company;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\Juror;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\JuryScenario;
use Tests\DatabaseTestCase;

class JurorEvaluationTest extends DatabaseTestCase
{
    use JuryScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildJuryScenario();
        $this->assign($this->jurorA, [$this->team1, $this->team2]);
        $this->assign($this->jurorB, [$this->team1]);
    }

    private function save($user, $assignment, array $payload)
    {
        return $this->actingAs($user)->putJson("/api/v1/juror-assignments/{$assignment->id}/evaluation", $payload);
    }

    private function submit($user, $assignment, array $payload = [])
    {
        return $this->actingAs($user)->postJson("/api/v1/juror-assignments/{$assignment->id}/evaluation/submit", $payload);
    }

    private function myEvaluations($user)
    {
        return $this->actingAs($user)->getJson("/api/v1/events/{$this->event->id}/my-evaluations");
    }

    public function test_juror_without_assignments_or_user_without_juror_gets_an_empty_list(): void
    {
        [$juror, $user] = $this->jurorWithAccount('Sem equipes');
        $this->myEvaluations($user)->assertOk()->assertJsonCount(0, 'data');

        $loneUser = User::factory()->role(RoleCode::Juror)->create();
        $this->myEvaluations($loneUser)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.juror', null);
    }

    public function test_juror_sees_only_own_assignments_including_not_started(): void
    {
        $company = Company::factory()->for($this->event)->create(['name' => 'Acme']);
        $challenge = Challenge::factory()->forCompany($company)->status(ChallengeStatus::Distributed)->create(['title' => 'Rotas']);
        $this->team1->forceFill(['challenge_id' => $challenge->id])->save();

        $this->myEvaluations($this->userA)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.team.id', $this->team1->id)
            ->assertJsonPath('data.0.challenge.title', 'Rotas')
            ->assertJsonPath('data.0.company.name', 'Acme')
            ->assertJsonPath('data.0.evaluation', null)
            ->assertJsonPath('data.0.progress', ['status' => 'NOT_STARTED', 'filled_criteria' => 0, 'total_criteria' => 2]);

        $this->myEvaluations($this->userB)->assertJsonCount(1, 'data');
        $this->assertSame(0, Evaluation::query()->count()); // nada pré-criado
    }

    public function test_partial_draft_and_one_evaluation_per_juror_and_team(): void
    {
        $assignment = $this->assignmentOf($this->jurorA, $this->team1);

        $this->save($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c1->id, 'score' => 7]]])->assertOk()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.scores.0.score', 7)
            ->assertJsonPath('data.percent', null);

        $this->save($this->userA, $assignment, ['comments' => 'Bom começo', 'scores' => [['criterion_id' => $this->c1->id, 'score' => 9]]])->assertOk()
            ->assertJsonPath('data.scores.0.score', 9);

        $this->assertSame(1, Evaluation::query()->where('juror_id', $this->jurorA->id)->where('team_id', $this->team1->id)->count());

        $this->save($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c1->id, 'score' => null]]])->assertOk()
            ->assertJsonCount(0, 'data.scores');

        $this->myEvaluations($this->userA)->assertJsonPath('data.0.progress.status', 'DRAFT');
        $this->assertSame(0, AuditLog::query()->where('module', 'evaluations')->whereNotIn('action', [AuditAction::JUROR_ASSIGNMENTS_CHANGED])->count());
    }

    public function test_score_must_be_in_the_criterion_range_by_api_and_database(): void
    {
        $assignment = $this->assignmentOf($this->jurorA, $this->team1);

        $response = $this->save($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c2->id, 'score' => 6]]])->assertUnprocessable();
        $this->assertSame('A nota de Viabilidade vai de 1.00 a 5.00.', $response->json('errors')['scores.0.score'][0]);
        $this->save($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c2->id, 'score' => 0.5]]])->assertUnprocessable();

        $foreign = EvaluationCriterion::factory()->create();
        $this->save($this->userA, $assignment, ['scores' => [['criterion_id' => $foreign->id, 'score' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['scores.0.criterion_id']);

        $this->save($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c2->id, 'score' => 3]]])->assertOk();
        $evaluation = Evaluation::query()->sole();

        $this->expectException(QueryException::class);
        DB::table('evaluation_scores')->where('evaluation_id', $evaluation->id)->update(['score' => 99]);
    }

    public function test_only_the_juror_of_the_same_person_writes(): void
    {
        $assignment = $this->assignmentOf($this->jurorA, $this->team1);

        $this->save($this->userB, $assignment, ['comments' => 'x'])->assertForbidden();
        $this->save($this->adminUser, $assignment, ['comments' => 'x'])->assertForbidden(); // admin não finge ser jurado
        $this->submit($this->adminUser, $assignment, ['scores' => $this->fullScores()])->assertForbidden();

        foreach ([RoleCode::Manager, RoleCode::Consultant, RoleCode::Editor, RoleCode::Voter] as $role) {
            $this->save($this->userWithRole($role), $assignment, ['comments' => 'x'])->assertForbidden();
        }

        $this->assertSame(0, Evaluation::query()->count());
    }

    public function test_admin_can_evaluate_only_as_a_juror_of_their_own_person(): void
    {
        $juror = Juror::factory()->for($this->event)->create(['person_id' => $this->adminUser->person_id]);
        $this->assign($juror, [$this->team3]);

        $this->submit($this->adminUser, $this->assignmentOf($juror, $this->team3), ['scores' => $this->fullScores()])->assertOk()
            ->assertJsonPath('data.status', 'SUBMITTED');
    }

    public function test_juror_cannot_see_another_jurors_evaluation(): void
    {
        $this->submit($this->userB, $this->assignmentOf($this->jurorB, $this->team1), ['scores' => $this->fullScores()])->assertOk();
        $evaluation = Evaluation::query()->sole();

        $this->actingAs($this->userA)->getJson("/api/v1/evaluations/{$evaluation->id}")->assertForbidden();
        $this->actingAs($this->userB)->getJson("/api/v1/evaluations/{$evaluation->id}")->assertOk();
        $this->actingAs($this->userA)->getJson("/api/v1/events/{$this->event->id}/evaluations")->assertForbidden();
    }

    public function test_submit_requires_all_active_criteria_and_locks_the_evaluation(): void
    {
        $assignment = $this->assignmentOf($this->jurorA, $this->team1);

        $this->submit($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c1->id, 'score' => 8]]])
            ->assertUnprocessable()->assertJsonPath('errors.scores.0', 'Preencha todos os critérios antes de enviar. Faltam: Viabilidade.');
        $this->assertSame(0, Evaluation::query()->count()); // envio recusado não grava nada (transação)

        $this->save($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c1->id, 'score' => 8]]])->assertOk();
        $this->submit($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c2->id, 'score' => 4]], 'comments' => 'Final'])->assertOk()
            ->assertJsonPath('data.status', 'SUBMITTED')
            ->assertJsonPath('data.percent', 78);
        $this->assertNotNull(Evaluation::query()->sole()->submitted_at);

        $this->submit($this->userA, $assignment, ['scores' => $this->fullScores(1, 1)])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->save($this->userA, $assignment, ['comments' => 'mudei'])->assertUnprocessable();
        $this->assertSame('Final', Evaluation::query()->sole()->comments);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::EVALUATION_SUBMITTED)->count());

        $log = AuditLog::query()->where('action', AuditAction::EVALUATION_SUBMITTED)->sole();
        $this->assertArrayNotHasKey('scores', $log->after_data);
        $this->assertStringNotContainsString('Final', json_encode($log->toArray()));
    }

    public function test_database_blocks_changes_to_submitted_evaluations(): void
    {
        $this->submit($this->userA, $this->assignmentOf($this->jurorA, $this->team1), ['scores' => $this->fullScores(), 'comments' => 'Final'])->assertOk();
        $evaluation = Evaluation::query()->sole();

        foreach ([
            fn () => DB::table('evaluation_scores')->where('evaluation_id', $evaluation->id)->delete(),
            fn () => DB::table('evaluations')->where('id', $evaluation->id)->update(['comments' => 'adulterado']),
            fn () => DB::table('evaluations')->where('id', $evaluation->id)->update(['status' => 'DRAFT']),
        ] as $statement) {
            try {
                DB::transaction($statement);
                $this->fail('O banco deveria ter recusado.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_revoked_assignment_blocks_writes_but_keeps_the_evaluation(): void
    {
        $assignment = $this->assignmentOf($this->jurorA, $this->team1);
        $this->save($this->userA, $assignment, ['scores' => [['criterion_id' => $this->c1->id, 'score' => 5]]])->assertOk();

        $this->assign($this->jurorA, [$this->team2]); // revoga equipe 1

        $this->save($this->userA, $assignment, ['comments' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['assignment']);
        $this->submit($this->userA, $assignment, ['scores' => $this->fullScores()])->assertUnprocessable();
        $this->assertSame(1, Evaluation::query()->count());
        $this->myEvaluations($this->userA)->assertJsonCount(1, 'data')->assertJsonPath('data.0.team.id', $this->team2->id);
    }

    public function test_inactive_juror_cannot_write(): void
    {
        $this->jurorA->update(['status' => JurorStatus::Inactive]);

        $this->save($this->userA, $this->assignmentOf($this->jurorA, $this->team1), ['comments' => 'x'])
            ->assertUnprocessable()->assertJsonPath('errors.assignment.0', 'Jurado inativo não altera avaliações.');
    }
}
