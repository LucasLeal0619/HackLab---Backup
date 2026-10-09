<?php

namespace App\Models;

use App\Domain\Meetings\Enums\MeetingStatus;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reunião geral (sector_id nulo) ou setorial.
 */
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'sector_id',
        'title',
        'description',
        'scheduled_at',
        'location',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status' => MeetingStatus::class,
            'sector_id' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isGeneral(): bool
    {
        return $this->sector_id === null;
    }

    /**
     * Reuniões gerais + as do setor do usuário; todas para quem não tem setor.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->sector_id !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('sector_id')->orWhere('sector_id', $user->sector_id));
        }
    }
}
