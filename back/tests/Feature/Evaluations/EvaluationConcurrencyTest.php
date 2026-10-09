<?php

namespace Tests\Feature\Evaluations;

use App\Domain\Audit\AuditAction;
use App\Domain\Evaluations\EvaluationService;
use App\Models\AuditLog;
use App\Models\Evaluation;
use App\Models\JurorTeamAssignment;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\JuryScenario;
use Tests\DatabaseTestCase;

/**
 * Envio e revisão releem atribuição/avaliação com lock: cópias desatualizadas (segunda requisição
 * simultânea) não produzem estado contraditório nem logs duplicados.
 */
class EvaluationConcurrencyTest extends DatabaseTestCase
{
    use JuryScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildJuryScenario();
        $this->assign($this->jurorA, [$this->team1]);
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

    public function test_double_submit_from_stale_copies_submits_once(): void
    {
        $service = app(EvaluationService::class);
        $assignment = $this->assignmentOf($this->jurorA, $this->team1);
        $stale = JurorTeamAssignment::query()->findOrFail($assignment->id);

        $service->submit($assignment, $this->userA, ['scores' => $this->fullScores()]);
        $this->rejects(fn () => $service->submit($stale, $this->userA, ['scores' => $this->fullScores(1, 1)]));

        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::EVALUATION_SUBMITTED)->count());
        $this->assertSame('8.00', Evaluation::query()->sole()->scores()->where('criterion_id', $this->c1->id)->value('score'));
    }

    public function test_double_revision_request_from_stale_copies_happens_once(): void
    {
        $service = app(EvaluationService::class);
        $service->submit($this->assignmentOf($this->jurorA, $this->team1), $this->userA, ['scores' => $this->fullScores()]);
        $evaluation = Evaluation::query()->sole();
        $stale = Evaluation::query()->findOrFail($evaluation->id);

        $service->requestRevision($evaluation, $this->adminUser, 'Primeiro motivo');
        $this->rejects(fn () => $service->requestRevision($stale, $this->adminUser, 'Segundo motivo'));

        $this->assertSame('Primeiro motivo', $evaluation->fresh()->revision_reason);
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::EVALUATION_REVISION_REQUESTED)->count());
    }

    public function test_stale_assignment_cannot_submit_after_revocation(): void
    {
        $service = app(EvaluationService::class);
        $stale = $this->assignmentOf($this->jurorA, $this->team1);

        $this->assign($this->jurorA, []);

        $this->rejects(fn () => $service->submit($stale, $this->userA, ['scores' => $this->fullScores()]));
        $this->assertSame(0, Evaluation::query()->count());
    }
}
