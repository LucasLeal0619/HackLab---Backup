<?php

namespace App\Domain\Sectors;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Event;
use App\Models\Sector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SectorService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, description?: ?string}  $data
     */
    public function create(Event $event, array $data): Sector
    {
        return DB::transaction(function () use ($event, $data) {
            $sector = $event->sectors()->create($data + ['active' => true]);

            $this->audit->record(
                AuditAction::SECTOR_CREATED,
                'sectors',
                "Setor {$sector->name} criado no evento {$event->name}.",
                entity: $sector,
                after: $this->snapshot($sector),
            );

            return $sector;
        });
    }

    /**
     * @param  array{name?: string, description?: ?string}  $data
     */
    public function update(Sector $sector, array $data): Sector
    {
        return DB::transaction(function () use ($sector, $data) {
            $before = $this->snapshot($sector);
            $sector->fill($data);

            if (! $sector->isDirty()) {
                return $sector;
            }

            $sector->save();

            $this->audit->record(
                AuditAction::SECTOR_UPDATED,
                'sectors',
                "Setor {$sector->name} alterado.",
                entity: $sector,
                before: $before,
                after: $this->snapshot($sector),
            );

            return $sector;
        });
    }

    /**
     * Setor inativo não recebe novos vínculos nem reuniões. Só é inativado sem Gestor/Editor ativo vinculado.
     */
    public function changeStatus(Sector $sector, bool $active): Sector
    {
        return DB::transaction(function () use ($sector, $active) {
            $sector = Sector::query()->lockForUpdate()->findOrFail($sector->id);

            if ($sector->active === $active) {
                return $sector;
            }

            if (! $active) {
                $linked = $sector->users()->where('status', UserStatus::Active->value)->count();

                if ($linked > 0) {
                    throw ValidationException::withMessages([
                        'active' => "O setor tem {$linked} usuário(s) ativo(s) vinculado(s). Mova-os para outro setor antes de inativar.",
                    ]);
                }
            }

            $before = $this->snapshot($sector);
            $sector->forceFill(['active' => $active])->save();

            $this->audit->record(
                $active ? AuditAction::SECTOR_ACTIVATED : AuditAction::SECTOR_INACTIVATED,
                'sectors',
                $active ? "Setor {$sector->name} ativado." : "Setor {$sector->name} inativado.",
                entity: $sector,
                before: $before,
                after: $this->snapshot($sector),
            );

            return $sector;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Sector $sector): array
    {
        return $sector->only(['event_id', 'name', 'description', 'active']);
    }
}
