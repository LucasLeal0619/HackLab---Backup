<?php

namespace App\Models;

use Database\Factories\EvaluationCriterionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Critério numérico de avaliação. Faixa e peso próprios; o cálculo normaliza pela faixa e pela soma dos pesos.
 */
class EvaluationCriterion extends Model
{
    /** @use HasFactory<EvaluationCriterionFactory> */
    use HasFactory;

    protected $table = 'evaluation_criteria';

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'min_score',
        'max_score',
        'weight',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'min_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'weight' => 'decimal:3',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
