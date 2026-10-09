// Lógica do componente Evaluate.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { profileConfig } from '@/js/config/access'
import { assignedTeams, companyOf, teamChallenge, teamName, uid } from '@/js/data/model'
import { useHack, go } from '@/js/stores/hack'

export function useEvaluate(props) {
  const { state, update, flash } = useHack()
  const audit = useAudit()

  function teamLabel(team) {
    const challenge = teamChallenge(state, team.id)
    const company = challenge ? companyOf(state, challenge.companyId) : null
    return { challenge, company }
  }

  function isDemo(item) {
    return item.demo || /demonstrativo/i.test(item.name)
  }

  // Jurado avalia só as equipes atribuídas a ele.
  const team = computed(() => {
    const pool = state.session?.profile === 'Jurado' ? assignedTeams(state) : state.teams
    return pool.find((item) => item.id === Number(props.params.id || 1)) || null
  })
  const meta = computed(() => (team.value ? teamLabel(team.value) : { challenge: null, company: null }))
  const criteria = computed(() => state.criteria.filter((item) => item.active !== false && item.status !== 'Inativo'))
  const judgeName = computed(() => state.session?.name || 'Jurado')
  // Correção administrativa é ação da organização, não do jurado.
  const canCorrect = computed(() => Boolean(profileConfig(state.session?.profile).globalAdmin))
  const existing = computed(() => (team.value ? state.evaluations.find((item) => item.teamId === team.value.id && item.judgeName === judgeName.value) : null))

  const initialExisting = team.value
    ? state.evaluations.find((item) => item.teamId === team.value.id && item.judgeName === (state.session?.name || 'Jurado'))
    : null
  const scores = ref({ ...(initialExisting?.scores || {}) })
  const notes = ref(initialExisting?.notes || '')
  const ask = ref(false)
  const correct = ref(false)
  const reason = ref('')
  const locked = computed(() => existing.value?.status === 'concluida')
  const filled = computed(() => criteria.value.filter((item) => scores.value[item.id] !== undefined && scores.value[item.id] !== '').length)

  function setScore(id, value) {
    scores.value = { ...scores.value, [id]: value }
  }

  function persist(status) {
    if (status === 'concluida' && criteria.value.length && criteria.value.some((item) => scores.value[item.id] === undefined || scores.value[item.id] === '')) {
      flash('Preencha os critérios antes de finalizar.', 'err')
      ask.value = false
      return
    }
    const currentTeam = team.value
    const name = judgeName.value
    const payloadScores = scores.value
    const payloadNotes = notes.value
    update((draft) => {
      const current = draft.evaluations.find((item) => item.teamId === currentTeam.id && item.judgeName === name)
      const payload = { id: current?.id || uid('av'), teamId: currentTeam.id, judgeName: name, scores: { ...payloadScores }, notes: payloadNotes, status, at: new Date().toLocaleString('pt-BR') }
      if (current) Object.assign(current, payload)
      else draft.evaluations.push(payload)
      if (status === 'concluida') audit.record(draft, { action: 'evaluation.finalized', label: 'Finalizou avaliação', module: 'evaluations', entityType: 'Avaliação', entityId: payload.id, entityLabel: teamName(currentTeam.id), description: `${name} finalizou a avaliação da ${teamName(currentTeam.id)}.` })
    })
    ask.value = false
    flash(status === 'concluida' ? 'Avaliação registrada.' : 'Rascunho salvo.')
  }

  function applyCorrection() {
    if (!reason.value.trim()) return flash('Informe o motivo da correção.', 'err')
    const currentTeam = team.value
    const name = judgeName.value
    const text = reason.value.trim()
    update((draft) => {
      const current = draft.evaluations.find((item) => item.teamId === currentTeam.id && item.judgeName === name)
      if (current) {
        current.status = 'revisao'
        current.correction = text
      }
      audit.record(draft, { action: 'evaluation.corrected', label: 'Reabriu avaliação para correção', module: 'evaluations', entityType: 'Avaliação', entityId: current?.id || '', entityLabel: teamName(currentTeam.id), description: `Correção administrativa: ${text}`, changes: [{ field: 'Status', before: 'Concluída', after: 'Em revisão' }] })
    })
    correct.value = false
    reason.value = ''
    flash('Correção registrada.')
  }

  return {
    isDemo,
    team,
    meta,
    criteria,
    canCorrect,
    existing,
    scores,
    notes,
    ask,
    correct,
    reason,
    locked,
    filled,
    setScore,
    persist,
    applyCorrection,
    teamName,
    go,
  }
}
