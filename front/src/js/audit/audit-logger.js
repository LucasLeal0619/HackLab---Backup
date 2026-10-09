// Auditoria do HackLab (protótipo).
// Os registros ficam no estado local (localStorage) apenas para demonstrar a experiência.
// Em produção, os logs deverão ser gravados pelo backend e não poderão depender
// exclusivamente do navegador: aqui eles podem ser apagados ou alterados pelo próprio usuário.
import { sectorScope } from '@/js/config/access'
import { uid } from '@/js/data/utils'
import { useHack } from '@/js/stores/hack'

const LIMIT = 500

export const AUDIT_MODULES = {
  users: 'Usuários',
  participants: 'Participantes',
  teams: 'Equipes',
  companies: 'Empresas',
  challenges: 'Desafios',
  tasks: 'Pendências',
  occurrences: 'Ocorrências',
  credentials: 'Credenciais',
  presence: 'Presença',
  judges: 'Jurados',
  evaluations: 'Avaliações',
  voting: 'Votação',
  results: 'Resultados',
  documents: 'Documentos',
}

// Quem está agindo, no contexto demonstrativo atual (perfil e setor do seletor "Meu perfil").
export function actorFrom(session) {
  const sector = sectorScope(session)?.[0] || ''
  return {
    userId: session?.userId || session?.email || '',
    userName: session?.name || 'Usuário não identificado',
    profile: session?.profile || '',
    sector,
  }
}

/**
 * Registra uma ação relevante no estado (dentro de um store.update).
 * entry: { action, label, module, entityType, entityId, entityLabel, description, changes, metadata }
 * changes: [{ field, before, after }] — valores já legíveis (nada de JSON cru na interface).
 */
export function appendAudit(draft, session, entry) {
  if (!Array.isArray(draft.audit)) draft.audit = []
  draft.audit.unshift({
    id: uid('log'),
    timestamp: new Date().toISOString(),
    ...actorFrom(session),
    action: entry.action,
    label: entry.label,
    module: entry.module,
    entityType: entry.entityType || '',
    entityId: entry.entityId || '',
    entityLabel: entry.entityLabel || '',
    description: entry.description || '',
    changes: (entry.changes || []).filter((change) => String(change.before ?? '') !== String(change.after ?? '')),
    metadata: entry.metadata || {},
  })
  if (draft.audit.length > LIMIT) draft.audit.length = LIMIT
}

// Uso nas páginas: audit.record(draft, {...}) dentro de update, ou audit.log({...}) isolado.
export function useAudit() {
  const hack = useHack()
  return {
    record: (draft, entry) => appendAudit(draft, hack.state.session, entry),
    log: (entry) => hack.update((draft) => appendAudit(draft, hack.state.session, entry)),
  }
}

// Registros anteriores ao logger (ex.: avaliações finalizadas) continuam legíveis.
export function normalizeAuditEntry(item) {
  if (item.label || item.timestamp) return item
  return {
    ...item,
    timestamp: '',
    when: item.at || '',
    label: item.action || 'Ação registrada',
    module: item.action?.includes('valia') || item.action?.includes('Correção') ? 'evaluations' : '',
    description: item.detail || '',
    userName: item.userName || '—',
    changes: [],
  }
}
