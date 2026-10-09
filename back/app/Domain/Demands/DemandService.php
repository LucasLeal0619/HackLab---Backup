<?php

namespace App\Domain\Demands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Demands\Contracts\Demand;
use App\Domain\Demands\Enums\InteractionType;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Event;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mecânica comum de pendências e ocorrências: criação, edição com histórico, envolvidos,
 * responsável individual, encaminhamento e comentário.
 *
 * Toda operação que muda estado relê a demanda com lock (FOR UPDATE) antes de decidir.
 */
abstract class DemandService
{
    public function __construct(protected readonly AuditLogger $audit) {}

    /**
     * Prefixo das ações de auditoria: "TASK" ou "OCCURRENCE".
     */
    abstract protected function auditPrefix(): string;

    abstract protected function module(): string;

    /**
     * Nome no texto: "Pendência" ou "Ocorrência".
     */
    abstract protected function noun(): string;

    /**
     * @return Builder<Model>
     */
    abstract protected function newQuery(): Builder;

    /**
     * @return list<string>
     */
    abstract protected function relations(): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function snapshot(Model&Demand $demand): array;

    /**
     * Cria a demanda com histórico CREATED. A auditoria fica com quem chama.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $defaults
     */
    protected function createDemand(Event $event, User $actor, array $data, array $defaults, array $createdMetadata = []): Model&Demand
    {
        $involved = array_values(array_unique(array_map('intval', Arr::pull($data, 'involved_sector_ids') ?? [])));

        $this->ensureInvolvedExcludesMainSectors($involved, (int) $data['origin_sector_id'], (int) $data['responsible_sector_id']);
        $this->ensureSectorsActive($event->id, [(int) $data['origin_sector_id'], (int) $data['responsible_sector_id'], ...$involved]);

        if (! empty($data['assigned_user_id'])) {
            $this->ensureAssignable((int) $data['assigned_user_id'], (int) $data['responsible_sector_id']);
        }

        /** @var Model&Demand $demand */
        $demand = $this->newQuery()->create($data + $defaults + [
            'event_id' => $event->id,
            'created_by_user_id' => $actor->id,
        ]);
        $demand->refresh(); // referência gerada pelo banco

        if ($involved !== []) {
            $demand->involvedSectors()->attach(array_fill_keys($involved, ['event_id' => $event->id]));
        }

        $this->interact($demand, $actor, InteractionType::Created, null, $createdMetadata + [
            'origin_sector_id' => $demand->origin_sector_id,
            'responsible_sector_id' => $demand->responsible_sector_id,
            'involved_sector_ids' => $involved,
            'assigned_user_id' => $demand->assigned_user_id,
        ]);

        return $demand;
    }

