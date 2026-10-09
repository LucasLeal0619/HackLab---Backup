# Plano de Implementação

## Objetivo

Construir o backend sem bloquear o frontend e sem tentar implementar tudo de uma vez.

## Fase 0 — Fundação

- [x] inicializar Laravel em `back/`;
- [x] Docker Compose de desenvolvimento (Laravel + PostgreSQL 17);
- [x] configurar PostgreSQL (`hacklab_dev`, `hacklab_test`, schema `hacklab`);
- [x] validar conexão com `php artisan hacklab:db-check` antes de qualquer migration;
- [x] configurar `.env.example`;
- [x] configurar CORS;
- [x] configurar Sanctum;
- [x] criar `/api/v1/health`;
- [x] configurar testes em PostgreSQL (`hacklab_test`, `.env.testing`);
- [x] DB guard (testes só em `_test`; dev/testes nunca no Supabase; produção só com SSL);
- [ ] configurar seed (feito na Fase 1);
- [x] criar estrutura de módulos;
- [x] criar tratamento padrão de exceções JSON.

Critério de pronto:

```text
GET /api/v1/health
→ 200
```

e migrations executam do zero em `hacklab_dev` (PostgreSQL) e todos os testes passam em `hacklab_test`, tudo via Docker.

Revalidação obrigatória após a troca MySQL → PostgreSQL (09/10/2026): health, CORS, erros JSON, Sanctum, testes, DB guard, migrations padrão e Pint. **Não iniciar a Fase 1 antes disso.** Revalidada em 09/10/2026: 26 testes passando em `hacklab_test`, migrations do zero em `hacklab_dev`, health 200, CORS, cookie CSRF e Pint ok. Fase 0 aprovada e concluída em 09/10/2026.

Pendência registrada: compilar a imagem Docker `app` numa rede sem o bloqueio do Fortinet (o firewall barra o `apt` do Debian). Até lá, a API roda no PHP da máquina contra o PostgreSQL do Docker. Não alterar certificados ou segurança da máquina para contornar.

## Fase 1 — Pessoas, autenticação e autorização

- [x] people;
- [x] users (vinculados a people; `sector_id` fica para a Fase 2);
- [x] roles;
- [x] permissions / role_permission;
- [x] login;
- [x] logout;
- [x] me;
- [x] policies;
- [x] seed dos 6 perfis;
- [x] auditoria inicial.

Concluída em 09/10/2026: 73 testes passando em `hacklab_test`, migrations do zero, seed idempotente, `db-check`, Pint, login/logout/me validados com cookies reais, nenhuma tabela em `public`, `hacklab_dev` intacto após os testes.

Endpoints entregues (`/api/v1`):

| Método | Rota | Acesso |
|---|---|---|
| POST | `/auth/login` | público (SPA, CSRF, 5 tentativas/min por e-mail+IP) |
| POST | `/auth/logout` | autenticado |
| GET | `/auth/me` | autenticado (inclui permissões) |
| GET | `/roles` | `roles.view` |
| GET/POST | `/people` | `people.view` / `people.manage` |
| GET/PATCH | `/people/{id}` | `people.view` ou a própria pessoa / `people.manage` |
| GET/POST | `/users` | `users.view` / `users.manage` |
| GET/PATCH | `/users/{id}` | `users.view` ou a própria conta / `users.manage` |
| PATCH | `/users/{id}/role` | `users.manage` |
| PATCH | `/users/{id}/status` | `users.manage` |

Todas as rotas autenticadas passam por `EnsureUserIsActive`: conta inativada perde o acesso na requisição seguinte.

Perfis:

```text
Administrador
Gestor
Editor
Consultor
Jurado
Votante
```

## Fase 2 — Evento e setores

- [ ] events;
- [ ] event_days;
- [ ] sectors;
- [ ] meetings;
- [ ] escopo Gestor/Editor por setor.

## Fase 3 — Participantes e equipes

- [ ] classes;
- [ ] participants;
- [ ] teams;
- [ ] team_members;
- [ ] regras de status;
- [ ] impedir membro ativo em múltiplas equipes;
- [ ] endpoints de ajuste manual.

Não implementar formação inteligente complexa antes do CRUD e regras básicas estarem estáveis.

## Fase 4 — Empresas e desafios

- [ ] companies;
- [ ] company_representatives;
- [ ] challenges;
- [ ] vínculo equipe/desafio.

## Fase 5 — Gestão operacional

- [ ] tasks;
- [ ] task_sectors;
- [ ] task_interactions;
- [ ] occurrences;
- [ ] occurrence_sectors;
- [ ] occurrence_interactions;
- [ ] encaminhamento;
- [ ] ocorrência → pendência;
- [ ] regras de visibilidade intersetorial.

