// Lógica do componente Dashboard.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { canAccess, inScope, isOperational, sectorScope } from '@/js/config/access'
import { currentEventDay, demandVisible, isAvailable, journeySteps, occurrenceSector, occurrenceStatus, plural, presenceOf } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useDashboard() {
  const { state } = useHack()
  const profile = computed(() => state.session?.profile)
  const steps = computed(() => journeySteps(state))
  const done = computed(() => steps.value.filter((step) => step.status === 'concluida').length)
  const current = computed(() => steps.value.find((step) => step.status === 'andamento') || steps.value[steps.value.length - 1])
  const openTasks = computed(() => state.tasks.filter((task) => task.status !== 'Concluído').length)
  const openOccList = computed(() => state.occurrences.filter((item) => occurrenceStatus(item) !== 'Resolvida'))
  const openOcc = computed(() => openOccList.value.length)
  const available = computed(() => state.students.filter(isAvailable).length)
  // Presença do dia de referência do evento (último dia com check-in; antes do evento, Dia 1).
  const eventDay = computed(() => currentEventDay(state))
  // Credenciais do evento (todas as categorias) para o dia de referência.
  const activeCredentials = computed(() => (state.credentials || []).filter((item) => item.status === 'Ativa'))
  const dayCredentials = computed(() => activeCredentials.value.filter((item) => item.days.includes(eventDay.value)))
  const dayPresent = computed(() => (state.credentials || []).filter((item) => presenceOf(state, item.personId, eventDay.value)).length)
  const dayMissing = computed(() => dayCredentials.value.filter((item) => !presenceOf(state, item.personId, eventDay.value)).length)
  const blockedCredentials = computed(() => (state.credentials || []).filter((item) => item.status === 'Bloqueada').length)
  const dayStarted = computed(() => state.checkins.some((item) => Number(item.day) === eventDay.value))
  const finishedEvals = computed(() => state.evaluations.filter((item) => item.status === 'concluida').length)
  const votes = computed(() => state.voting?.ballots?.length || 0)
  const withoutTeam = computed(() => {
    const placed = new Set(state.teams.flatMap((team) => team.members || []))
    return state.students.filter((student) => isAvailable(student) && !placed.has(student.id)).length
  })
  const eventMeta = computed(() => [
    state.event.date || 'Data a definir',
    `${state.event.days || 3} dias`,
    `${state.event.start || '08:00'}–${state.event.end || '12:00'}`,
    state.event.location || 'Local a cadastrar',
  ])
  const adminDomains = computed(() => [
    {
      title: 'Preparação',
      to: 'config',
      action: 'Ver preparação',
      items: [
        { label: 'Participantes', value: state.students.length, to: 'participantes' },
        { label: 'Disponíveis', value: available.value, to: 'participantes' },
        { label: 'Equipes', value: state.teams.length, to: 'equipes' },
        { label: 'Empresas', value: state.companies.length, to: 'empresas' },
        { label: 'Desafios', value: state.challenges.length, to: 'desafios' },
      ],
    },
    {
      title: 'Gestão',
      to: 'setores',
      action: 'Ver gestão',
      items: [
        { label: 'Pendências', value: openTasks.value, to: 'pendencias' },
        { label: 'Reuniões', value: state.meetings.length, to: 'reunioes' },
        { label: 'Ocorrências abertas', value: openOcc.value, to: 'ocorrencias' },
        { label: 'Documentos', value: state.documents.length, to: 'documentos' },
      ],
    },
    {
      title: 'Evento',
      to: `presenca?dia=${eventDay.value}`,
      action: 'Ver Credenciais e Presença',
      items: [
        { label: 'Credenciais ativas', value: activeCredentials.value.length, to: 'presenca?aba=credenciais' },
        { label: `Presentes · Dia ${eventDay.value}`, value: dayPresent.value, to: `presenca?dia=${eventDay.value}` },
        { label: 'Não registrados', value: dayMissing.value, to: `presenca?dia=${eventDay.value}` },
        { label: 'Ocorrências abertas', value: openOcc.value, to: 'ocorrencias' },
      ],
    },
    {
      title: 'Encerramento',
      to: 'jurados',
      action: 'Ver encerramento',
      items: [
        { label: 'Avaliações', value: finishedEvals.value, to: 'avaliacoes' },
        { label: 'Votos', value: votes.value, to: 'votacao-gestao' },
      ],
    },
  ])
  const consultantDomains = computed(() => [
    {
      title: 'Preparação',
      to: 'participantes',
      action: 'Ver participantes',
      items: [
        { label: 'Participantes', value: state.students.length, to: 'participantes' },
        { label: 'Disponíveis', value: available.value, to: 'participantes' },
        { label: 'Equipes', value: state.teams.length, to: 'equipes' },
        { label: 'Sem equipe', value: withoutTeam.value, to: 'equipes' },
      ],
    },
    {
      title: 'Gestão',
      to: 'pendencias',
      action: 'Ver pendências',
      items: [
        { label: 'Pendências', value: openTasks.value, to: 'pendencias' },
        { label: 'Ocorrências abertas', value: openOcc.value, to: 'ocorrencias' },
        { label: 'Reuniões', value: state.meetings.length, to: 'reunioes' },
      ],
    },
    adminDomains.value[2],
    {
      title: 'Encerramento',
      to: 'avaliacoes',
      action: 'Ver avaliações',
      items: [
        { label: 'Avaliações concluídas', value: finishedEvals.value, to: 'avaliacoes' },
        { label: 'Resultados divulgados', value: state.resultsReleased ? 'Sim' : 'Não', to: 'resultados' },
      ],
    },
  ])
  const allowed = (to) => canAccess(profile.value, to)
  const domains = computed(() => (profile.value === 'Consultor' ? consultantDomains.value : adminDomains.value)
    .map((item) => ({ ...item, items: item.items.filter((entry) => allowed(entry.to)) })))
  // Atenção: problema/urgente primeiro, depois pendências, depois informação.
  const RANK = { bad: 0, warn: 1, info: 2 }
  const showAllAttention = ref(false)
  const attention = computed(() => {
    const items = []
    const broken = state.equipment.filter((item) => item.status === 'Com problema').length
    const urgent = openOccList.value.filter((item) => item.priority === 'Urgente').length
    const otherOcc = openOcc.value - urgent
    if (urgent) items.push({ tone: 'bad', text: plural(urgent, 'ocorrência urgente', 'ocorrências urgentes'), to: 'ocorrencias' })
    if (broken) items.push({ tone: 'bad', text: plural(broken, 'equipamento com problema', 'equipamentos com problema'), to: 'setores?setor=Tecnologia' })
    if (otherOcc) items.push({ tone: 'warn', text: plural(otherOcc, 'ocorrência aberta', 'ocorrências abertas'), to: 'ocorrencias' })
    if (openTasks.value) items.push({ tone: 'warn', text: `${plural(openTasks.value, 'pendência precisa', 'pendências precisam')} de atenção`, to: 'pendencias' })
    if (dayStarted.value && dayMissing.value) items.push({ tone: 'warn', text: `${plural(dayMissing.value, 'pessoa', 'pessoas')} ainda sem presença no Dia ${eventDay.value}`, to: `presenca?dia=${eventDay.value}` })
    if (blockedCredentials.value) items.push({ tone: 'warn', text: plural(blockedCredentials.value, 'credencial bloqueada', 'credenciais bloqueadas'), to: 'presenca?aba=credenciais' })
    if (withoutTeam.value) items.push({ tone: 'warn', text: `${plural(withoutTeam.value, 'participante', 'participantes')} sem equipe`, to: 'equipes' })
    if (state.teams.length && state.evaluations.length === 0) items.push({ tone: 'info', text: 'Avaliações ainda não iniciadas', to: 'avaliacoes' })
    // Administrador: atividade do dia registrada na Auditoria (sem virar card próprio).
    const today = new Date().toDateString()
    const logged = (state.audit || []).filter((item) => item.timestamp && new Date(item.timestamp).toDateString() === today).length
    if (logged) items.push({ tone: 'info', text: `${plural(logged, 'ação registrada', 'ações registradas')} hoje`, to: 'auditoria' })
    return items.filter((item) => allowed(item.to)).sort((x, y) => RANK[x.tone] - RANK[y.tone])
  })
  // Gestor e Editor: tudo recortado pelos setores atribuídos.
  const sectors = computed(() => sectorScope(state.session) || [])
  const scoped = computed(() => sectors.value.length > 0)
  const operational = computed(() => isOperational(state.session))
  const myName = computed(() => state.session?.name || '')
  const URGENT = ['Alta', 'Urgente']
  const sectorTasks = computed(() => state.tasks.filter((task) => demandVisible(sectorScope(state.session), task, 'task') && task.status !== 'Concluído'))
  const myTasks = computed(() => sectorTasks.value.filter((task) => task.responsible === myName.value))
  const sectorOcc = computed(() => openOccList.value.filter((item) => demandVisible(sectorScope(state.session), item, 'occurrence')))
  const sectorDocs = computed(() => state.documents.filter((item) => inScope(state.session, item.sector)))
  const sectorMembers = computed(() => (state.orgMembers || []).filter((item) => inScope(state.session, item.sector)))
  const sectorLink = (name) => `setores?setor=${encodeURIComponent(name)}`
  const managerSummary = computed(() => sectors.value.map((name) => ({
    title: name,
    to: sectorLink(name),
    action: 'Abrir setor',
    items: [
      { label: 'Pendências abertas', value: sectorTasks.value.filter((task) => task.sector === name).length, to: 'pendencias' },
      { label: 'Ocorrências abertas', value: sectorOcc.value.filter((item) => occurrenceSector(item) === name).length, to: 'ocorrencias' },
      { label: 'Membros', value: sectorMembers.value.filter((item) => item.sector === name).length, to: sectorLink(name) },
      { label: 'Documentos', value: sectorDocs.value.filter((item) => item.sector === name).length, to: 'documentos' },
    ],
  })))
  const priorities = computed(() => [
    ...sectorOcc.value.filter((item) => URGENT.includes(item.priority)).map((item) => ({ id: item.id, kind: 'Ocorrência', title: item.title, tone: 'bad', badge: item.priority, to: 'ocorrencias' })),
    ...sectorTasks.value.filter((task) => URGENT.includes(task.priority)).map((task) => ({ id: task.id, kind: 'Pendência', title: task.title, tone: 'warn', badge: task.priority, to: 'pendencias' })),
  ].slice(0, 5))
  const recent = computed(() => [
    ...sectorDocs.value.slice(0, 3).map((item) => ({ id: item.id, kind: 'Documento', title: item.name, when: item.date, to: 'documentos' })),
    ...state.occurrences.filter((item) => demandVisible(sectorScope(state.session), item, 'occurrence')).slice(0, 3).map((item) => ({ id: item.id, kind: 'Ocorrência', title: item.title, when: item.at || (item.day ? `Dia ${item.day}` : ''), to: 'ocorrencias' })),
  ].slice(0, 5))

  const STEP_LABELS = ['Configurar evento', 'Participantes', 'Equipes', 'Empresas e desafios', 'Preparação operacional', 'Realizar evento', 'Encerramento']

  function statusLabel(status) {
    if (status === 'concluida') return 'Concluída'
    if (status === 'andamento') return 'Em andamento'
    return 'Pendente'
  }

  return {
    state,
    steps,
    done,
    current,
    eventMeta,
    allowed,
    domains,
    showAllAttention,
    attention,
    sectors,
    scoped,
    operational,
    URGENT,
    sectorTasks,
    myTasks,
    sectorOcc,
    sectorDocs,
    sectorMembers,
    sectorLink,
    managerSummary,
    priorities,
    recent,
    STEP_LABELS,
    statusLabel,
    go,
    toneFor,
  }
}
