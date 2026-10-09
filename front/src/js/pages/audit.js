// Lógica do componente Audit.vue: leitura e filtros dos registros de auditoria (somente leitura).
// Em produção, os logs deverão ser gravados pelo backend e não poderão depender exclusivamente do navegador.
import { computed, ref } from 'vue'
import { AUDIT_MODULES, normalizeAuditEntry } from '@/js/audit/audit-logger'
import { useHack } from '@/js/stores/hack'

const PERIODS = [['', 'Todo o período'], ['hoje', 'Hoje'], ['7', 'Últimos 7 dias'], ['30', 'Últimos 30 dias']]

function stamp(entry) {
  if (!entry.timestamp) return entry.when || '—'
  const date = new Date(entry.timestamp)
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} · ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

function within(entry, period) {
  if (!period) return true
  if (!entry.timestamp) return false
  const date = new Date(entry.timestamp)
  if (period === 'hoje') return date.toDateString() === new Date().toDateString()
  return Date.now() - date.getTime() <= Number(period) * 86400000
}

export function useAuditPage() {
  const { state } = useHack()
  const empty = { query: '', period: '', user: '', profile: '', sector: '', module: '', action: '' }
  const filters = ref({ ...empty })
  const detailId = ref(null)

  // Mais recente primeiro.
  const entries = computed(() => (state.audit || []).map(normalizeAuditEntry).sort((a, b) => String(b.timestamp || '').localeCompare(String(a.timestamp || ''))))
  const options = computed(() => {
    const pick = (key) => [...new Set(entries.value.map((item) => item[key]).filter(Boolean))].sort()
    return { users: pick('userName'), profiles: pick('profile'), sectors: pick('sector'), modules: pick('module'), actions: pick('label') }
  })
  const list = computed(() => {
    const f = filters.value
    const term = f.query.trim().toLowerCase()
    return entries.value.filter((item) => (!term || `${item.label} ${item.description} ${item.entityLabel} ${item.userName}`.toLowerCase().includes(term))
      && within(item, f.period)
      && (!f.user || item.userName === f.user)
      && (!f.profile || item.profile === f.profile)
      && (!f.sector || item.sector === f.sector)
      && (!f.module || item.module === f.module)
      && (!f.action || item.label === f.action))
  })
  const active = computed(() => ['period', 'user', 'profile', 'sector', 'module', 'action'].filter((key) => filters.value[key]).length)
  const detail = computed(() => entries.value.find((item) => item.id === detailId.value) || null)

  function moduleLabel(key) {
    return AUDIT_MODULES[key] || key || '—'
  }

  function who(entry) {
    return [entry.profile, entry.sector].filter(Boolean).join(' · ') || '—'
  }

  function clear() {
    filters.value = { ...filters.value, ...empty, query: filters.value.query }
  }

  return {
    PERIODS,
    filters,
    detailId,
    entries,
    options,
    list,
    active,
    detail,
    moduleLabel,
    who,
    clear,
    stamp,
  }
}
