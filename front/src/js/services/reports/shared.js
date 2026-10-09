import { CREDENTIAL_CATEGORIES, SETORES, TURMAS, activeMembers, companyOf, isAvailable, occurrenceCategory, occurrenceSector, occurrenceStatus, teamChallenge, teamName } from '@/js/data/model'

// Leitura e formatação dos dados do protótipo para os relatórios em PDF.
// Os builders só leem o estado; nada aqui altera a store.

export const NOT_INFORMED = 'Não informado'

export function filled(value) {
  if (value == null) return false
  if (typeof value === 'number') return !Number.isNaN(value)
  return String(value).trim() !== ''
}

export function text(value, fallback = NOT_INFORMED) {
  return filled(value) ? String(value) : fallback
}

export function count(value) {
  return String(Number(value) || 0)
}

export function date(value) {
  if (!filled(value)) return NOT_INFORMED
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return match ? `${match[3]}/${match[2]}/${match[1]}` : String(value)
}

export function stamp(when) {
  const pad = (n) => String(n).padStart(2, '0')
  return {
    date: `${pad(when.getDate())}/${pad(when.getMonth() + 1)}/${when.getFullYear()}`,
    time: `${pad(when.getHours())}:${pad(when.getMinutes())}`,
    iso: `${when.getFullYear()}-${pad(when.getMonth() + 1)}-${pad(when.getDate())}`,
  }
}

export function number(value) {
  if (!filled(value)) return null
  const parsed = Number(value)
  return Number.isNaN(parsed) ? null : parsed
}

