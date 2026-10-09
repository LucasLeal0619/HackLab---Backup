// Demandas intersetoriais: Pendências (algo a fazer) e Ocorrências (algo que aconteceu).
// Cada demanda tem um setor de origem, um único setor responsável e setores envolvidos.
// Os setores trabalham e conversam no histórico da demanda; o Administrador só acompanha e intervém.
import { occurrenceSector } from './occurrences'
import { uid } from './utils'

export const DEMAND_KINDS = {
  task: { prefix: 'PEN', label: 'pendência', list: 'tasks' },
  occurrence: { prefix: 'OCO', label: 'ocorrência', list: 'occurrences' },
}

export function responsibleSector(item, kind) {
  return kind === 'occurrence' ? occurrenceSector(item) : item.sector || ''
}

// Todos os setores com relação com a demanda (origem, responsável e envolvidos).
export function demandSectors(item, kind) {
  return [...new Set([item.originSector, responsibleSector(item, kind), ...(item.involvedSectors || [])].filter(Boolean))]
}

// scope = setores do usuário (Gestor/Editor); null = visão global (Administrador, Consultor).
export function demandVisible(scope, item, kind) {
  if (!scope) return true
  const sectors = demandSectors(item, kind)
  // Demanda sem setor nenhum é da organização em geral: fica com quem tem visão global.
  return sectors.some((sector) => scope.includes(sector))
}

export function nextRef(list, prefix) {
  const top = (list || []).reduce((max, item) => Math.max(max, Number(String(item.ref || '').replace(/\D/g, '')) || 0), 0)
  return `${prefix}-${String(top + 1).padStart(4, '0')}`
}

export function historyEntry(actor, type, text, extra = {}) {
  return {
    id: uid('his'),
    at: new Date().toISOString(),
    type, // 'event' (automático) ou 'comment' (interação escrita)
    authorName: actor.userName,
    profile: actor.profile,
    sector: actor.sector,
    text,
    ...extra,
  }
}

export function formatMoment(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return String(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)} · ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

// Completa demandas salvas antes desta versão (código, origem, envolvidos e histórico).
export function normalizeDemands(draft) {
  for (const [kind, meta] of Object.entries(DEMAND_KINDS)) {
    const list = draft[meta.list]
    if (!Array.isArray(list)) continue
    // Mais antigas primeiro, para os códigos seguirem a ordem de criação.
    for (const item of [...list].reverse()) {
      if (!item.ref) item.ref = nextRef(list, meta.prefix)
      const responsible = responsibleSector(item, kind)
      if (item.originSector === undefined) item.originSector = responsible
      if (!Array.isArray(item.involvedSectors)) item.involvedSectors = responsible ? [responsible] : []
      if (!Array.isArray(item.history)) item.history = []
    }
  }
  return draft
}

const DONE = { task: 'Concluído', occurrence: 'Resolvida' }

// Compara antes/depois de uma edição: gera eventos do histórico e o registro da auditoria.
export function trackDemandEdit(kind, before, after) {
  const label = DEMAND_KINDS[kind].label
  const events = []
  const changes = []
  const field = (name, from, to, text) => {
    if (String(from ?? '') === String(to ?? '')) return false
    changes.push({ field: name, before: from || '—', after: to || '—' })
    events.push(text)
    return true
  }
  const statusChanged = field('Status', before.status, after.status, `Alterou o status: ${before.status || '—'} → ${after.status || '—'}.`)
  const sectorChanged = field('Setor responsável', responsibleSector(before, kind), responsibleSector(after, kind), `Alterou o setor responsável: ${responsibleSector(before, kind) || '—'} → ${responsibleSector(after, kind) || '—'}.`)
  const priorityChanged = field('Prioridade', before.priority, after.priority, `Alterou a prioridade: ${before.priority || '—'} → ${after.priority || '—'}.`)
  const personChanged = field('Responsável', before.responsible, after.responsible, `Alterou o responsável: ${before.responsible || '—'} → ${after.responsible || '—'}.`)
  const was = new Set(before.involvedSectors || [])
  const now = new Set(after.involvedSectors || [])
  for (const sector of now) if (!was.has(sector)) events.push(`Adicionou o setor ${sector}.`)
  for (const sector of was) if (!now.has(sector)) events.push(`Removeu o setor ${sector}.`)
  if ([...now].sort().join() !== [...was].sort().join()) changes.push({ field: 'Setores envolvidos', before: [...was].join(', ') || '—', after: [...now].join(', ') || '—' })
  const otherChanged = ['title', 'description', 'due', 'category', 'place', 'day', 'at', 'team', 'notes'].some((key) => String(before[key] ?? '') !== String(after[key] ?? ''))
  if (otherChanged) events.push('Editou as informações.')

  let action = 'updated'
  let actionLabel = `Editou ${label}`
  if (statusChanged && after.status === DONE[kind]) {
    action = kind === 'task' ? 'completed' : 'resolved'
    actionLabel = kind === 'task' ? 'Concluiu pendência' : 'Resolveu ocorrência'
  } else if (statusChanged && before.status === DONE[kind]) {
    action = 'reopened'
    actionLabel = `Reabriu ${label}`
  } else if (sectorChanged) {
    action = 'reassigned'
    actionLabel = `Alterou setor responsável da ${label}`
  } else if (priorityChanged && !personChanged && !otherChanged) {
    action = 'priority'
    actionLabel = `Alterou prioridade da ${label}`
  } else if (personChanged && !otherChanged) {
    action = 'assigned'
    actionLabel = `Atribuiu responsável da ${label}`
  }
  return { events, changes, action: `${kind}.${action}`, label: actionLabel }
}