    /**
     * Edição de conteúdo, prioridade, prazo, responsável individual, envolvidos e status (não-final).
     * Uma interação por tipo de mudança e um único log de auditoria.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model&Demand $demand, array $data, User $actor): Model&Demand
    {
        return DB::transaction(function () use ($demand, $data, $actor) {
            $demand = $this->lock($demand);
            $before = $this->snapshot($demand);
            $involved = array_key_exists('involved_sector_ids', $data)
                ? array_values(array_unique(array_map('intval', Arr::pull($data, 'involved_sector_ids') ?? [])))
                : null;

            if (array_key_exists('status', $data) && $demand->isClosed()) {
                throw ValidationException::withMessages(['status' => "{$this->noun()} fechada: use a reabertura."]);
            }

            if (! empty($data['assigned_user_id']) && (int) $data['assigned_user_id'] !== $demand->assigned_user_id) {
                $this->ensureAssignable((int) $data['assigned_user_id'], $demand->responsible_sector_id);
            }

            $demand->fill($data);
            $dirty = array_keys($demand->getDirty());
            $original = $demand->getOriginal();
            $demand->save();

            $changedFields = [];

            foreach ($dirty as $field) {
                $type = match ($field) {
                    'status' => InteractionType::StatusChanged,
                    'priority' => InteractionType::PriorityChanged,
                    'due_at' => InteractionType::DueChanged,
                    'assigned_user_id' => InteractionType::AssigneeChanged,
                    default => null,
                };

                if ($type === null) {
                    $changedFields[] = $field;

                    continue;
                }

                $this->interact($demand, $actor, $type, null, [
                    'from' => $before[$field] ?? null,
                    'to' => $this->snapshot($demand)[$field] ?? null,
                ]);
            }

            if ($changedFields !== []) {
                $this->interact($demand, $actor, InteractionType::Updated, null, ['fields' => $changedFields]);
            }

            $sectorChanges = $involved === null ? [] : $this->syncInvolved($demand, $involved, $actor);

            if ($dirty === [] && $sectorChanges === []) {
                return $demand->load($this->relations());
            }

            $onlyStatus = $dirty === ['status'] && $sectorChanges === [];

            $this->record(
                $onlyStatus ? 'STATUS_CHANGED' : 'UPDATED',
                $onlyStatus
                    ? "{$this->noun()} {$demand->reference}: status alterado de {$original['status']->value} para {$demand->status->value}."
                    : "{$this->noun()} {$demand->reference} alterada.",
                $demand,
                $before,
                $this->snapshot($demand->load('involvedSectors')),
            );

            return $demand->load($this->relations());
        });
    }

    /**
     * Encaminha para outro setor: responsável anterior vira envolvido (se não for a origem),
     * novo responsável sai dos envolvidos, origem nunca muda e o responsável individual é limpo.
     */
    public function forward(Model&Demand $demand, int $sectorId, string $reason, User $actor): Model&Demand
    {
        return DB::transaction(function () use ($demand, $sectorId, $reason, $actor) {
            $demand = $this->lock($demand);

            if ($demand->isClosed()) {
                throw ValidationException::withMessages(['sector_id' => "{$this->noun()} fechada não é encaminhada. Reabra antes."]);
            }

            if ($sectorId === $demand->responsible_sector_id) {
                throw ValidationException::withMessages(['sector_id' => 'O setor informado já é o responsável.']);
            }

            $target = Sector::query()->lockForUpdate()->find($sectorId);

            if ($target === null || $target->event_id !== $demand->event_id || ! $target->active) {
                throw ValidationException::withMessages(['sector_id' => 'Setor inexistente, inativo ou de outro evento.']);
            }

            $before = $this->snapshot($demand);
            $from = Sector::query()->findOrFail($demand->responsible_sector_id);
            $previousAssignee = $demand->assigned_user_id;

            $demand->forceFill(['responsible_sector_id' => $target->id, 'assigned_user_id' => null])->save();

            if ($from->id !== $demand->origin_sector_id) {
                $demand->involvedSectors()->syncWithoutDetaching([$from->id => ['event_id' => $demand->event_id]]);
            }

            $demand->involvedSectors()->detach($target->id);
            $demand->load('involvedSectors');

            $this->interact($demand, $actor, InteractionType::Forwarded, $reason, [
                'from_sector_id' => $from->id,
                'from_sector_name' => $from->name,
                'to_sector_id' => $target->id,
                'to_sector_name' => $target->name,
                'previous_assigned_user_id' => $previousAssignee,
            ]);

            $this->record(
                'FORWARDED',
                "{$this->noun()} {$demand->reference} encaminhada de {$from->name} para {$target->name}.",
                $demand,
                $before,
                $this->snapshot($demand) + ['reason' => $reason],
            );

            return $demand->load($this->relations());
        });
    }

