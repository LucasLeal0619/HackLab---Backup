<?php

namespace App\Models;

use App\Domain\Teams\Enums\TeamStatus;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equipe do evento. Não pertence a turma: reúne alunos de várias turmas.
 */
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'code',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => TeamStatus::class,
            'challenge_id' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Desafio recebido pela equipe. Alterado só pelo ChallengeService (PATCH /challenges/{id}/team).
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class)->where('active', true);
    }

    public function isActive(): bool
    {
        return $this->status === TeamStatus::Active;
    }
}
