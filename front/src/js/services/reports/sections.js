import * as d from '@/js/services/reports/shared'

// Blocos neutros: o template diz "o quê", o document.js decide "como" desenhar.
export const kv = (rows) => ({ kind: 'kv', rows })
export const table = (head, rows, widths, empty) => ({ kind: 'table', head, rows, widths, empty })
export const sub = (title, blocks) => ({ kind: 'sub', title, blocks })
export const note = (text) => ({ kind: 'note', text })
export const section = (title, blocks) => ({ title, blocks })

export function participantsSection(state, { full = true } = {}) {
  const facts = d.peopleFacts(state)
  const blocks = [
    kv([
      ['Participantes cadastrados', d.count(facts.total)],
      ['Disponíveis', d.count(facts.available)],
      ['Indisponíveis ou desistentes', d.count(facts.unavailable)],
      ['Com presença registrada em ao menos um dia', d.count(facts.withPresence)],
    ]),
    sub('Distribuição por turma', [table(['Turma', 'Cadastrados', 'Disponíveis', 'Em equipe'], d.turmaRows(state), ['*', 70, 70, 70])]),
    sub('Frequência por dia', [table(['Dia', 'Presentes', 'Ausentes', 'Não registrado', 'Presença'], d.dayRows(state), ['*', 60, 60, 80, 60])]),
  ]
  if (full) {
    blocks.push(sub('Participantes cadastrados', [table(['Participante', 'Turma', 'Disponibilidade', 'Equipe'], d.participantRows(state), ['*', 70, 90, 70])]))
  }
  blocks.push(sub('Presença por participante', [table(['Participante', 'Turma', 'Equipe', 'Dia 1', 'Dia 2', 'Dia 3', 'Frequência'], d.presenceRows(state), ['*', 48, 54, 56, 56, 56, 50])]))
  return section('Participantes e presença', blocks)
}

export function accessSection(state) {
  const credentials = state.credentials || []
  return section('Acesso e presença', [
    kv([
      ['Credenciais emitidas', d.count(credentials.length)],
      ['Credenciais ativas', d.count(credentials.filter((item) => item.status === 'Ativa').length)],
      ['Bloqueadas ou canceladas', d.count(credentials.filter((item) => item.status !== 'Ativa').length)],
    ]),
    table(['Categoria', 'Credenciais', 'Ativas', 'Dia 1', 'Dia 2', 'Dia 3'], d.accessRows(state), ['*', 65, 50, 45, 45, 45]),
    note('Presença por dia considera todas as categorias de credencial: participantes, organização, professores, jurados, público e convidados.'),
  ])
}

export function teamsSection(state) {
  return section('Equipes', [
    kv([['Equipes cadastradas', d.count((state.teams || []).length)], ['Tamanho de referência por equipe', d.count(state.teamSize)]]),
    table(['Equipe', 'Integrantes ativos', 'Empresa', 'Desafio', 'Status'], d.teamRows(state), [55, 60, '*', '*', 65]),
  ])
}

export function companiesSection(state) {
  return section('Empresas e desafios', [
    kv([['Empresas cadastradas', d.count((state.companies || []).length)], ['Desafios cadastrados', d.count((state.challenges || []).length)], ['Desafios distribuídos a equipes', d.count((state.challenges || []).filter((item) => item.teamId).length)]]),
    sub('Empresas', [table(['Empresa', 'Segmento', 'Tipo', 'Desafios'], d.companyRows(state), ['*', 90, 110, 50])]),
    sub('Desafios', [table(['Desafio', 'Empresa', 'Equipe', 'Status'], d.challengeRows(state), ['*', '*', 60, 70])]),
  ])
}

