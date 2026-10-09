<?php

namespace Tests\Feature\Sectors;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\Event;
use App\Models\Sector;
use Tests\DatabaseTestCase;

class SectorTest extends DatabaseTestCase
{
    private Event $event;

    private Sector $sectorA;

    private Sector $sectorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
        $this->sectorA = Sector::factory()->for($this->event)->create(['name' => 'Produção']);
        $this->sectorB = Sector::factory()->for($this->event)->create(['name' => 'Comunicação']);
    }

    public function test_admin_creates_and_updates_sectors_with_audit(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)
            ->postJson("/api/v1/events/{$this->event->id}/sectors", ['name' => 'Logística', 'description' => 'Apoio'])
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.event_id', $this->event->id)
            ->json('data.id');

        $this->actingAs($admin)
            ->patchJson("/api/v1/sectors/{$id}", ['description' => 'Apoio logístico'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Apoio logístico');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::SECTOR_CREATED, 'entity_id' => (string) $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::SECTOR_UPDATED, 'entity_id' => (string) $id]);
    }

    public function test_sector_name_is_unique_per_event_ignoring_case(): void
    {
        $this->actingAs($this->admin())
            ->postJson("/api/v1/events/{$this->event->id}/sectors", ['name' => '  PRODUÇÃO '])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Já existe um setor com este nome neste evento.');

        $other = Event::factory()->create();
        $this->actingAs($this->admin())
            ->postJson("/api/v1/events/{$other->id}/sectors", ['name' => 'Produção'])
            ->assertCreated();
    }

    public function test_admin_sees_all_sectors(): void
    {
        $this->actingAs($this->admin())
            ->getJson("/api/v1/events/{$this->event->id}/sectors")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_sees_and_manages_only_own_sector(): void
    {
        $manager = $this->sectorUser(RoleCode::Manager, $this->sectorA);

        $this->actingAs($manager)
            ->getJson("/api/v1/events/{$this->event->id}/sectors")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->sectorA->id);

        $this->actingAs($manager)->getJson("/api/v1/sectors/{$this->sectorA->id}")->assertOk();
        $this->actingAs($manager)->getJson("/api/v1/sectors/{$this->sectorB->id}")->assertForbidden();

        $this->actingAs($manager)->patchJson("/api/v1/sectors/{$this->sectorA->id}", ['description' => 'Meu setor'])->assertOk();
        $this->actingAs($manager)->patchJson("/api/v1/sectors/{$this->sectorB->id}", ['description' => 'X'])->assertForbidden();

        $this->actingAs($manager)->postJson("/api/v1/events/{$this->event->id}/sectors", ['name' => 'Novo'])->assertForbidden();
        $this->actingAs($manager)->patchJson("/api/v1/sectors/{$this->sectorA->id}/status", ['active' => false])->assertForbidden();
    }

    public function test_editor_sees_own_sector_but_cannot_change_it(): void
    {
        $editor = $this->sectorUser(RoleCode::Editor, $this->sectorB);

        $this->actingAs($editor)
            ->getJson("/api/v1/events/{$this->event->id}/sectors")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->sectorB->id);

        $this->actingAs($editor)->getJson("/api/v1/sectors/{$this->sectorA->id}")->assertForbidden();
        $this->actingAs($editor)->patchJson("/api/v1/sectors/{$this->sectorB->id}", ['description' => 'X'])->assertForbidden();
    }

    public function test_consultant_has_transversal_view_without_management(): void
    {
        $consultant = $this->userWithRole(RoleCode::Consultant);

        $this->actingAs($consultant)->getJson("/api/v1/events/{$this->event->id}/sectors")->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($consultant)->getJson("/api/v1/sectors/{$this->sectorA->id}")->assertOk();
        $this->actingAs($consultant)->patchJson("/api/v1/sectors/{$this->sectorA->id}", ['description' => 'X'])->assertForbidden();
        $this->actingAs($consultant)->postJson("/api/v1/events/{$this->event->id}/sectors", ['name' => 'Novo'])->assertForbidden();
    }

    public function test_juror_and_voter_do_not_see_sectors(): void
    {
        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $this->actingAs($this->userWithRole($role))->getJson("/api/v1/events/{$this->event->id}/sectors")->assertForbidden();
        }
    }

    public function test_admin_inactivates_and_reactivates_a_sector_without_active_users(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/v1/sectors/{$this->sectorB->id}/status", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->actingAs($admin)->getJson("/api/v1/events/{$this->event->id}/sectors?active=1")->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->patchJson("/api/v1/sectors/{$this->sectorB->id}/status", ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::SECTOR_INACTIVATED, 'entity_id' => (string) $this->sectorB->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::SECTOR_ACTIVATED, 'entity_id' => (string) $this->sectorB->id]);
    }

    public function test_sector_with_active_manager_or_editor_cannot_be_inactivated(): void
    {
        $this->sectorUser(RoleCode::Manager, $this->sectorA);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/sectors/{$this->sectorA->id}/status", ['active' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['active']);

        $this->assertTrue($this->sectorA->fresh()->active);
    }
}