export function brl(value) {
  const parsed = number(value)
  return parsed == null ? NOT_INFORMED : parsed.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export function percent(part, total) {
  if (!total) return '0%'
  return `${Math.round((part / total) * 100)}%`
}

export function yesNo(value) {
  return value ? 'Sim' : 'Não'
}

// Evento e identificação

export function eventInfo(state) {
  const event = state.event || {}
  const location = event.location && event.location !== 'A cadastrar' ? event.location : ''
  const days = Number(event.days) || 0
  return {
    name: text(event.name, 'Hackathon'),
    theme: text(event.theme),
    date: date(event.date),
    duration: days ? `${days} ${days === 1 ? 'dia' : 'dias'}` : NOT_INFORMED,
    hours: filled(event.start) && filled(event.end) ? `${event.start} às ${event.end}` : NOT_INFORMED,
    location: text(location),
  }
}

// Participantes e presença

export function teamOf(state, studentId) {
  return (state.teams || []).find((team) => (team.members || []).includes(studentId))
}

export function presenceOn(state, personId, day) {
  const records = (state.checkins || []).filter((item) => item.personId === personId && Number(item.day) === day)
  if (records.some((item) => item.status === 'Presente')) return 'Presente'
  if (records.length) return text(records[0].status, 'Ausente')
  return 'Não registrado'
}

export function participantRows(state) {
  return (state.students || []).map((student) => {
    const team = teamOf(state, student.id)
    return [text(student.name), text(student.turma), text(student.availability, 'Disponível'), team ? teamName(team.id) : 'Sem equipe']
  })
}

export function presenceRows(state) {
  return (state.students || []).map((student) => {
    const team = teamOf(state, student.id)
    const marks = [1, 2, 3].map((day) => presenceOn(state, student.id, day))
    const present = marks.filter((mark) => mark === 'Presente').length
    return [text(student.name), text(student.turma), team ? teamName(team.id) : 'Sem equipe', ...marks, `${present} de 3`]
  })
}

export function turmaRows(state) {
  const students = state.students || []
  const known = TURMAS.map((turma) => turma.id)
  const extra = [...new Set(students.map((item) => item.turma).filter((item) => item && !known.includes(item)))]
  return [...known, ...extra].map((turma) => {
    const group = students.filter((item) => item.turma === turma)
    return [turma, count(group.length), count(group.filter(isAvailable).length), count(group.filter((item) => teamOf(state, item.id)).length)]
  })
}

export function dayRows(state) {
  const students = state.students || []
  return [1, 2, 3].map((day) => {
    const marks = students.map((item) => presenceOn(state, item.id, day))
    const present = marks.filter((mark) => mark === 'Presente').length
    const missing = marks.filter((mark) => mark === 'Não registrado').length
    return [`Dia ${day}`, count(present), count(marks.length - present - missing), count(missing), percent(present, students.length)]
  })
}

export function peopleFacts(state) {
  const students = state.students || []
  const available = students.filter(isAvailable).length
  const withPresence = students.filter((item) => [1, 2, 3].some((day) => presenceOn(state, item.id, day) === 'Presente')).length
  return { total: students.length, available, unavailable: students.length - available, withPresence }
}

// Acesso e presença de todas as categorias de credencial (não só participantes).
export function accessRows(state) {
  const credentials = state.credentials || []
  return CREDENTIAL_CATEGORIES.map((category) => {
    const group = credentials.filter((item) => item.category === category)
    const present = (day) => group.filter((item) => presenceOn(state, item.personId, day) === 'Presente').length
    return [category, count(group.length), count(group.filter((item) => item.status === 'Ativa').length), count(present(1)), count(present(2)), count(present(3))]
  }).filter((row) => row[1] !== '0')
}

// Equipes, empresas e desafios

const TEAM_STATUS = { 'nao-formada': 'Não formada', 'em-montagem': 'Em formação', confirmada: 'Confirmada' }

export function teamRows(state) {
  return (state.teams || []).map((team) => {
    const challenge = teamChallenge(state, team.id)
    const company = challenge ? companyOf(state, challenge.companyId) : null
    return [teamName(team.id), count(activeMembers(team, state.students || []).length), text(company?.name), text(challenge?.title), text(TEAM_STATUS[team.status] || team.status)]
  })
}

export function companyRows(state) {
  return (state.companies || []).map((company) => [
    text(company.name),
    text(company.segmento),
    text(company.tipo),
    count((state.challenges || []).filter((item) => item.companyId === company.id).length),
  ])
}

export function challengeRows(state) {
  return (state.challenges || []).map((item) => [
    text(item.title),
    text(companyOf(state, item.companyId)?.name),
    item.teamId ? teamName(item.teamId) : 'Sem equipe',
    text(item.status),
  ])
}

// Gestão

export function openTasks(state) {
  return (state.tasks || []).filter((task) => task.status !== 'Concluído')
}

export function taskRows(state) {
  return (state.tasks || []).map((task) => [text(task.title), text(task.sector), text(task.responsible), date(task.due), text(task.priority), text(task.status)])
}

export function meetingRows(state) {
  return (state.meetings || []).map((item) => [text(item.title), date(item.date), text(item.type), text(item.status), item.ata ? `Nº ${text(item.ata.number, 's/n')} · ${text(item.ata.status, 'Registrada')}` : 'Sem ata'])
}

export function decisionRows(state) {
  return (state.decisions || []).map((item) => [text(item.title), text(item.sector), text(item.responsible), date(item.date), text(item.status)])
}

export function documentRows(state) {
  return (state.documents || []).map((item) => [text(item.name), text(item.category), text(item.sector), text(item.version), text(item.date)])
}

export function sectorFacts(state, name) {
  const open = openTasks(state).filter((task) => task.sector === name).length
  const members = (state.orgMembers || []).filter((item) => item.sector === name)
  const base = [['Integrantes do setor', count(members.length)], ['Pendências abertas', count(open)]]
  if (name === 'Recursos Humanos') {
    return [['Integrantes da organização', count((state.orgMembers || []).length)], ['Com função definida', count((state.orgMembers || []).filter((item) => item.func).length)], ['Participantes cadastrados', count((state.students || []).length)], ['Pendências abertas', count(open)]]
  }
  if (name === 'Finanças') {
    const money = financeTotals(state)
    return [...base, ['Receitas', money.incomes == null ? NOT_INFORMED : brl(money.incomes)], ['Despesas', money.expenses == null ? NOT_INFORMED : brl(money.expenses)], ['Saldo', money.balance == null ? NOT_INFORMED : brl(money.balance)]]
  }
  if (name === 'Marketing') {
    const contents = state.contents || []
    return [...base, ['Campanhas', count((state.campaigns || []).length)], ['Conteúdos', count(contents.filter((item) => item.kind !== 'Material').length)], ['Materiais de divulgação', count(contents.filter((item) => item.kind === 'Material').length)]]
  }
  if (name === 'Tecnologia') {
    const equipment = state.equipment || []
    return [...base, ['Equipamentos', count(equipment.length)], ['Equipamentos com problema', count(equipment.filter((item) => item.status === 'Com problema').length)], ['Ocorrências abertas do setor', count(sectorOccurrences(state, name).filter(isOpen).length)]]
  }
  return [...base, ['Espaços', count((state.spaces || []).length)], ['Materiais', count((state.materials || []).length)], ['Ocorrências abertas do setor', count(sectorOccurrences(state, name).filter(isOpen).length)]]
}

export { SETORES }

// Operação do evento

export function isOpen(item) {
  return occurrenceStatus(item) !== 'Resolvida'
}

export function sectorOccurrences(state, name) {
  return (state.occurrences || []).filter((item) => occurrenceSector(item) === name)
}

export function occurrenceRows(state) {
  return (state.occurrences || []).map((item) => [text(item.title), occurrenceCategory(item), text(item.place), text(occurrenceSector(item), 'Sem setor'), text(item.priority), occurrenceStatus(item), text(item.responsible)])
}

export function spaceRows(state) {
  return (state.spaces || []).map((item) => [text(item.name), text(item.type), text(item.capacity), text(item.purpose), text(item.status)])
}

export function equipmentRows(state) {
  return (state.equipment || []).map((item) => [text(item.name), text(item.category), count(item.qty), text(item.place), text(item.status)])
}

export function infraRows(state) {
  return (state.infra || []).map((item) => [text(item.name), text(item.responsible), text(item.status)])
}

export function materialRows(state) {
  return (state.materials || []).map((item) => [text(item.name), text(item.needed), text(item.available), text(item.status)])
}

// Encerramento

export function activeJudges(state) {
  return (state.judges || []).filter((item) => item.status !== 'Inativo')
}

export function judgeRows(state) {
  return (state.judges || []).map((item) => [text(item.name), text(item.companyName || companyOf(state, item.companyId)?.name), text(item.cargo), text(item.status)])
}

export function criterionRows(state) {
  return [...(state.criteria || [])]
    .sort((a, b) => (a.order || 0) - (b.order || 0))
    .map((item) => [text(item.name), text(item.type), filled(item.min) && filled(item.max) ? `${item.min} a ${item.max}` : NOT_INFORMED, text(item.weight, 'Sem peso'), item.active === false ? 'Inativo' : 'Ativo'])
}

export function technicalAverage(state, teamId) {
  const scores = (state.evaluations || [])
    .filter((item) => item.teamId === teamId && item.status === 'concluida')
    .flatMap((item) => Object.values(item.scores || {}).map(Number).filter((value) => !Number.isNaN(value)))
  if (!scores.length) return null
  return scores.reduce((sum, value) => sum + value, 0) / scores.length
}

export function evaluationStatus(state, teamId) {
  const items = (state.evaluations || []).filter((item) => item.teamId === teamId)
  if (items.some((item) => item.status === 'revisao')) return 'Em revisão'
  const done = items.filter((item) => item.status === 'concluida').length
  const judges = activeJudges(state).length
  if (!items.length) return 'Não iniciada'
  if ((judges && done >= judges) || (!judges && done)) return 'Concluída'
  return 'Em andamento'
}

export function evaluationRows(state) {
  const judges = activeJudges(state).length
  return (state.teams || []).map((team) => {
    const done = (state.evaluations || []).filter((item) => item.teamId === team.id && item.status === 'concluida').length
    const average = technicalAverage(state, team.id)
    return [teamName(team.id), judges ? `${done} de ${judges}` : count(done), average == null ? 'Sem nota' : average.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }), evaluationStatus(state, team.id)]
  })
}

