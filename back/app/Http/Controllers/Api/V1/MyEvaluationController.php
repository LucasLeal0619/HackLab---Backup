<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evaluations\EvaluationScoring;
use App\Domain\Evaluations\EvaluationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluations\SaveEvaluationRequest;
use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Event;
use App\Models\Juror;
use App\Models\JurorTeamAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Área do jurado: atribuições ativas da própria Person, com a avaliação (se iniciada).
 * Sem atribuições (ou sem registro de jurado), a lista é vazia.
 */
class MyEvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluations,
        private readonly EvaluationScoring $scoring,
    ) {}

    public function index(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('viewOwn', Evaluation::class);

        $juror = Juror::query()->where('event_id', $event->id)->where('person_id', $request->user()->person_id)->first();

        if ($juror === null) {
            return response()->json(['data' => [], 'meta' => ['juror' => null]]);
        }

        $assignments = $juror->activeAssignments()->with('team.challenge.company')->orderBy('team_id')->get();
        $evaluations = Evaluation::query()->where('juror_id', $juror->id)->with('scores.criterion')->get()->keyBy('team_id');
        $criteria = $this->scoring->activeCriteria($event->id);

        $data = $assignments->map(function (JurorTeamAssignment $assignment) use ($evaluations, $criteria, $request) {
            $evaluation = $evaluations->get($assignment->team_id);
            $challenge = $assignment->team->challenge;
            $filled = $evaluation?->scores->whereIn('criterion_id', $criteria->keys())->count() ?? 0;

            return [
                'assignment' => ['id' => $assignment->id, 'status' => $assignment->status->value, 'assigned_at' => $assignment->assigned_at?->toIso8601String()],
                'team' => ['id' => $assignment->team->id, 'name' => $assignment->team->name],
                'challenge' => $challenge === null ? null : ['id' => $challenge->id, 'title' => $challenge->title],
                'company' => $challenge?->company === null ? null : ['id' => $challenge->company->id, 'name' => $challenge->company->name],
                'evaluation' => $evaluation === null ? null : EvaluationResource::make($evaluation)
                    ->withPercent($this->scoring->percent($evaluation, $criteria))
                    ->toArray($request),
                'progress' => [
                    'status' => $evaluation?->status?->value ?? 'NOT_STARTED',
                    'filled_criteria' => $filled,
                    'total_criteria' => $criteria->count(),
                ],
            ];
        })->values();

        return response()->json(['data' => $data, 'meta' => ['juror' => ['id' => $juror->id, 'status' => $juror->status->value]]]);
    }

    /**
     * PUT idempotente (cria na primeira gravação): sempre 200.
     */
    public function save(SaveEvaluationRequest $request, JurorTeamAssignment $assignment): JsonResponse
    {
        $evaluation = $this->evaluations->saveDraft($assignment, $request->user(), $request->validated());

        return EvaluationResource::make($evaluation)->withPercent($this->scoring->percent($evaluation))->response()->setStatusCode(200);
    }

    public function submit(SaveEvaluationRequest $request, JurorTeamAssignment $assignment): JsonResponse
    {
        $evaluation = $this->evaluations->submit($assignment, $request->user(), $request->validated());

        return EvaluationResource::make($evaluation)->withPercent($this->scoring->percent($evaluation))->response()->setStatusCode(200);
    }
}
