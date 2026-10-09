// Lógica do componente Awards.vue (o template fica no .vue).
import { ref } from 'vue'
import { teamName, uid } from '@/js/data/model'
import { useHack, go } from '@/js/stores/hack'

export function useAwards() {
  const { state, update, flash } = useHack()
  const modal = ref(false)
  const form = ref({ name: '', description: '', team: '', criterion: 'Resultado dos Jurados' })
  const removing = ref(null)

  function save() {
    if (!form.value.name.trim()) return flash('Informe o nome.', 'err')
    const editing = form.value.id
    const record = { ...form.value }
    update((draft) => {
      const index = editing ? draft.awards.findIndex((item) => item.id === editing) : -1
      if (index >= 0) draft.awards[index] = { ...draft.awards[index], ...record }
      else draft.awards.push({ id: uid('pre'), ...record })
    })
    modal.value = false
    flash(editing ? 'Alterações salvas.' : 'Premiação salva.')
  }

  function removeAward() {
    const id = removing.value.id
    update((draft) => {
      draft.awards = draft.awards.filter((item) => item.id !== id)
    })
    removing.value = null
    flash('Premiação excluída.')
  }

  return {
    state,
    modal,
    form,
    removing,
    save,
    removeAward,
    teamName,
    go,
  }
}
