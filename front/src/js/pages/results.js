// Lógica do componente Results.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { isAdmin } from '@/js/config/access'
import { companyOf, teamChallenge, teamName } from '@/js/data/model'
import { useHack, go } from '@/js/stores/hack'

export function useResults() {
  const { state, update, flash } = useHack()
  const audit = useAudit()
  const ask = ref(false)

  function teamLabel(team) {
    const challenge = teamChallenge(state, team.id)
    const company = challenge ? companyOf(state, challenge.companyId) : null
    return { challenge, company }
  }

  function doneEvals(teamId) {
    return state.evaluations.filter((item) => item.teamId === teamId && item.status === 'concluida')
  }

  function averageOf(teamId) {
    const scores = doneEvals(teamId).flatMap((item) => Object.values(item.scores || {}).map(Number).filter((value) => !Number.isNaN(value)))
    if (!scores.length) return null
    return scores.reduce((sum, value) => sum + value, 0) / scores.length
  }

  function voteCount(teamId) {
    return state.voting.ballots.filter((item) => item.teamId === teamId).length
  }

  const rows = computed(() => state.teams.map((team) => {
    const meta = teamLabel(team)
    return { team, ...meta, avg: averageOf(team.id), votes: voteCount(team.id) }
  }))
  const technical = computed(() => rows.value.filter((item) => item.avg != null))
  const totalVotes = computed(() => state.voting.ballots.length)
  const topVotes = computed(() => Math.max(0, ...rows.value.map((item) => item.votes)))
  const leaders = computed(() => rows.value.filter((item) => item.votes === topVotes.value && topVotes.value > 0))
  const admin = computed(() => isAdmin(state.session))

  function release() {
    update((draft) => {
      draft.resultsReleased = true
      audit.record(draft, { action: 'results.published', label: 'Publicou resultados', module: 'results', entityType: 'Resultados', description: 'Resultados liberados no painel.' })
    })
    ask.value = false
    flash('Resultados liberados.')
  }

  return {
    state,
    ask,
    rows,
    technical,
    totalVotes,
    leaders,
    admin,
    release,
    teamName,
    go,
  }
}
