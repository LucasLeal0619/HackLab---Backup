<?php

namespace Tests\Concerns;

use App\Domain\Jurors\JurorService;
use App\Domain\Users\Enums\RoleCode;
use App\Models\EvaluationCriterion;
use App\Models\Event;
use App\Models\Juror;
use App\Models\JurorTeamAssignment;
use App\Models\Person;
use App\Models\Team;
use App\Models\User;

/**
 * Cenário de jurados: equipes T1–T3, critérios (0–10 peso 3; 1–5 peso 2), jurados com conta
 * (perfil JUROR, mesma Person) e um sem conta, além dos demais perfis.
 */
trait JuryScenario
{
    protected Event $event;

    protected Team $team1;

    protected Team $team2;

    protected Team $team3;

    protected EvaluationCriterion $c1;

    protected EvaluationCriterion $c2;

    protected Juror $jurorA;

    protected Juror $jurorB;

    protected Juror $jurorNoAccount;

    protected User $userA;

    protected User $userB;

    protected User $adminUser;

    /**
     * Chamado explicitamente no setUp (não usar setUpJuryScenario: o Laravel chamaria sozinho, antes do seed).
     */
    protected function buildJuryScenario(): void
    {
        $this->event = Event::factory()->create();
        $this->team1 = Team::factory()->for($this->event)->create(['name' => 'Equipe 1']);
        $this->team2 = Team::factory()->for($this->event)->create(['name' => 'Equipe 2']);
        $this->team3 = Team::factory()->for($this->event)->create(['name' => 'Equipe 3']);

        $this->c1 = EvaluationCriterion::factory()->for($this->event)->create(['name' => 'Inovação', 'min_score' => 0, 'max_score' => 10, 'weight' => 3, 'sort_order' => 1]);
        $this->c2 = EvaluationCriterion::factory()->for($this->event)->create(['name' => 'Viabilidade', 'min_score' => 1, 'max_score' => 5, 'weight' => 2, 'sort_order' => 2]);

        [$this->jurorA, $this->userA] = $this->jurorWithAccount('Jurada A');
        [$this->jurorB, $this->userB] = $this->jurorWithAccount('Jurado B');
        $this->jurorNoAccount = Juror::factory()->for($this->event)->create();

        $this->adminUser = $this->admin();
    }

    /**
     * @return array{0: Juror, 1: User}
     */
    protected function jurorWithAccount(string $name): array
    {
        $person = Person::factory()->create(['full_name' => $name]);
        $juror = Juror::factory()->for($this->event)->create(['person_id' => $person->id]);
        $user = User::factory()->role(RoleCode::Juror)->create(['person_id' => $person->id]);

        return [$juror, $user];
    }

    /**
     * @param  list<Team>  $teams
     */
    protected function assign(Juror $juror, array $teams): void
    {
        app(JurorService::class)->syncAssignments($juror, array_map(fn (Team $t) => $t->id, $teams), $this->adminUser);
    }

    protected function assignmentOf(Juror $juror, Team $team): JurorTeamAssignment
    {
        return JurorTeamAssignment::query()->where('juror_id', $juror->id)->where('team_id', $team->id)->firstOrFail();
    }

    /**
     * @return list<array{criterion_id: int, score: float|int|null}>
     */
    protected function fullScores(float $s1 = 8, float $s2 = 4): array
    {
        return [['criterion_id' => $this->c1->id, 'score' => $s1], ['criterion_id' => $this->c2->id, 'score' => $s2]];
    }
}
