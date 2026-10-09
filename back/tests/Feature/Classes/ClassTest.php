<?php

namespace Tests\Feature\Classes;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Participant;
use App\Models\SchoolClass;
use Tests\DatabaseTestCase;

class ClassTest extends DatabaseTestCase
{
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create();
    }

    public function test_admin_creates_any_number_of_classes_in_the_event(): void
    {
        $admin = $this->admin();

        foreach (['Turma Manhã', 'Turma Tarde', 'Turma Noite', 'Turma Sábado'] as $name) {
            $this->actingAs($admin)
                ->postJson("/api/v1/events/{$this->event->id}/classes", ['name' => $name])
                ->assertCreated()
                ->assertJsonPath('data.event_id', $this->event->id)
                ->assertJsonPath('data.active', true);
        }

        $this->actingAs($admin)
            ->getJson("/api/v1/events/{$this->event->id}/classes")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.participants_count', 0);

        $this->assertSame(4, AuditLog::query()->where('action', AuditAction::CLASS_CREATED)->count());
    }

    public function test_class_name_is_unique_per_event_ignoring_case(): void
    {
        SchoolClass::factory()->for($this->event)->create(['name' => 'Turma A']);

        $this->actingAs($this->admin())
            ->postJson("/api/v1/events/{$this->event->id}/classes", ['name' => ' turma a '])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Já existe uma turma com este nome neste evento.');

        $this->actingAs($this->admin())
            ->postJson('/api/v1/events/'.Event::factory()->create()->id.'/classes', ['name' => 'Turma A'])
            ->assertCreated();
    }

    public function test_classes_listed_belong_to_the_event(): void
    {
        SchoolClass::factory()->for($this->event)->create(['name' => 'Daqui']);
        SchoolClass::factory()->create(['name' => 'De outro evento']);

        $this->actingAs($this->admin())
            ->getJson("/api/v1/events/{$this->event->id}/classes")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Daqui');
    }

    public function test_class_is_updated_inactivated_and_reactivated_with_audit(): void
    {
        $class = SchoolClass::factory()->for($this->event)->create(['name' => 'Turma A']);
        Participant::factory()->inClass($class)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/api/v1/classes/{$class->id}", ['name' => 'Turma Alfa'])->assertOk()->assertJsonPath('data.name', 'Turma Alfa');
        $this->actingAs($admin)->patchJson("/api/v1/classes/{$class->id}/status", ['active' => false])->assertOk()->assertJsonPath('data.active', false);

        // Inativa continua existindo (histórico) com seus participantes.
        $this->actingAs($admin)->getJson("/api/v1/classes/{$class->id}")->assertOk()->assertJsonPath('data.participants_count', 1);

        $this->actingAs($admin)->patchJson("/api/v1/classes/{$class->id}/status", ['active' => true])->assertOk()->assertJsonPath('data.active', true);

        foreach ([AuditAction::CLASS_UPDATED, AuditAction::CLASS_INACTIVATED, AuditAction::CLASS_ACTIVATED] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'entity_id' => (string) $class->id]);
        }
    }

    public function test_view_and_manage_permissions_per_profile(): void
    {
        $class = SchoolClass::factory()->for($this->event)->create();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->getJson("/api/v1/events/{$this->event->id}/classes")->assertOk();
            $this->actingAs($user)->postJson("/api/v1/events/{$this->event->id}/classes", ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/classes/{$class->id}/status", ['active' => false])->assertForbidden();
        }

        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $this->actingAs($this->userWithRole($role))->getJson("/api/v1/events/{$this->event->id}/classes")->assertForbidden();
        }
    }
}
