<?php

namespace App\Domain\Evaluations;

use App\Domain\Evaluations\Enums\EvaluationStatus;
use App\Domain\Jurors\Enums\AssignmentStatus;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\Event;
use App\Models\JurorTeamAssignment;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Cálculos derivados (nunca persistidos) da avaliação técnica. Não usa voto público.
 *
 * normalizado = (score - min) / (max - min)
 * percentual  = 100 * Σ(normalizado * peso) / Σ(peso), sobre os critérios ativos
 */
class EvaluationScoring
{
    /**
     * @return Collection<int, EvaluationCriterion>
     */
    public function activeCriteria(int $eventId): Collection
    {
        return EvaluationCriterion::query()->where('event_id', $eventId)->where('active', true)->orderBy('sort_order')->get()->keyBy('id');
    }

    /**
     * Percentual da avaliação (0–100) ou null se faltar nota em algum critério ativo.
     *
     * @param  Collection<int, EvaluationCriterion>|null  $criteria
     */
    public function percent(Evaluation $evaluation, ?Collection $criteria = null): ?float
    {
        $criteria ??= $this->activeCriteria($evaluation->event_id);
        $scores = $evaluation->scores->keyBy('criterion_id');

        if ($criteria->isEmpty()) {
            return null;
        }

        $weighted = 0.0;
        $weights = 0.0;

        foreach ($criteria as $criterion) {
            $score = $scores->get($criterion->id);

            if ($score === null) {
                return null;
            }

            $min = (float) $criterion->min_score;
            $max = (float) $criterion->max_score;
            $weight = (float) $criterion->weight;

            $weighted += (((float) $score->score - $min) / ($max - $min)) * $weight;
            $weights += $weight;
        }

        return 100 * $weighted / $weights;
    }

    /**
     * Resultado técnico por equipe: média dos percentuais das avaliações SUBMITTED com atribuição ACTIVE.
     * Não é o resultado final do Hackathon.
     *
     * @return list<array<string, mixed>>
     */
    public function technicalResults(Event $event): array
    {
        $criteria = $this->activeCriteria($event->id);
        $evaluations = $this->currentEvaluations($event)
            ->filter(fn (Evaluation $e) => $e->status === EvaluationStatus::Submitted)
            ->groupBy('team_id');

        return Team::query()->where('event_id', $event->id)->orderBy('name')->get()->map(function (Team $team) use ($evaluations, $criteria) {
            $percents = ($evaluations->get($team->id) ?? collect())
                ->map(fn (Evaluation $e) => $this->percent($e, $criteria))
                ->filter(fn ($p) => $p !== null)
                ->values();

            $average = $percents->isEmpty() ? null : $percents->avg();

            return [
                'team' => ['id' => $team->id, 'name' => $team->name],
                'submitted_evaluations' => $percents->count(),
                'technical_score' => $average === null ? null : round($average, 2),
            ];
        })->values()->all();
    }

    /**
     * Progresso por equipe, derivado das atribuições ACTIVE e das avaliações delas (sem notas).
     *
     * @return list<array<string, mixed>>
     */
    public function progress(Event $event): array
    {
        $assignments = JurorTeamAssignment::query()
            ->where('event_id', $event->id)
            ->where('status', AssignmentStatus::Active->value)
            ->get()
            ->groupBy('team_id');

        $evaluations = $this->currentEvaluations($event)->groupBy('team_id');

        return Team::query()->where('event_id', $event->id)->orderBy('name')->get()->map(function (Team $team) use ($assignments, $evaluations) {
            $assigned = ($assignments->get($team->id) ?? collect())->count();
            $byStatus = ($evaluations->get($team->id) ?? collect())->countBy(fn (Evaluation $e) => $e->status->value);

            $submitted = $byStatus->get(EvaluationStatus::Submitted->value, 0);
            $draft = $byStatus->get(EvaluationStatus::Draft->value, 0);
            $revision = $byStatus->get(EvaluationStatus::RevisionRequested->value, 0);

            return [
                'team' => ['id' => $team->id, 'name' => $team->name],
                'assigned_jurors' => $assigned,
                'submitted' => $submitted,
                'draft' => $draft,
                'revision_requested' => $revision,
                'not_started' => $assigned - $submitted - $draft - $revision,
                'completion_percent' => $assigned === 0 ? null : round(100 * $submitted / $assigned, 2),
            ];
        })->values()->all();
    }

    /**
     * Avaliações que fazem parte do conjunto atual: as de atribuições ACTIVE (revogadas ficam só no histórico).
     *
     * @return Collection<int, Evaluation>
     */
    private function currentEvaluations(Event $event): Collection
    {
        return Evaluation::query()
            ->where('evaluations.event_id', $event->id)
            ->join('juror_team_assignments as a', function ($join) {
                $join->on('a.juror_id', '=', 'evaluations.juror_id')->on('a.team_id', '=', 'evaluations.team_id');
            })
            ->where('a.status', AssignmentStatus::Active->value)
            ->select('evaluations.*')
            ->with('scores')
            ->get();
    }
}
