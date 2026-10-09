<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evaluations\EvaluationScoring;
use App\Domain\Evaluations\EvaluationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluations\RequestRevisionRequest;
use App\Http\Resources\EvaluationCollection;
use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Visão administrativa das avaliações (todas as notas, sem editar), progresso agregado e resultado técnico.
 */
class EvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluations,
        private readonly EvaluationScoring $scoring,
    ) {}

    /**
     * Filtros: status, team_id, juror_id.
     */
    public function index(Request $request, Event $event): EvaluationCollection
    {
        Gate::authorize('viewAny', Evaluation::class);

        $criteria = $this->scoring->activeCriteria($event->id);

        $evaluations = Evaluation::query()
            ->where('event_id', $event->id)
            ->with('juror.person', 'team', 'scores.criterion')
            ->when($request->query('status'), fn ($q, string $v) => $q->where('status', $v))
            ->when($request->query('team_id'), fn ($q, $v) => $q->where('team_id', $v))
            ->when($request->query('juror_id'), fn ($q, $v) => $q->where('juror_id', $v))
            ->orderBy('team_id')
            ->orderBy('juror_id')
            ->paginate(min((int) $request->query('per_page', 50), 100));

        return new EvaluationCollection($evaluations, $criteria);
    }

    public function show(Evaluation $evaluation): EvaluationResource
    {
        Gate::authorize('view', $evaluation);

        $evaluation->load('juror.person', 'team', 'scores.criterion');

        return EvaluationResource::make($evaluation)
            ->withPercent($this->scoring->percent($evaluation))
            ->additional(['meta' => ['assignment_status' => $evaluation->assignment()?->status?->value]]);
    }

    public function requestRevision(RequestRevisionRequest $request, Evaluation $evaluation): EvaluationResource
    {
        $evaluation = $this->evaluations->requestRevision($evaluation, $request->user(), $request->validated('reason'));

        return EvaluationResource::make($evaluation->load('juror.person', 'team'))->withPercent($this->scoring->percent($evaluation));
    }

    /**
     * Progresso agregado por equipe (sem notas nem comentários).
     */
    public function progress(Event $event): JsonResponse
    {
        Gate::authorize('viewProgress', Evaluation::class);

        return response()->json(['data' => $this->scoring->progress($event)]);
    }

    /**
     * Resultado técnico por equipe (avaliações SUBMITTED com atribuição ACTIVE). Não é o resultado final.
     */
    public function technicalResults(Event $event): JsonResponse
    {
        Gate::authorize('viewTechnicalResults', Evaluation::class);

        return response()->json([
            'data' => $this->scoring->technicalResults($event),
            'meta' => ['note' => 'Resultado técnico parcial (somente jurados). Não inclui voto público nem é o resultado final.'],
        ]);
    }
}
