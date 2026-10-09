// Lógica do componente People.vue (o template fica no .vue).
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { availabilityOf, isAvailable, teamName, TURMAS, uid } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'

export function usePeople() {
  const blank = { name: '', turma: '', email: '', matricula: '', note: '', availability: 'Disponível' }
  const { state, update, flash } = useHack()
  const audit = useAudit()
  const query = ref('')
  const turma = ref('Todas')
  const statusFilter = ref('Todas')
  const equipe = ref('Todas')
  const askConfirm = ref(false)
  const askReopen = ref(false)
  const modal = ref(false)
  const viewing = ref(null)
  const menu = ref('')
  const removing = ref(null)
  const form = ref({ ...blank })

  const placedIds = computed(() => {
    const ids = new Set()
    state.teams.forEach((team) => {
      if (team.members.length) team.members.forEach((id) => ids.add(id))
    })
    return ids
  })

  function teamOf(id) {
    return state.teams.find((team) => team.members.includes(id))
  }

  function closeMenu() { menu.value = '' }
  watch(menu, (value) => {
    if (!value) return
    document.addEventListener('mousedown', closeMenu)
  }, { flush: 'sync' })
  onUnmounted(() => document.removeEventListener('mousedown', closeMenu))
  onMounted(() => {})

  const rows = computed(() => state.students.filter((student) => {
    const matches = student.name.toLowerCase().includes(query.value.toLowerCase())
    const turmaOk = turma.value === 'Todas' || student.turma === turma.value
    const statusOk = statusFilter.value === 'Todas' || availabilityOf(student) === statusFilter.value
    const team = teamOf(student.id)
    const equipeOk = equipe.value === 'Todas' || (equipe.value === 'Sem equipe' && !team) || (team && teamName(team.id) === equipe.value)
    return matches && turmaOk && statusOk && equipeOk
  }))

  const registered = computed(() => state.students.length)

  function openCreate() {
    form.value = { ...blank }
    modal.value = true
  }

  function save(event) {
    event?.preventDefault?.()
    if (!form.value.name.trim() || !form.value.turma) {
      flash('Informe o nome e a turma.', 'err')
      return
    }
    update((draft) => {
      if (form.value.id) {
        const index = draft.students.findIndex((item) => item.id === form.value.id)
        if (index >= 0) {
          const before = draft.students[index]
          const after = { ...before, ...form.value, name: form.value.name.trim(), availability: form.value.availability || 'Disponível' }
          draft.students[index] = after
          audit.record(draft, { action: 'participant.updated', label: 'Editou participante', module: 'participants', entityType: 'Participante', entityId: after.id, entityLabel: after.name, description: `Participante ${after.name}.`, changes: [{ field: 'Nome', before: before.name, after: after.name }, { field: 'Turma', before: before.turma, after: after.turma }, { field: 'Situação', before: before.availability || 'Disponível', after: after.availability }] })
        }
      } else {
        const created = { ...form.value, id: uid('alu'), name: form.value.name.trim(), availability: form.value.availability || 'Disponível' }
        draft.students.push(created)
        audit.record(draft, { action: 'participant.created', label: 'Cadastrou participante', module: 'participants', entityType: 'Participante', entityId: created.id, entityLabel: created.name, description: `Cadastrou ${created.name} (turma ${created.turma}).` })
      }
    })
    const previous = form.value.id ? state.students.find((student) => student.id === form.value.id) : null
    const leaving = previous && isAvailable(previous) && (form.value.availability || 'Disponível') !== 'Disponível' && teamOf(form.value.id)
    const editing = Boolean(form.value.id)
    modal.value = false
    form.value = { ...blank }
    flash(leaving
      ? 'A composição das equipes foi alterada porque um participante ficou indisponível.'
      : (editing ? 'Participante atualizado.' : 'Participante cadastrado.'))
  }

  function removeStudent() {
    if (!removing.value) return
    update((draft) => {
      draft.students = draft.students.filter((item) => item.id !== removing.value.id)
      draft.teams.forEach((team) => {
        if (!team.members.includes(removing.value.id)) return
        team.members = team.members.filter((id) => id !== removing.value.id)
        team.status = team.members.length ? 'em-montagem' : 'nao-formada'
      })
      draft.checkins = draft.checkins.filter((item) => item.personId !== removing.value.id)
    })
    removing.value = null
    viewing.value = null
    menu.value = ''
    flash('Participante excluído.')
  }

  function setAvailability(student, availability) {
    const leaving = isAvailable(student) && availability !== 'Disponível' && teamOf(student.id)
    update((draft) => {
      const current = draft.students.find((item) => item.id === student.id)
      if (!current) return
      audit.record(draft, { action: 'participant.status', label: 'Alterou situação do participante', module: 'participants', entityType: 'Participante', entityId: current.id, entityLabel: current.name, description: `Participante ${current.name}.`, changes: [{ field: 'Situação', before: current.availability || 'Disponível', after: availability }] })
      current.availability = availability
    })
    menu.value = ''
    flash(leaving
      ? 'A composição das equipes foi alterada porque um participante ficou indisponível.'
      : 'Situação do participante atualizada.')
  }

  function confirmList() {
    if (!state.students.some(isAvailable)) {
      flash('Cadastre ao menos um participante disponível antes de confirmar a lista.', 'err')
      return
    }
    update((draft) => { draft.participantsConfirmed = true })
    askConfirm.value = false
    flash('Lista de participantes confirmada.')
  }

  function reopenList() {
    update((draft) => { draft.participantsConfirmed = false })
    askReopen.value = false
    flash('Lista de participantes reaberta.')
  }

  function noteFor(student) {
    const availability = availabilityOf(student)
    if (availability === 'Disponível') return placedIds.value.has(student.id) ? 'Pode participar e já está em uma equipe.' : 'Pode participar normalmente.'
    if (availability === 'Indisponível') return 'Está cadastrado, mas não participará naquele momento.'
    return 'Não participará mais do Hackathon.'
  }

  return {
    state,
    query,
    turma,
    statusFilter,
    equipe,
    askConfirm,
    askReopen,
    modal,
    viewing,
    menu,
    removing,
    form,
    teamOf,
    rows,
    registered,
    openCreate,
    save,
    removeStudent,
    setAvailability,
    confirmList,
    reopenList,
    noteFor,
    availabilityOf,
    isAvailable,
    teamName,
    TURMAS,
    go,
    blank,
  }
}
