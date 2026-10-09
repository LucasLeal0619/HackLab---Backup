<?php

namespace Tests\Concerns;

use App\Domain\Users\Enums\RoleCode;
use App\Models\Event;
use App\Models\Sector;
use App\Models\User;

/**
 * Cenário intersetorial para pendências/ocorrências:
 * setores A, B, C (relacionáveis) e D (nunca relacionado), com Gestor e Editor em cada um,
 * mais Administrador, Consultor, Jurado e Votante.
 */
trait DemandScenario
{
    protected Event $event;

    protected Sector $sectorA;

    protected Sector $sectorB;

    protected Sector $sectorC;

    protected Sector $sectorD;

    protected User $adminUser;

    protected User $consultant;

    /** @var array<string, User> */
    protected array $managers = [];

    /** @var array<string, User> */
    protected array $editors = [];

    /**
     * Chamado explicitamente no setUp do teste. Não usar o nome setUpDemandScenario: o Laravel
     * executa setUp<NomeDoTrait>() automaticamente, antes do seed dos perfis.
     */
    protected function buildDemandScenario(): void
    {
        $this->event = Event::factory()->create();

        foreach (['A', 'B', 'C', 'D'] as $key) {
            $sector = Sector::factory()->for($this->event)->create(['name' => "Setor {$key}"]);
            $this->{"sector{$key}"} = $sector;
            $this->managers[$key] = $this->sectorUser(RoleCode::Manager, $sector);
            $this->editors[$key] = $this->sectorUser(RoleCode::Editor, $sector);
        }

        $this->adminUser = $this->admin();
        $this->consultant = $this->userWithRole(RoleCode::Consultant);
    }

    protected function manager(string $key): User
    {
        return $this->managers[$key];
    }

    protected function editor(string $key): User
    {
        return $this->editors[$key];
    }
}
