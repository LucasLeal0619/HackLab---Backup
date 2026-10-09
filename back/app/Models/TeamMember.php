<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo participante ↔ equipe. Encerrado com active=false + left_at; nunca apagado (histórico).
 */
class TeamMember extends Model
{
    protected $fillable = [
        'event_id',
        'team_id',
        'participant_id',
        'joined_at',
        'left_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'active' => 'boolean',
            'team_id' => 'integer',
            'participant_id' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
