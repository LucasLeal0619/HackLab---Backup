<?php

namespace App\Domain\Evaluations;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Critérios numéricos. A estrutura trava quando o evento tem qualquer avaliação (inclusive DRAFT):
 * depois disso só nome e descrição podem mudar (texto, sem mudar o significado da nota).
 */
class EvaluationCriterionService
{
    /** Campos que mudam o significado de uma nota já dada. */
    public const STRUCTURAL_FIELDS = ['min_score', 'max_score', 'weight', 'sort_order', 'active'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function isLocked(int $eventId): bool
    {
        return Evaluation::query()->where('event_id', $eventId)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, array $data): EvaluationCriterion
    {
        return DB::transaction(function () use ($event, $data) {
            $this->lockEventCriteria($event->id);
            $this->ensureUnlocked($event->id, 'Já existem avaliações neste evento: não é possível criar critério.');

            $criterion = EvaluationCriterion::query()->create($data + [
                'event_id' => $event->id,
                'active' => true,
                'sort_order' => (int) EvaluationCriterion::query()->where('event_id', $event->id)->max('sort_order') + 1,
            ]);

            $this->audit->record(AuditAction::EVALUATION_CRITERION_CREATED, 'evaluations', "Critério {$criterion->name} criado.", entity: $criterion, after: $this->snapshot($criterion));

            return $criterion;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EvaluationCriterion $criterion, array $data): EvaluationCriterion
    {
        return DB::transaction(function () use ($criterion, $data) {
            $this->lockEventCriteria($criterion->event_id);
            $before = $this->snapshot($criterion);
            $criterion->fill($data);

            if (! $criterion->isDirty()) {
                return $criterion;
            }

            if ($criterion->isDirty(self::STRUCTURAL_FIELDS)) {
                $this->ensureUnlocked($criterion->event_id, 'Já existem avaliações neste evento: só nome e descrição do critério podem mudar.');
            }

            if ((float) $criterion->max_score <= (float) $criterion->min_score) {
                throw ValidationException::withMessages(['max_score' => 'A nota máxima precisa ser maior que a mínima.']);
            }

            $criterion->save();

            $this->audit->record(AuditAction::EVALUATION_CRITERION_UPDATED, 'evaluations', "Critério {$criterion->name} alterado.", entity: $criterion, before: $before, after: $this->snapshot($criterion));

            return $criterion;
        });
    }

    public function changeStatus(EvaluationCriterion $criterion, bool $active): EvaluationCriterion
    {
        return DB::transaction(function () use ($criterion, $active) {
            $this->lockEventCriteria($criterion->event_id);

            if ($criterion->active === $active) {
                return $criterion;
            }

            $this->ensureUnlocked($criterion->event_id, 'Já existem avaliações neste evento: não é possível ativar/inativar critérios.');

            $before = $this->snapshot($criterion);
            $criterion->forceFill(['active' => $active])->save();

            $this->audit->record(
                AuditAction::EVALUATION_CRITERION_STATUS_CHANGED,
                'evaluations',
                $active ? "Critério {$criterion->name} ativado." : "Critério {$criterion->name} inativado.",
                entity: $criterion,
                before: $before,
                after: $this->snapshot($criterion),
            );

            return $criterion;
        });
    }

    private function ensureUnlocked(int $eventId, string $message): void
    {
        if ($this->isLocked($eventId)) {
            throw ValidationException::withMessages(['criteria' => $message]);
        }
    }

    /**
     * Serializa alterações de critérios do evento com a criação da primeira avaliação.
     */
    private function lockEventCriteria(int $eventId): void
    {
        DB::table('events')->where('id', $eventId)->lockForUpdate()->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(EvaluationCriterion $criterion): array
    {
        return $criterion->only(['event_id', 'name', 'description', 'min_score', 'max_score', 'weight', 'sort_order', 'active']);
    }
}
