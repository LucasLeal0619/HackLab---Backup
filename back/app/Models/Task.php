<?php

namespace App\Models;

use App\Domain\Demands\Contracts\Demand;
use App\Domain\Demands\Enums\DemandPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\Concerns\HasDemandSectors;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pendência: algo que precisa ser feito. Referência (PEN-0001) gerada pelo banco.
 */
class Task extends Model implements Demand
{
    /** @use HasFactory<TaskFactory> */
    use HasDemandSectors, HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'origin_sector_id',
        'responsible_sector_id',
        'assigned_user_id',
        'priority',
        'status',
        'due_at',
        'source_occurrence_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => DemandPriority::class,
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'origin_sector_id' => 'integer',
            'responsible_sector_id' => 'integer',
            'assigned_user_id' => 'integer',
            'source_occurrence_id' => 'integer',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function isOverdue(): bool
    {
        return ! $this->isClosed() && $this->due_at !== null && $this->due_at->isPast();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sourceOccurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class, 'source_occurrence_id');
    }

    public function involvedSectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'task_sectors')->withPivot('event_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(TaskInteraction::class)->orderBy('id');
    }
}
