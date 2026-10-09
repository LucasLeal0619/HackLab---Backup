# Regras de Negócio

## 1. Terminologia

### Pessoa
Qualquer indivíduo conhecido pelo HackLab.

### Inscrito
Pessoa com inscrição no evento, normalmente originada de plataforma externa.

### Participante
Aluno que efetivamente participa do Hackathon, integra equipe, desenvolve solução e apresenta resultado.

### Usuário
Pessoa com credencial de acesso ao HackLab.

### Jurado
Pessoa habilitada internamente para avaliar equipes atribuídas.

### Votante
Usuário habilitado para voto público.

Esses conceitos não são sinônimos.

## 2. Participantes

- Contagem é dinâmica.
- Não assumir 60 alunos.
- Não assumir 10 equipes de 6.
- Tamanho preferido de equipe pode ser 6, mas não é regra rígida.
- Turmas podem ser Breno/Rafael/Clara ou outras definidas no banco.
- Status possíveis devem suportar ao menos: disponível, indisponível, desistente.
- Mudança de disponibilidade não deve reembaralhar automaticamente equipes já formadas.
- Ajustes manuais são permitidos.
- Participante sem turma é permitido; turma inativa não recebe novos participantes.
- A mesma pessoa não pode ser dois participantes do mesmo evento.

## 3. Equipes

- Equipe possui participantes.
- Participante não pode estar simultaneamente em duas equipes ativas.
- Formação deve tentar equilibrar turmas quando possível.
- Não forçar equilíbrio impossível.
- Equipe pode ser vinculada a desafio.
- Equipe não pertence a turma: reúne alunos de várias turmas.
- Só participante `AVAILABLE` entra em equipe (adicionar ou mover); `UNAVAILABLE`/`WITHDRAWN` mantém o vínculo que já tinha.
- Remover e mover são ações explícitas e preservam o histórico.
- Formação automática ainda não existe; quando existir, usa as mesmas operações do `TeamService`.

## 4. Perfis e setores

Perfis:

```text
Administrador
Gestor
Editor
Consultor
Jurado
Votante
```

### Administrador
Visão global.

### Gestor
Um setor principal.
Pode gerenciar demandas do próprio setor e encaminhar demandas para outro setor.

### Editor
Um setor principal.
Pode atuar dentro do setor com menos poderes que Gestor.

### Consultor
Acompanhamento/orientação transversal.

### Jurado
Somente avaliações atribuídas e funcionalidades necessárias ao perfil.

### Votante
Somente votação e experiência pública autorizada.

## 5. Pendência

Pendência = algo que precisa ser feito.

Campos conceituais:
- referência legível (`PEN-0001`), gerada pelo backend;
- título;
- descrição;
- setor de origem (nunca muda);
- setor responsável (único; muda só por encaminhamento);
- setores envolvidos adicionais;
- prioridade (`LOW`, `MEDIUM`, `HIGH`, `URGENT`);
- status (`PENDING`, `IN_PROGRESS`, `COMPLETED`);
- prazo;
- responsável individual opcional (usuário ativo do setor responsável);
- histórico.

Concluir é operação própria (preenche `resolved_at`). Reabrir leva para `IN_PROGRESS` e limpa `resolved_at`. Não há exclusão.

### 5.1 Quem cria

- Administrador: escolhe origem e responsável entre setores ativos.
- Gestor: origem = próprio setor; responsável = próprio setor ou outro setor ativo; pode informar envolvidos.
- Editor: origem e responsável = próprio setor; pode se atribuir como responsável individual.
- Consultor, Jurado, Votante: não criam.

### 5.2 Quem opera

Princípio: **origem/envolvido acompanha e conversa; o responsável atual controla a execução.**

- Gestor do setor responsável: altera título, descrição, prioridade, prazo, status, responsável individual e envolvidos; encaminha; conclui/reabre.
- Editor do setor responsável: comenta, altera status, conclui/reabre. Não encaminha, não muda envolvidos, não atribui outra pessoa, não muda prioridade/prazo.
- Gestor/Editor de setor só de origem ou envolvido: visualiza e comenta.
- Administrador: tudo. Consultor: visualiza e comenta qualquer demanda.

## 6. Ocorrência

Ocorrência = algo que aconteceu.

Campos conceituais:
- referência legível (`OCO-0001`);
- título, descrição e categoria;
- setor de origem, setor responsável e envolvidos adicionais;
- responsável individual opcional;
- prioridade;
- status (`OPEN`, `IN_PROGRESS`, `RESOLVED`);
- dia do evento (opcional), data/hora, local, equipe (opcional);
- observações e solução;
- histórico.

