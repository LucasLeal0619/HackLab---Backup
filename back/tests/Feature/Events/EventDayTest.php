<?php

namespace Tests\Feature\Events;

use App\Domain\Audit\AuditAction;
use App\Domain\Users\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventDay;
use Illuminate\Database\QueryException;
use Tests\DatabaseTestCase;

class EventDayTest extends DatabaseTestCase
{
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['start_date' => '2026-11-10', 'end_date' => '2026-11-14']);
    }

    private function addDay(array $data)
    {
        return $this->actingAs($this->admin())->postJson("/api/v1/events/{$this->event->id}/days", $data);
    }

    public function test_number_of_days_is_not_fixed(): void
    {
        $admin = $this->admin();

        foreach (range(1, 5) as $n) {
            $this->actingAs($admin)
                ->postJson("/api/v1/events/{$this->event->id}/days", [
                    'day_number' => $n, 'date' => sprintf('2026-11-%02d', 9 + $n), 'label' => "Dia {$n}",
                ])
                ->assertCreated();
        }

        $this->actingAs($admin)
            ->getJson("/api/v1/events/{$this->event->id}/days")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.4.day_number', 5);

        $this->actingAs($admin)->getJson("/api/v1/events/{$this->event->id}")->assertJsonCount(5, 'data.days');
    }

    public function test_day_with_times_is_created_and_audited(): void
    {
        $this->addDay(['day_number' => 1, 'date' => '2026-11-10', 'label' => 'Abertura', 'start_time' => '08:00', 'end_time' => '18:00'])
            ->assertCreated()
            ->assertJsonPath('data.start_time', '08:00')
            ->assertJsonPath('data.end_time', '18:00');

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::EVENT_DAY_CREATED]);
    }

    public function test_duplicate_day_number_or_date_is_rejected(): void
    {
        EventDay::factory()->for($this->event)->create(['day_number' => 1, 'date' => '2026-11-10', 'label' => 'Dia 1']);

        $this->addDay(['day_number' => 1, 'date' => '2026-11-11', 'label' => 'Outro'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.day_number.0', 'Já existe um dia com este número neste evento.');

        $this->addDay(['day_number' => 2, 'date' => '2026-11-10', 'label' => 'Outro'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'Já existe um dia com esta data neste evento.');
    }

    public function test_same_day_number_is_allowed_in_another_event(): void
    {
        $other = Event::factory()->create(['start_date' => '2026-12-01', 'end_date' => '2026-12-03']);
        EventDay::factory()->for($other)->create(['day_number' => 1, 'date' => '2026-12-01']);

        $this->addDay(['day_number' => 1, 'date' => '2026-11-10', 'label' => 'Dia 1'])->assertCreated();
    }

    public function test_database_rejects_duplicate_day_even_without_the_api(): void
    {
        EventDay::factory()->for($this->event)->create(['day_number' => 1, 'date' => '2026-11-10']);

        $this->expectException(QueryException::class);
        EventDay::factory()->for($this->event)->create(['day_number' => 1, 'date' => '2026-11-11']);
    }

    public function test_day_must_be_inside_the_event_period(): void
    {
        $this->addDay(['day_number' => 1, 'date' => '2026-11-20', 'label' => 'Fora'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->addDay(['day_number' => 1, 'date' => '2026-11-10', 'label' => 'Dia', 'start_time' => '18:00', 'end_time' => '08:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_day_is_updated_with_audit_and_scoped_to_its_event(): void
    {
        $day = EventDay::factory()->for($this->event)->create(['day_number' => 1, 'date' => '2026-11-10', 'label' => 'Dia 1']);

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/events/{$this->event->id}/days/{$day->id}", ['label' => 'Abertura'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Abertura');

        $log = AuditLog::query()->where('action', AuditAction::EVENT_DAY_UPDATED)->sole();
        $this->assertSame('Dia 1', $log->before_data['label']);

        $other = Event::factory()->create();
        $this->actingAs($this->admin())
            ->patchJson("/api/v1/events/{$other->id}/days/{$day->id}", ['label' => 'X'])
            ->assertNotFound();
    }

    public function test_manager_cannot_manage_days(): void
    {
        $this->actingAs($this->userWithRole(RoleCode::Manager))
            ->postJson("/api/v1/events/{$this->event->id}/days", ['day_number' => 1, 'date' => '2026-11-10', 'label' => 'Dia 1'])
            ->assertForbidden();
    }
}
