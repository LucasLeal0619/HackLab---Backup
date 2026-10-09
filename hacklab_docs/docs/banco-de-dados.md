# Banco de Dados — PostgreSQL

## 1. Princípios

- PostgreSQL é a fonte de verdade do HackLab, em todos os ambientes.
- Tabelas no schema `hacklab` (nunca `public`), via `search_path`.
- A estrutura é criada só por migrations Laravel. Nada de tabela criada manualmente nem pelo Dashboard do Supabase.
- IDs internos independem da plataforma externa.
- IDs externos são armazenados para sincronização.
- Usar `bigint` (`$table->id()`) ou UUID/ULID conforme decisão inicial do projeto; não misturar estratégias sem motivo. PostgreSQL não tem `unsigned`.
- Colunas `json` das tabelas abaixo usam `jsonb` (`$table->jsonb()`).
- Usar `timestampTz` quando o instante importa (check-ins, auditoria, sincronização).
- Todas as FKs relevantes devem possuir índice.
- Timestamps em UTC no backend; converter no frontend.
- Usar enums PHP/constantes de domínio quando útil, mas manter persistência evolutiva.
- Não guardar token externo em tabela sem necessidade; preferir secret em `.env`.

## 1.1 Ambientes e bancos

| Banco | Ambiente (`APP_ENV`) | Onde |
|---|---|---|
| `hacklab_dev` | `local` | container `postgres` do `docker-compose.yml` |
| `hacklab_test` | `testing` | mesmo container, banco separado. É apagado e recriado pelos testes |
| `postgres` (schema `hacklab`) | `production` | Supabase |

- PostgreSQL 17 no Docker, mesma versão major dos projetos novos do Supabase.
- `hacklab_dev` é criado pelo `POSTGRES_DB` do compose. `hacklab_test` e o schema `hacklab` (nos dois bancos) são criados por `docker/postgres/init/01-init.sh` na primeira inicialização do volume.
- O backend recusa conexões fora da regra (`App\Support\Database\DatabaseEnvironmentGuard`):
  - fora de `production`, nunca um host do Supabase (`*.supabase.co`, `*.supabase.com`);
  - em `testing`, só bancos terminados em `_test`;
  - em `production`, só com `sslmode` `require`, `verify-ca` ou `verify-full`.

### Conexão local (Docker)

`back/.env`:

```text
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1        # dentro do container o compose sobrescreve para "postgres"
DB_PORT=5442             # porta publicada só em 127.0.0.1; dentro do Docker é 5432
DB_DATABASE=hacklab_dev
DB_USERNAME=hacklab
DB_PASSWORD=hacklab
DB_SEARCH_PATH=hacklab
DB_SSLMODE=disable       # container local, sem SSL
```

### Conexão de produção (Supabase)

Só nas variáveis de ambiente do Render. Nunca no Git.

```text
DB_CONNECTION=pgsql
DB_URL=postgresql://postgres.[PROJECT-REF]:[SENHA-PERCENT-ENCODED]@aws-[N]-[REGIAO].pooler.supabase.com:5432/postgres
DB_SEARCH_PATH=hacklab
DB_SSLMODE=require
```

- `DB_URL` = string do **Session pooler**, copiada do botão **Connect** do projeto. Não usar o Transaction pooler (6543).
- Caracteres reservados na senha (`&`, `#`, `?`, `@`, `/`, espaço) devem ser percent-encoded.
- O schema `hacklab` precisa existir antes do primeiro `migrate`. O SQL está em `infraestrutura.md` §3.1.

### Validação

```bash
docker compose exec app php artisan hacklab:db-check
```

Mostra ambiente, host, versão do servidor, banco, usuário, `search_path`/schema ativo, se a conexão usa SSL e quantas migrations foram aplicadas. Falha se o schema configurado não existir.

### Testes automatizados

- Rodam em PostgreSQL, no banco `hacklab_test` do Docker. Não usar SQLite.
- `phpunit.xml` força `DB_CONNECTION=pgsql` e `DB_DATABASE=hacklab_test`. Credenciais em `back/.env.testing` (fora do Git; modelo em `back/.env.testing.example`).
- Com `APP_ENV=testing`, o Laravel lê só o `.env.testing`. O `.env` não é carregado.
- Testes que tocam o banco usam `RefreshDatabase`. O guard impede que apontem para `hacklab_dev` ou para o Supabase.

```bash
docker compose exec app php artisan test
```

## 2. Modelo conceitual

