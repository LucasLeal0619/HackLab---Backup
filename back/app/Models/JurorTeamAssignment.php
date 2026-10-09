<?php

namespace App\Models;

use App\Domain\Jurors\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atribuição explícita jurado ↔ equipe. Revogar preserva o registro (e a avaliação); reativar reutiliza.
 */
class JurorTeamAssignment extends Model
{
    protected $fillable = [
        'event_id',
        'juror_id',
        'team_id',
        'status',
        'assigned_by_user_id',
        'assigned_at',
        'revoked_by_user_id',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'assigned_at' => 'datetime',
            'revoked_at' => 'datetime',
            'juror_id' => 'integer',
            'team_id' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === AssignmentStatus::Active;
    }

    public function juror(): BelongsTo
    {
        return $this->belongsTo(Juror::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * Avaliação desta atribuição (chave natural jurado + equipe), se já foi iniciada.
     */
    public function evaluation(): ?Evaluation
    {
        return Evaluation::query()->where('juror_id', $this->juror_id)->where('team_id', $this->team_id)->first();
    }
}
