<?php

namespace Tests\Feature\People;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\Person;
use Tests\DatabaseTestCase;

class PersonTest extends DatabaseTestCase
{
    public function test_admin_registers_a_person_without_account(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/people', ['full_name' => 'João Lima', 'email' => 'JOAO@hacklab.test'])
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'João Lima')
            ->assertJsonPath('data.email', 'joao@hacklab.test')
            ->assertJsonPath('data.has_account', false);

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PERSON_CREATED]);
    }

    public function test_people_email_is_not_unique(): void
    {
        Person::factory()->create(['email' => 'familia@hacklab.test']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/people', ['full_name' => 'Outra Pessoa', 'email' => 'familia@hacklab.test'])
            ->assertCreated();
    }

    public function test_manager_editor_and_consultant_view_people_but_cannot_register(): void
    {
        Person::factory()->count(2)->create();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->getJson('/api/v1/people')->assertOk();
            $this->actingAs($user)->postJson('/api/v1/people', ['full_name' => 'X'])->assertForbidden();
        }
    }

    public function test_juror_and_voter_cannot_list_people(): void
    {
        foreach ([RoleCode::Juror, RoleCode::Voter] as $role) {
            $this->actingAs($this->userWithRole($role))->getJson('/api/v1/people')->assertForbidden();
        }
    }

    public function test_user_sees_own_person_record(): void
    {
        $voter = $this->userWithRole(RoleCode::Voter);

        $this->actingAs($voter)
            ->getJson("/api/v1/people/{$voter->person_id}")
            ->assertOk()
            ->assertJsonPath('data.has_account', true);
    }

    public function test_admin_updates_person_with_audit(): void
    {
        $person = Person::factory()->create(['full_name' => 'Nome Antigo']);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/people/{$person->id}", ['full_name' => 'Nome Novo'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Nome Novo');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PERSON_UPDATED, 'entity_id' => (string) $person->id]);
    }
}