```text
Person
├── User?
├── Participant?
├── Juror?
├── CompanyRepresentative*
└── EventRegistration*

Event
├── EventDay*
├── EventRegistration*
├── Team*
├── Challenge*
└── VotingSession*

EventRegistration
├── ExternalTicketType
└── AttendanceRecord*

Sector
├── User*
├── Task*
└── Occurrence*

Juror
└── JurorTeamAssignment*

Task
└── TaskSector*

Occurrence
└── OccurrenceSector*
```

## 3. Tabelas principais

### people

```text
id
full_name
email nullable/index
phone nullable
document nullable
status
created_at
updated_at
```

Observações:
- e-mail não é identidade absoluta;
- pode haver pessoa sem e-mail;
- duplicidade deve ser tratada de forma controlada.

### users

```text
id
person_id FK unique
role_id FK
sector_id FK nullable        (entra na Fase 2, junto com a tabela sectors)
email unique                 (sempre minúsculo; check no banco)
password                     (hash padrão do Laravel)
status                       (ACTIVE | INACTIVE; check no banco)
email_verified_at nullable
last_login_at nullable
remember_token nullable
created_at
updated_at
```

Implementado na Fase 1 sem `sector_id`: a coluna e a FK chegam na migration da Fase 2, para não depender de tabela inexistente.

Usuário não é apagado: é inativado (`status = INACTIVE`), o que encerra as sessões abertas e bloqueia o acesso na próxima requisição. O sistema recusa inativar ou rebaixar o último Administrador ativo.

### roles

Seed inicial:

```text
ADMINISTRATOR
MANAGER
EDITOR
CONSULTANT
JUROR
VOTER
```

Campos:

```text
id
code unique
name
description nullable
```

### permissions

```text
id
code unique
name
description nullable
```

### role_permission

```text
role_id
permission_id
primary key(role_id, permission_id)
```

Policies ainda devem aplicar escopo por setor.

Permissões iniciais (Fase 1), definidas em `App\Domain\Users\Enums\PermissionCode`:

| Permissão | Administrador | Gestor | Editor | Consultor | Jurado | Votante |
|---|---|---|---|---|---|---|
| `people.view` | ✓ | ✓ | ✓ | ✓ | | |
| `people.manage` | ✓ | | | | | |
| `users.view` | ✓ | | | | | |
| `users.manage` | ✓ | | | | | |
| `roles.view` | ✓ | | | | | |
| `audit.view` | ✓ | | | | | |
| `events.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `events.manage` | ✓ | | | | | |
| `sectors.view` | ✓ | ✓ | ✓ | ✓ | | |
| `sectors.manage` | ✓ | ✓ | | | | |
| `meetings.view` | ✓ | ✓ | ✓ | ✓ | | |
| `meetings.manage` | ✓ | ✓ | | | | |

Qualquer usuário vê a própria conta e o próprio cadastro de pessoa. Novas permissões entram junto com os módulos que as usam.

**Escopo (Fase 2):** a Policy combina permissão + escopo. Quem tem setor (Gestor, Editor) só alcança o próprio setor; quem não tem setor (Administrador, Consultor) tem alcance global, limitado pelas permissões. Criar setor, ativar/inativar setor e gerenciar evento e reunião geral exigem alcance global.

### events

```text
id
name
description nullable
location nullable
start_date
end_date
status
external_provider nullable
external_event_id nullable
created_at
updated_at
```

Implementado na Fase 2 sem `external_provider`/`external_event_id`: o vínculo com a plataforma externa fica em `event_integrations` (Fase 9). Status `PLANNED | ACTIVE | FINISHED | CANCELLED` (check) e `end_date >= start_date` (check). Nome, datas e quantidade de dias vêm do cadastro; o código não assume 3 dias.

### event_days

```text
id
event_id FK
day_number
date
label
start_time nullable
end_time nullable
unique(event_id, day_number)
unique(event_id, date)
```

Checks: `day_number >= 1` e `end_time > start_time` quando ambos existem. O backend recusa dia fora do período do evento e alteração de datas do evento que deixaria dias de fora.

### classes

Turmas dos participantes.

```text
id
event_id FK
name
active
created_at
updated_at
```

Não hardcode Breno/Rafael/Clara no schema.

### participants

Somente alunos do Hackathon.

```text
id
event_id FK
person_id FK
class_id FK nullable
status
notes nullable
created_at
updated_at

