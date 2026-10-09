# Arquitetura do HackLab

## 1. Visão geral

O HackLab é um monólito modular com frontend Vue, backend Laravel e PostgreSQL.

```text
┌────────────────────┐
│      Vue Front      │
│   JavaScript/Vue    │
└─────────┬──────────┘
          │ HTTPS / JSON
          ▼
┌────────────────────┐
│    Laravel API      │
│      /api/v1        │
└──────┬───────┬─────┘
       │       │
       │       └───────────────┐
       ▼                       ▼
┌─────────────┐        ┌────────────────┐
│ PostgreSQL  │        │ Plataforma     │
│             │        │ externa/Even3 │
└─────────────┘        └────────────────┘
```

### 1.1 Onde cada parte roda

| Parte | Dev/testes | Produção |
|---|---|---|
| Vue | `npm run dev` | Static Site |
| Laravel | Docker | Render Web Service (Docker) |
| PostgreSQL | Docker (`hacklab_dev`, `hacklab_test`) | Supabase |

O Supabase é usado só como PostgreSQL gerenciado. Autenticação, autorização e regras ficam no Laravel. Detalhes em `infraestrutura.md`.

## 2. Princípio arquitetural

A plataforma externa resolve:
- divulgação pública;
- inscrição;
- credencial/QR;
- credenciamento;
- check-in.

O HackLab resolve:
- organização interna;
- regras do Hackathon;
- equipes;
- setores;
- jurados;
- avaliações;
- votação;
- resultados;
- auditoria;
- consolidação de presença.

## 3. Monólito modular

Não usar microserviços nesta etapa.

Sugestão de organização Laravel:

```text
backend/
├── app/
│   ├── Domain/
│   │   ├── Auth/
│   │   ├── People/
│   │   ├── Users/
│   │   ├── Events/
│   │   ├── Participants/
│   │   ├── Teams/
│   │   ├── Companies/
│   │   ├── Challenges/
│   │   ├── Sectors/
│   │   ├── Meetings/
│   │   ├── Tasks/
│   │   ├── Occurrences/
│   │   ├── Documents/
│   │   ├── Jurors/
│   │   ├── Evaluations/
│   │   ├── Voting/
│   │   ├── Attendance/
│   │   └── Audit/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Integrations/
│   │   ├── EventPlatform/
│   │   └── Even3/
│   ├── Policies/
│   ├── Jobs/
│   └── Providers/
├── routes/api.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
└── tests/
    ├── Feature/
    └── Unit/
```

A estrutura pode ser adaptada ao estilo do projeto, mas a separação de responsabilidades deve ser preservada.

## 4. Domínios

### People

`Person` representa uma pessoa real conhecida pelo HackLab.

Pode existir sem usuário.

É o ponto central para evitar duplicidade entre:
- participante;
- jurado;
- representante de empresa;
- inscrito externo;
- usuário.

### Users/Auth

Usuário é uma credencial de acesso ao HackLab.

Um usuário:
- pertence a uma pessoa;
- possui um perfil;
- pode possuir setor;
- possui status ativo/inativo.

### Events

Mantém configuração do Hackathon:
- nome;
- período;
- local;
- status;
- dias do evento;
- integração externa associada.

### Participants

Representa exclusivamente o aluno que participa do Hackathon desenvolvendo solução.

Não usar `Participant` como termo genérico para todos os inscritos externos.

### Teams

Equipes são dinâmicas.
Não assumir quantidade fixa de participantes.

### Companies

Empresa é entidade interna, de um evento.
Representantes são pessoas (Person) vinculadas à empresa, sem conta de acesso obrigatória.

Não criar empresa automaticamente a partir de inscrição externa.

### Challenges

Desafios pertencem ao evento e podem ter empresa. A equipe recebe o desafio (`teams.challenge_id`, fonte única da relação); a API de desafio devolve a equipe pela relação inversa.

### Sectors

Setores organizam a operação interna.

Gestor e Editor são escopados ao próprio setor.

### Tasks/Pendências

Algo que precisa ser feito.

Pode ter:
- setor de origem;
- um setor responsável principal;
- múltiplos setores envolvidos;
- histórico/interações.

### Occurrences

Algo que aconteceu.

Pode:
- ter setor de origem;
- ter responsável principal;
- ter múltiplos envolvidos;
- ser encaminhada;
- gerar uma Pendência.

### Jurors

Jurado é pessoa elegível a avaliar.

Atribuição a equipes é explícita.

```text
Jurado ↔ Equipes
```

Empresa do jurado é opcional e nunca define automaticamente as equipes avaliadas.

### Evaluations

Avaliação técnica por jurado é separada do voto público.

### Voting

Votação pública possui regras próprias.

### Attendance

Consolida inscrição e presença vindas da plataforma externa.

O HackLab acompanha presença, não executa QR/credenciamento.

### Audit

Auditoria backend, imutável pela interface.

## 5. Perfis

