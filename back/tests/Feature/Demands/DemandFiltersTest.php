<?php

namespace Tests\Feature\Demands;

use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Occurrences\Enums\OccurrenceCategory;
use App\Models\EventDay;
use App\Models\Occurrence;
use App\Models\Task;
use App\Models\Team;
use Tests\Concerns\DemandScenario;
use Tests\DatabaseTestCase;

class DemandFiltersTest extends DatabaseTestCase
{
    use DemandScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDemandScenario();
    }

    private function ids($user, string $url): array
    {
        return collect($this->actingAs($user)->getJson($url)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_task_filters_and_pagination(): void
    {
        $a = Task::factory()->between($this->sectorA)->create(['title' => 'Comprar cabos', 'priority' => DemandPriority::High, 'due_at' => now()->subDay(), 'assigned_user_id' => $this->manager('A')->id]);
        $b = Task::factory()->between($this->sectorA, $this->sectorB)->create(['due_at' => now()->addDays(5)]);
        $b->involvedSectors()->attach($this->sectorC->id, ['event_id' => $this->event->id]);
        $c = Task::factory()->between($this->sectorC)->create();
        $url = "/api/v1/events/{$this->event->id}/tasks";

        $this->assertSame([$a->id], $this->ids($this->adminUser, "{$url}?search=cabos"));
        $this->assertSame([$a->id], $this->ids($this->adminUser, "{$url}?search={$a->fresh()->reference}"));
        $this->assertSame([$a->id], $this->ids($this->adminUser, "{$url}?priority=HIGH"));
        $this->assertSame([$a->id, $b->id], $this->ids($this->adminUser, "{$url}?origin_sector_id={$this->sectorA->id}"));
        $this->assertSame([$b->id], $this->ids($this->adminUser, "{$url}?responsible_sector_id={$this->sectorB->id}"));
        $this->assertSame([$b->id], $this->ids($this->adminUser, "{$url}?involved_sector_id={$this->sectorC->id}"));
        $this->assertSame([$a->id], $this->ids($this->adminUser, "{$url}?assigned_user_id={$this->manager('A')->id}"));
        $this->assertSame([$a->id], $this->ids($this->adminUser, "{$url}?overdue=1"));
        $this->assertSame([$b->id], $this->ids($this->adminUser, $url.'?due_after='.now()->addDay()->toDateString()));
        $this->assertSame([$a->id], $this->ids($this->adminUser, $url.'?due_before='.now()->toDateString()));
        $this->assertSame([$a->id, $b->id, $c->id], $this->ids($this->adminUser, "{$url}?status=PENDING"));

        // Visibilidade antes dos filtros: o setor C vê a própria e a que está envolvido.
        $this->assertSame([$b->id, $c->id], $this->ids($this->manager('C'), $url));
        $this->assertSame([], $this->ids($this->manager('C'), "{$url}?origin_sector_id={$this->sectorA->id}&responsible_sector_id={$this->sectorA->id}"));

        $this->actingAs($this->adminUser)->getJson("{$url}?per_page=2")->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $this->actingAs($this->adminUser)->getJson("{$url}?search=cabos")->assertJsonPath('data.0.overdue', true);
    }

    public function test_occurrence_filters_and_pagination(): void
    {
        $day = EventDay::factory()->for($this->event)->create(['day_number' => 1, 'date' => $this->event->start_date->toDateString()]);
        $team = Team::factory()->for($this->event)->create();
        $a = Occurrence::factory()->between($this->sectorA)->create(['title' => 'Projetor', 'category' => OccurrenceCategory::Technology, 'event_day_id' => $day->id, 'occurred_at' => now()->subHours(2)]);
        $b = Occurrence::factory()->between($this->sectorA, $this->sectorB)->create(['team_id' => $team->id, 'occurred_at' => now()->addHour()]);
        $c = Occurrence::factory()->between($this->sectorD)->create();
        $url = "/api/v1/events/{$this->event->id}/occurrences";

        $this->assertSame([$a->id], $this->ids($this->adminUser, "{$url}?search=projetor"));
        $this->assertSame([$a->id], $this->ids($this->adminUser, "{$url}?category=TECHNOLOGY"));
        $this->assertSame([$a->id], $this->ids($this->adminUser, "{$url}?event_day_id={$day->id}"));
        $this->assertSame([$b->id], $this->ids($this->adminUser, "{$url}?team_id={$team->id}"));
        $this->assertSame([$b->id], $this->ids($this->adminUser, "{$url}?responsible_sector_id={$this->sectorB->id}"));
        $this->assertSame([$a->id], $this->ids($this->adminUser, $url.'?occurred_before='.urlencode(now()->toIso8601String())));
        $this->assertSame([$b->id], $this->ids($this->adminUser, $url.'?occurred_after='.urlencode(now()->toIso8601String())));
        $this->assertSame([$a->id, $b->id, $c->id], $this->ids($this->adminUser, "{$url}?status=OPEN"));

        $this->assertSame([$a->id, $b->id], $this->ids($this->manager('A'), $url));
        $this->assertSame([$c->id], $this->ids($this->manager('D'), $url));

        $this->actingAs($this->adminUser)->getJson("{$url}?per_page=1")->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3);
    }
}
