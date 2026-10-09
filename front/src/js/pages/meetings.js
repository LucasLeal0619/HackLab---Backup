// Lógica do componente Meetings.vue (o template fica no .vue).
import { computed, ref, watch } from 'vue'
import { SETORES, demandVisible, historyEntry, nextRef, trackDemandEdit, uid } from '@/js/data/model'
import { actorFrom, useAudit } from '@/js/audit/audit-logger'
import { canRouteDemands, inScope, isOperational, sectorScope } from '@/js/config/access'
import { useHack, go } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useMeetings(props) {
  const DOC_CATS = ['Atas', 'Contratos', 'Empresas', 'Desafios', 'Finanças', 'Marketing', 'Relatórios', 'Outros']
  const DOC_FILTERS = ['Todos', ...DOC_CATS]
  const MEETING_TYPES = ['Geral', 'Setor', 'Consultores', 'Empresa', 'Administrativa', 'Extraordinária']
  const PRIORITIES = ['Baixa', 'Média', 'Alta', 'Urgente']
  const DUE_FILTERS = ['Dentro do prazo', 'Próximo do prazo', 'Vence hoje', 'Atrasado', 'Concluído', 'Sem prazo']
  const TAB_LABELS = { atas: 'Atas', decisoes: 'Decisões', pendencias: 'Pendências', documentos: 'Documentos' }

  function docCat(doc) {
    if (!doc?.category || doc.category === 'Documentos gerais') return 'Outros'
    return doc.category
  }

  function dueInfo(task) {
    if (task.status === 'Concluído' || task.status === 'Concluída') return { label: 'Concluído', tone: 'done' }
    if (!task.due) return { label: 'Sem prazo', tone: '' }
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const due = new Date(`${task.due}T00:00:00`)
    if (Number.isNaN(due.getTime())) return { label: 'Sem prazo', tone: '' }
    const diff = Math.round((due - today) / 86400000)
    if (diff < 0) return { label: 'Atrasado', tone: 'late' }
    if (diff === 0) return { label: 'Vence hoje', tone: 'today' }
    if (diff <= 2) return { label: 'Próximo do prazo', tone: 'soon' }
    return { label: 'Dentro do prazo', tone: 'ok' }
  }

  function outsideHours(value) {
    if (!value) return false
    return value < '08:00' || value > '12:00'
  }

  function ataProgress(item) {
    const done = item.ata.manifestations?.length || 0
    const total = item.participantIds?.length || 0
    return total ? `${done} de ${total} manifestaram` : (done || '—')
  }


  const { state, update, flash } = useHack()

  const tab = computed(() => {
    if (props.params.lista === 'atas' || props.params.aba === 'atas') return 'atas'
    if (props.params.lista === 'decisoes' || props.params.aba === 'decisoes') return 'decisoes'
    if (['pendencias', 'documentos'].includes(props.params.aba)) return props.params.aba
    return 'reunioes'
  })
  const pageCopy = computed(() => {
    const scope = sectorScope(state.session)
    if (tab.value === 'pendencias' && operational.value) return ['Minhas Pendências', `Pendências de ${scope.join(' e ')}. Você atualiza as que estão sob sua responsabilidade.`]
    if (tab.value === 'pendencias' && scope) return ['Pendências', `Pendências de ${scope.join(' e ')}: atribua responsáveis, prioridades e conclua demandas.`]
    if (tab.value === 'documentos' && scope) return ['Documentos', `Documentos de ${scope.join(' e ')}.`]
    if (tab.value === 'pendencias') return ['Pendências', 'Acompanhe as atividades pendentes da organização.']
    if (tab.value === 'documentos') return ['Documentos', 'Busque, organize e registre os documentos.']
    if (tab.value === 'atas') return ['Atas', 'Atas registradas nas reuniões.']
    if (tab.value === 'decisoes') return ['Decisões', 'Decisões vinculadas às reuniões.']
    return ['Reuniões', 'Organize as reuniões, atas e decisões.']
  })
  const modal = ref(null)
  const form = ref({})
  const query = ref('')
  const status = ref('')
  const sector = ref('')
  const priority = ref('')
  const docCatFilter = ref('Todos')
  const prazo = ref('')
  const detail = ref(null)
  const removing = ref(null)
  const ataForm = ref({
    number: '',
    discussed: '',
    decisions: '',
    forwards: '',
    observations: '',
    status: 'Rascunho',
  })

  const crumb = computed(() => (tab.value === 'reunioes'
    ? 'HackLab / Gestão / Reuniões e Pendências'
    : `HackLab / Gestão / Reuniões e Pendências / ${TAB_LABELS[tab.value]}`))

  // Editor vê pendências e documentos apenas dos setores atribuídos.
  const sectorOptions = computed(() => sectorScope(state.session) || SETORES)
  const audit = useAudit()
  // Pendências podem ter responsável em outro setor (Administrador e Gestor atribuem e envolvem setores).
  const canRoute = computed(() => canRouteDemands(state.session))
  const taskSectorOptions = computed(() => (canRoute.value || !sectorScope(state.session) ? SETORES : sectorScope(state.session)))
  const globalView = computed(() => !sectorScope(state.session))
  // Editor (visão operacional): cria e atualiza só as próprias pendências; não exclui nem reatribui.
  const operational = computed(() => isOperational(state.session))
  const myName = computed(() => state.session?.name || '')
  function canEditTask(item) {
    return !operational.value || item.responsible === myName.value
  }
  // Visíveis: pendências em que o setor é origem, responsável ou envolvido.
  const scopedTasks = computed(() => state.tasks.filter((item) => demandVisible(sectorScope(state.session), item, 'task')))
  const scopedDocs = computed(() => state.documents.filter((item) => inScope(state.session, item.sector)))
  const meetings = computed(() => state.meetings.filter((item) => item.title.toLowerCase().includes(query.value.toLowerCase()) && (!status.value || item.status === status.value)))
  const atas = computed(() => state.meetings.filter((item) => item.ata && item.title.toLowerCase().includes(query.value.toLowerCase()) && (!status.value || item.ata.status === status.value)))
  const decisions = computed(() => state.decisions.filter((item) => item.title.toLowerCase().includes(query.value.toLowerCase()) && (!status.value || item.status === status.value)))
  const tasks = computed(() => scopedTasks.value.filter((item) => {
    const due = dueInfo(item).label
    return item.title.toLowerCase().includes(query.value.toLowerCase())
      && (!status.value || item.status === status.value)
      && (!sector.value || item.sector === sector.value)
      && (!priority.value || item.priority === priority.value)
      && (!prazo.value || prazo.value === due)
  }))
  const docs = computed(() => scopedDocs.value.filter((item) => item.name.toLowerCase().includes(query.value.toLowerCase()) && (docCatFilter.value === 'Todos' || docCat(item) === docCatFilter.value)))
  const upcoming = computed(() => state.meetings.filter((item) => item.status === 'Agendada').length)
  const atasPendentes = computed(() => state.meetings.filter((item) => item.ata?.status === 'Aguardando manifestações' || (!item.ata && item.status !== 'Realizada')).length)
  const pendingTasks = computed(() => scopedTasks.value.filter((item) => item.status === 'Pendente').length)
  const doingTasks = computed(() => scopedTasks.value.filter((item) => item.status === 'Em andamento').length)
  const lateTasks = computed(() => scopedTasks.value.filter((item) => dueInfo(item).label === 'Atrasado').length)
  const doneTasks = computed(() => scopedTasks.value.filter((item) => item.status === 'Concluído').length)
  const recentDocs = computed(() => scopedDocs.value.filter((item) => item.date && item.date !== 'Data demonstrativa').length)
  const pendingDocs = computed(() => scopedDocs.value.filter((item) => !item.fileName).length)
  const activeUsers = computed(() => state.users.filter((user) => user.status === 'Ativo'))

  const detailMeeting = computed(() => {
    if (detail.value?.type !== 'reuniao') return null
    return state.meetings.find((item) => item.id === detail.value.id) || null
  })
  const detailDecision = computed(() => {
    if (detail.value?.type !== 'decisao') return null
    return state.decisions.find((item) => item.id === detail.value.id) || null
  })
  const detailTask = computed(() => {
    if (detail.value?.type !== 'pendencia') return null
    // Só abre pendências visíveis para o setor do usuário (inclusive por link direto).
    return scopedTasks.value.find((item) => item.id === detail.value.id) || null
  })
  const detailDoc = computed(() => {
    if (detail.value?.type !== 'doc') return null
    return state.documents.find((item) => item.id === detail.value.id) || null
  })
  const meetingPeople = computed(() => {
    const meeting = detailMeeting.value
    if (!meeting) return []
    return state.users.filter((user) => meeting.participantIds?.includes(user.id))
  })
  const linkedDecisions = computed(() => {
    const meeting = detailMeeting.value
    if (!meeting) return []
    return state.decisions.filter((item) => item.meetingId === meeting.id)
  })
  const linkedDocs = computed(() => {
    const meeting = detailMeeting.value
    if (!meeting) return []
    return state.documents.filter((item) => docCat(item) === 'Atas' && (item.description || '').includes(meeting.title))
  })
  const manifestationDone = computed(() => detailMeeting.value?.ata?.manifestations?.length || 0)
  const decisionSubtitle = computed(() => {
    const decision = detailDecision.value
    if (!decision) return ''
    return state.meetings.find((item) => item.id === decision.meetingId)?.title || 'Sem reunião vinculada'
  })
  const taskDue = computed(() => (detailTask.value ? dueInfo(detailTask.value) : { label: '', tone: '' }))
  const modalTitle = computed(() => {
    if (modal.value === 'reuniao') return form.value.id ? 'Editar reunião' : 'Nova reunião'
    if (modal.value === 'decisao') return form.value.id ? 'Editar decisão' : 'Nova decisão'
    if (modal.value === 'pendencia') return form.value.id ? 'Editar pendência' : 'Nova pendência'
    return form.value.id ? 'Editar documento' : 'Adicionar documento'
  })

  function choose(id) {
    query.value = ''
    status.value = ''
    sector.value = ''
    priority.value = ''
    prazo.value = ''
    if (id === 'pendencias') go('pendencias')
    else if (id === 'documentos') go('documentos')
    else if (id === 'atas') go('reunioes?lista=atas')
    else if (id === 'decisoes') go('reunioes?lista=decisoes')
    else go('reunioes')
  }

  function openPendencia(seed) {
    detail.value = null
    form.value = {
      title: '',
      description: '',
      sector: '',
      due: '',
      responsible: '',
      status: 'Pendente',
      priority: 'Média',
      origin: '',
      decisionId: '',
      originSector: sectorScope(state.session)?.[0] || '',
      involvedSectors: [],
      ...(sectorScope(state.session) ? { sector: sectorScope(state.session)[0] } : {}),
      ...(operational.value ? { responsible: myName.value } : {}),
      ...(seed || {}),
    }
    modal.value = 'pendencia'
  }

  function openMeeting() {
    form.value = {
      title: '',
      type: 'Geral',
      responsible: state.users[0]?.name || '',
      date: '',
      start: '08:00',
      end: '12:00',
      place: 'A cadastrar',
      agenda: '',
      notes: '',
      participantIds: [],
    }
    modal.value = 'reuniao'
  }

  function editMeeting(item) {
    form.value = { ...item, participantIds: item.participantIds || [], agenda: item.agenda || '', notes: item.notes || '' }
    modal.value = 'reuniao'
  }

  function openDecision() {
    form.value = {
      title: '',
      description: '',
      meetingId: state.meetings[0]?.id || '',
      responsible: '',
      status: 'Registrada',
      sector: 'Tecnologia',
      date: '',
      notes: '',
    }
    modal.value = 'decisao'
  }

  function editDecision(item) {
    form.value = { description: '', notes: '', sector: 'Tecnologia', status: 'Registrada', meetingId: '', ...item }
    modal.value = 'decisao'
  }

  function openDocument() {
    form.value = {
      name: '',
      category: 'Outros',
      responsible: '',
      sector: '',
      description: '',
      version: '1.0',
      note: '',
      fileName: '',
    }
    modal.value = 'doc'
  }

  function editDocument(item) {
    form.value = { category: docCat(item), sector: '', description: '', version: item.version || '1.0', note: '', ...item }
    modal.value = 'doc'
  }

  function showMeeting(id, focus) {
    const meeting = state.meetings.find((item) => item.id === id)
    ataForm.value = meeting?.ata
      ? { ...meeting.ata }
      : {
        number: String(state.meetings.length).padStart(2, '0'),
        discussed: '',
        decisions: '',
        forwards: '',
        observations: '',
        status: 'Rascunho',
      }
    detail.value = focus ? { type: 'reuniao', id, focus } : { type: 'reuniao', id }
  }

  function toggleParticipant(userId) {
    const ids = form.value.participantIds || []
    const on = ids.includes(userId)
    form.value = {
      ...form.value,
      participantIds: on ? ids.filter((id) => id !== userId) : [...ids, userId],
    }
  }

  function onFile(event) {
    form.value = { ...form.value, fileName: event.target.files?.[0]?.name || '' }
  }

  function meetingName(id) {
    return state.meetings.find((item) => item.id === id)?.title || '—'
  }

  function save() {
    const kind = modal.value
    const current = form.value
    if ((kind === 'reuniao' && !current.title?.trim()) || (kind === 'decisao' && !current.title?.trim()) || (kind === 'pendencia' && !current.title?.trim()) || (kind === 'doc' && !current.name?.trim())) {
      flash('Informe o título para salvar.', 'err')
      return
    }
    const fromDecision = kind === 'pendencia' && current.decisionId
    update((draft) => {
      if (kind === 'reuniao') {
        const record = { ...current, title: current.title.trim(), participantIds: current.participantIds || [] }
        const index = current.id ? draft.meetings.findIndex((item) => item.id === current.id) : -1
        if (index >= 0) draft.meetings[index] = { ...draft.meetings[index], ...record }
        else draft.meetings.unshift({ id: uid('reu'), ...record, presence: {}, status: 'Agendada', ata: null })
      }
      if (kind === 'decisao') {
        const record = { ...current, title: current.title.trim(), forwards: current.forwards || [] }
        const index = current.id ? draft.decisions.findIndex((item) => item.id === current.id) : -1
        if (index >= 0) draft.decisions[index] = { ...draft.decisions[index], ...record }
        else draft.decisions.unshift({ id: uid('dec'), ...record })
      }
      if (kind === 'pendencia') {
        const record = {
          title: current.title.trim(),
          description: current.description,
          sector: current.sector,
          due: current.due,
          responsible: current.responsible,
          status: current.status || 'Pendente',
          priority: current.priority,
          origin: current.origin || '',
          decisionId: current.decisionId || '',
          notes: current.notes || '',
          involvedSectors: [...new Set([...(current.involvedSectors || []), current.originSector, current.sector].filter(Boolean))],
        }
        const actor = actorFrom(state.session)
        const index = current.id ? draft.tasks.findIndex((item) => item.id === current.id) : -1
        if (index >= 0) {
          const before = draft.tasks[index]
          const after = { ...before, ...record }
          const tracked = trackDemandEdit('task', before, after)
          after.history = [...(before.history || []), ...tracked.events.map((text) => historyEntry(actor, 'event', text))]
          draft.tasks[index] = after
          if (tracked.events.length) audit.record(draft, { action: tracked.action, label: tracked.label, module: 'tasks', entityType: 'Pendência', entityId: after.id, entityLabel: after.ref, description: `Pendência "${after.title}".`, changes: tracked.changes })
        } else {
          const created = { id: uid('pen'), ref: nextRef(draft.tasks, 'PEN'), createdAt: new Date().toLocaleString('pt-BR'), ...record, originSector: current.originSector || '', history: [historyEntry(actor, 'event', 'Criou a pendência.')] }
          draft.tasks.unshift(created)
          audit.record(draft, { action: 'task.created', label: 'Criou pendência', module: 'tasks', entityType: 'Pendência', entityId: created.id, entityLabel: created.ref, description: `Criou a pendência "${created.title}" (responsável: ${created.sector || 'sem setor'}).` })
        }
      }
      if (kind === 'doc') {
        const record = { ...current, name: current.name.trim() }
        const index = current.id ? draft.documents.findIndex((item) => item.id === current.id) : -1
        if (!current.id) audit.record(draft, { action: 'document.created', label: 'Cadastrou documento', module: 'documents', entityType: 'Documento', entityLabel: record.name, description: `Cadastrou o documento "${record.name}" (${record.category || 'sem categoria'}).` })
        if (index >= 0) draft.documents[index] = { ...draft.documents[index], ...record }
        else draft.documents.unshift({
          id: uid('doc'),
          ...record,
          date: new Date().toLocaleDateString('pt-BR'),
          history: [{ version: current.version || '1.0', date: new Date().toLocaleDateString('pt-BR'), responsible: current.responsible || '—', note: current.note || 'Versão inicial' }],
        })
      }
    })
    flash(current.id ? 'Alterações salvas.' : 'Registro salvo. A lista foi atualizada.')
    modal.value = null
    if (fromDecision) choose('pendencias')
  }

  function confirmRemove() {
    const current = removing.value
    if (!current) return
    update((draft) => {
      if (current.kind === 'reuniao') {
        draft.meetings = draft.meetings.filter((item) => item.id !== current.id)
        draft.decisions.forEach((item) => { if (item.meetingId === current.id) item.meetingId = '' })
      }
      if (current.kind === 'ata') {
        const meeting = draft.meetings.find((item) => item.id === current.id)
        if (meeting) {
          meeting.ata = null
          if (meeting.status === 'Aguardando manifestações') meeting.status = 'Agendada'
        }
      }
      if (current.kind === 'decisao') draft.decisions = draft.decisions.filter((item) => item.id !== current.id)
      if (current.kind === 'pendencia') draft.tasks = draft.tasks.filter((item) => item.id !== current.id)
      if (current.kind === 'doc') {
        audit.record(draft, { action: 'document.removed', label: 'Removeu documento', module: 'documents', entityType: 'Documento', entityId: current.id, entityLabel: current.name, description: `Removeu o documento "${current.name}".` })
        draft.documents = draft.documents.filter((item) => item.id !== current.id)
      }
    })
    const labels = { reuniao: 'Reunião excluída.', ata: 'Ata excluída.', decisao: 'Decisão excluída.', pendencia: 'Pendência excluída.', doc: 'Documento excluído.' }
    removing.value = null
    flash(labels[current.kind])
  }

  function publishAta() {
    const meeting = detailMeeting.value
    if (!meeting) return
    const snapshot = { ...ataForm.value }
    update((draft) => {
      const current = draft.meetings.find((item) => item.id === meeting.id)
      current.ata = {
        ...snapshot,
        status: 'Aguardando manifestações',
        manifestations: current.ata?.manifestations || [],
        versions: [{ version: '1.0', date: new Date().toLocaleDateString('pt-BR'), responsible: state.session?.name, change: 'Versão disponibilizada' }],
      }
      current.status = 'Aguardando manifestações'
    })
    flash('Ata disponibilizada para manifestação.')
  }

  function completeTask() {
    const task = detailTask.value
    if (!task) return
    const actor = actorFrom(state.session)
    update((draft) => {
      const found = draft.tasks.find((item) => item.id === task.id)
      if (!found) return
      const before = found.status
      found.status = 'Concluído'
      found.history.push(historyEntry(actor, 'event', 'Concluiu a pendência.'))
      audit.record(draft, { action: 'task.completed', label: 'Concluiu pendência', module: 'tasks', entityType: 'Pendência', entityId: found.id, entityLabel: found.ref, description: `Pendência "${found.title}" concluída.`, changes: [{ field: 'Status', before, after: 'Concluído' }] })
    })
    flash('Pendência marcada como concluída.')
  }

  function reopenTask() {
    const task = detailTask.value
    if (!task) return
    const actor = actorFrom(state.session)
    update((draft) => {
      const found = draft.tasks.find((item) => item.id === task.id)
      if (!found) return
      found.status = 'Em andamento'
      found.history.push(historyEntry(actor, 'event', 'Reabriu a pendência.'))
      audit.record(draft, { action: 'task.reopened', label: 'Reabriu pendência', module: 'tasks', entityType: 'Pendência', entityId: found.id, entityLabel: found.ref, description: `Pendência "${found.title}" reaberta.`, changes: [{ field: 'Status', before: 'Concluído', after: 'Em andamento' }] })
    })
    flash('Pendência reaberta.')
  }

  // Pendência gerada a partir de uma ocorrência.
  const sourceOccurrence = computed(() => (detailTask.value?.sourceOccurrenceId ? state.occurrences.find((item) => item.id === detailTask.value.sourceOccurrenceId) : null))

  function toggleTaskSector(sectorName) {
    const list = form.value.involvedSectors || []
    form.value.involvedSectors = list.includes(sectorName) ? list.filter((item) => item !== sectorName) : [...list, sectorName]
  }

  // Link direto (ex.: "Ver pendência" a partir de uma ocorrência).
  watch(() => props.params?.ver, (id) => {
    if (id && scopedTasks.value.some((item) => item.id === id)) detail.value = { type: 'pendencia', id }
  }, { immediate: true })

  function createTaskFromDecision() {
    const decision = detailDecision.value
    if (!decision) return
    openPendencia({
      title: decision.title,
      description: decision.description || '',
      sector: decision.sector || '',
      responsible: decision.responsible || '',
      origin: `Decisão · ${decision.title}`,
      decisionId: decision.id,
    })
  }

  function askRemove(kind, id, name) {
    removing.value = { kind, id, name }
  }

  return {
    canRoute,
    taskSectorOptions,
    globalView,
    reopenTask,
    sourceOccurrence,
    toggleTaskSector,
    SETORES,
    DOC_CATS,
    DOC_FILTERS,
    MEETING_TYPES,
    PRIORITIES,
    DUE_FILTERS,
    docCat,
    dueInfo,
    outsideHours,
    ataProgress,
    state,
    tab,
    pageCopy,
    modal,
    form,
    query,
    status,
    sector,
    priority,
    docCatFilter,
    prazo,
    detail,
    removing,
    ataForm,
    sectorOptions,
    operational,
    myName,
    canEditTask,
    meetings,
    atas,
    decisions,
    tasks,
    docs,
    activeUsers,
    detailMeeting,
    detailDecision,
    detailTask,
    detailDoc,
    meetingPeople,
    linkedDecisions,
    linkedDocs,
    manifestationDone,
    decisionSubtitle,
    taskDue,
    modalTitle,
    openPendencia,
    openMeeting,
    editMeeting,
    openDecision,
    editDecision,
    openDocument,
    editDocument,
    showMeeting,
    toggleParticipant,
    onFile,
    meetingName,
    save,
    confirmRemove,
    publishAta,
    completeTask,
    createTaskFromDecision,
    askRemove,
    go,
    toneFor,
  }
}
