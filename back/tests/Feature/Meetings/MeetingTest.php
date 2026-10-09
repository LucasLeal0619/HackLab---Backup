<?php

namespace Tests\Feature\Meetings;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Sector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

class MeetingTest extends DatabaseTestCase
{
    private Event $event;

    private Sector $sectorA;

    private Sector $sectorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->sectorA = Sector::factory()->for($this->event)->create();
        $this->sectorB = Sector::factory()->for($this->event)->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Alinhamento',
            'scheduled_at' => '2026-11-05T14:00:00-03:00',
            'location' => 'Sala 1',
        ], $overrides);
    }

    private function meetings(): string
    {
        return "/api/v1/events/{$this->event->id}/meetings";
    }

    public function test_admin_creates_general_and_sector_meetings_with_audit(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson($this->meetings(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.scope', 'GENERAL')
            ->assertJsonPath('data.sector', null)
            ->assertJsonPath('data.status', 'SCHEDULED')
            ->assertJsonPath('data.created_by.id', $admin->id);

        $this->actingAs($admin)
            ->postJson($this->meetings(), $this->payload(['sector_id' => $this->sectorA->id]))
            ->assertCreated()
            ->assertJsonPath('data.scope', 'SECTOR')
            ->assertJsonPath('data.sector.id', $this->sectorA->id);

        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::MEETING_CREATED)->count());
    }

    public function test_manager_manages_meetings_of_own_sector_only(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sectorA);

        $this->actingAs($manager)
            ->postJson($this->meetings(), $this->payload(['sector_id' => $this->sectorA->id]))
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson($this->meetings(), $this->payload(['sector_id' => $this->sectorB->id]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson($this->meetings(), $this->payload())
            ->assertForbidden();
    }

    public function test_general_meetings_are_visible_but_only_global_scope_edits_them(): void
    {
        $general = Meeting::factory()->for($this->event)->create(['title' => 'Geral']);
        $manager = $this->sectorUser(RoleCode::Manager, $this->sectorA);
        $editor = $this->sectorUser(RoleCode::Editor, $this->sectorB);

        $this->actingAs($manager)->getJson("/api/v1/meetings/{$general->id}")->assertOk();
        $this->actingAs($editor)->getJson("/api/v1/meetings/{$general->id}")->assertOk();
        $this->actingAs($manager)->patchJson("/api/v1/meetings/{$general->id}", ['title' => 'X'])->assertForbidden();

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/meetings/{$general->id}", ['title' => 'Geral atualizada', 'status' => 'DONE'])
            ->assertOk()
            ->assertJsonPath('data.status', 'DONE');

        $log = AuditLog::query()->where('action', AuditAction::MEETING_UPDATED)->sole();
        $this->assertSame('Geral', $log->before_data['title']);
    }

    public function test_meeting_of_another_sector_returns_403(): void
    {
        $meetingB = Meeting::factory()->forSector($this->sectorB)->create();

        $this->actingAs($this->sectorUser(RoleCode::Manager, $this->sectorA))->getJson("/api/v1/meetings/{$meetingB->id}")->assertForbidden();
        $this->actingAs($this->sectorUser(RoleCode::Editor, $this->sectorA))->getJson("/api/v1/meetings/{$meetingB->id}")->assertForbidden();
        $this->actingAs($this->sectorUser(RoleCode::Manager, $this->sectorA))->patchJson("/api/v1/meetings/{$meetingB->id}", ['title' => 'X'])->assertForbidden();
    }

    public function test_list_is_scoped_to_general_plus_own_sector(): void
    {
        Meeting::factory()->for($this->event)->create(['title' => 'Geral']);
        Meeting::factory()->forSector($this->sectorA)->create(['title' => 'Do A']);
        Meeting::factory()->forSector($this->sectorB)->create(['title' => 'Do B']);

        $titles = fn ($user) => collect($this->actingAs($user)->getJson($this->meetings())->assertOk()->json('data'))->pluck('title')->sort()->values()->all();

        $this->assertSame(['Do A', 'Geral'], $titles($this->sectorUser(RoleCode::Manager, $this->sectorA)));
        $this->assertSame(['Do B', 'Geral'], $titles($this->sectorUser(RoleCode::Editor, $this->sectorB)));
        $this->assertSame(['Do A', 'Do B', 'Geral'], $titles($this->userWithRole(RoleCode::Consultant)));
        $this->assertSame(['Do A', 'Do B', 'Geral'], $titles($this->admin()));
    }

    public function test_editor_views_but_does_not_manage_own_sector_meetings(): void
    {
        $editor = $this->sectorUser(RoleCode::Editor, $this->sectorA);
        $meetingA = Meeting::factory()->forSector($this->sectorA)->create();

        $this->actingAs($editor)->getJson("/api/v1/meetings/{$meetingA->id}")->assertOk();
        $this->actingAs($editor)->patchJson("/api/v1/meetings/{$meetingA->id}", ['title' => 'X'])->assertForbidden();
        $this->actingAs($editor)->postJson($this->meetings(), $this->payload(['sector_id' => $this->sectorA->id]))->assertForbidden();
    }

    public function test_consultant_views_all_but_manages_none(): void
    {
        $consultant = $this->userWithRole(RoleCode::Consultant);
        $meetingA = Meeting::factory()->forSector($this->sectorA)->create();

        $this->actingAs($consultant)->getJson("/api/v1/meetings/{$meetingA->id}")->assertOk();
        $this->actingAs($consultant)->postJson($this->meetings(), $this->payload())->assertForbidden();
        $this->actingAs($consultant)->patchJson("/api/v1/meetings/{$meetingA->id}", ['title' => 'X'])->assertForbidden();
    }

    public function test_juror_and_voter_have_no_access_to_meetings(): void
    {
        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $this->actingAs($this->userWithRole($role))->getJson($this->meetings())->assertForbidden();
        }
    }

    public function test_manager_cannot_move_meeting_to_another_sector_or_make_it_general(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sectorA);
        $meetingA = Meeting::factory()->forSector($this->sectorA)->create();

        $this->actingAs($manager)->patchJson("/api/v1/meetings/{$meetingA->id}", ['sector_id' => $this->sectorB->id])->assertForbidden();
        $this->actingAs($manager)->patchJson("/api/v1/meetings/{$meetingA->id}", ['sector_id' => null])->assertForbidden();
        $this->actingAs($manager)->patchJson("/api/v1/meetings/{$meetingA->id}", ['title' => 'Novo título'])->assertOk();

        $this->assertSame($this->sectorA->id, $meetingA->fresh()->sector_id);
    }

    public function test_sector_from_another_event_or_inactive_is_rejected(): void
    {
        $foreign = Sector::factory()->create();
        $inactive = Sector::factory()->for($this->event)->inactive()->create();

        $this->actingAs($this->admin())
            ->postJson($this->meetings(), $this->payload(['sector_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.sector_id.0', 'Setor inexistente, inativo ou de outro evento.');

        $this->actingAs($this->admin())
            ->postJson($this->meetings(), $this->payload(['sector_id' => $inactive->id]))
            ->assertUnprocessable();
    }

    public function test_database_rejects_meeting_with_sector_of_another_event(): void
    {
        $foreign = Sector::factory()->create();
        $meeting = Meeting::factory()->for($this->event)->create();

        $this->expectException(QueryException::class);
        DB::table('meetings')->where('id', $meeting->id)->update(['sector_id' => $foreign->id]);
    }

    public function test_status_cannot_be_set_on_creation(): void
    {
        $this->actingAs($this->admin())
            ->postJson($this->meetings(), $this->payload(['status' => 'DONE']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
