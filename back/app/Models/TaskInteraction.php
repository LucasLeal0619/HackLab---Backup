<?php

namespace App\Models;

use App\Domain\Demands\Enums\InteractionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Histórico contextual da pendência. Somente inserção (também garantido por trigger no banco).
 */
class TaskInteraction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'user_id',
        'type',
        'message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Histórico é somente inserção.'));
        static::deleting(fn () => throw new LogicException('Histórico é somente inserção.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
