<?php

namespace Tests\Feature\Participants;

use App\Domain\Audit\AuditAction;
use App\Domain\Participants\Enums\ParticipantStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Person;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\DatabaseTestCase;

class ParticipantTest extends DatabaseTestCase
{
    private Event $event;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->class = SchoolClass::factory()->for($this->event)->create(['name' => 'Turma A']);
    }

    private function url(): string
    {
        return "/api/v1/events/{$this->event->id}/participants";
    }

    public function test_participant_is_created_with_a_new_person_without_duplicating_identity(): void
    {
        $this->actingAs($this->admin())
            ->postJson($this->url(), [
                'person' => ['full_name' => 'Ana Lima', 'email' => 'ana@hacklab.test', 'phone' => '11999990000'],
                'class_id' => $this->class->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'AVAILABLE')
            ->assertJsonPath('data.person.full_name', 'Ana Lima')
            ->assertJsonPath('data.class.name', 'Turma A')
            ->assertJsonPath('data.team', null);

        $participant = Participant::query()->sole();
        $this->assertSame('ana@hacklab.test', $participant->person->email);
        $this->assertFalse(Schema::hasColumn('participants', 'email'));
        $this->assertFalse(Schema::hasColumn('participants', 'full_name'));

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PARTICIPANT_CREATED, 'entity_id' => (string) $participant->id]);
    }

    public function test_participant_from_existing_person_and_without_class(): void
    {
        $person = Person::factory()->create();

        $this->actingAs($this->admin())
            ->postJson($this->url(), ['person_id' => $person->id])
            ->assertCreated()
            ->assertJsonPath('data.person.id', $person->id)
            ->assertJsonPath('data.class', null);
    }

    public function test_person_is_unique_per_event(): void
    {
        $person = Person::factory()->create();
        Participant::factory()->create(['event_id' => $this->event->id, 'person_id' => $person->id]);

        $this->actingAs($this->admin())
            ->postJson($this->url(), ['person_id' => $person->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.person_id.0', 'Esta pessoa já é participante deste evento.');

        // Mesma pessoa em outro evento é permitido.
        $other = Event::factory()->create();
        $this->actingAs($this->admin())
            ->postJson("/api/v1/events/{$other->id}/participants", ['person_id' => $person->id])
            ->assertCreated();

        $this->expectException(QueryException::class);
        Participant::factory()->create(['event_id' => $this->event->id, 'person_id' => $person->id]);
    }

    public function test_inactive_class_does_not_receive_new_participants(): void
    {
        $inactive = SchoolClass::factory()->for($this->event)->inactive()->create();

        $this->actingAs($this->admin())
            ->postJson($this->url(), ['person' => ['full_name' => 'X'], 'class_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.class_id.0', 'Turma inexistente, inativa ou de outro evento.');

        $participant = Participant::factory()->create(['event_id' => $this->event->id]);
        $this->actingAs($this->admin())
            ->patchJson("/api/v1/participants/{$participant->id}", ['class_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['class_id']);
    }

    public function test_participant_keeps_its_class_after_class_is_inactivated(): void
    {
        $participant = Participant::factory()->inClass($this->class)->create();
        $this->class->update(['active' => false]);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/participants/{$participant->id}", ['class_id' => $this->class->id, 'notes' => 'ok'])
            ->assertOk()
            ->assertJsonPath('data.class.id', $this->class->id);
    }

    public function test_class_from_another_event_is_rejected_by_api_and_database(): void
    {
        $foreignClass = SchoolClass::factory()->create();

        $this->actingAs($this->admin())
            ->postJson($this->url(), ['person' => ['full_name' => 'X'], 'class_id' => $foreignClass->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['class_id']);

        $participant = Participant::factory()->create(['event_id' => $this->event->id]);
        $this->expectException(QueryException::class);
        DB::table('participants')->where('id', $participant->id)->update(['class_id' => $foreignClass->id]);
    }

    public function test_only_valid_statuses_are_accepted(): void
    {
        $participant = Participant::factory()->create(['event_id' => $this->event->id]);

        foreach (['AVAILABLE', 'UNAVAILABLE', 'WITHDRAWN'] as $status) {
            $this->actingAs($this->admin())
                ->patchJson("/api/v1/participants/{$participant->id}", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/participants/{$participant->id}", ['status' => 'DESISTENTE'])
            ->assertUnprocessable();

        $this->expectException(QueryException::class);
        DB::table('participants')->where('id', $participant->id)->update(['status' => 'INVALIDO']);
    }

    public function test_status_change_is_a_single_specific_audit_log(): void
    {
        $participant = Participant::factory()->create(['event_id' => $this->event->id]);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/participants/{$participant->id}", ['status' => 'UNAVAILABLE', 'notes' => 'Doente no dia 1'])
            ->assertOk();

        $logs = AuditLog::query()->whereIn('action', [AuditAction::PARTICIPANT_STATUS_CHANGED, AuditAction::PARTICIPANT_UPDATED])->get();
        $this->assertCount(1, $logs);
        $this->assertSame(AuditAction::PARTICIPANT_STATUS_CHANGED, $logs[0]->action);
        $this->assertSame('AVAILABLE', $logs[0]->before_data['status']);
        $this->assertSame('UNAVAILABLE', $logs[0]->after_data['status']);

        $this->actingAs($this->admin())->patchJson("/api/v1/participants/{$participant->id}", ['notes' => 'Melhorou'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PARTICIPANT_UPDATED, 'entity_id' => (string) $participant->id]);
    }

    public function test_filters_by_class_status_and_team(): void
    {
        $classB = SchoolClass::factory()->for($this->event)->create(['name' => 'Turma B']);
        $team = Team::factory()->for($this->event)->create();

        $inTeam = Participant::factory()->inClass($this->class)->create();
        TeamMember::query()->create(['event_id' => $this->event->id, 'team_id' => $team->id, 'participant_id' => $inTeam->id, 'joined_at' => now(), 'active' => true]);
        Participant::factory()->inClass($this->class)->status(ParticipantStatus::Unavailable)->create();
        Participant::factory()->inClass($classB)->create();
        Participant::factory()->create(); // outro evento

        $admin = $this->admin();
        $count = fn (string $query) => count($this->actingAs($admin)->getJson($this->url().$query)->assertOk()->json('data'));

        $this->assertSame(3, $count(''));
        $this->assertSame(2, $count("?class_id={$this->class->id}"));
        $this->assertSame(1, $count("?class_id={$classB->id}"));
        $this->assertSame(1, $count('?status=UNAVAILABLE'));
        $this->assertSame(1, $count('?has_team=1'));
        $this->assertSame(2, $count('?has_team=0'));
        $this->assertSame(1, $count("?team_id={$team->id}"));

        $this->actingAs($admin)->getJson($this->url().'?has_team=1')->assertJsonPath('data.0.team.id', $team->id);
        $this->actingAs($admin)->getJson($this->url().'?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
    }

    public function test_search_by_person_name(): void
    {
        Participant::factory()->create(['event_id' => $this->event->id, 'person_id' => Person::factory()->create(['full_name' => 'Bruno Souza'])->id]);
        Participant::factory()->create(['event_id' => $this->event->id, 'person_id' => Person::factory()->create(['full_name' => 'Carla Dias'])->id]);

        $this->actingAs($this->admin())
            ->getJson($this->url().'?search=bruno')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.person.full_name', 'Bruno Souza');
    }

    public function test_view_and_manage_permissions_per_profile(): void
    {
        $participant = Participant::factory()->create(['event_id' => $this->event->id]);

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson($this->url())->assertOk();
            $this->actingAs($user)->getJson("/api/v1/participants/{$participant->id}")->assertOk();
            $this->actingAs($user)->postJson($this->url(), ['person' => ['full_name' => 'X']])->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/participants/{$participant->id}", ['status' => 'WITHDRAWN'])->assertForbidden();
        }

        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson($this->url())->assertForbidden();
            $this->actingAs($user)->getJson("/api/v1/participants/{$participant->id}")->assertForbidden();
        }
    }
}
