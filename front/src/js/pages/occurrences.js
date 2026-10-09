// Lógica do componente Occurrences.vue (o template fica no .vue).
import { computed, ref, watch } from 'vue'
import { OCC_CATEGORIES, OCC_PRIORITIES, OCC_STATUS, SETORES, demandVisible, historyEntry, nextRef, occurrenceCategory, occurrenceSector, occurrenceStatus, teamName, trackDemandEdit, uid } from '@/js/data/model'
import { actorFrom, useAudit } from '@/js/audit/audit-logger'
import { canRouteDemands, isOperational, sectorScope } from '@/js/config/access'
import { go, useHack } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useOccurrences(props) {
  const DAYS = [['', 'Fora dos dias do evento'], ['1', 'Dia 1'], ['2', 'Dia 2'], ['3', 'Dia 3']]


  const { state, update, flash } = useHack()
  const audit = useAudit()
  const scope = computed(() => sectorScope(state.session))
  // Filtro por setor responsável: a lista inclui demandas de outros setores que envolvem o meu.
  const sectorOptions = computed(() => SETORES)
  const operational = computed(() => isOperational(state.session))
  // Administrador e Gestor podem atribuir a outro setor, envolver setores e gerar pendências.
  const canRoute = computed(() => canRouteDemands(state.session))
  const responsibleOptions = computed(() => (canRoute.value || !scope.value ? SETORES : scope.value))
  const generating = ref(null)
  const filters = ref({ query: '', category: '', sector: '', priority: '', status: '', day: '' })
  const form = ref(null)
  const detailId = ref(null)
  const solving = ref(null)
  const removing = ref(null)

  function dayLabel(value) {
    return value ? `Dia ${value}` : '—'
  }

  function blank(extra = {}) {
    return { title: '', category: 'Outro', description: '', day: '', at: '', place: '', team: '', sector: scope.value?.[0] || '', originSector: scope.value?.[0] || '', involvedSectors: [], priority: 'Média', responsible: state.session?.name || '', status: 'Aberta', notes: '', ...extra }
  }

  // Atalhos dos setores chegam como parâmetros e abrem o mesmo formulário da central.
  watch(() => props.params, (params) => {
    if (params.setor && SETORES.includes(params.setor)) filters.value.sector = params.setor
    // Link direto vindo de uma pendência relacionada.
    if (params.ver) detailId.value = params.ver
    if (params.novo) {
      form.value = blank({
        title: params.titulo || '',
        category: OCC_CATEGORIES.includes(params.categoria) ? params.categoria : 'Outro',
        sector: SETORES.includes(params.setor) ? params.setor : scope.value?.[0] || '',
        place: params.local || '',
      })
      filters.value.sector = ''
      window.history.replaceState(null, '', '#/ocorrencias')
    }
  }, { immediate: true })

  const list = computed(() => {
    const f = filters.value
    const term = f.query.trim().toLowerCase()
    return state.occurrences
      .map((item) => ({ ...item, categoryLabel: occurrenceCategory(item), sectorLabel: occurrenceSector(item), statusLabel: occurrenceStatus(item) }))
      .filter((item) => demandVisible(scope.value, item, 'occurrence'))
      .filter((item) => !term || `${item.title} ${item.description || ''} ${item.place || ''} ${item.responsible || ''}`.toLowerCase().includes(term))
      .filter((item) => !f.category || item.categoryLabel === f.category)
      .filter((item) => !f.sector || item.sectorLabel === f.sector)
      .filter((item) => !f.priority || item.priority === f.priority)
      .filter((item) => !f.status || item.statusLabel === f.status)
      .filter((item) => !f.day || String(item.day || '') === f.day)
  })
  const counts = computed(() => OCC_STATUS.map((status) => [status, state.occurrences.filter((item) => demandVisible(scope.value, item, 'occurrence') && occurrenceStatus(item) === status).length]))
  const detail = computed(() => {
    // Só abre ocorrências visíveis para o setor do usuário (inclusive por link direto).
    const item = state.occurrences.find((entry) => entry.id === detailId.value && demandVisible(scope.value, entry, 'occurrence'))
    return item ? { ...item, categoryLabel: occurrenceCategory(item), sectorLabel: occurrenceSector(item), statusLabel: occurrenceStatus(item) } : null
  })
  const filtering = computed(() => Object.values(filters.value).some(Boolean))
  const advancedFilters = computed(() => ['category', 'sector', 'priority', 'status', 'day'].filter((key) => filters.value[key]).length)

  function clearFilters() {
    filters.value = { query: '', category: '', sector: '', priority: '', status: '', day: '' }
  }

  function openNew() {
    form.value = blank()
  }

  function openEdit(item) {
    form.value = blank({ ...item, category: occurrenceCategory(item), sector: occurrenceSector(item), status: occurrenceStatus(item), day: item.day ? String(item.day) : '', involvedSectors: [...(item.involvedSectors || [])] })
    detailId.value = null
  }

  function save() {
    const current = form.value
    if (!current.title.trim()) {
      flash('Informe o título da ocorrência.', 'err')
      return
    }
    const record = {
      title: current.title.trim(),
      category: current.category,
      description: current.description,
      day: current.day ? Number(current.day) : '',
      at: current.at,
      place: current.place,
      team: current.team,
      sector: current.sector,
      priority: current.priority,
      responsible: current.responsible,
      status: current.status,
      notes: current.notes,
      involvedSectors: [...new Set([...(current.involvedSectors || []), current.originSector, current.sector].filter(Boolean))],
    }
    const actor = actorFrom(state.session)
    update((draft) => {
      const index = current.id ? draft.occurrences.findIndex((item) => item.id === current.id) : -1
      if (index >= 0) {
        const before = draft.occurrences[index]
        const after = { ...before, ...record }
        const tracked = trackDemandEdit('occurrence', before, after)
        after.history = [...(before.history || []), ...tracked.events.map((text) => historyEntry(actor, 'event', text))]
        draft.occurrences[index] = after
        if (tracked.events.length) audit.record(draft, { action: tracked.action, label: tracked.label, module: 'occurrences', entityType: 'Ocorrência', entityId: after.id, entityLabel: after.ref, description: `Ocorrência "${after.title}".`, changes: tracked.changes })
      } else {
        const created = { id: uid('oc'), ref: nextRef(draft.occurrences, 'OCO'), ...record, originSector: current.originSector || '', solution: '', history: [historyEntry(actor, 'event', 'Registrou a ocorrência.')] }
        draft.occurrences.unshift(created)
        audit.record(draft, { action: 'occurrence.created', label: 'Registrou ocorrência', module: 'occurrences', entityType: 'Ocorrência', entityId: created.id, entityLabel: created.ref, description: `Registrou a ocorrência "${created.title}" (responsável: ${created.sector || 'sem setor'}).` })
      }
    })
    flash(current.id ? 'Ocorrência atualizada.' : 'Ocorrência registrada.')
    form.value = null
  }

  function openSolve() {
    solving.value = { id: detail.value.id, solution: '', responsible: detail.value.responsible || state.session?.name || '', note: '' }
  }

  function saveSolution() {
    const current = solving.value
    if (!current.solution.trim()) {
      flash('Descreva a solução adotada.', 'err')
      return
    }
    const actor = actorFrom(state.session)
    update((draft) => {
      const item = draft.occurrences.find((entry) => entry.id === current.id)
      if (!item) return
      const before = occurrenceStatus(item)
      item.status = 'Resolvida'
      item.solution = current.solution.trim()
      if (current.responsible) item.responsible = current.responsible
      if (current.note) item.notes = [item.notes, current.note].filter(Boolean).join(' · ')
      item.history.push(historyEntry(actor, 'event', `Resolveu a ocorrência. Solução: ${item.solution}`))
      audit.record(draft, { action: 'occurrence.resolved', label: 'Resolveu ocorrência', module: 'occurrences', entityType: 'Ocorrência', entityId: item.id, entityLabel: item.ref, description: `Ocorrência "${item.title}" resolvida.`, changes: [{ field: 'Status', before, after: 'Resolvida' }] })
    })
    solving.value = null
    flash('Ocorrência resolvida.')
  }

  function reopen() {
    const id = detail.value.id
    const actor = actorFrom(state.session)
    update((draft) => {
      const item = draft.occurrences.find((entry) => entry.id === id)
      if (!item) return
      item.status = 'Aberta'
      item.history.push(historyEntry(actor, 'event', 'Reabriu a ocorrência.'))
      audit.record(draft, { action: 'occurrence.reopened', label: 'Reabriu ocorrência', module: 'occurrences', entityType: 'Ocorrência', entityId: item.id, entityLabel: item.ref, description: `Ocorrência "${item.title}" reaberta.`, changes: [{ field: 'Status', before: 'Resolvida', after: 'Aberta' }] })
    })
    flash('Ocorrência reaberta.')
  }

  // Ocorrência (algo que aconteceu) pode gerar uma Pendência (algo a fazer) relacionada.
  const relatedTasks = computed(() => (detail.value ? state.tasks.filter((task) => task.sourceOccurrenceId === detail.value.id) : []))

  function openGenerate() {
    generating.value = { title: '', sector: detail.value.sectorLabel || scope.value?.[0] || SETORES[0], priority: detail.value.priority || 'Média', due: '', description: `Originada da ocorrência ${detail.value.ref}: ${detail.value.title}.` }
  }

  function generateTask() {
    const current = generating.value
    if (!current.title.trim()) {
      flash('Informe o título da pendência.', 'err')
      return
    }
    const occurrenceId = detail.value.id
    const actor = actorFrom(state.session)
    let ref = ''
    update((draft) => {
      const occurrence = draft.occurrences.find((entry) => entry.id === occurrenceId)
      ref = nextRef(draft.tasks, 'PEN')
      const origin = actor.sector || occurrenceSector(occurrence) || ''
      const task = {
        id: uid('pen'),
        ref,
        title: current.title.trim(),
        description: current.description,
        sector: current.sector,
        due: current.due,
        responsible: '',
        status: 'Pendente',
        priority: current.priority,
        origin: `Ocorrência ${occurrence.ref}`,
        sourceOccurrenceId: occurrence.id,
        originSector: origin,
        involvedSectors: [...new Set([origin, current.sector, ...(occurrence.involvedSectors || [])].filter(Boolean))],
        notes: '',
        createdAt: new Date().toLocaleString('pt-BR'),
        history: [historyEntry(actor, 'event', `Criou a pendência a partir da ocorrência ${occurrence.ref}.`)],
      }
      draft.tasks.unshift(task)
      occurrence.history.push(historyEntry(actor, 'event', `Gerou a pendência ${ref}: ${task.title}.`))
      audit.record(draft, { action: 'occurrence.task-generated', label: 'Gerou pendência', module: 'occurrences', entityType: 'Ocorrência', entityId: occurrence.id, entityLabel: occurrence.ref, description: `Gerou a pendência ${ref} "${task.title}" (responsável: ${task.sector}).`, metadata: { taskId: task.id } })
    })
    generating.value = null
    flash(`Pendência ${ref} criada.`)
  }

  function openTask(id) {
    go(`pendencias?ver=${id}`)
  }

  function toggleInvolved(sector) {
    const list = form.value.involvedSectors || []
    form.value.involvedSectors = list.includes(sector) ? list.filter((item) => item !== sector) : [...list, sector]
  }

  function remove() {
    const id = removing.value.id
    update((draft) => {
      draft.occurrences = draft.occurrences.filter((item) => item.id !== id)
    })
    removing.value = null
    detailId.value = null
    flash('Ocorrência excluída.')
  }

  return {
    canRoute,
    responsibleOptions,
    generating,
    reopen,
    relatedTasks,
    openGenerate,
    generateTask,
    openTask,
    toggleInvolved,
    SETORES,
    DAYS,
    state,
    scope,
    sectorOptions,
    operational,
    filters,
    form,
    detailId,
    solving,
    removing,
    dayLabel,
    list,
    counts,
    detail,
    filtering,
    advancedFilters,
    clearFilters,
    openNew,
    openEdit,
    save,
    openSolve,
    saveSolution,
    remove,
    OCC_CATEGORIES,
    OCC_PRIORITIES,
    OCC_STATUS,
    teamName,
    toneFor,
  }
}
