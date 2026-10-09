<?php

namespace App\Models;

use Database\Factories\CompanyRepresentativeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pessoa vinculada a uma empresa. Não cria conta de acesso e não vira Jurado automaticamente.
 */
class CompanyRepresentative extends Model
{
    /** @use HasFactory<CompanyRepresentativeFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'person_id',
        'title',
        'notes',
        'is_primary',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
