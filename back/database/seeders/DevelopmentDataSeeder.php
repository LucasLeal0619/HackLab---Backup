<?php

namespace Database\Seeders;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Meetings\Enums\MeetingStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Event;
use App\Models\Person;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados FICTÍCIOS de desenvolvimento: um evento de exemplo com 3 dias, 2 setores,
 * um Gestor e um Editor de exemplo e uma reunião geral. Só roda em local/testing.
 *
 * O código do sistema não depende desses dados (nomes, datas e número de dias vêm do banco).
 */
class DevelopmentDataSeeder extends Seeder
{
    public const EVENT_NAME = 'HackLab Exemplo (dev)';

    public const DEMO_PASSWORD = 'hacklab123';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->getOutput()->writeln('<comment>DevelopmentDataSeeder ignorado: só roda em local/testing.</comment>');

            return;
        }

        $start = now()->addMonth()->startOfWeek()->addDays(2);

        $event = Event::query()->firstOrCreate(['name' => self::EVENT_NAME], [
            'description' => 'Evento fictício para desenvolvimento.',
            'location' => 'Local de exemplo',
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'status' => EventStatus::Planned,
        ]);

        foreach (range(1, 3) as $number) {
            $event->days()->firstOrCreate(['day_number' => $number], [
                'date' => $event->start_date->copy()->addDays($number - 1)->toDateString(),
                'label' => "Dia {$number}",
            ]);
        }

        $sectorA = $event->sectors()->firstOrCreate(['name' => 'Setor Exemplo A'], ['active' => true]);
        $sectorB = $event->sectors()->firstOrCreate(['name' => 'Setor Exemplo B'], ['active' => true]);

        $this->demoUser('gestor@hacklab.local', 'Gestor de Exemplo', RoleCode::Manager, $sectorA);
        $this->demoUser('editor@hacklab.local', 'Editor de Exemplo', RoleCode::Editor, $sectorB);

        $admin = User::query()->where('email', DevelopmentAdminSeeder::DEFAULT_EMAIL)->first();

        if ($admin !== null) {
            $event->meetings()->firstOrCreate(['title' => 'Reunião geral de alinhamento'], [
                'sector_id' => null,
                'scheduled_at' => $event->start_date->copy()->subWeek()->setTime(14, 0),
                'status' => MeetingStatus::Scheduled,
                'created_by_user_id' => $admin->id,
            ]);
        }
    }

    private function demoUser(string $email, string $name, RoleCode $role, Sector $sector): void
    {
        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $person = Person::query()->create(['full_name' => $name, 'email' => $email]);

        User::query()->create([
            'person_id' => $person->id,
            'role_id' => Role::forCode($role)->id,
            'sector_id' => $sector->id,
            'email' => $email,
            'password' => self::DEMO_PASSWORD,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
    }
}
