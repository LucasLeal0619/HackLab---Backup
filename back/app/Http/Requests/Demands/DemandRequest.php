<?php

namespace App\Http\Requests\Demands;

use App\Domain\Demands\Contracts\Demand;
use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Users\Enums\PermissionCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Base dos requests de pendências e ocorrências.
 */
abstract class DemandRequest extends FormRequest
{
    /**
     * Demanda da rota (task ou occurrence), quando houver.
     */
    protected function demand(): (Model&Demand)|null
    {
        return $this->route('task') ?? $this->route('occurrence');
    }

    protected function eventId(): int
    {
        return $this->demand()?->event_id ?? $this->route('event')->id;
    }

    /**
     * Setor ativo do mesmo evento.
     */
    protected function activeSector(): Exists
    {
        return Rule::exists('sectors', 'id')->where('event_id', $this->eventId())->where('active', true);
    }

    /**
     * Regras comuns de criação (pendência e ocorrência).
     *
     * @return array<string, mixed>
     */
    protected function creationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'origin_sector_id' => ['required', 'integer', $this->activeSector()],
            'responsible_sector_id' => ['required', 'integer', $this->activeSector()],
            'assigned_user_id' => ['nullable', 'integer'],
            'priority' => ['sometimes', Rule::enum(DemandPriority::class)],
            'involved_sector_ids' => ['sometimes', 'nullable', 'array'],
            'involved_sector_ids.*' => ['integer', 'distinct', $this->activeSector()],
            'status' => ['prohibited'],
            'reference' => ['prohibited'],
        ];
    }

    /**
     * Quem tem setor cria com origem no próprio setor; sem permissão de roteamento (Editor),
     * o responsável também é o próprio setor.
     */
    protected function applySectorDefaults(string $routePermission): void
    {
        $user = $this->user();

        if ($user?->sector_id === null) {
            return;
        }

        if (! $this->has('origin_sector_id')) {
            $this->merge(['origin_sector_id' => $user->sector_id]);
        }

        if (! $this->has('responsible_sector_id') && ! $user->hasPermission(PermissionCode::from($routePermission))) {
            $this->merge(['responsible_sector_id' => $user->sector_id]);
        }
    }

    protected function intOrNull(string $key): ?int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'origin_sector_id.exists' => 'Setor inexistente, inativo ou de outro evento.',
            'responsible_sector_id.exists' => 'Setor inexistente, inativo ou de outro evento.',
            'involved_sector_ids.*.exists' => 'Setor inexistente, inativo ou de outro evento.',
            'sector_id.exists' => 'Setor inexistente, inativo ou de outro evento.',
            'status.prohibited' => 'O status inicial é definido pelo sistema; depois use as operações próprias.',
            'origin_sector_id.prohibited' => 'A origem nunca muda.',
            'responsible_sector_id.prohibited' => 'Para mudar o setor responsável, use o encaminhamento.',
            'reference.prohibited' => 'A referência é gerada pelo sistema.',
            'source_occurrence_id.prohibited' => 'A ocorrência de origem é definida só ao gerar a pendência pela ocorrência.',
        ];
    }

    /**
     * Para edição: autoriza conteúdo/estrutura e status separadamente.
     *
     * @param  list<string>  $structuralKeys
     */
    protected function authorizeUpdate(array $structuralKeys): bool
    {
        $demand = $this->demand();

        if (! $this->user()->can('view', $demand)) {
            return false;
        }

        $touchesStructure = collect($structuralKeys)->contains(fn ($key) => $this->exists($key));

        if ($touchesStructure && ! $this->user()->can('updateStructure', $demand)) {
            return false;
        }

        return ! $this->exists('status') || $this->user()->can('changeStatus', $demand);
    }

    /**
     * @return array<string, mixed>
     */
    protected function immutableRules(): array
    {
        return [
            'origin_sector_id' => ['prohibited'],
            'responsible_sector_id' => ['prohibited'],
            'reference' => ['prohibited'],
            'source_occurrence_id' => ['prohibited'],
            'resolved_at' => ['prohibited'],
            'event_id' => ['prohibited'],
        ];
    }
}