export function sectorsSection(state, { detailed = false } = {}) {
  const blocks = d.SETORES.map((name) => {
    const content = [kv(d.sectorFacts(state, name))]
    if (detailed) {
      if (name === 'Recursos Humanos') content.push(table(['Integrante', 'Setor', 'Função', 'Perfil', 'Status'], (state.orgMembers || []).map((item) => [d.text(item.name), d.text(item.sector), d.text(item.func), d.text(item.profile), d.text(item.status)]), ['*', 80, 70, 60, 45]))
      if (name === 'Marketing') content.push(table(['Campanha', 'Canal', 'Público', 'Data', 'Status'], (state.campaigns || []).map((item) => [d.text(item.name), d.text(item.channel), d.text(item.audience), d.date(item.date), d.text(item.status)]), ['*', 60, 70, 55, 65]))
      if (name === 'Tecnologia') {
        content.push(table(['Equipamento', 'Categoria', 'Qtd.', 'Local', 'Status'], d.equipmentRows(state), ['*', 65, 30, 55, 70]))
        content.push(table(['Infraestrutura', 'Responsável', 'Status'], d.infraRows(state), ['*', '*', 70]))
      }
      if (name === 'Produção') content.push(table(['Material', 'Necessário', 'Disponível', 'Status'], d.materialRows(state), ['*', 60, 60, 70]))
    }
    return sub(name, content)
  })
  return section('Gestão e setores', blocks)
}

export function meetingsSection(state, { split = false } = {}) {
  const meetings = sub('Reuniões', [
    kv([['Reuniões registradas', d.count((state.meetings || []).length)], ['Atas registradas', d.count((state.meetings || []).filter((item) => item.ata).length)], ['Decisões registradas', d.count((state.decisions || []).length)]]),
    table(['Reunião', 'Data', 'Tipo', 'Status', 'Ata'], d.meetingRows(state), ['*', 55, 65, 85, 90]),
  ])
  const decisions = sub('Decisões e encaminhamentos', [table(['Decisão', 'Setor', 'Responsável', 'Data', 'Status'], d.decisionRows(state), ['*', 65, 85, 50, 65])])
  const tasks = sub('Pendências', [
    kv([['Pendências registradas', d.count((state.tasks || []).length)], ['Pendências abertas', d.count(d.openTasks(state).length)]]),
    table(['Pendência', 'Setor', 'Responsável', 'Prazo', 'Prioridade', 'Status'], d.taskRows(state), ['*', 65, 80, 50, 50, 60]),
  ])
  const documents = sub('Documentos', [table(['Documento', 'Categoria', 'Setor', 'Versão', 'Data'], d.documentRows(state), ['*', 65, 60, 40, 55])])
  if (split) return [section('Reuniões, atas e decisões', [meetings, decisions]), section('Pendências', tasks.blocks), section('Documentos', documents.blocks)]
  return [section('Reuniões, pendências e documentos', [meetings, decisions, tasks, documents])]
}

export function operationSection(state) {
  return section('Operação do evento', [
    sub('Frequência por dia', [table(['Dia', 'Presentes', 'Ausentes', 'Não registrado', 'Presença'], d.dayRows(state), ['*', 60, 60, 80, 60])]),
    sub('Salas e estrutura', [table(['Espaço', 'Tipo', 'Capacidade', 'Finalidade', 'Status'], d.spaceRows(state), [70, 50, 55, '*', 75])]),
    sub('Equipamentos e suporte', [
      kv([['Equipamentos cadastrados', d.count((state.equipment || []).length)], ['Com problema', d.count((state.equipment || []).filter((item) => item.status === 'Com problema').length)], ['Ocorrências abertas de Tecnologia', d.count(d.sectorOccurrences(state, 'Tecnologia').filter(d.isOpen).length)]]),
      table(['Equipamento', 'Categoria', 'Qtd.', 'Local', 'Status'], d.equipmentRows(state), ['*', 65, 30, 55, 70]),
    ]),
  ])
}

export function occurrencesSection(state) {
  const items = state.occurrences || []
  return section('Ocorrências', [
    kv([['Ocorrências registradas', d.count(items.length)], ['Abertas', d.count(items.filter(d.isOpen).length)], ['Resolvidas', d.count(items.length - items.filter(d.isOpen).length)]]),
    table(['Ocorrência', 'Categoria', 'Local', 'Setor', 'Prioridade', 'Status', 'Responsável'], d.occurrenceRows(state), ['*', 58, 45, 55, 45, 52, 70]),
  ])
}