export function voteRows(state) {
  const ballots = state.voting?.ballots || []
  return (state.teams || []).map((team) => {
    const votes = ballots.filter((item) => item.teamId === team.id).length
    return [teamName(team.id), count(votes), percent(votes, ballots.length)]
  })
}

export function presentationRows(state) {
  return (state.teams || []).map((team) => [teamName(team.id), text(teamChallenge(state, team.id)?.title), text(team.solution)])
}

export function awardRows(state) {
  return (state.awards || []).map((item) => [text(item.name), text(item.criterion), text(item.team), text(item.description)])
}

// Finanças

function amountOf(item, kind) {
  if (kind === 'receita') return number(item.value)
  return number(item.actual) ?? number(item.planned)
}

export function financeTotals(state) {
  const incomes = (state.incomes || []).map((item) => amountOf(item, 'receita')).filter((value) => value != null)
  const expenses = (state.expenses || []).map((item) => amountOf(item, 'despesa')).filter((value) => value != null)
  const sum = (list) => (list.length ? list.reduce((total, value) => total + value, 0) : null)
  const incomeTotal = sum(incomes)
  const expenseTotal = sum(expenses)
  return {
    budget: number(state.event?.budget),
    incomes: incomeTotal,
    expenses: expenseTotal,
    balance: incomeTotal == null && expenseTotal == null ? null : (incomeTotal || 0) - (expenseTotal || 0),
  }
}

export function incomeRows(state) {
  return (state.incomes || []).map((item) => [text(item.description), text(item.category), text(item.origin), date(item.date), text(item.status), brl(item.value)])
}

export function expenseRows(state) {
  return (state.expenses || []).map((item) => [text(item.description), text(item.category), text(item.supplier), date(item.date), text(item.status), brl(item.planned), brl(item.actual)])
}

export function expenseCategoryRows(state) {
  const totals = {}
  for (const item of state.expenses || []) {
    const value = amountOf(item, 'despesa')
    if (value == null) continue
    const key = text(item.category, 'Sem categoria')
    totals[key] = (totals[key] || 0) + value
  }
  return Object.entries(totals).sort((a, b) => b[1] - a[1]).map(([category, total]) => [category, brl(total)])
}

export function movementRows(state) {
  const items = [
    ...(state.incomes || []).map((item) => ({ date: item.date, kind: 'Receita', description: item.description, status: item.status, value: amountOf(item, 'receita') })),
    ...(state.expenses || []).map((item) => ({ date: item.date, kind: 'Despesa', description: item.description, status: item.status, value: amountOf(item, 'despesa') })),
  ]
  return items
    .sort((a, b) => String(a.date || '').localeCompare(String(b.date || '')))
    .map((item) => [date(item.date), item.kind, text(item.description), text(item.status), brl(item.value)])
}

export function supplierRows(state) {
  return (state.suppliers || []).map((item) => [text(item.name), text(item.category), text(item.contact), text(item.status)])
}

export function receiptRows(state) {
  return (state.documents || []).filter((doc) => doc.category === 'Finanças').map((item) => [text(item.name), text(item.fileName, 'Sem arquivo anexado'), text(item.date), text(item.responsible)])
}
