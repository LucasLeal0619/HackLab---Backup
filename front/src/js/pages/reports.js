// Lógica do componente Reports.vue (o template fica no .vue).
import { onUnmounted, reactive, ref } from 'vue'
import { REPORT_MODELS, generateReport } from '@/js/services/reports'
import { useHack } from '@/js/stores/hack'

export function useReports() {
  const { state, flash } = useHack()
  const busy = reactive({})
  const ready = reactive({})
  const preview = ref(null)

  function save(result) {
    const link = document.createElement('a')
    link.href = result.url
    link.download = result.fileName
    document.body.appendChild(link)
    link.click()
    link.remove()
  }

  async function build(id, mode) {
    busy[id] = mode
    try {
      const result = await generateReport(id, state)
      if (ready[id]) URL.revokeObjectURL(ready[id].url)
      ready[id] = { ...result, url: URL.createObjectURL(result.blob) }
      return ready[id]
    } catch (error) {
      console.error(error)
      flash('Não foi possível gerar o relatório. Tente novamente.', 'err')
      return null
    } finally {
      busy[id] = ''
    }
  }

  async function view(id) {
    const result = await build(id, 'preview')
    if (result) preview.value = { id, ...result }
  }

  async function exportPdf(id) {
    const result = ready[id] || (await build(id, 'pdf'))
    if (result) save(result)
  }

  onUnmounted(() => Object.values(ready).forEach((item) => URL.revokeObjectURL(item.url)))

  return {
    busy,
    ready,
    preview,
    save,
    view,
    exportPdf,
    REPORT_MODELS,
  }
}