Resolver é operação própria e exige solução. Reabrir volta para `OPEN`, limpa `resolved_at` e mantém a solução; uma nova solução substitui o resumo, e a anterior fica no histórico.

Criação e operação seguem as mesmas regras da pendência (§5.1 e §5.2), com "resolver" no lugar de "concluir".

Uma ocorrência pode gerar uma ou várias pendências vinculadas (Administrador ou Gestor de setor relacionado; para o Gestor, a origem é o próprio setor). A ligação é imutável.

## 7. Visibilidade intersetorial

Um setor visualiza:
- itens originados por ele;
- itens pelos quais é responsável;
- itens nos quais aparece como envolvido.

Setores não relacionados não visualizam (nem aparecem na listagem: a query já aplica a visibilidade).

Administrador visualiza tudo. Consultor visualiza tudo, sem poder operacional estrutural. Jurado e Votante não acessam pendências e ocorrências.

### 7.1 Encaminhamento

Operação própria, com motivo obrigatório. Ao encaminhar:
- o responsável anterior passa a envolvido (exceto se for a origem, que já vê a demanda);
- o novo responsável sai da lista de envolvidos, se estava nela;
- a origem nunca muda;
- o responsável individual é limpo;
- tudo numa transação, com um único registro de auditoria.

Encaminha: Administrador (qualquer demanda) e Gestor do setor responsável atual. Destino precisa ser setor ativo do mesmo evento.

### 7.2 Setor inativo

Setor responsável por demanda aberta não pode ser inativado (encaminhe antes). Ser origem ou envolvido não bloqueia. Setor inativo não recebe nova demanda, não vira envolvido e não recebe encaminhamento.

## 8. Histórico e interação

Pendências e ocorrências possuem histórico contextual.

Registrar:
- criação;
- comentário;
- encaminhamento;
- alteração de responsável;
- prioridade;
- prazo;
- status;
- inclusão/remoção de setor;
- conclusão/resolução;
- reabertura;
- geração de pendência.

Histórico é somente inserção (sem editar/apagar). Não é chat genérico: é contextual à demanda.

Histórico de colaboração não substitui Auditoria: alteração estrutural gera interação **e** auditoria; comentário gera só interação.

## 9. Auditoria

Auditoria é global e somente leitura.

Apenas Administrador acessa a visão global.

Registrar ações relevantes, não cliques de interface.

## 10. Jurados

Regra obrigatória:

```text
Jurado
→ equipes atribuídas explicitamente
```

Não usar:
- empresa do jurado;
- desafio da empresa;
- representante de empresa;
- categoria externa;
- turma ou setor;

para determinar automaticamente avaliações.

Um jurado pode:
- não possuir empresa;
- não possuir conta de acesso (Juror ≠ User);
- ser a mesma Person de um representante de empresa;
- avaliar várias equipes.

Uma equipe pode:
- ter vários jurados.

Sem atribuições:

```text
Nenhuma avaliação atribuída a você no momento.
```

Para avaliar pelo sistema: Person + Juror ACTIVE + User ACTIVE da mesma Person com permissão de avaliar + atribuição ACTIVE. Ser Administrador não cria identidade de jurado.

Revogar uma atribuição preserva a avaliação (histórico); ela só deixa de contar enquanto a atribuição estiver revogada. Reativar volta a contar. Jurado inativado continua com as atribuições ativas (esperadas no progresso) até o Administrador revogá-las explicitamente.

### 10.1 Avaliação

- Critérios numéricos (faixa e peso próprios). Estrutura trava na primeira avaliação do evento; só nome/descrição continuam editáveis.
- Rascunho (`DRAFT`) pode ser parcial. Só o próprio jurado, com atribuição ativa, escreve. Um jurado nunca vê a avaliação de outro.
- Envio (`SUBMITTED`) exige nota válida em todos os critérios ativos e trava a avaliação.
- Correção: o Administrador **não altera nota**. Ele solicita revisão (`REVISION_REQUESTED`, motivo obrigatório); o próprio jurado corrige e reenvia.
- Percentual da avaliação = média ponderada das notas normalizadas pela faixa de cada critério.
- Resultado técnico da equipe = média dos percentuais das avaliações enviadas com atribuição ativa (consulta administrativa, não persistida).

### 10.2 Quem vê o quê

- Jurado: só a própria avaliação.
- Administrador: todas (sem editar notas).
- Gestor e Consultor: só progresso agregado, sem notas nem comentários.
- Editor e Votante: sem acesso.