    /**
     * Comentário: só histórico (sem auditoria). Não concede poder de edição.
     */
    public function comment(Model&Demand $demand, User $actor, string $message): Model
    {
        return $this->interact($demand, $actor, InteractionType::Comment, $message);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{type: string, sector_id: int}>
     */
    protected function syncInvolved(Model&Demand $demand, array $ids, ?User $actor): array
    {
        $this->ensureInvolvedExcludesMainSectors($ids, $demand->origin_sector_id, $demand->responsible_sector_id);

        $current = $demand->involvedSectors->pluck('id')->map(fn ($id) => (int) $id)->all();
        $added = array_values(array_diff($ids, $current));
        $removed = array_values(array_diff($current, $ids));

        if ($added !== []) {
            $this->ensureSectorsActive($demand->event_id, $added);
            $demand->involvedSectors()->attach(array_fill_keys($added, ['event_id' => $demand->event_id]));
        }

        if ($removed !== []) {
            $demand->involvedSectors()->detach($removed);
        }

        $names = Sector::query()->whereIn('id', [...$added, ...$removed])->pluck('name', 'id');
        $changes = [];

        foreach ($added as $id) {
            $this->interact($demand, $actor, InteractionType::SectorAdded, null, ['sector_id' => $id, 'sector_name' => $names[$id] ?? null]);
            $changes[] = ['type' => 'added', 'sector_id' => $id];
        }

        foreach ($removed as $id) {
            $this->interact($demand, $actor, InteractionType::SectorRemoved, null, ['sector_id' => $id, 'sector_name' => $names[$id] ?? null]);
            $changes[] = ['type' => 'removed', 'sector_id' => $id];
        }

        $demand->load('involvedSectors');

        return $changes;
    }

    /**
     * Responsável individual: usuário ATIVO do setor responsável (na prática, Gestor ou Editor dele).
     */
    protected function ensureAssignable(int $userId, int $responsibleSectorId): void
    {
        $valid = User::query()
            ->whereKey($userId)
            ->where('status', UserStatus::Active->value)
            ->where('sector_id', $responsibleSectorId)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'O responsável individual precisa ser usuário ativo do setor responsável.',
            ]);
        }
    }

    protected function isAssignable(?int $userId, int $responsibleSectorId): bool
    {
        return $userId === null || User::query()
            ->whereKey($userId)
            ->where('status', UserStatus::Active->value)
            ->where('sector_id', $responsibleSectorId)
            ->exists();
    }

    /**
     * @param  list<int>  $involved
     */
    protected function ensureInvolvedExcludesMainSectors(array $involved, int $originId, int $responsibleId): void
    {
        if (in_array($originId, $involved, true) || in_array($responsibleId, $involved, true)) {
            throw ValidationException::withMessages([
                'involved_sector_ids' => 'Origem e responsável já participam da demanda; não os informe como envolvidos.',
            ]);
        }
    }

    /**
     * @param  list<int>  $sectorIds
     */
    protected function ensureSectorsActive(int $eventId, array $sectorIds): void
    {
        $sectorIds = array_values(array_unique($sectorIds));

        $valid = Sector::query()
            ->whereIn('id', $sectorIds)
            ->where('event_id', $eventId)
            ->where('active', true)
            ->count();

        if ($valid !== count($sectorIds)) {
            throw ValidationException::withMessages(['sector_id' => 'Setor inexistente, inativo ou de outro evento.']);
        }
    }

    protected function lock(Model&Demand $demand): Model&Demand
    {
        /** @var Model&Demand */
        return $this->newQuery()->with('involvedSectors')->lockForUpdate()->findOrFail($demand->getKey());
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    protected function interact(Model&Demand $demand, ?User $actor, InteractionType $type, ?string $message = null, ?array $metadata = null): Model
    {
        return $demand->interactions()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'message' => $message,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function record(string $suffix, string $description, Model $demand, ?array $before, ?array $after): void
    {
        $this->audit->record("{$this->auditPrefix()}_{$suffix}", $this->module(), $description, entity: $demand, before: $before, after: $after);
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseSnapshot(Model&Demand $demand): array
    {
        return [
            'reference' => $demand->reference,
            'event_id' => $demand->event_id,
            'title' => $demand->title,
            'description' => $demand->description,
            'origin_sector_id' => $demand->origin_sector_id,
            'responsible_sector_id' => $demand->responsible_sector_id,
            'involved_sector_ids' => $demand->relationLoaded('involvedSectors')
                ? $demand->involvedSectors->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()
                : $demand->involvedSectors()->pluck('sectors.id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'assigned_user_id' => $demand->assigned_user_id,
            'priority' => $demand->priority?->value,
            'status' => $demand->status?->value,
            'resolved_at' => $demand->resolved_at?->toIso8601String(),
        ];
    }
}
