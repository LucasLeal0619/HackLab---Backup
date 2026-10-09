// Lógica do componente Shell.vue (o template fica no .vue).
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { navFor, navState } from '@/js/config/access'
import { go, useHack } from '@/js/stores/hack'

export function useShell(props) {
  const hack = useHack()
  const open = ref(false)
  const panel = ref('')
  const expanded = ref([])

  function isOpen(id) {
    return expanded.value.includes(id)
  }

  function toggleGroup(id) {
    expanded.value = isOpen(id) ? expanded.value.filter((item) => item !== id) : [...expanded.value, id]
  }

  const current = computed(() => navState(props.path, hack.state.session?.profile))
  const session = computed(() => hack.state.session)
  const alertCount = computed(() => (
    hack.state.tasks.filter((item) => item.status !== 'Concluído').length
    + hack.state.occurrences.filter((item) => item.status !== 'Resolvida').length
  ))

  const alertItems = computed(() => [
    ...hack.state.equipment.filter((item) => item.status === 'Com problema').map((item) => `Equipamento com problema · ${item.name} · ${item.place}`),
    ...hack.state.occurrences.filter((item) => item.priority === 'Urgente' && item.status !== 'Resolvida').map((item) => `Ocorrência urgente · ${item.title} · ${item.place}`),
    ...hack.state.occurrences.filter((item) => item.status !== 'Resolvida' && /sala|estrutura|material/i.test(`${item.category} ${item.place}`) && item.priority !== 'Urgente').map((item) => `Sala com problema · ${item.title} · ${item.place || '—'}`),
    ...(hack.state.students.length && hack.state.students.some((student) => !hack.state.checkins.some((item) => item.personId === student.id && item.status === 'Presente'))
      ? [`Presença pendente · ${hack.state.students.filter((student) => !hack.state.checkins.some((item) => item.personId === student.id && item.status === 'Presente')).length} participante(s) sem registro`]
      : []),
    ...hack.state.tasks.filter((item) => item.status !== 'Concluído').map((item) => `Pendência · ${item.title}`),
  ])

  const spacingOptions = [['padrao', 'Padrão'], ['confortavel', 'Confortável'], ['amplo', 'Amplo']]

  watch(() => props.path, () => {
    panel.value = ''
    open.value = false
    const parent = current.value.group
    if (parent && !expanded.value.includes(parent)) expanded.value = [...expanded.value, parent]
  }, { immediate: true })

  function onPointer(event) {
    if (!panel.value) return
    if (event.target.closest('.top-tools')) return
    panel.value = ''
  }

  function onKey(event) {
    if (event.key !== 'Escape') return
    if (panel.value) panel.value = ''
    else if (open.value) open.value = false
  }

  onMounted(() => {
    document.addEventListener('mousedown', onPointer)
    document.addEventListener('keydown', onKey)
  })
  onUnmounted(() => {
    document.removeEventListener('mousedown', onPointer)
    document.removeEventListener('keydown', onKey)
  })

  return {
    hack,
    open,
    panel,
    isOpen,
    toggleGroup,
    current,
    session,
    alertCount,
    alertItems,
    spacingOptions,
    navFor,
    go,
  }
}
