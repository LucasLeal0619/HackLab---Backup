<?php

namespace App\Domain\Companies;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\People\PersonService;
use App\Models\Company;
use App\Models\CompanyRepresentative;
use App\Models\Person;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Representantes = Persons vinculadas à empresa. Não cria User e não cria Jurado.
 * No máximo um principal ativo por empresa; a troca do principal é explícita (sem sobrescrever).
 */
class CompanyRepresentativeService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PersonService $people,
    ) {}

    /**
     * @param  array{
     *     person_id?: int,
     *     person?: array{full_name: string, email?: ?string, phone?: ?string, document?: ?string},
     *     title?: ?string,
     *     notes?: ?string,
     *     is_primary?: bool,
     * }  $data
     */
    public function add(Company $company, array $data): CompanyRepresentative
    {
        return DB::transaction(function () use ($company, $data) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);

            if ($company->isInactive()) {
                throw ValidationException::withMessages(['company' => 'Empresa inativa não recebe novos representantes.']);
            }

            $isPrimary = (bool) ($data['is_primary'] ?? false);

            if ($isPrimary) {
                $this->ensureNoOtherPrimary($company);
            }

            $person = isset($data['person_id'])
                ? Person::query()->findOrFail($data['person_id'])
                : $this->people->create($data['person']);

            $representative = $this->persist(fn () => $company->representatives()->create([
                'person_id' => $person->id,
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_primary' => $isPrimary,
                'active' => true,
            ]));

            $this->audit->record(
                AuditAction::COMPANY_REPRESENTATIVE_ADDED,
                'companies',
                "{$person->full_name} vinculado(a) como representante da empresa {$company->name}.",
                entity: $representative,
                after: $this->snapshot($representative),
            );

            return $representative->load('person');
        });
    }

    /**
     * @param  array{title?: ?string, notes?: ?string, is_primary?: bool}  $data
     */
    public function update(CompanyRepresentative $representative, array $data): CompanyRepresentative
    {
        return DB::transaction(function () use ($representative, $data) {
            $company = Company::query()->lockForUpdate()->findOrFail($representative->company_id);
            $before = $this->snapshot($representative);
            $representative->fill($data);

            if (! $representative->isDirty()) {
                return $representative->load('person');
            }

            if ($representative->isDirty('is_primary') && $representative->is_primary) {
                if (! $representative->active) {
                    throw ValidationException::withMessages(['is_primary' => 'Representante inativo não pode ser o principal.']);
                }

                $this->ensureNoOtherPrimary($company, $representative);
            }

            $this->persist(fn () => $representative->save());

            $this->audit->record(
                AuditAction::COMPANY_REPRESENTATIVE_UPDATED,
                'companies',
                "Representante {$representative->person->full_name} da empresa {$company->name} alterado(a).",
                entity: $representative,
                before: $before,
                after: $this->snapshot($representative),
            );

            return $representative->load('person');
        });
    }

    /**
     * Desativar também remove a marca de principal (registrado no mesmo log).
     */
    public function changeStatus(CompanyRepresentative $representative, bool $active): CompanyRepresentative
    {
        return DB::transaction(function () use ($representative, $active) {
            $company = Company::query()->lockForUpdate()->findOrFail($representative->company_id);

            if ($representative->active === $active) {
                return $representative->load('person');
            }

            if ($active && $company->isInactive()) {
                throw ValidationException::withMessages(['active' => 'Empresa inativa não recebe representantes ativos.']);
            }

            $before = $this->snapshot($representative);
            $representative->forceFill($active ? ['active' => true] : ['active' => false, 'is_primary' => false])->save();

            $name = $representative->person->full_name;

            $this->audit->record(
                AuditAction::COMPANY_REPRESENTATIVE_STATUS_CHANGED,
                'companies',
                $active
                    ? "Representante {$name} da empresa {$company->name} reativado(a)."
                    : "Representante {$name} da empresa {$company->name} desativado(a).",
                entity: $representative,
                before: $before,
                after: $this->snapshot($representative),
            );

            return $representative->load('person');
        });
    }

    private function ensureNoOtherPrimary(Company $company, ?CompanyRepresentative $except = null): void
    {
        $current = $company->representatives()
            ->with('person')
            ->where('is_primary', true)
            ->where('active', true)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->first();

        if ($current !== null) {
            throw ValidationException::withMessages([
                'is_primary' => "A empresa já tem um representante principal ativo ({$current->person->full_name}). Desmarque-o antes.",
            ]);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function persist(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            // Corrida entre requisições: o índice do banco barrou um segundo principal ativo.
            throw ValidationException::withMessages(['is_primary' => 'A empresa já tem um representante principal ativo.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(CompanyRepresentative $representative): array
    {
        return $representative->only(['company_id', 'person_id', 'title', 'notes', 'is_primary', 'active']);
    }
}
