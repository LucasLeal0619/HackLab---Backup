// Lógica do componente ChallengeDetail.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { companyOf, teamName } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useChallengeDetail(props) {
  const { state, update, flash } = useHack()
  const challenge = computed(() => state.challenges.find((item) => item.id === props.params.id) || state.challenges[0])
  const company = computed(() => (challenge.value ? companyOf(state, challenge.value.companyId) : null))
  const ask = ref(false)
  const note = ref('')
  const approve = ref(false)
  const assign = ref(false)
  const teamId = ref(1)

  function setStatus(status, extra = {}) {
    update((draft) => {
      const current = draft.challenges.find((item) => item.id === challenge.value.id)
      Object.assign(current, extra, { status, updatedAt: new Date().toLocaleString('pt-BR') })
      if (status === 'Aprovado' || status === 'Distribuído') {
        const owner = draft.companies.find((item) => item.id === current.companyId)
        if (owner) owner.status = 'Com desafio'
      }
    })
  }

  function startAnalysis() {
    setStatus('Em análise')
    flash('Desafio em análise.')
  }

  function registerAsk() {
    setStatus('Em análise', { note: note.value })
    ask.value = false
    flash('Solicitação registrada.')
  }

  function confirmApprove() {
    setStatus('Aprovado')
    approve.value = false
    flash('Desafio aprovado.')
  }

  function confirmAssign() {
    setStatus('Distribuído', { teamId: Number(teamId.value) })
    assign.value = false
    flash(`Desafio associado à ${teamName(teamId.value)}.`)
  }

  return {
    state,
    challenge,
    company,
    ask,
    note,
    approve,
    assign,
    teamId,
    startAnalysis,
    registerAsk,
    confirmApprove,
    confirmAssign,
    teamName,
    go,
    toneFor,
  }
}
