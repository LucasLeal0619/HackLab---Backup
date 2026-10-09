<?php

namespace App\Models\Concerns;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relações e escopo comuns a pendências e ocorrências.
 *
 * O model define involvedSectors() (pivot própria) e interactions().
 */
trait HasDemandSectors
{
    public function originSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'origin_sector_id');
    }

    public function responsibleSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'responsible_sector_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function involvesSector(?int $sectorId): bool
    {
        if ($sectorId === null) {
            return false;
        }

        if ($this->origin_sector_id === $sectorId || $this->responsible_sector_id === $sectorId) {
            return true;
        }

        return $this->relationLoaded('involvedSectors')
            ? $this->involvedSectors->contains('id', $sectorId)
            : $this->involvedSectors()->where('sectors.id', $sectorId)->exists();
    }

    /**
     * Visibilidade na query: quem tem setor vê o que o setor origina, é responsável ou está envolvido.
     * Quem não tem setor (Administrador, Consultor) tem alcance global; a Policy decide se pode ver.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $sectorId = $user->sector_id;

        if ($sectorId === null) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where($this->qualifyColumn('origin_sector_id'), $sectorId)
            ->orWhere($this->qualifyColumn('responsible_sector_id'), $sectorId)
            ->orWhereHas('involvedSectors', fn (Builder $s) => $s->where('sectors.id', $sectorId)));
    }
}
