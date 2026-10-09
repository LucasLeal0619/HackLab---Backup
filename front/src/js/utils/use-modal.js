import { ref } from 'vue'

export function useModal() {
  const modal = ref(null)
  return {
    modal,
    open: (value) => { modal.value = value },
    close: () => { modal.value = null },
  }
}
