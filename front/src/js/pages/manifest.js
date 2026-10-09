// Lógica do componente Manifest.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useHack, go } from '@/js/stores/hack'

export function useManifest(props) {
  const options = [
    ['De acordo', 'acordo', 'Li e estou de acordo com o conteúdo desta ata.'],
    ['Com observação', 'obs', 'Com observação ou discordância. O campo de observação aparece nesta mesma tela.'],
    ['Ciente', 'ciente', 'Li e estou ciente do conteúdo, sem manifestação de concordância ou discordância.'],
  ]


  const { state, update, flash } = useHack()
  const type = ref('')
  const note = ref('')
  const ask = ref(false)
  const meeting = computed(() => state.meetings.find((item) => item.id === props.params.id))
  const total = computed(() => meeting.value?.participantIds?.length || 0)
  const done = computed(() => meeting.value?.ata?.manifestations?.length || 0)

  function manifestLabel(value) {
    return value === 'Com observação' ? 'Com observação ou discordância' : value
  }

  function confirmManifest() {
    const currentType = type.value
    const currentNote = note.value
    const meetingId = meeting.value.id
    update((draft) => {
      const current = draft.meetings.find((item) => item.id === meetingId)
      current.ata.manifestations.push({
        userId: 'session',
        name: state.session?.name || 'Usuário',
        type: currentType,
        note: currentNote,
        at: new Date().toLocaleString('pt-BR'),
      })
    })
    ask.value = false
    type.value = ''
    note.value = ''
    flash('Manifestação registrada.')
  }

  return {
    options,
    type,
    note,
    ask,
    meeting,
    total,
    done,
    manifestLabel,
    confirmManifest,
    go,
  }
}