## Fase 6 — Jurados e avaliações

- [ ] jurors;
- [ ] juror_team_assignments;
- [ ] evaluation_criteria;
- [ ] evaluations;
- [ ] evaluation_scores;
- [ ] fluxo de submissão.

Regra obrigatória:
atribuição de equipes é explícita.

## Fase 7 — Votação e resultados

- [ ] voting_sessions;
- [ ] public_votes;
- [ ] elegibilidade;
- [ ] impedir voto duplicado;
- [ ] cálculo/consulta de resultados.

Manter nota técnica e voto público separados.

## Fase 8 — Auditoria completa

- [ ] audit_logs;
- [ ] filtros;
- [ ] paginação;
- [ ] detalhes before/after;
- [ ] endpoint somente leitura;
- [ ] Policies apenas Administrador.

## Fase 9 — Integração Even3

- [ ] event_integrations;
- [ ] external_ticket_types;
- [ ] event_registrations;
- [ ] attendance_records;
- [ ] integration_sync_runs;
- [ ] EventPlatformProvider;
- [ ] Even3Client;
- [ ] Even3Mapper;
- [ ] Even3SyncService;
- [ ] status;
- [ ] sync manual;
- [ ] PoC real;
- [ ] webhook opcional.

## Fase 10 — Integração progressiva do frontend

Migrar uma tela por vez.

Ordem sugerida:

1. login;
2. usuários;
3. evento;
4. participantes;
5. equipes;
6. empresas;
7. desafios;
8. setores;
9. pendências;
10. ocorrências;
11. jurados;
12. avaliações;
13. votação;
14. presença;
15. auditoria.

Para cada tela:

- [ ] criar service JS;
- [ ] ligar GET;
- [ ] ligar POST/PATCH/DELETE;
- [ ] tratar loading;
- [ ] tratar erro;
- [ ] preservar responsividade;
- [ ] remover mock correspondente.

## Fase 11 — Relatórios

- [ ] endpoints agregados;
- [ ] relatório geral;
- [ ] participantes/presença;
- [ ] gestão/operação;
- [ ] encerramento/resultados;
- [ ] financeiro se houver dados.

Relatório deve ser gerado com dados reais do backend.

## Fase 12 — Implantação (Render + Supabase)

- [ ] projeto Supabase de produção, schema `hacklab` criado (SQL em `infraestrutura.md` §3.1);
- [ ] alvo de produção no `back/Dockerfile` (PHP-FPM + servidor HTTP, porta `PORT` do Render);
- [ ] Web Service no Render com variáveis de ambiente (Session pooler, `sslmode=require`);
- [ ] `migrate --force` no start/pre-deploy;
- [ ] Static Site do Vue com `VITE_API_URL` e rewrite `/* → /index.html`;
- [ ] **PoC do cookie de sessão** (rewrite `/api` no Static Site ou domínio próprio; ver `infraestrutura.md` §3.4);
- [ ] `GET /api/v1/system/readiness` (Laravel + conexão + consulta ao banco);
- [ ] `pg_dump` periódico para armazenamento fora do Supabase + teste de restauração.

## Fase 13 — Teste de carga do dia 3

- [ ] degraus de 50, 100, 200 e 300 usuários concorrentes;
- [ ] fluxos: login, consulta de equipes, votação e gravação de voto, jurados, resultados;
- [ ] medir p95, taxa de erro, memória da instância e conexões no pooler;
- [ ] decidir: Render Free aprovado, ou contingência (mais compute no Render ou outro host para o Laravel).

## Critérios globais

Antes de considerar backend pronto:

- [ ] banco sobe do zero (`migrate:fresh --seed`);
- [ ] ambiente de dev/testes sobe do zero com `docker compose up`;
- [ ] produção conecta no Supabase só pelo Session pooler, com SSL;
- [ ] seed funciona;
- [ ] autenticação funciona;
- [ ] Policies cobrem endpoints;
- [ ] testes críticos passam;
- [ ] auditoria funciona;
- [ ] sync externo é idempotente;
- [ ] frontend não acessa Even3 diretamente;
- [ ] secrets não estão versionados;
- [ ] erros são JSON consistentes;
- [ ] sem dependência de localStorage como banco;
- [ ] documentação atualizada.

## Estratégia para prazo curto

Prioridade máxima:

```text
Auth
→ Participantes/Equipes
→ Empresas/Desafios
→ Setores/Pendências/Ocorrências
→ Jurados/Avaliações
→ Votação
→ Auditoria
→ Even3
→ Relatórios
```

Se faltar tempo, reduzir automações, não integridade.

Nunca cortar:
- migrations;
- validação;
- autorização;
- constraints;
- auditoria de ações sensíveis;
- testes dos fluxos críticos.