Perfis internos atuais:

```text
Administrador
Gestor
Editor
Consultor
Jurado
Votante
```

### Administrador
Acesso global.

### Gestor
Lidera um setor e gerencia apenas seu escopo, salvo demandas intersetoriais onde seu setor participe.

### Editor
Atua em um setor com poderes reduzidos.

### Consultor
Visão transversal/orientação, sem administração de usuários.

### Jurado
Acessa apenas avaliações atribuídas.

### Votante
Acessa votação pública quando elegível.

## 6. Autorização

Usar Policies/Gates.

A autorização precisa combinar:
- perfil;
- setor;
- vínculo com recurso;
- estado do recurso.

Exemplo:

```text
Editor de Marketing
→ pode ver pendência do Marketing
→ pode ver pendência intersetorial envolvendo Marketing
→ não pode ver pendência privada de Produção
```

Administrador vê tudo.

### 6.2 Escopo setorial (implementado na Fase 2)

```text
Policy = permissão do perfil (PermissionCode) + escopo do usuário
```

- O escopo vem do vínculo, não do nome do perfil: `User::canReachSector()` / `User::reachesAllSectors()`.
- Gestor e Editor têm setor obrigatório (trigger no banco) e alcançam só o próprio setor.
- Administrador e Consultor não têm setor: alcance global, cada um até onde suas permissões vão (Consultor só consulta).
- Ações estruturais (criar/ativar/inativar setor, gerenciar evento, gerenciar reunião geral) exigem alcance global.
- Listagens aplicam o mesmo escopo na query (`Sector::visibleTo`, `Meeting::visibleTo`).
- Controllers não testam nome de perfil. Endpoints de escrita autorizam no `authorize()` do Form Request, antes da validação (sem permissão = 403, sem detalhes de validação).

Pendências e ocorrências (Fase 5) reutilizam esse escopo, somando setor de origem, responsável e envolvidos.

## 7. Integração externa

Criar contrato abstrato:

```php
interface EventPlatformProvider
{
    public function getEvent(): array;
    public function getAttendees(): array;
    public function getSchedule(): array;
}
```

O contrato real pode evoluir.

Implementação inicial:

```text
EventPlatformProvider
        ↑
   Even3Provider
```

Futuro possível:

```text
EventPlatformProvider
   ↑             ↑
Even3         Sympla
```

## 8. Fluxo de sincronização

```text
Even3
  ↓
Even3Client
  ↓
Even3Mapper
  ↓
Even3SyncService
  ↓
transaction
  ↓
people
event_registrations
external_ticket_types
attendance
  ↓
módulos internos
```

Sincronização deve ser idempotente.

## 9. API

Base:

```text
/api/v1
```

Principais grupos:

```text
/auth
/users
/people
/events
/participants
/teams
/companies
/challenges
/sectors
/meetings
/tasks
/occurrences
/documents
/jurors
/evaluations
/voting
/attendance
/audit
/integrations/even3
```

## 10. Respostas e erros

Padronizar resposta JSON.

Exemplo de sucesso:

```json
{
  "data": {},
  "meta": {}
}
```

Erro:

```json
{
  "message": "Mensagem legível",
  "errors": {}
}
```

Usar status HTTP corretamente.

## 11. Auditoria

A auditoria deve ser gerada no backend.

Campos mínimos:
- id;
- actor_user_id;
- actor_person_id;
- action;
- module;
- entity_type;
- entity_id;
- description;
- before_data;
- after_data;
- ip_address;
- user_agent;
- created_at.

Evitar registrar segredos, senha ou tokens.

## 12. Segurança

- HTTPS em produção.
- Sanctum/CSRF.
- CORS restrito.
- Rate limiting.
- Secrets em `.env`.
- Banco de produção (Supabase) só pelo Session pooler com SSL obrigatório; credenciais só no Render.
- O frontend nunca recebe URL nem chave do Supabase.
- Credenciais do banco só no backend.
- Token Even3 apenas no backend.
- Logs sem dados sensíveis.
- Policies em endpoints.
- Validação via Form Request.
- SQL apenas via Eloquent/Query Builder parametrizado.
- Nunca confiar em IDs enviados pelo frontend sem autorização.

## 13. Frontend

O frontend não deve conhecer detalhes da Even3.

Exemplo:

```text
Vue
→ GET /api/v1/attendance
→ Laravel
→ dados locais sincronizados
```

Botão de sincronização:

```text
Vue
→ POST /api/v1/integrations/even3/sync
→ Laravel
→ Even3
→ PostgreSQL
→ resposta resumida
```

## 14. Estratégia de migração do protótipo

Migrar tela a tela:

1. manter layout;
2. criar service no frontend;
3. substituir mock/localStorage pelo endpoint;
4. manter fallback apenas durante desenvolvimento;
5. remover mock quando endpoint estiver validado.

Não reescrever todo o frontend antes da API estar pronta.
