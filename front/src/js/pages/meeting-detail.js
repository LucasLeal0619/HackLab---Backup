// Lógica do componente MeetingDetail.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useHack, go } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useMeetingDetail(props) {
  const PRESENCE = ['Presente', 'Ausente', 'Não informado']


  const { state, update, flash } = useHack()

  const meeting = computed(() => state.meetings.find((item) => item.id === props.params.id) || state.meetings[0])
  const initial = state.meetings.find((item) => item.id === props.params.id) || state.meetings[0]
  const ataForm = ref(initial?.ata
    ? { ...initial.ata }
    : {
      number: String(state.meetings.length).padStart(2, '0'),
      discussed: '',
      decisions: '',
      forwards: '',
      observations: '',
    })
  const presence = ref(false)
  const people = computed(() => {
    const current = meeting.value
    if (!current) return []
    return state.users.filter((user) => current.participantIds?.includes(user.id))
  })

  function publish() {
    const currentMeeting = meeting.value
    if (!currentMeeting) return
    const snapshot = { ...ataForm.value }
    update((draft) => {
      const current = draft.meetings.find((item) => item.id === currentMeeting.id)
      current.ata = {
        ...snapshot,
        status: 'Aguardando manifestações',
        manifestations: current.ata?.manifestations || [],
        versions: [{ version: '1.0', date: new Date().toLocaleDateString('pt-BR'), responsible: state.session?.name, change: 'Versão disponibilizada' }],
      }
      current.status = 'Aguardando manifestações'
    })
    flash('Ata disponibilizada para manifestação.')
  }

  function markPresence(userId, option) {
    update((draft) => {
      const current = draft.meetings.find((item) => item.id === meeting.value.id)
      current.presence[userId] = option
    })
  }

  return {
    PRESENCE,
    state,
    meeting,
    ataForm,
    presence,
    people,
    publish,
    markPresence,
    go,
    toneFor,
  }
}