export function presentationsSection(state) {
  return section('Apresentações', [table(['Equipe', 'Desafio', 'Solução apresentada'], d.presentationRows(state), [55, '*', '*'])])
}

export function judgesSection(state) {
  const judges = d.activeJudges(state).length
  return section('Avaliação dos jurados', [
    kv([['Jurados ativos', d.count(judges)], ['Critérios de avaliação', d.count((state.criteria || []).length)], ['Avaliações concluídas', d.count((state.evaluations || []).filter((item) => item.status === 'concluida').length)]]),
    sub('Jurados', [table(['Jurado', 'Empresa', 'Cargo', 'Status'], d.judgeRows(state), ['*', '*', 80, 50])]),
    sub('Critérios de avaliação', [table(['Critério', 'Tipo', 'Escala', 'Peso', 'Situação'], d.criterionRows(state), ['*', 75, 50, 50, 45])]),
    sub('Resultado técnico por equipe', [
      table(['Equipe', 'Avaliações concluídas', 'Resultado técnico', 'Andamento'], d.evaluationRows(state), ['*', 90, 80, 80]),
      note('Resultado técnico: média aritmética das notas registradas nas avaliações concluídas. Notas individuais dos jurados não são exibidas.'),
    ]),
  ])
}

export function votingSection(state) {
  const ballots = state.voting?.ballots || []
  return section('Votação do público', [
    kv([['Situação da votação', d.text(state.voting?.status)], ['Votos registrados', d.count(ballots.length)]]),
    table(['Equipe', 'Votos', 'Percentual'], d.voteRows(state), ['*', 70, 70]),
    note('A votação do público é apresentada separadamente e não é somada ao resultado técnico dos jurados.'),
  ])
}

export function resultsSection(state) {
  return section('Resultados e premiação', [
    kv([['Resultados divulgados', d.yesNo(state.resultsReleased)], ['Premiações cadastradas', d.count((state.awards || []).length)]]),
    table(['Premiação', 'Critério', 'Equipe', 'Descrição'], d.awardRows(state), [100, 90, 60, '*']),
  ])
}

export function financeSections(state) {
  const money = d.financeTotals(state)
  const fmt = (value) => (value == null ? d.NOT_INFORMED : d.brl(value))
  return [
    section('Orçamento', [kv([
      ['Orçamento previsto', fmt(money.budget)],
      ['Despesas registradas', fmt(money.expenses)],
      ['Orçamento disponível', money.budget == null ? d.NOT_INFORMED : d.brl(money.budget - (money.expenses || 0))],
    ])]),
    section('Receitas', [table(['Descrição', 'Categoria', 'Origem', 'Data', 'Status', 'Valor'], d.incomeRows(state), ['*', 60, 80, 50, 55, 65])]),
    section('Despesas', [
      table(['Descrição', 'Categoria', 'Fornecedor', 'Data', 'Status', 'Previsto', 'Realizado'], d.expenseRows(state), ['*', 55, 70, 48, 52, 55, 55]),
      sub('Despesas por categoria', [table(['Categoria', 'Total'], d.expenseCategoryRows(state), ['*', 90])]),
    ]),
    section('Saldo', [kv([['Receitas', fmt(money.incomes)], ['Despesas', fmt(money.expenses)], ['Saldo', fmt(money.balance)]])]),
    section('Movimentações', [table(['Data', 'Tipo', 'Descrição', 'Status', 'Valor'], d.movementRows(state), [50, 50, '*', 65, 70])]),
    section('Fornecedores', [table(['Fornecedor', 'Categoria', 'Contato', 'Status'], d.supplierRows(state), ['*', 70, '*', 50])]),
    section('Comprovantes registrados', [table(['Comprovante', 'Arquivo', 'Data', 'Responsável'], d.receiptRows(state), ['*', '*', 55, 90])]),
  ]
}
