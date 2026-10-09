// Lógica do componente Judges.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { canAccess } from '@/js/config/access'
import { companyOf, inviteCode, teamChallenge, teamName, uid } from '@/js/data/model'
import { useHack, go } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useJudges(props) {
  const TYPES = ['Nota numérica', 'Escala', 'Conceito']


  const { state, update, flash } = useHack()
  const audit = useAudit()

  function dash(value) {
    return value ? value : '—'
  }

  function teamLabel(team) {
    const challenge = teamChallenge(state, team.id)
    const company = challenge ? companyOf(state, challenge.companyId) : null
    return { challenge, company }
  }

  function doneEvals(teamId) {
    return state.evaluations.filter((item) => item.teamId === teamId && item.status === 'concluida')
  }

  function teamStatus(teamId) {
    const items = state.evaluations.filter((item) => item.teamId === teamId)
    if (items.some((item) => item.status === 'revisao')) return 'Em revisão'
    const active = state.judges.filter((item) => item.status !== 'Inativo')
    const done = items.filter((item) => item.status === 'concluida')
    if (!items.length) return 'Não iniciada'
    if (active.length && done.length >= active.length) return 'Concluída'
    if (!active.length && done.length) return 'Concluída'
    return 'Em andamento'
  }

  function voteCount(teamId) {
    return state.voting.ballots.filter((item) => item.teamId === teamId).length
  }

  function isInactive(item) {
    return item.active === false || item.status === 'Inativo'
  }

  function isDemo(item) {
    return item.demo || /demonstrativo/i.test(item.name)
  }

  function scaleOf(item) {
    return item.min !== '' && item.max !== '' && item.min != null && item.max != null ? `${item.min}–${item.max}` : '—'
  }

  function weightOf(item) {
    return item.weight === '' || item.weight == null ? '—' : item.weight
  }

  const tab = computed(() => (props.part === 'votacao' || props.params.aba === 'publico' ? 'publico' : 'avaliacoes'))
  const showAdmin = computed(() => (props.part ? props.part === 'jurados' || props.part === 'avaliacoes' : tab.value === 'avaliacoes'))
  const showJudgesBlock = computed(() => !props.part || props.part === 'jurados')
  const showEvalBlock = computed(() => !props.part || props.part === 'avaliacoes')
  const showVote = computed(() => props.part === 'votacao' || (!props.part && tab.value === 'publico'))
  const showResults = computed(() => props.part === 'resultados')
  const heading = computed(() => {
    if (props.part === 'avaliacoes') return ['Avaliações', 'Acompanhe as avaliações das equipes e dos jurados.']
    if (props.part === 'votacao') return ['Votação', 'Gerencie a votação do público.']
    if (props.part === 'resultados') return ['Resultados', 'Visualize, libere e apresente os resultados do Hackathon.']
    return ['Jurados', 'Gerencie os jurados participantes das avaliações.']
  })

  const modal = ref(null)
  const form = ref({})
  const confirmVote = ref(false)
  const judgeDetail = ref(null)
  const removing = ref(null)
  const showVotes = ref(false)

  const activeJudges = computed(() => state.judges.filter((item) => item.status !== 'Inativo'))
  const finished = computed(() => state.evaluations.filter((item) => item.status === 'concluida').length)
  const pendingTeams = computed(() => (activeJudges.value.length ? state.teams.filter((team) => teamStatus(team.id) !== 'Concluída').length : 0))
  const company = computed(() => state.companies.find((item) => item.id === form.value.companyId))
  const reps = computed(() => company.value?.reps || [])
  const selectedRep = computed(() => reps.value.find((item) => item.id === form.value.repId))
  const totalVotes = computed(() => state.voting.ballots.length)

  function releaseResults() {
    update((draft) => {
      draft.resultsReleased = true
      audit.record(draft, { action: 'results.published', label: 'Publicou resultados', module: 'results', entityType: 'Resultados', description: 'Resultados liberados para divulgação.' })
    })
    flash('Resultados liberados para divulgação.')
  }

  // Jurado: empresa e representante são opcionais (contexto). As equipes avaliadas vêm só de assignedTeamIds.
  function saveJudge() {
    const rep = selectedRep.value
    const owner = company.value
    const name = (rep?.name || form.value.name || '').trim()
    if (!name) return flash('Informe o nome do jurado ou selecione um representante.', 'err')
    const exists = state.judges.some((item) => item.id !== form.value.id && ((rep && item.repId === rep.id) || item.name === name))
    if (exists) return flash('Esta pessoa já está definida como jurado.', 'err')
    const editing = form.value.id
    const record = {
      repId: rep?.id || '',
      name,
      companyId: owner?.id || '',
      companyName: owner?.name || '',
      cargo: rep?.cargo || form.value.cargo || '',
      email: rep?.email || form.value.email || '',
      phone: rep?.phone || '',
      status: form.value.status || 'Ativo',
      assignedTeamIds: [...(form.value.assignedTeamIds || [])],
    }
    update((draft) => {
      const index = editing ? draft.judges.findIndex((item) => item.id === editing) : -1
      const teamsLabel = (ids) => (ids?.length ? ids.map((id) => teamName(id)).join(', ') : 'Nenhuma')
      if (index >= 0) {
        const before = draft.judges[index]
        draft.judges[index] = { ...before, ...record }
        const assignedChanged = teamsLabel(before.assignedTeamIds) !== teamsLabel(record.assignedTeamIds)
        audit.record(draft, { action: assignedChanged ? 'judge.teams-assigned' : 'judge.updated', label: assignedChanged ? 'Atribuiu equipes ao jurado' : 'Editou jurado', module: 'judges', entityType: 'Jurado', entityId: before.id, entityLabel: record.name, description: `Jurado ${record.name}.`, changes: [{ field: 'Equipes atribuídas', before: teamsLabel(before.assignedTeamIds), after: teamsLabel(record.assignedTeamIds) }, { field: 'Empresa', before: before.companyName || 'Sem empresa', after: record.companyName || 'Sem empresa' }, { field: 'Status', before: before.status, after: record.status }] })
      } else {
        const id = uid('jur')
        draft.judges.push({ id, ...record })
        audit.record(draft, { action: 'judge.created', label: 'Cadastrou jurado', module: 'judges', entityType: 'Jurado', entityId: id, entityLabel: record.name, description: `Cadastrou o jurado ${record.name} · equipes: ${teamsLabel(record.assignedTeamIds)}.` })
      }
    })
    modal.value = null
    flash(editing ? 'Alterações salvas.' : 'Jurado adicionado.')
  }

  function toggleAssigned(teamId) {
    const list = form.value.assignedTeamIds || []
    form.value.assignedTeamIds = list.includes(teamId) ? list.filter((id) => id !== teamId) : [...list, teamId]
  }

  function assignedLabel(judge) {
    const ids = judge.assignedTeamIds || []
    return ids.length ? ids.map((id) => teamName(id)).join(', ') : 'Nenhuma'
  }

  function saveCriterion() {
    if (!form.value.name?.trim()) return flash('Informe o nome do critério.', 'err')
    const editing = form.value.id
    const record = {
      name: form.value.name.trim(),
      description: form.value.description || '',
      type: form.value.type || 'Nota numérica',
      min: form.value.min ?? '',
      max: form.value.max ?? '',
      weight: form.value.weight ?? '',
      active: form.value.status !== 'Inativo',
      status: form.value.status || 'Ativo',
      demo: /demonstrativo/i.test(form.value.name),
    }
    update((draft) => {
      const index = editing ? draft.criteria.findIndex((item) => item.id === editing) : -1
      if (index >= 0) draft.criteria[index] = { ...draft.criteria[index], ...record }
      else draft.criteria.push({ id: uid('cri'), ...record, order: draft.criteria.length + 1 })
    })
    modal.value = null
    flash(editing ? 'Alterações salvas.' : 'Critério salvo.')
  }

  // Convite de jurado (demonstrativo): o código autoriza o cadastro público como Jurado. Nenhum e-mail é enviado.
  const inviteForm = ref(null)
  const inviteResult = ref(null)
  const inviteCompany = computed(() => state.companies.find((item) => item.id === inviteForm.value?.companyId))
  const inviteReps = computed(() => inviteCompany.value?.reps || [])

  function openInvite() {
    inviteForm.value = { companyId: state.companies[0]?.id || '', repId: '', repName: '', email: '' }
    inviteResult.value = null
  }

  function pickInviteRep(id) {
    const rep = inviteReps.value.find((item) => item.id === id)
    inviteForm.value = { ...inviteForm.value, repId: id, repName: rep?.name || '', email: rep?.email || inviteForm.value.email }
  }

  function generateInvite() {
    const current = inviteForm.value
    if (!current.repName.trim()) return flash('Informe o representante.', 'err')
    if (!current.email.trim()) return flash('Informe o e-mail do representante.', 'err')
    let code = inviteCode()
    while ((state.invites || []).some((item) => item.code === code)) code = inviteCode()
    const record = {
      id: uid('conv'),
      code,
      repId: current.repId,
      repName: current.repName.trim(),
      companyId: current.companyId,
      companyName: inviteCompany.value?.name || '',
      email: current.email.trim().toLowerCase(),
      status: 'Não utilizado',
      usedBy: '',
      createdAt: new Date().toLocaleDateString('pt-BR'),
    }
    update((draft) => {
      if (!draft.invites) draft.invites = []
      draft.invites.unshift(record)
    })
    inviteResult.value = record
    inviteForm.value = null
  }

  async function copyInvite(code) {
    try {
      await navigator.clipboard.writeText(code)
      flash('Código copiado.')
    } catch {
      flash('Não foi possível copiar. Selecione o código e copie manualmente.', 'err')
    }
  }

  function openJudge() {
    form.value = { companyId: '', repId: '', name: '', email: '', cargo: '', status: 'Ativo', assignedTeamIds: [] }
    modal.value = 'juiz'
  }

  function openCriterion(item) {
    if (item) form.value = { ...item, status: item.active === false || item.status === 'Inativo' ? 'Inativo' : 'Ativo' }
    else form.value = { name: '', description: '', type: 'Nota numérica', min: '', max: '', weight: '', status: 'Ativo' }
    modal.value = 'criterio'
  }

  function editJudge(judge) {
    const owner = state.companies.find((item) => item.id === judge.companyId)
    const rep = owner?.reps?.find((item) => item.id === judge.repId) || owner?.reps?.find((item) => item.name === judge.name)
    form.value = { id: judge.id, companyId: judge.companyId || '', repId: rep?.id || '', name: judge.name, email: judge.email || '', cargo: judge.cargo || '', status: judge.status || 'Ativo', assignedTeamIds: [...(judge.assignedTeamIds || [])] }
    modal.value = 'juiz'
  }

  function judgeDone(judge) {
    return state.evaluations.filter((item) => item.judgeName === judge.name && item.status === 'concluida').length
  }

  function toggleCriterion(item) {
    update((draft) => {
      const current = draft.criteria.find((criterion) => criterion.id === item.id)
      current.active = !current.active
      current.status = current.active ? 'Ativo' : 'Inativo'
    })
  }

  function applyVote() {
    const action = confirmVote.value
    update((draft) => {
      const before = draft.voting.status
      draft.voting.status = action === 'start' ? 'Em andamento' : 'Encerrada'
      audit.record(draft, { action: action === 'start' ? 'voting.opened' : 'voting.closed', label: action === 'start' ? 'Abriu votação' : 'Encerrou votação', module: 'voting', entityType: 'Votação do público', description: action === 'start' ? 'Votação do público iniciada.' : `Votação do público encerrada com ${draft.voting.ballots.length} votos.`, changes: [{ field: 'Situação', before, after: draft.voting.status }] })
    })
    confirmVote.value = false
    showVotes.value = false
    flash(action === 'start' ? 'Votação iniciada.' : 'Votação encerrada.')
  }

  function removeRecord() {
    const current = removing.value
    update((draft) => {
      if (current.kind === 'jurado') draft.judges = draft.judges.filter((item) => item.id !== current.id)
      if (current.kind === 'criterio') draft.criteria = draft.criteria.filter((item) => item.id !== current.id)
      if (current.kind === 'avaliacao') draft.evaluations = draft.evaluations.filter((item) => item.teamId !== current.id)
    })
    removing.value = null
    flash(current.kind === 'avaliacao' ? 'Avaliação excluída.' : 'Registro excluído.')
  }

  return {
    TYPES,
    state,
    dash,
    teamLabel,
    doneEvals,
    teamStatus,
    voteCount,
    isInactive,
    isDemo,
    scaleOf,
    weightOf,
    showAdmin,
    showJudgesBlock,
    showEvalBlock,
    showVote,
    showResults,
    heading,
    modal,
    form,
    confirmVote,
    judgeDetail,
    removing,
    showVotes,
    activeJudges,
    reps,
    selectedRep,
    totalVotes,
    releaseResults,
    saveJudge,
    toggleAssigned,
    assignedLabel,
    saveCriterion,
    inviteForm,
    inviteResult,
    inviteReps,
    openInvite,
    pickInviteRep,
    generateInvite,
    copyInvite,
    openJudge,
    openCriterion,
    editJudge,
    judgeDone,
    toggleCriterion,
    applyVote,
    removeRecord,
    canAccess,
    teamName,
    go,
    toneFor,
  }
}
