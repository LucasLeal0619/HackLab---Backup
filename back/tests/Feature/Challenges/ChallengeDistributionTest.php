<?php

namespace Tests\Feature\Challenges;

use App\Domain\Audit\AuditAction;
use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Domain\Teams\Enums\TeamStatus;
use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

/**
 * Relação desafio ↔ equipe (teams.challenge_id, 1:1).
 */
class ChallengeDistributionTest extends DatabaseTestCase
{
    private Event $event;

    private Team $teamA;

    private Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->teamA = Team::factory()->for($this->event)->create(['name' => 'Equipe A']);
        $this->teamB = Team::factory()->for($this->event)->create(['name' => 'Equipe B']);
    }

    private function approved(array $attributes = []): Challenge
    {
        return Challenge::factory()->for($this->event)->status(ChallengeStatus::Approved)->create($attributes);
    }

    private function assign(Challenge $challenge, ?int $teamId)
    {
        return $this->actingAs($this->admin())->patchJson("/api/v1/challenges/{$challenge->id}/team", ['team_id' => $teamId]);
    }

    private function logs(Challenge $challenge): array
    {
        return AuditLog::query()
            ->where('entity_type', 'Challenge')->where('entity_id', (string) $challenge->id)
            ->whereIn('action', [AuditAction::CHALLENGE_TEAM_ASSIGNED, AuditAction::CHALLENGE_TEAM_CHANGED, AuditAction::CHALLENGE_TEAM_UNASSIGNED, AuditAction::CHALLENGE_STATUS_CHANGED])
            ->orderBy('id')->pluck('action')->all();
    }

    public function test_distributing_an_approved_challenge_links_the_team_and_sets_distributed(): void
    {
        $challenge = $this->approved();

        $this->assign($challenge, $this->teamA->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'DISTRIBUTED')
            ->assertJsonPath('data.team.id', $this->teamA->id);

        $this->assertSame($challenge->id, $this->teamA->fresh()->challenge_id);
        $this->assertSame([AuditAction::CHALLENGE_TEAM_ASSIGNED], $this->logs($challenge));

        $log = AuditLog::query()->where('action', AuditAction::CHALLENGE_TEAM_ASSIGNED)->sole();
        $this->assertSame('APPROVED', $log->before_data['status']);
        $this->assertSame('DISTRIBUTED', $log->after_data['status']);

        $this->actingAs($this->admin())->getJson("/api/v1/teams/{$this->teamA->id}")->assertJsonPath('data.challenge.id', $challenge->id);
    }

    public function test_only_approved_challenges_are_distributed(): void
    {
        $draft = Challenge::factory()->for($this->event)->create();

        $this->assign($draft, $this->teamA->id)->assertUnprocessable()->assertJsonValidationErrors(['team_id']);
        $this->assertNull($this->teamA->fresh()->challenge_id);
    }

    public function test_challenge_and_team_from_different_events_are_rejected_by_api_and_database(): void
    {
        $foreignTeam = Team::factory()->create();
        $challenge = $this->approved();

        $this->assign($challenge, $foreignTeam->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'Equipe inexistente ou de outro evento.');

        $this->expectException(QueryException::class);
        DB::table('teams')->where('id', $foreignTeam->id)->update(['challenge_id' => $challenge->id]);
    }

    public function test_a_team_does_not_receive_two_challenges(): void
    {
        $first = $this->approved();
        $second = $this->approved();
        $this->assign($first, $this->teamA->id)->assertOk();

        $this->assign($second, $this->teamA->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'A equipe já está com outro desafio. Retire-o antes de distribuir este.');

        $this->assertSame($first->id, $this->teamA->fresh()->challenge_id);
        $this->assertSame('APPROVED', $second->fresh()->status->value);
    }

    public function test_a_challenge_never_sits_in_two_teams(): void
    {
        $challenge = $this->approved();
        $this->teamA->forceFill(['challenge_id' => $challenge->id])->save();

        $this->expectException(QueryException::class);
        $this->teamB->forceFill(['challenge_id' => $challenge->id])->save();
    }

    public function test_moving_between_teams_is_atomic_with_a_single_log(): void
    {
        $challenge = $this->approved();
        $this->assign($challenge, $this->teamA->id)->assertOk();

        $this->assign($challenge, $this->teamB->id)
            ->assertOk()
            ->assertJsonPath('data.team.id', $this->teamB->id)
            ->assertJsonPath('data.status', 'DISTRIBUTED');

        $this->assertNull($this->teamA->fresh()->challenge_id);
        $this->assertSame($challenge->id, $this->teamB->fresh()->challenge_id);
        $this->assertSame([AuditAction::CHALLENGE_TEAM_ASSIGNED, AuditAction::CHALLENGE_TEAM_CHANGED], $this->logs($challenge));

        $log = AuditLog::query()->where('action', AuditAction::CHALLENGE_TEAM_CHANGED)->sole();
        $this->assertSame('Equipe A', $log->before_data['team_name']);
        $this->assertSame('Equipe B', $log->after_data['team_name']);
    }

    public function test_failed_move_leaves_everything_as_it_was(): void
    {
        $challenge = $this->approved();
        $other = $this->approved();
        $this->assign($challenge, $this->teamA->id)->assertOk();
        $this->assign($other, $this->teamB->id)->assertOk();

        // Equipe B já tem desafio: mover deve falhar sem liberar a Equipe A.
        $this->assign($challenge, $this->teamB->id)->assertUnprocessable();

        $this->assertSame($challenge->id, $this->teamA->fresh()->challenge_id);
        $this->assertSame($other->id, $this->teamB->fresh()->challenge_id);
    }

    public function test_inactive_team_does_not_receive_a_challenge(): void
    {
        $this->teamA->update(['status' => TeamStatus::Inactive]);

        $this->assign($this->approved(), $this->teamA->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'Equipe inativa não recebe desafio.');
    }

    public function test_unassigning_a_distributed_challenge_returns_it_to_approved(): void
    {
        $challenge = $this->approved();
        $this->assign($challenge, $this->teamA->id)->assertOk();

        $this->assign($challenge, null)
            ->assertOk()
            ->assertJsonPath('data.team', null)
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertNull($this->teamA->fresh()->challenge_id);
        $this->assertSame([AuditAction::CHALLENGE_TEAM_ASSIGNED, AuditAction::CHALLENGE_TEAM_UNASSIGNED], $this->logs($challenge));
    }

    public function test_in_development_or_finished_locks_the_team_until_status_changes(): void
    {
        $challenge = $this->approved();
        $this->assign($challenge, $this->teamA->id)->assertOk();
        $admin = $this->admin();

        foreach (['IN_DEVELOPMENT', 'FINISHED'] as $status) {
            $this->actingAs($admin)->patchJson("/api/v1/challenges/{$challenge->id}/status", ['status' => $status])->assertOk();
            $this->assign($challenge, null)->assertUnprocessable()->assertJsonValidationErrors(['team_id']);
            $this->assign($challenge, $this->teamB->id)->assertUnprocessable()->assertJsonValidationErrors(['team_id']);
            $this->assertSame($challenge->id, $this->teamA->fresh()->challenge_id);
        }

        // FINISHED mantém a equipe (histórico).
        $this->actingAs($admin)->getJson("/api/v1/challenges/{$challenge->id}")->assertJsonPath('data.status', 'FINISHED')->assertJsonPath('data.team.id', $this->teamA->id);

        // Correção explícita: volta para DISTRIBUTED e então pode redistribuir.
        $this->actingAs($admin)->patchJson("/api/v1/challenges/{$challenge->id}/status", ['status' => 'DISTRIBUTED'])->assertOk();
        $this->assign($challenge, $this->teamB->id)->assertOk()->assertJsonPath('data.team.id', $this->teamB->id);
    }

    public function test_status_without_team_semantics(): void
    {
        $challenge = $this->approved();
        $this->assign($challenge, $this->teamA->id)->assertOk();

        // Com equipe vinculada, não volta para etapas anteriores à distribuição.
        $this->actingAs($this->admin())
            ->patchJson("/api/v1/challenges/{$challenge->id}/status", ['status' => 'APPROVED'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_assigning_to_the_same_team_is_a_no_op(): void
    {
        $challenge = $this->approved();
        $this->assign($challenge, $this->teamA->id)->assertOk();
        $this->assign($challenge, $this->teamA->id)->assertOk();

        $this->assertSame([AuditAction::CHALLENGE_TEAM_ASSIGNED], $this->logs($challenge));
    }

    public function test_team_id_is_required_in_payload(): void
    {
        $this->actingAs($this->admin())
            ->patchJson("/api/v1/challenges/{$this->approved()->id}/team", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_team_list_filters_by_challenge(): void
    {
        $this->assign($this->approved(), $this->teamA->id)->assertOk();

        $this->actingAs($this->admin())->getJson("/api/v1/events/{$this->event->id}/teams?has_challenge=1")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Equipe A');
        $this->actingAs($this->admin())->getJson("/api/v1/events/{$this->event->id}/teams?has_challenge=0")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Equipe B');
    }
}
