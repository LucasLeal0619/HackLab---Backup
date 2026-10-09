// Lógica do componente PublicVote.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { companyOf, currentUser, teamChallenge, teamName } from '@/js/data/model'
import { useHack } from '@/js/stores/hack'

export function usePublicVote() {
  // Sem sessão (link público), o voto único é controlado neste navegador.
  const VOTE_KEY = 'hacklab.vote.cast'

  const { state, update } = useHack()

  function teamLabel(team) {
    const challenge = teamChallenge(state, team.id)
    const company = challenge ? companyOf(state, challenge.companyId) : null
    return { challenge, company }
  }

  const choice = ref(null)
  const localVote = ref(localStorage.getItem(VOTE_KEY) === '1')
  const voter = computed(() => state.session?.email || '')
  // Voto único por conta: hasVoted fica na conta do Votante (demonstrativo).
  const account = computed(() => currentUser(state))
  const voted = computed(() => (voter.value ? Boolean(account.value?.hasVoted) || state.voting.ballots.some((item) => item.voter === voter.value) : localVote.value))
  const open = computed(() => state.voting.status === 'Em andamento')

  function confirm() {
    const teamId = choice.value
    const who = voter.value
    const accountId = account.value?.id
    update((draft) => {
      draft.voting.ballots.push({ teamId, voter: who, at: new Date().toISOString() })
      const user = accountId && draft.users.find((item) => item.id === accountId)
      if (user) user.hasVoted = true
    })
    if (!who) {
      localStorage.setItem(VOTE_KEY, '1')
      localVote.value = true
    }
    choice.value = null
  }

  return {
    state,
    teamLabel,
    choice,
    voted,
    open,
    confirm,
    teamName,
  }
}