unique(event_id, person_id)
```

Status sugeridos:

```text
AVAILABLE
UNAVAILABLE
WITHDRAWN
```

### teams

```text
id
event_id FK
name
code nullable
challenge_id FK nullable
status
created_at
updated_at
```

### team_members

```text
id
team_id FK
participant_id FK
joined_at nullable
left_at nullable
active
```

Regra: um participante só pode ter um vínculo ativo de equipe por evento.

### companies

```text
id
event_id FK
name
description nullable
email nullable
phone nullable
website nullable
status
created_at
updated_at
```

### company_representatives

```text
id
company_id FK
person_id FK
title nullable
notes nullable
unique(company_id, person_id)
```

### challenges

```text
id
event_id FK
company_id FK nullable
title
description
status
created_at
updated_at
```

### sectors

```text
id
event_id FK
name
description nullable
active
created_at
updated_at
```

Nome único por evento sem diferença de caixa (`unique(event_id, lower(name))`) e `unique(id, event_id)` como alvo de FKs compostas. Setor inativo não recebe novos vínculos nem reuniões; só é inativado sem Gestor/Editor ativo vinculado.

### users.sector_id (Fase 2)

```text
users.sector_id FK nullable → sectors
```

Regra no banco (trigger `users_sector_matches_role`): Gestor (`MANAGER`) e Editor (`EDITOR`) **exigem** setor; os demais perfis **não têm** setor. Ao trocar de Gestor/Editor para outro perfil o setor é removido (auditado). Usuários não pertencem a um evento: o evento vem do setor.

### meetings

```text
id
event_id FK
sector_id FK nullable
title
description nullable
scheduled_at
location nullable
status
created_by_user_id FK
created_at
updated_at
```

Reunião geral: `sector_id` nulo. FK composta `(sector_id, event_id) → sectors(id, event_id)`: o setor é sempre do mesmo evento da reunião. Status `SCHEDULED | DONE | CANCELLED` (check).

## 4. Pendências

### tasks

```text
id
event_id FK
title
description
origin_sector_id FK
responsible_sector_id FK
assigned_user_id FK nullable
priority
status
due_at nullable
source_occurrence_id FK nullable
created_by_user_id FK
resolved_at nullable
created_at
updated_at
```

### task_sectors

Setores envolvidos.

```text
task_id FK
sector_id FK
unique(task_id, sector_id)
```

### task_interactions

```text
id
task_id FK
user_id FK nullable
type
message nullable
metadata json nullable
created_at
```

`type` pode representar COMMENT, STATUS_CHANGED, FORWARDED etc.

## 5. Ocorrências

### occurrences

```text
id
event_id FK
title
description
origin_sector_id FK
responsible_sector_id FK
priority
status
occurred_at nullable
created_by_user_id FK
resolved_at nullable
created_at
updated_at
```

### occurrence_sectors

```text
occurrence_id FK
sector_id FK
unique(occurrence_id, sector_id)
```

### occurrence_interactions

```text
id
occurrence_id FK
user_id FK nullable
type
message nullable
metadata json nullable
created_at
```

## 6. Jurados e avaliações

### jurors

```text
id
event_id FK
person_id FK
company_id FK nullable
status
created_at
updated_at

unique(event_id, person_id)
```

### juror_team_assignments

```text
id
juror_id FK
team_id FK
assigned_by_user_id FK
created_at

unique(juror_id, team_id)
```

Nunca criar atribuição por empresa automaticamente.

### evaluation_criteria

```text
id
event_id FK
name
description nullable
weight decimal
max_score decimal
sort_order
active
```

### evaluations

```text
id
event_id FK
juror_id FK
team_id FK
status
comments nullable
submitted_at nullable
created_at
updated_at

unique(juror_id, team_id)
```

### evaluation_scores

```text
id
evaluation_id FK
criterion_id FK
score decimal
comment nullable

unique(evaluation_id, criterion_id)
```

## 7. Votação pública

### voting_sessions

```text
id
event_id FK
name
status
starts_at nullable
ends_at nullable
created_at
updated_at
```

### public_votes

```text
id
voting_session_id FK
user_id FK
team_id FK
created_at

unique(voting_session_id, user_id)
```

A regra de voto pode mudar; manter schema preparado para evolução.

## 8. Integração externa

### event_integrations

```text
id
event_id FK
provider
external_event_id
status
last_sync_at nullable
settings json nullable
created_at
updated_at

