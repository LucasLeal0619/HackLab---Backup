<?php

namespace App\Models;

use App\Domain\Demands\Contracts\Demand;
use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Occurrences\Enums\OccurrenceCategory;
use App\Domain\Occurrences\Enums\OccurrenceStatus;
use App\Models\Concerns\HasDemandSectors;
use Database\Factories\OccurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ocorrência: algo que aconteceu. Referência (OCO-0001) gerada pelo banco.
 */
class Occurrence extends Model implements Demand
{
    /** @use HasFactory<OccurrenceFactory> */
    use HasDemandSectors, HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'category',
        'origin_sector_id',
        'responsible_sector_id',
        'assigned_user_id',
        'priority',
        'status',
        'event_day_id',
        'occurred_at',
        'location',
        'team_id',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OccurrenceStatus::class,
            'priority' => DemandPriority::class,
            'category' => OccurrenceCategory::class,
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'origin_sector_id' => 'integer',
            'responsible_sector_id' => 'integer',
            'assigned_user_id' => 'integer',
            'event_day_id' => 'integer',
            'team_id' => 'integer',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function generatedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'source_occurrence_id')->orderBy('id');
    }

    public function involvedSectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'occurrence_sectors')->withPivot('event_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(OccurrenceInteraction::class)->orderBy('id');
    }
}
