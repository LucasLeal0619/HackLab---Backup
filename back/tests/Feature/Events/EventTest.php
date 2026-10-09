<?php

namespace Tests\Feature\Events;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventDay;
use Tests\DatabaseTestCase;

class EventTest extends DatabaseTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'HackLab 2026',
            'description' => 'Hackathon do Senac',
            'location' => 'Senac',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
        ], $overrides);
    }

    public function test_admin_creates_lists_shows_and_updates_an_event(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)
            ->postJson('/api/v1/events', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'HackLab 2026')
            ->assertJsonPath('data.status', 'PLANNED')
            ->assertJsonPath('data.days', [])
            ->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/events')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($admin)->getJson("/api/v1/events/{$id}")->assertOk()->assertJsonPath('data.start_date', '2026-11-10');

        $this->actingAs($admin)
            ->patchJson("/api/v1/events/{$id}", ['status' => 'ACTIVE', 'location' => 'Senac Centro'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.location', 'Senac Centro');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::EVENT_CREATED, 'entity_id' => (string) $id]);
        $updated = AuditLog::query()->where('action', AuditAction::EVENT_UPDATED)->sole();
        $this->assertSame('PLANNED', $updated->before_data['status']);
        $this->assertSame('ACTIVE', $updated->after_data['status']);
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/events', $this->payload(['end_date' => '2026-11-09']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $event = Event::factory()->create(['start_date' => '2026-11-10', 'end_date' => '2026-11-12']);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/events/{$event->id}", ['end_date' => '2026-11-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_changing_dates_cannot_leave_days_outside_the_event(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-11-10', 'end_date' => '2026-11-12']);
        EventDay::factory()->for($event)->create(['day_number' => 3, 'date' => '2026-11-12', 'label' => 'Dia 3']);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/events/{$event->id}", ['end_date' => '2026-11-11'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        $this->assertSame('2026-11-12', $event->fresh()->end_date->toDateString());
    }

    public function test_only_global_admin_manages_events(): void
    {
        $event = Event::factory()->create();

        foreach ([RoleCode::Manager, RoleCode::Editor, RoleCode::Consultant, RoleCode::Juror, RoleCode::Voter] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->postJson('/api/v1/events', $this->payload())->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/events/{$event->id}", ['name' => 'X'])->assertForbidden();
        }

        $this->assertSame(0, Event::query()->where('name', 'HackLab 2026')->count());
        $this->assertSame($event->name, $event->fresh()->name);
    }

    public function test_every_profile_can_view_events(): void
    {
        $event = Event::factory()->create();

        foreach (RoleCode::cases() as $role) {
            $this->actingAs($this->userWithRole($role))->getJson("/api/v1/events/{$event->id}")->assertOk();
        }
    }

    public function test_events_require_authentication(): void
    {
        $this->getJson('/api/v1/events')->assertUnauthorized();
    }
}
