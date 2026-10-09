# Integração Even3

## 1. Objetivo

Usar a Even3 como plataforma externa para:
- página pública;
- inscrição;
- entradas/categorias;
- QR/código de credenciamento;
- credenciamento;
- check-in.

O HackLab consome/sincroniza esses dados para gestão interna.

## 2. Princípio

O frontend nunca chama a Even3.

```text
Vue
 ↓
Laravel
 ↓
Even3
```

Credenciais da Even3 ficam exclusivamente no backend.

## 3. Abstração

Criar contrato genérico:

```text
EventPlatformProvider
```

Implementação:

```text
Even3Provider
```

Componentes sugeridos:

```text
backend/app/Integrations/
├── EventPlatform/
│   ├── Contracts/
│   │   └── EventPlatformProvider.php
│   └── DTOs/
└── Even3/
    ├── Even3Client.php
    ├── Even3Provider.php
    ├── Even3Mapper.php
    ├── Even3SyncService.php
    └── Exceptions/
```

## 4. Configuração

`.env`:

```text
EVEN3_ENABLED=false
EVEN3_BASE_URL=
EVEN3_TOKEN=
EVEN3_EVENT_ID=
EVEN3_WEBHOOK_SECRET=
```

Os nomes exatos podem ser adaptados.

Nunca commitar token real.

## 5. Dados sincronizados

### Evento

Trazer, quando disponível:
- ID externo;
- título;
- início/fim;
- local;
- URL pública;
- entradas/categorias;
- programação.

### Inscritos

Trazer:
- ID externo do inscrito;
- nome;
- e-mail;
- telefone se disponível;
- categoria/entrada;
- status da inscrição;
- código de credenciamento quando aplicável;
- metadados úteis.

### Categorias

Mapear entrada externa para categoria interna.

Exemplo:

```text
ID externo 1001 → PARTICIPANT
ID externo 1002 → ORGANIZATION
ID externo 1003 → COMPANY_GUEST
ID externo 1004 → JUROR
ID externo 1005 → PUBLIC
```

Não mapear somente por nome.

## 6. Sincronização central

Endpoint interno sugerido:

```http
POST /api/v1/integrations/even3/sync
```

Apenas Administrador.

Payload opcional:

```json
{
  "scope": "all"
}
```

Scopes futuros:
- event;
- attendees;
- schedule;
- attendance.

Resposta:

```json
{
  "data": {
    "status": "completed",
    "created": 10,
    "updated": 4,
    "skipped": 82,
    "errors": 0,
    "synced_at": "..."
  }
}
```

## 7. Algoritmo de inscritos

Para cada inscrito externo:

1. buscar `event_registration` pelo ID externo;
2. se existir, atualizar dados permitidos;
3. se não existir:
   - tentar associação controlada com Person;
   - criar Person quando necessário;
   - criar EventRegistration;
4. associar ExternalTicketType;
5. atualizar status;
6. nunca duplicar silenciosamente.

E-mail pode ajudar no match, mas ID externo é a referência principal da integração.

## 8. Participante interno

Se a categoria mapeada for `PARTICIPANT`, o sistema pode:
- sugerir criação/vínculo com `participants`;
- ou criar automaticamente se esta regra for habilitada.

Mesmo assim, campos internos continuam no HackLab:
- turma;
- status;
- equipe;
- desafio.

## 9. Jurado

Categoria externa `JUROR` não deve conceder automaticamente acesso.

Fluxo recomendado:

```text
Even3: inscrição como Jurado
        ↓
Person + EventRegistration
        ↓
Jurado interno criado/vinculado
        ↓
Administrador confirma conta/perfil
        ↓
equipes atribuídas explicitamente
```

`assignedTeamIds` é regra interna.

## 10. Empresa/Convidado

Categoria externa `COMPANY_GUEST` cria/vincula pessoa e inscrição.

Não criar empresa automaticamente.

Administrador pode vincular a pessoa como representante de empresa existente.

## 11. Público

Público pode existir apenas como:
- Person;
- EventRegistration;
- Attendance.

Não precisa de User.

Se criar conta para votar:
- vincular à Person existente;
- conceder Votante conforme regra interna.

## 12. Presença

Na arquitetura atual, o HackLab não lê QR.

O operador usa a Even3.

HackLab sincroniza presença.

O modelo interno é por dia:

```text
Dia 1
Dia 2
Dia 3
```

Usar `event_days` + `attendance_records`.

## 13. Múltiplos check-ins

A Even3 possui recursos de credenciamento/múltiplos check-ins e check-in por atividade, mas a implementação do HackLab deve ser baseada no contrato real disponível no ambiente escolhido.

### Ponto de validação obrigatório

Antes de depender exclusivamente de sincronização por API para presença diária, realizar PoC para confirmar:

