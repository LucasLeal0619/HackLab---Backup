<?php

namespace App\Models;

use App\Domain\People\Enums\PersonStatus;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pessoa real. Pode existir sem conta (User), e futuramente ser Participante, Jurado etc.
 */
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    protected $table = 'people';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'document',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => PersonStatus::class,
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }
}