unique(event_id, provider)
```

Não guardar secret sensível em `settings`.

### external_ticket_types

Representa entrada/categoria externa.

```text
id
event_integration_id FK
external_id
external_name
internal_category nullable
active
metadata json nullable
created_at
updated_at

unique(event_integration_id, external_id)
```

Categorias internas sugeridas:

```text
PARTICIPANT
ORGANIZATION
COMPANY_GUEST
JUROR
PUBLIC
OTHER
```

### event_registrations

```text
id
event_id FK
person_id FK
event_integration_id FK nullable
external_attendee_id nullable
external_ticket_type_id FK nullable
external_registration_status nullable
external_checkin_code nullable
registered_at nullable
cancelled_at nullable
metadata json nullable
created_at
updated_at
```

Índices/uniques devem impedir duplicidade por integração.

### attendance_records

```text
id
event_id FK
event_day_id FK
person_id FK
event_registration_id FK nullable
source
external_id nullable
checked_in_at
metadata json nullable
created_at
updated_at
```

Constraint lógica:

```text
uma presença consolidada por pessoa + dia + evento
```

Se houver necessidade de armazenar múltiplas passagens no mesmo dia, criar `attendance_events` bruto separado da presença consolidada.

### attendance_events

Opcional, recomendado se webhook fornecer cada check-in:

```text
id
event_id FK
person_id FK nullable
event_registration_id FK nullable
provider
external_event_key nullable
event_type
occurred_at
payload json
created_at
```

Usar para rastreabilidade de integração.

## 9. Webhooks e sincronização

### webhook_receipts

```text
id
provider
external_event_key nullable
event_type
payload_hash
payload json
status
received_at
processed_at nullable
error_message nullable
created_at
updated_at
```

Criar índice para idempotência.

### integration_sync_runs

```text
id
event_integration_id FK
triggered_by_user_id FK nullable
mode
status
started_at
finished_at nullable
created_count default 0
updated_count default 0
skipped_count default 0
error_count default 0
summary json nullable
created_at
updated_at
```

## 10. Documentos

### documents

```text
id
event_id FK
sector_id FK nullable
title
description nullable
file_path
mime_type nullable
size nullable
uploaded_by_user_id FK
created_at
updated_at
```

## 11. Auditoria

### audit_logs

```text
id
actor_user_id FK nullable
actor_person_id FK nullable
action
module
entity_type nullable
entity_id nullable
description
before_data jsonb nullable
after_data jsonb nullable
ip_address nullable
user_agent nullable
created_at
```

Não permitir update/delete por endpoints normais.

Implementado na Fase 1:
- somente inserção, garantido em duas camadas: o model `AuditLog` recusa update/delete e um trigger no PostgreSQL (`audit_logs_no_update_delete`) recusa `UPDATE`/`DELETE`;
- `actor_user_id`/`actor_person_id` com `restrict`: usuários e pessoas são inativados, não apagados;
- `App\Domain\Audit\AuditLogger` remove de `before_data`/`after_data`, em qualquer nível, chaves com `password`, `token`, `secret`, `cookie`, `authorization`, `api_key` ou `remember`;
- ações auditadas na Fase 1: `LOGIN`, `LOGIN_FAILED`, `LOGIN_BLOCKED_INACTIVE`, `LOGOUT`, `USER_CREATED`, `USER_UPDATED`, `USER_ROLE_CHANGED`, `USER_ACTIVATED`, `USER_INACTIVATED`, `PERSON_CREATED`, `PERSON_UPDATED`.

## 12. Índices importantes

Criar índices para:
- `people.email`;
- `users.email`;
- `users.role_id`;
- `users.sector_id`;
- `participants(event_id, status)`;
- `team_members(team_id, active)`;
- `tasks(responsible_sector_id, status)`;
- `occurrences(responsible_sector_id, status)`;
- `event_registrations(event_id, person_id)`;
- IDs externos;
- `attendance_records(event_day_id, person_id)`;
- `audit_logs(created_at)`;
- `audit_logs(actor_user_id)`;
- `audit_logs(module, entity_type, entity_id)`.

## 13. Seed mínimo

Criar seed de desenvolvimento com:
- 1 evento;
- 3 dias;
- roles;
- permissions básicas;
- 1 Administrador;
- setores;
- turmas;
- participantes;
- equipes;
- empresas;
- desafios;
- jurados;
- assignments;
- pendências;
- ocorrências;
- inscrições externas simuladas;
- presença dos três dias;
- logs de auditoria.

Dados fictícios apenas.
