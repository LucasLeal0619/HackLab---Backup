<?php

namespace App\Http\Resources;

use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Evaluation
 *
 * "percent" é calculado (não persistido) e só aparece com todos os critérios ativos preenchidos.
 * Passe-o via additional/with: o controller injeta ['percent' => ...].
 */
class EvaluationResource extends JsonResource
{
    public ?float $percent = null;

    public function withPercent(?float $percent): static
    {
        $this->percent = $percent;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'juror' => $this->whenLoaded('juror', fn () => ['id' => $this->juror->id, 'name' => $this->juror->person?->full_name]),
            'team' => $this->whenLoaded('team', fn () => ['id' => $this->team->id, 'name' => $this->team->name]),
            'status' => $this->status?->value,
            'comments' => $this->comments,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'revision' => $this->revision_reason === null ? null : [
                'reason' => $this->revision_reason,
                'requested_at' => $this->revision_requested_at?->toIso8601String(),
                'requested_by_user_id' => $this->revision_requested_by_user_id,
            ],
            'scores' => $this->whenLoaded('scores', fn () => $this->scores->sortBy(fn ($s) => $s->criterion?->sort_order)->values()->map(fn ($score) => [
                'criterion_id' => $score->criterion_id,
                'criterion_name' => $score->criterion?->name,
                'score' => (float) $score->score,
                'comment' => $score->comment,
            ])),
            'percent' => $this->percent === null ? null : round($this->percent, 2),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
