// Lógica do componente TeamBuild.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { activeMembers, AVAILABILITY, balanceLabel, isAvailable, memberCounts, pausedMembers, reservedIds, teamName, TURMAS } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'

export function useTeamBuild(props) {
  function countText(value, singular, plural) {
    return `${value} ${value === 1 ? singular : plural}`
  }

  function availabilityOfSafe(student) {
    return AVAILABILITY.includes(student.availability) ? student.availability : 'Disponível'
  }

  const { state, update, flash } = useHack()
  const audit = useAudit()
  const query = ref('')
  const turma = ref('Todas')
  const blocked = ref(null)
  const team = computed(() => state.teams.find((item) => String(item.id) === String(props.params.id)) || state.teams[0])
  const taken = computed(() => reservedIds(state.teams, team.value?.id))
  const active = computed(() => (team.value ? activeMembers(team.value, state.students) : []))
  const paused = computed(() => (team.value ? pausedMembers(team.value, state.students) : []))
  const counts = computed(() => (team.value ? memberCounts(team.value, state.students, { onlyAvailable: true }) : {}))
  const pool = computed(() => state.students.filter((student) => {
    if (!team.value) return false
    const matches = student.name.toLowerCase().includes(query.value.toLowerCase())
    const turmaOk = turma.value === 'Todas' || student.turma === turma.value
    return matches && turmaOk && isAvailable(student) && !team.value.members.includes(student.id) && !taken.value.has(student.id)
  }))
  const tabs = computed(() => [{ id: 'Todas', label: 'Todos' }, ...TURMAS.map((item) => ({ id: item.id, label: item.id }))])

  function add(student) {
    if (!isAvailable(student)) {
      flash('Somente participantes disponíveis entram na formação.', 'err')
      return
    }
    if (taken.value.has(student.id)) {
      const owner = state.teams.find((item) => item.id !== team.value.id && item.members.includes(student.id))
      blocked.value = { student, owner }
      return
    }
    if (team.value.members.includes(student.id)) return
    update((draft) => {
      const current = draft.teams.find((item) => item.id === team.value.id)
      current.members.push(student.id)
      if (current.status === 'nao-formada') current.status = 'em-montagem'
      audit.record(draft, { action: 'team.member-added', label: 'Adicionou participante à equipe', module: 'teams', entityType: 'Equipe', entityId: String(current.id), entityLabel: teamName(current.id), description: `${student.name} entrou na ${teamName(current.id)}.` })
    })
  }

  function remove(studentId) {
    update((draft) => {
      const current = draft.teams.find((item) => item.id === team.value.id)
      current.members = current.members.filter((item) => item !== studentId)
      const student = draft.students.find((item) => item.id === studentId)
      audit.record(draft, { action: 'team.member-removed', label: 'Removeu participante da equipe', module: 'teams', entityType: 'Equipe', entityId: String(current.id), entityLabel: teamName(current.id), description: `${student?.name || 'Participante'} saiu da ${teamName(current.id)}.` })
    })
  }

  function transfer() {
    if (!blocked.value?.owner) return
    const student = blocked.value.student
    update((draft) => {
      const from = draft.teams.find((item) => item.id === blocked.value.owner.id)
      const to = draft.teams.find((item) => item.id === team.value.id)
      if (from) from.members = from.members.filter((item) => item !== student.id)
      if (to && !to.members.includes(student.id)) to.members.push(student.id)
      audit.record(draft, { action: 'team.member-moved', label: 'Moveu participante', module: 'teams', entityType: 'Equipe', entityId: String(to?.id || ''), entityLabel: teamName(to?.id), description: `${student.name} foi transferido entre equipes.`, changes: [{ field: 'Equipe', before: from ? teamName(from.id) : '—', after: teamName(to?.id) }] })
    })
    blocked.value = null
    flash(`${student.name} foi transferido para a ${teamName(team.value.id)}.`)
  }

  return {
    countText,
    availabilityOfSafe,
    state,
    query,
    turma,
    blocked,
    team,
    active,
    paused,
    counts,
    pool,
    tabs,
    add,
    remove,
    transfer,
    balanceLabel,
    teamName,
    go,
  }
}
