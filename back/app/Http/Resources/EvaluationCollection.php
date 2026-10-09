<?php

namespace App\Http\Resources;

use App\Domain\Evaluations\EvaluationScoring;
use App\Models\EvaluationCriterion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

/**
 * Lista de avaliações com o percentual calculado (não persistido) de cada uma.
 * Mantém o formato paginado padrão (data/links/meta).
 */
class EvaluationCollection extends ResourceCollection
{
    public $collects = EvaluationResource::class;

    /**
     * @param  Collection<int, EvaluationCriterion>  $criteria  critérios ativos do evento
     */
    public function __construct(mixed $resource, private readonly Collection $criteria)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $scoring = app(EvaluationScoring::class);

        return $this->collection
            ->map(fn (EvaluationResource $item) => $item->withPercent($scoring->percent($item->resource, $this->criteria))->toArray($request))
            ->all();
    }
}