## 11. Avaliação técnica e voto público

São domínios separados.

Nunca misturar nota técnica de jurado com voto público antes da regra final de resultado (Fase 7).

## 12. Empresas

Empresa é entidade interna e pertence a um evento.

Pode possuir:
- nome, razão social, documento, segmento e tipo (`PARTICIPANT`, `PARTNER`, `SPONSOR`, `SUPPORT`, `OTHER`);
- descrição;
- contatos;
- representantes (Persons);
- desafios.

Status: `DRAFT`, `CONFIRMED`, `INACTIVE`. "Aguardando desafio" e "Com desafio" são situações derivadas dos desafios, não status.

Empresa não é apagada: é inativada. Inativa não recebe novos desafios nem representantes ativos; o histórico permanece.

Pessoa inscrita externamente como `Empresa / Convidado` não cria automaticamente uma Empresa.

Ela pode ser posteriormente vinculada como representante.

Representante de empresa **não** vira Jurado automaticamente, e a empresa **não** define quais equipes um Jurado avalia.

## 12.1 Desafios

- Desafio pertence ao evento; empresa é opcional (desafio institucional).
- Fluxo: Rascunho (`DRAFT`) → Recebido (`RECEIVED`) → Em análise (`UNDER_REVIEW`) → Aprovado (`APPROVED`) → Distribuído (`DISTRIBUTED`) → Em desenvolvimento (`IN_DEVELOPMENT`) → Finalizado (`FINISHED`).
- Nesta versão: uma equipe tem no máximo um desafio e um desafio vai para no máximo uma equipe.
- Distribuir um desafio aprovado vincula a equipe e muda o status para `DISTRIBUTED` na mesma operação.
- A equipe destino não pode já ter outro desafio (422, sem sobrescrever).
- Mover um desafio distribuído para outra equipe é atômico: libera a anterior e vincula a nova.
- Retirar a equipe de um desafio `DISTRIBUTED` faz ele voltar para `APPROVED`.
- Em `IN_DEVELOPMENT` ou `FINISHED`, mover ou retirar a equipe exige primeiro mudar o status explicitamente.
- Desafio pode existir sem equipe durante preparação e análise.

## 13. Integração de inscrição

Categorias externas sugeridas:

```text
Participante
Organização
Empresa / Convidado
Jurado
Público
```

A plataforma externa pode usar nomes diferentes.

O HackLab mantém mapeamento:

```text
external_ticket_type_id
→ internal_category
```

Não depender apenas do texto.

## 14. Sincronização de pessoas

A sincronização traz todos os inscritos do evento, não apenas Participantes.

O HackLab:
1. localiza vínculo externo existente;
2. se não existir, tenta associação controlada;
3. cria/atualiza `Person`;
4. cria/atualiza `EventRegistration`;
5. classifica pela entrada externa;
6. não concede automaticamente permissão interna sensível.

## 15. Jurado externo

Uma inscrição externa classificada como `JUROR` indica que a pessoa está inscrita no evento como jurado.

Isso não deve conceder sozinho:
- conta;
- senha;
- role;
- equipes atribuídas.

Esses vínculos são internos.

## 16. Público e Votante

Público pode existir sem conta.

Quando criar conta para votar:
- tentar vincular à pessoa já sincronizada;
- não duplicar pessoa;
- aplicar critérios de elegibilidade definidos pelo HackLab.

## 17. Organização externa vs perfil interno

Categoria externa `ORGANIZATION` não define o perfil.

Exemplo:

```text
Categoria externa: Organização
Perfil interno: Gestor
Setor: Produção
```

ou:

```text
Categoria externa: Organização
Perfil interno: Consultor
```

## 18. Presença

Na arquitetura atual:
- QR e credenciamento são feitos fora do HackLab;
- HackLab sincroniza e consolida presença.

A presença deve poder ser analisada por dia do evento.

Criar `event_days`.

Não modelar presença apenas como booleano global.

## 19. Contas

Pessoa pode não possuir conta.

Conta deve ser criada somente quando houver necessidade de acesso ao HackLab.

## 20. Relatórios

Relatórios devem usar dados do banco, não DOM do frontend.

Jurados e voto público devem permanecer distinguíveis.

## 21. Regra de consistência

Sempre preferir:
- IDs internos;
- IDs externos persistidos;
- constraints no banco;
- transactions;
- operações idempotentes.

Evitar regras frágeis baseadas em texto.
