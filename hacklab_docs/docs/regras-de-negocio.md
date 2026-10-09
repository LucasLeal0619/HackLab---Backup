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

## 3. Equipes

- Equipe possui participantes.
- Participante não pode estar simultaneamente em duas equipes ativas.
- Formação deve tentar equilibrar turmas quando possível.
- Não forçar equilíbrio impossível.
- Equipe pode ser vinculada a desafio.

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
- título;
- descrição;
- setor de origem;
- setor responsável;
- setores envolvidos;
- prioridade;
- status;
- prazo;
- responsável individual opcional;
- histórico.

Deve existir um único setor responsável principal.

## 6. Ocorrência

Ocorrência = algo que aconteceu.

Campos conceituais:
- título;
- descrição;
- setor de origem;
- setor responsável;
- setores envolvidos;
- prioridade;
- status;
- data/hora;
- histórico.

Uma ocorrência pode gerar uma pendência vinculada.

## 7. Visibilidade intersetorial

Um setor visualiza:
- itens originados por ele;
- itens pelos quais é responsável;
- itens nos quais aparece como envolvido.

Setores não relacionados não visualizam.

Administrador visualiza tudo.

## 8. Histórico e interação

Pendências e ocorrências possuem histórico contextual.

Registrar:
- criação;
- comentário;
- encaminhamento;
- alteração de responsável;
- prioridade;
- status;
- inclusão/remoção de setor;
- resolução;
- reabertura;
- geração de pendência.

Histórico de colaboração não substitui Auditoria.

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
- categoria externa;

para determinar automaticamente avaliações.

Um jurado pode:
- não possuir empresa;
- avaliar várias equipes.

Uma equipe pode:
- ter vários jurados.

Sem atribuições:

```text
Nenhuma avaliação atribuída a você no momento.
```

## 11. Avaliação técnica e voto público

São domínios separados.

Nunca misturar nota técnica de jurado com voto público antes da regra final de resultado.

## 12. Empresas

Empresa é entidade interna.

Pode possuir:
- nome;
- descrição;
- contatos;
- representantes;
- desafios.

Pessoa inscrita externamente como `Empresa / Convidado` não cria automaticamente uma Empresa.

Ela pode ser posteriormente vinculada como representante.

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
