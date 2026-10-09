<?php

namespace Tests\Feature\Teams;

use App\Domain\Audit\AuditAction;
use App\Domain\Participants\Enums\ParticipantStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Participant;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;

class TeamTest extends DatabaseTestCase
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

    private function participant(array $attributes = []): Participant
    {
        return Participant::factory()->create(['event_id' => $this->event->id] + $attributes);
    }

    private function add(Team $team, Participant $participant)
    {
        return $this->actingAs($this->admin())->postJson("/api/v1/teams/{$team->id}/members", ['participant_id' => $participant->id]);
    }

    public function test_admin_creates_and_updates_teams_with_audit(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)
            ->postJson("/api/v1/events/{$this->event->id}/teams", ['name' => 'Equipe C', 'code' => 'C01'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.members_count', 0)
            ->json('data.id');

        $this->actingAs($admin)->patchJson("/api/v1/teams/{$id}", ['name' => 'Equipe Gama'])->assertOk()->assertJsonPath('data.name', 'Equipe Gama');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TEAM_CREATED, 'entity_id' => (string) $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TEAM_UPDATED, 'entity_id' => (string) $id]);

        $this->actingAs($admin)
            ->postJson("/api/v1/events/{$this->event->id}/teams", ['name' => 'equipe a'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
        $this->actingAs($admin)
            ->postJson("/api/v1/events/{$this->event->id}/teams", ['name' => 'Equipe D', 'code' => 'c01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_challenge_cannot_be_set_through_the_team_endpoint(): void
    {
        // Desde a Fase 4 a equipe tem challenge_id, mas ele só muda por PATCH /challenges/{challenge}/team.
        $this->assertTrue(Schema::hasColumn('teams', 'challenge_id'));

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/teams/{$this->teamA->id}", ['challenge_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['challenge_id']);
    }

    public function test_participant_joins_a_team_and_team_composition_is_listed(): void
    {
        $classA = SchoolClass::factory()->for($this->event)->create();
        $classB = SchoolClass::factory()->for($this->event)->create();
        $p1 = Participant::factory()->inClass($classA)->create();
        $p2 = Participant::factory()->inClass($classB)->create();

        $this->add($this->teamA, $p1)->assertOk()->assertJsonPath('data.members_count', 1);
        $this->add($this->teamA, $p2)->assertOk()->assertJsonPath('data.members_count', 2);

        // Equipe mistura turmas.
        $this->actingAs($this->admin())
            ->getJson("/api/v1/teams/{$this->teamA->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.members')
            ->assertJsonPath('data.members.0.participant.class.id', $classA->id)
            ->assertJsonPath('data.members.1.participant.class.id', $classB->id);

        $this->actingAs($this->admin())
            ->getJson("/api/v1/events/{$this->event->id}/teams")
            ->assertJsonPath('data.0.members_count', 2)
            ->assertJsonPath('data.1.members_count', 0);

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PARTICIPANT_ADDED_TO_TEAM, 'entity_id' => (string) $p1->id]);
    }

    public function test_participant_cannot_be_in_two_active_teams(): void
    {
        $p = $this->participant();
        $this->add($this->teamA, $p)->assertOk();

        $this->add($this->teamB, $p)
            ->assertUnprocessable()
            ->assertJsonPath('errors.participant_id.0', 'O participante já está na equipe Equipe A. Use a movimentação de equipe.');

        $this->add($this->teamA, $p)
            ->assertUnprocessable()
            ->assertJsonPath('errors.participant_id.0', 'O participante já é membro desta equipe.');
    }

    public function test_database_blocks_a_second_active_membership(): void
    {
        $p = $this->participant();
        TeamMember::query()->create(['event_id' => $this->event->id, 'team_id' => $this->teamA->id, 'participant_id' => $p->id, 'joined_at' => now(), 'active' => true]);

        $this->expectException(QueryException::class);
        TeamMember::query()->create(['event_id' => $this->event->id, 'team_id' => $this->teamB->id, 'participant_id' => $p->id, 'joined_at' => now(), 'active' => true]);
    }

    public function test_move_closes_old_membership_and_opens_new_one_with_a_single_log(): void
    {
        $p = $this->participant();
        $this->add($this->teamA, $p)->assertOk();

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/participants/{$p->id}/team", ['team_id' => $this->teamB->id])
            ->assertOk()
            ->assertJsonPath('data.team.id', $this->teamB->id);

        $memberships = TeamMember::query()->where('participant_id', $p->id)->orderBy('id')->get();
        $this->assertCount(2, $memberships);
        $this->assertFalse($memberships[0]->active);
        $this->assertNotNull($memberships[0]->left_at);
        $this->assertSame($this->teamA->id, $memberships[0]->team_id);
        $this->assertTrue($memberships[1]->active);
        $this->assertSame($this->teamB->id, $memberships[1]->team_id);

        $logs = AuditLog::query()->where('entity_type', 'Participant')->where('entity_id', (string) $p->id)
            ->whereIn('action', [AuditAction::PARTICIPANT_TEAM_CHANGED, AuditAction::PARTICIPANT_REMOVED_FROM_TEAM, AuditAction::PARTICIPANT_ADDED_TO_TEAM])
            ->orderBy('id')->get();
        $this->assertSame([AuditAction::PARTICIPANT_ADDED_TO_TEAM, AuditAction::PARTICIPANT_TEAM_CHANGED], $logs->pluck('action')->all());
        $this->assertSame($this->teamA->id, $logs[1]->before_data['team_id']);
        $this->assertSame($this->teamB->id, $logs[1]->after_data['team_id']);

        $this->actingAs($this->admin())
            ->getJson("/api/v1/participants/{$p->id}")
            ->assertJsonCount(2, 'data.team_history');
    }

    public function test_assign_adds_when_participant_has_no_team(): void
    {
        $p = $this->participant();

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/participants/{$p->id}/team", ['team_id' => $this->teamA->id])
            ->assertOk()
            ->assertJsonPath('data.team.name', 'Equipe A');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PARTICIPANT_ADDED_TO_TEAM, 'entity_id' => (string) $p->id]);
    }

    public function test_removal_preserves_history(): void
    {
        $p = $this->participant();
        $this->add($this->teamA, $p)->assertOk();

        $this->actingAs($this->admin())->deleteJson("/api/v1/teams/{$this->teamA->id}/members/{$p->id}")->assertNoContent();

        $membership = TeamMember::query()->where('participant_id', $p->id)->sole();
        $this->assertFalse($membership->active);
        $this->assertNotNull($membership->left_at);

        $this->actingAs($this->admin())->getJson("/api/v1/teams/{$this->teamA->id}/members")->assertJsonCount(0, 'data');
        $this->actingAs($this->admin())
            ->getJson("/api/v1/teams/{$this->teamA->id}/members?history=1")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.active', false);

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PARTICIPANT_REMOVED_FROM_TEAM, 'entity_id' => (string) $p->id]);

        // Pode voltar depois: novo vínculo, histórico mantido.
        $this->add($this->teamA, $p)->assertOk();
        $this->assertSame(2, TeamMember::query()->where('participant_id', $p->id)->count());
    }

    public function test_removing_a_non_member_is_rejected(): void
    {
        $p = $this->participant();

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/teams/{$this->teamA->id}/members/{$p->id}")
            ->assertUnprocessable();
    }

    public function test_participant_and_team_from_different_events_are_rejected_by_api_and_database(): void
    {
        $foreign = Participant::factory()->create();

        $this->add($this->teamA, $foreign)
            ->assertUnprocessable()
            ->assertJsonPath('errors.participant_id.0', 'Participante inexistente ou de outro evento.');

        $p = $this->participant();
        $foreignTeam = Team::factory()->create();
        $this->actingAs($this->admin())
            ->patchJson("/api/v1/participants/{$p->id}/team", ['team_id' => $foreignTeam->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'Equipe inexistente ou de outro evento.');

        $this->expectException(QueryException::class);
        DB::table('team_members')->insert([
            'event_id' => $this->event->id, 'team_id' => $this->teamA->id, 'participant_id' => $foreign->id,
            'joined_at' => now(), 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_unavailable_does_not_reorganize_team(): void
    {
        $p = $this->participant();
        $this->add($this->teamA, $p)->assertOk();

        $this->actingAs($this->admin())->patchJson("/api/v1/participants/{$p->id}", ['status' => 'UNAVAILABLE'])->assertOk()
            ->assertJsonPath('data.status', 'UNAVAILABLE')
            ->assertJsonPath('data.team.id', $this->teamA->id);

        $this->assertSame(1, TeamMember::query()->where('participant_id', $p->id)->where('active', true)->where('team_id', $this->teamA->id)->count());
    }

    public function test_withdrawn_does_not_reorganize_team_automatically(): void
    {
        $p = $this->participant();
        $this->add($this->teamA, $p)->assertOk();

        $this->actingAs($this->admin())->patchJson("/api/v1/participants/{$p->id}", ['status' => 'WITHDRAWN'])->assertOk()
            ->assertJsonPath('data.team.id', $this->teamA->id);

        $this->assertSame(1, TeamMember::query()->where('participant_id', $p->id)->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::PARTICIPANT_REMOVED_FROM_TEAM)->count());

        // Remover continua possível como ação explícita.
        $this->actingAs($this->admin())->deleteJson("/api/v1/teams/{$this->teamA->id}/members/{$p->id}")->assertNoContent();
    }

    public function test_only_available_participants_enter_a_team(): void
    {
        $withdrawn = $this->participant(['status' => ParticipantStatus::Withdrawn]);

        $this->add($this->teamA, $withdrawn)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['participant_id']);
    }

    public function test_inactive_team_does_not_receive_members_and_cannot_be_inactivated_with_members(): void
    {
        $p = $this->participant();
        $this->add($this->teamA, $p)->assertOk();

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/teams/{$this->teamA->id}", ['status' => 'INACTIVE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->actingAs($this->admin())->patchJson("/api/v1/teams/{$this->teamB->id}", ['status' => 'INACTIVE'])->assertOk();
        $this->add($this->teamB, $this->participant())
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'A equipe está inativa.');
    }

    public function test_no_fixed_team_size(): void
    {
        foreach (range(1, 9) as $i) {
            $this->add($this->teamA, $this->participant())->assertOk();
        }

        $this->actingAs($this->admin())->getJson("/api/v1/teams/{$this->teamA->id}")->assertJsonPath('data.members_count', 9);
    }

    public function test_view_and_manage_permissions_per_profile(): void
    {
        $p = $this->participant();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson("/api/v1/events/{$this->event->id}/teams")->assertOk();
            $this->actingAs($user)->getJson("/api/v1/teams/{$this->teamA->id}")->assertOk();
            $this->actingAs($user)->postJson("/api/v1/events/{$this->event->id}/teams", ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->postJson("/api/v1/teams/{$this->teamA->id}/members", ['participant_id' => $p->id])->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/participants/{$p->id}/team", ['team_id' => $this->teamA->id])->assertForbidden();
            $this->actingAs($user)->deleteJson("/api/v1/teams/{$this->teamA->id}/members/{$p->id}")->assertForbidden();
        }

        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson("/api/v1/events/{$this->event->id}/teams")->assertForbidden();
            $this->actingAs($user)->getJson("/api/v1/teams/{$this->teamA->id}")->assertForbidden();
        }

        $this->assertSame(0, TeamMember::query()->count());
    }
}
