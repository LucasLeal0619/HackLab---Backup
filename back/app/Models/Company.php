<?php

namespace App\Models;

use App\Domain\Companies\Enums\CompanyStatus;
use App\Domain\Companies\Enums\CompanyType;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa interna do HackLab, de um único evento. Nunca criada a partir de inscrição externa.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'legal_name',
        'document',
        'segment',
        'type',
        'description',
        'email',
        'phone',
        'website',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'status' => CompanyStatus::class,
        ];
    }

    /**
     * Só letras e dígitos, em maiúsculas (ex.: "12.ABC.345/0001-99" → "12ABC345000199").
     */
    public static function normalizeDocument(?string $document): ?string
    {
        if ($document === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $document));

        return $normalized === '' ? null : $normalized;
    }

    protected function document(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::normalizeDocument($value));
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    public function isInactive(): bool
    {
        return $this->status === CompanyStatus::Inactive;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(CompanyRepresentative::class);
    }

    public function activeRepresentatives(): HasMany
    {
        return $this->hasMany(CompanyRepresentative::class)->where('active', true);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }
}
