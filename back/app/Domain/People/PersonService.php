<?php

namespace App\Domain\People;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class PersonService
{
    private const AUDITED_FIELDS = ['full_name', 'email', 'phone', 'document', 'status'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{full_name: string, email?: ?string, phone?: ?string, document?: ?string}  $data
     */
    public function create(array $data): Person
    {
        return DB::transaction(function () use ($data) {
            $person = Person::query()->create($data);

            $this->audit->record(
                AuditAction::PERSON_CREATED,
                'people',
                "Pessoa {$person->full_name} cadastrada.",
                entity: $person,
                after: $this->snapshot($person),
            );

            return $person;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Person $person, array $data): Person
    {
        return DB::transaction(function () use ($person, $data) {
            $before = $this->snapshot($person);
            $person->fill($data);

            if (! $person->isDirty()) {
                return $person;
            }

            $person->save();

            $this->audit->record(
                AuditAction::PERSON_UPDATED,
                'people',
                "Pessoa {$person->full_name} alterada.",
                entity: $person,
                before: $before,
                after: $this->snapshot($person),
            );

            return $person;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Person $person): array
    {
        $data = $person->only(self::AUDITED_FIELDS);
        $data['status'] = $person->status?->value;

        return $data;
    }
}
