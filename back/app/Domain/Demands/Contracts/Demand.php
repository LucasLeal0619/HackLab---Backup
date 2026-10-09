<?php

namespace App\Domain\Demands\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pendência ou ocorrência: origem, responsável, envolvidos adicionais, responsável individual e histórico.
 *
 * @property int $id
 * @property int $event_id
 * @property string $reference
 * @property int $origin_sector_id
 * @property int $responsible_sector_id
 * @property int|null $assigned_user_id
 */
interface Demand
{
    public function involvedSectors(): BelongsToMany;

    public function interactions(): HasMany;

    public function isClosed(): bool;

    /**
     * Setor participa da demanda: origem, responsável ou envolvido.
     */
    public function involvesSector(?int $sectorId): bool;
}
