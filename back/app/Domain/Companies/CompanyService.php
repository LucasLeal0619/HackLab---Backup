<?php

namespace App\Domain\Companies;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Companies\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * Empresas. Sem exclusão física: INACTIVE preserva desafios e representantes existentes.
 */
class CompanyService
{
    private const AUDITED_FIELDS = [
        'event_id', 'name', 'legal_name', 'document', 'segment', 'description', 'email', 'phone', 'website',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, array $data): Company
    {
        return DB::transaction(function () use ($event, $data) {
            $company = Company::query()->create($data + [
                'event_id' => $event->id,
                'status' => CompanyStatus::Draft->value,
            ]);

            $this->audit->record(
                AuditAction::COMPANY_CREATED,
                'companies',
                "Empresa {$company->name} cadastrada no evento {$event->name}.",
                entity: $company,
                after: $this->snapshot($company),
            );

            return $company;
        });
    }

    /**
     * Dados cadastrais. Status tem operação própria (changeStatus).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        return DB::transaction(function () use ($company, $data) {
            $before = $this->snapshot($company);
            $company->fill($data);

            if (! $company->isDirty()) {
                return $company;
            }

            $company->save();

            $this->audit->record(
                AuditAction::COMPANY_UPDATED,
                'companies',
                "Empresa {$company->name} alterada.",
                entity: $company,
                before: $before,
                after: $this->snapshot($company),
            );

            return $company;
        });
    }

    /**
     * Inativar não apaga nem desvincula desafios e representantes.
     */
    public function changeStatus(Company $company, CompanyStatus $status): Company
    {
        return DB::transaction(function () use ($company, $status) {
            if ($company->status === $status) {
                return $company;
            }

            $before = $this->snapshot($company);
            $company->forceFill(['status' => $status])->save();

            $this->audit->record(
                AuditAction::COMPANY_STATUS_CHANGED,
                'companies',
                "Status da empresa {$company->name} alterado de {$before['status']} para {$status->value}.",
                entity: $company,
                before: $before,
                after: $this->snapshot($company),
            );

            return $company;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Company $company): array
    {
        return $company->only(self::AUDITED_FIELDS) + [
            'type' => $company->type?->value,
            'status' => $company->status?->value,
        ];
    }
}
