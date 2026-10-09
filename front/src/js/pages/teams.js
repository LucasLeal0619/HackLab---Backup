// Lógica do componente Teams.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { activeMembers, balanceLabel, isAvailable, memberCounts, pausedMembers, suggestTeams, teamName, TURMAS } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'

export function useTeams() {
  function countText(value, singular, plural) {
    return `${value} ${value === 1 ? singular : plural}`
  }

  const { state, update, flash } = useHack()
  const audit = useAudit()
  const open = ref(false)
  const replace = ref(false)
  const available = computed(() => state.students.filter(isAvailable))
  const changed = computed(() => state.teams.some((team) => pausedMembers(team, state.students).length))
  const size = computed(() => state.teamSize || 6)

  function applySuggestion() {
    const next = suggestTeams(state.students, size.value, { vary: true })
    if (!next.length) {
      flash('Nenhum participante disponível para formar equipes.', 'err')
      return
    }
    update((draft) => {
      draft.teams = next
      audit.record(draft, { action: 'team.generated', label: 'Gerou equipes', module: 'teams', entityType: 'Equipes', entityLabel: `${next.length} equipes`, description: `Formação automática de ${next.length} equipes com tamanho de referência ${size.value}.` })
    })
    open.value = false
    replace.value = false
    flash('Nova sugestão criada. A divisão das turmas mudou e todo mundo disponível entrou.')
  }

  function askGenerate() {
    if (state.teams.some((team) => (team.members || []).length)) replace.value = true
    else applySuggestion()
  }

  function mountManual() {
    if (state.teams.length) {
      open.value = false
      go(`montar?id=${state.teams[0].id}`)
      return
    }
    const numbers = state.teams.map((team) => Number(team.id)).filter((value) => Number.isFinite(value))
    const id = (numbers.length ? Math.max(...numbers) : 0) + 1
    update((draft) => {
      draft.teams.push({ id, status: 'em-montagem', members: [], solution: '' })
      audit.record(draft, { action: 'team.created', label: 'Criou equipe', module: 'teams', entityType: 'Equipe', entityId: String(id), entityLabel: teamName(id), description: `Criou a ${teamName(id)} para montagem manual.` })
    })
    open.value = false
    go(`montar?id=${id}`)
  }

  return {
    countText,
    state,
    update,
    open,
    replace,
    available,
    changed,
    size,
    applySuggestion,
    askGenerate,
    mountManual,
    activeMembers,
    balanceLabel,
    memberCounts,
    pausedMembers,
    teamName,
    TURMAS,
    go,
  }
}
