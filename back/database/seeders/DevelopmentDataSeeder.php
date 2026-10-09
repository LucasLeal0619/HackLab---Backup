<?php

namespace Database\Seeders;

use App\Domain\Challenges\Enums\ChallengeStatus;
use App\Domain\Companies\Enums\CompanyStatus;
use App\Domain\Companies\Enums\CompanyType;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Meetings\Enums\MeetingStatus;
use App\Domain\Participants\Enums\ParticipantStatus;
use App\Domain\Teams\Enums\TeamStatus;
use App\Domain\Users\Enums\RoleCode;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Challenge;
use App\Models\Company;
use App\Models\CompanyRepresentative;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Person;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Sector;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados FICTÍCIOS de desenvolvimento: um evento de exemplo com 3 dias, 2 setores,
 * um Gestor e um Editor de exemplo, uma reunião geral, 3 turmas, 12 participantes
 * e 2 equipes com alguns membros, 3 empresas (com representantes) e 5 desafios em etapas
 * diferentes (um institucional, dois aprovados sem equipe, um distribuído). Só roda em local/testing.
 * Representantes são Persons sem conta de acesso.
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

        $this->participantsAndTeams($event);
        $this->companiesAndChallenges($event);
    }

    private function participantsAndTeams(Event $event): void
    {
        $classes = collect(range(1, 3))->map(fn (int $n) => SchoolClass::query()->firstOrCreate(
            ['event_id' => $event->id, 'name' => "Turma Exemplo {$n}"],
            ['active' => true],
        ));

        $teams = collect(range(1, 2))->map(fn (int $n) => Team::query()->firstOrCreate(
            ['event_id' => $event->id, 'name' => "Equipe Exemplo {$n}"],
            ['code' => "EX{$n}", 'status' => TeamStatus::Active],
        ));

        foreach (range(1, 12) as $n) {
            $email = sprintf('participante%02d@hacklab.local', $n);
            $person = Person::query()->firstOrCreate(['email' => $email], ['full_name' => sprintf('Participante Exemplo %02d', $n)]);

            $participant = Participant::query()->firstOrCreate(
                ['event_id' => $event->id, 'person_id' => $person->id],
                ['class_id' => $classes[($n - 1) % 3]->id, 'status' => ParticipantStatus::Available],
            );

            // Os 8 primeiros em equipes (alternando turmas); os demais ficam sem equipe.
            if ($n <= 8 && ! TeamMember::query()->where('participant_id', $participant->id)->exists()) {
                TeamMember::query()->create([
                    'event_id' => $event->id,
                    'team_id' => $teams[($n - 1) % 2]->id,
                    'participant_id' => $participant->id,
                    'joined_at' => now(),
                    'active' => true,
                ]);
            }
        }
    }

    private function companiesAndChallenges(Event $event): void
    {
        $alfa = $this->company($event, 'Alfa Tecnologia (exemplo)', CompanyType::Participant, CompanyStatus::Confirmed, '00.ABC.000/0001-00');
        $beta = $this->company($event, 'Beta Parcerias (exemplo)', CompanyType::Partner, CompanyStatus::Confirmed, null);
        $gama = $this->company($event, 'Gama Patrocínios (exemplo)', CompanyType::Sponsor, CompanyStatus::Draft, null);

        $this->representative($alfa, 'representante01@hacklab.local', 'Representante Exemplo 01', 'Gerente de Inovação', true);
        $this->representative($alfa, 'representante02@hacklab.local', 'Representante Exemplo 02', 'Analista', false);
        $this->representative($beta, 'representante03@hacklab.local', 'Representante Exemplo 03', 'Coordenadora', true);
        // Gama em DRAFT, ainda sem representante.

        $distributed = $this->challenge($event, $alfa, 'Desafio Exemplo: Logística', ChallengeStatus::Distributed);
        $this->challenge($event, $alfa, 'Desafio Exemplo: Atendimento', ChallengeStatus::Approved);
        $this->challenge($event, $beta, 'Desafio Exemplo: Sustentabilidade', ChallengeStatus::UnderReview);
        $this->challenge($event, null, 'Desafio Institucional Exemplo', ChallengeStatus::Approved);
        $this->challenge($event, $gama, 'Desafio Exemplo: Rascunho', ChallengeStatus::Draft);

        // Desafio distribuído para a primeira equipe de exemplo (sem sobrescrever vínculo existente).
        $team = Team::query()->where('event_id', $event->id)->where('name', 'Equipe Exemplo 1')->first();

        if ($team !== null && $team->challenge_id === null && ! Team::query()->where('challenge_id', $distributed->id)->exists()) {
            $team->forceFill(['challenge_id' => $distributed->id])->save();
        }
    }

    private function company(Event $event, string $name, CompanyType $type, CompanyStatus $status, ?string $document): Company
    {
        return Company::query()->firstOrCreate(['event_id' => $event->id, 'name' => $name], [
            'type' => $type,
            'status' => $status,
            'document' => $document,
            'segment' => 'Exemplo',
        ]);
    }

    private function representative(Company $company, string $email, string $name, string $title, bool $primary): void
    {
        $person = Person::query()->firstOrCreate(['email' => $email], ['full_name' => $name]);

        CompanyRepresentative::query()->firstOrCreate(
            ['company_id' => $company->id, 'person_id' => $person->id],
            ['title' => $title, 'is_primary' => $primary, 'active' => true],
        );
    }

    private function challenge(Event $event, ?Company $company, string $title, ChallengeStatus $status): Challenge
    {
        return Challenge::query()->firstOrCreate(['event_id' => $event->id, 'title' => $title], [
            'company_id' => $company?->id,
            'problem' => 'Problema fictício para desenvolvimento.',
            'objective' => 'Objetivo fictício.',
            'status' => $status,
        ]);
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
