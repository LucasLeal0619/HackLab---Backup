<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evaluations\EvaluationCriterionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluations\ChangeCriterionStatusRequest;
use App\Http\Requests\Evaluations\CriterionRequest;
use App\Http\Resources\EvaluationCriterionResource;
use App\Models\EvaluationCriterion;
use App\Models\Event;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EvaluationCriterionController extends Controller
{
    public function __construct(private readonly EvaluationCriterionService $criteria) {}

    /**
     * meta.locked indica se a estrutura já está travada (existe avaliação no evento).
     */
    public function index(Event $event): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', EvaluationCriterion::class);

        return EvaluationCriterionResource::collection(
            EvaluationCriterion::query()->where('event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get()
        )->additional(['meta' => ['locked' => $this->criteria->isLocked($event->id)]]);
    }

    public function store(CriterionRequest $request, Event $event): EvaluationCriterionResource
    {
        return EvaluationCriterionResource::make($this->criteria->create($event, $request->validated()));
    }

    public function update(CriterionRequest $request, EvaluationCriterion $criterion): EvaluationCriterionResource
    {
        return EvaluationCriterionResource::make($this->criteria->update($criterion, $request->validated()));
    }

    public function updateStatus(ChangeCriterionStatusRequest $request, EvaluationCriterion $criterion): EvaluationCriterionResource
    {
        return EvaluationCriterionResource::make($this->criteria->changeStatus($criterion, $request->boolean('active')));
    }
}