- quais endpoints GET devolvem histórico de check-ins;
- se múltiplos check-ins por dia são retornados;
- quais IDs correlacionam check-in e inscrito;
- se data/hora completa está disponível;
- como atividade/sessão é representada;
- como cancelamentos aparecem.

Não assumir comportamento não confirmado.

## 14. Webhook

Se a infraestrutura do Senac permitir endpoint HTTPS público, usar Webhook como caminho em tempo real.

Exemplo:

```http
POST /api/v1/webhooks/even3
```

Fluxo:

```text
Even3
 ↓ POST
WebhookController
 ↓
validação/autenticidade
 ↓
persistir webhook_receipt
 ↓
Job
 ↓
processar
 ↓
atualizar banco
```

### Eventos de interesse

Prioridade:
- inscrição confirmada no evento;
- inscrição cancelada no evento;
- inscrição confirmada em atividade;
- inscrição cancelada em atividade;
- check-in no evento;
- check-in em atividade.

Ignorar inicialmente:
- vendas;
- submissões;
- outros eventos sem uso no HackLab.

## 15. Idempotência

Webhook pode ser reenviado.

Nunca duplicar presença ou inscrição.

Usar, conforme payload disponível:
- event ID externo;
- tipo;
- attendee ID;
- session/activity ID;
- timestamp;
- hash do payload.

Persistir recibo antes do processamento.

## 16. Se webhook não for permitido

Modo fallback:

```text
Administrador
→ Inscrições e Presença
→ Sincronizar Even3
```

O backend consulta a API e reconcilia dados.

Também pode existir Job agendado se a política do ambiente permitir chamadas de saída:

```text
HackLab → Even3
```

A arquitetura não pode depender obrigatoriamente de conexões de entrada externas.

## 17. Requisitos de rede para webhook

Para webhook direto:

- DNS público;
- HTTPS válido;
- porta 443 acessível;
- proxy/firewall permitindo POST externo;
- rota pública específica;
- autenticação/validação do webhook.

Se servidor estiver apenas em IP privado, Even3 não conseguirá acessá-lo diretamente.

## 18. Tela no HackLab

A antiga ideia de `Credenciais e Presença` deve evoluir para algo como:

```text
Inscrições e Presença
```

Mostrar:
- status da integração;
- evento externo;
- última sincronização;
- total de inscritos;
- totais por categoria;
- presentes por dia;
- ausentes;
- inconsistências;
- botão `Sincronizar Even3`.

Não incluir:
- câmera;
- leitor QR;
- botão “Liberar entrada”;
- perfil Validador.

## 19. Status de integração

Criar endpoints internos:

```http
GET /api/v1/integrations/even3/status
POST /api/v1/integrations/even3/sync
```

Resposta de status pode incluir:

```json
{
  "data": {
    "enabled": true,
    "connected": true,
    "external_event_id": "...",
    "last_sync_at": "...",
    "last_sync_status": "success"
  }
}
```

## 20. Observabilidade

Registrar:
- início/fim de sync;
- quantidade criada/atualizada;
- erros;
- endpoint externo com falha;
- status HTTP;
- webhook recebido/processado;
- tempo de processamento.

Nunca logar token.

## 21. Erros

Separar:
- erro de autenticação externa;
- rate limit;
- timeout;
- erro de payload;
- evento não encontrado;
- inscrito inconsistente;
- erro de mapeamento;
- indisponibilidade externa.

Uma falha da Even3 não deve derrubar toda a API do HackLab.

## 22. Timeouts/retry

Definir timeout curto e retry controlado para chamadas idempotentes.

Não repetir automaticamente operações que possam gerar efeito duplicado sem confirmação.

## 23. Testes

Criar testes com HTTP fake/mock para:
- sync de evento;
- novo inscrito;
- atualização;
- cancelamento;
- duplicidade;
- categoria desconhecida;
- erro 401/403;
- timeout;
- webhook duplicado;
- webhook inválido;
- criação de presença;
- presença já existente.

Não usar API real em testes automatizados.

## 24. PoC antes de produção

Checklist:

- [ ] criar evento de teste Even3;
- [ ] gerar token;
- [ ] consultar evento;
- [ ] consultar inscritos;
- [ ] confirmar IDs das entradas;
- [ ] mapear categorias;
- [ ] realizar credenciamento;
- [ ] testar múltiplos check-ins;
- [ ] testar atividade/sessão;
- [ ] inspecionar payload de webhook;
- [ ] confirmar mecanismo de autenticação do webhook;
- [ ] confirmar consulta posterior do histórico de presença;
- [ ] testar cancelamento;
- [ ] testar falha de rede;
- [ ] documentar resultados reais.

Somente depois do PoC fechar o contrato definitivo.
