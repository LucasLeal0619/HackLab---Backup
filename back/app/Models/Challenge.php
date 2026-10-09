<?php

namespace App\Models;

use App\Domain\Challenges\Enums\ChallengeStatus;
use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Desafio do evento, com ou sem empresa. A equipe vem da relação inversa (teams.challenge_id).
 */
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'company_id',
        'title',
        'problem',
        'objective',
        'requirements',
        'restrictions',
        'expected_outcome',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChallengeStatus::class,
            'company_id' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function team(): HasOne
    {
        return $this->hasOne(Team::class);
    }
}
