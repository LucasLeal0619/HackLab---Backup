<?php

namespace Tests\Feature\Jurors;

use App\Domain\Audit\AuditAction;
use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Domain\Jurors\Enums\JurorStatus;
use App\Domain\Teams\Enums\TeamStatus;
use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\Company;
use App\Models\JurorTeamAssignment;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\JuryScenario;
use Tests\DatabaseTestCase;

class JurorAssignmentTest extends DatabaseTestCase
{
    use JuryScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildJuryScenario();
    }

    private function sync($juror, array $teamIds)
    {
        return $this->actingAs($this->adminUser)->putJson("/api/v1/jurors/{$juror->id}/assignments", ['team_ids' => $teamIds]);
    }

    public function test_explicit_assignment_with_several_teams_and_a_single_audit_log(): void
    {
        $this->sync($this->jurorA, [$this->team1->id, $this->team2->id])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'ACTIVE');

        $log = AuditLog::query()->where('action', AuditAction::JUROR_ASSIGNMENTS_CHANGED)->sole();
        $this->assertSame([], $log->before_data['team_ids']);
        $this->assertSame([$this->team1->id, $this->team2->id], $log->after_data['team_ids']);
    }

    public function test_team_with_several_jurors(): void
    {
        $this->sync($this->jurorA, [$this->team1->id])->assertOk();
        $this->sync($this->jurorB, [$this->team1->id])->assertOk();

        $this->assertSame(2, JurorTeamAssignment::query()->where('team_id', $this->team1->id)->where('status', 'ACTIVE')->count());
    }

    public function test_sync_compares_before_and_after_and_reactivation_reuses_the_record(): void
    {
        $this->sync($this->jurorA, [$this->team1->id, $this->team2->id])->assertOk();
        $original = $this->assignmentOf($this->jurorA, $this->team1)->id;

        $this->sync($this->jurorA, [$this->team2->id, $this->team3->id])->assertOk();
        $this->assertSame('REVOKED', $this->assignmentOf($this->jurorA, $this->team1)->status->value);
        $this->assertNotNull($this->assignmentOf($this->jurorA, $this->team1)->revoked_at);

        $this->sync($this->jurorA, [$this->team1->id, $this->team2->id, $this->team3->id])->assertOk();
        $reactivated = $this->assignmentOf($this->jurorA, $this->team1);
        $this->assertSame($original, $reactivated->id);
        $this->assertSame('ACTIVE', $reactivated->status->value);
        $this->assertNull($reactivated->revoked_at);
        $this->assertSame(3, JurorTeamAssignment::query()->where('juror_id', $this->jurorA->id)->count());

        $this->sync($this->jurorA, [$this->team1->id, $this->team2->id, $this->team3->id])->assertOk(); // sem mudança
        $this->assertSame(3, AuditLog::query()->where('action', AuditAction::JUROR_ASSIGNMENTS_CHANGED)->count());

        $this->actingAs($this->adminUser)->getJson("/api/v1/jurors/{$this->jurorA->id}/assignments")->assertJsonCount(3, 'data');
    }

    public function test_company_or_challenge_never_create_assignments(): void
    {
        $company = Company::factory()->for($this->event)->create();
        $this->jurorA->update(['company_id' => $company->id]);
        $challenge = Challenge::factory()->forCompany($company)->status(ChallengeStatus::Distributed)->create();
        $this->team1->forceFill(['challenge_id' => $challenge->id])->save();

        $this->assertSame(0, JurorTeamAssignment::query()->count());
        $this->actingAs($this->userA)->getJson("/api/v1/events/{$this->event->id}/my-evaluations")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_team_from_another_event_is_rejected_by_api_and_database(): void
    {
        $foreign = Team::factory()->create();

        $response = $this->sync($this->jurorA, [$foreign->id])->assertUnprocessable()->assertJsonValidationErrors(['team_ids.0']);
        $this->assertSame('Equipe inexistente ou de outro evento.', $response->json('errors')['team_ids.0'][0]);

        $this->expectException(QueryException::class);
        DB::table('juror_team_assignments')->insert([
            'event_id' => $this->event->id, 'juror_id' => $this->jurorA->id, 'team_id' => $foreign->id,
            'status' => 'ACTIVE', 'assigned_by_user_id' => $this->adminUser->id, 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_inactive_team_or_juror_does_not_receive_new_assignments(): void
    {
        $this->team3->update(['status' => TeamStatus::Inactive]);
        $this->sync($this->jurorA, [$this->team3->id])->assertUnprocessable()->assertJsonValidationErrors(['team_ids']);

        $this->sync($this->jurorB, [$this->team1->id])->assertOk();
        $this->jurorB->update(['status' => JurorStatus::Inactive]);

        $this->sync($this->jurorB, [$this->team1->id, $this->team2->id])->assertUnprocessable()
            ->assertJsonPath('errors.team_ids.0', 'Jurado inativo não recebe nova atribuição.');

        // Revogar continua possível para jurado inativo.
        $this->sync($this->jurorB, [])->assertOk();
        $this->assertSame('REVOKED', $this->assignmentOf($this->jurorB, $this->team1)->status->value);
    }

    public function test_database_keeps_assignment_status_coherent(): void
    {
        $this->sync($this->jurorA, [$this->team1->id])->assertOk();

        $this->expectException(QueryException::class);
        DB::table('juror_team_assignments')->where('juror_id', $this->jurorA->id)->update(['status' => 'REVOKED']); // sem revoked_at
    }
}
