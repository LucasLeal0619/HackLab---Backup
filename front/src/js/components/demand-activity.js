// Colaboração entre setores dentro de uma Pendência ou Ocorrência:
// setores (origem, responsável, envolvidos), histórico, atualizações escritas e encaminhamento.
// Não é um chat genérico: tudo existe só no contexto da demanda.
import { computed, ref } from 'vue'
import { actorFrom, useAudit } from '@/js/audit/audit-logger'
import { canRouteDemands } from '@/js/config/access'
import { DEMAND_KINDS, SETORES, formatMoment, historyEntry, responsibleSector } from '@/js/data/model'
import { useHack } from '@/js/stores/hack'

export function useDemandActivity(props) {
  const { state, update, flash } = useHack()
  const audit = useAudit()
  const meta = computed(() => DEMAND_KINDS[props.kind])
  const item = computed(() => (state[meta.value.list] || []).find((entry) => entry.id === props.id) || null)
  const responsible = computed(() => (item.value ? responsibleSector(item.value, props.kind) : ''))
  const involved = computed(() => (item.value?.involvedSectors || []).filter((sector) => sector !== responsible.value))
  const history = computed(() => [...(item.value?.history || [])].reverse())
  const canRoute = computed(() => canRouteDemands(state.session))
  const message = ref('')
  const forwarding = ref(null)
  const targets = computed(() => SETORES.filter((sector) => sector !== responsible.value))

  function authorLine(entry) {
    return [entry.profile, entry.sector].filter(Boolean).join(' · ')
  }

  function send() {
    const text = message.value.trim()
    if (!text) return
    const actor = actorFrom(state.session)
    update((draft) => {
      const target = draft[meta.value.list].find((entry) => entry.id === props.id)
      if (target) target.history.push(historyEntry(actor, 'comment', text))
    })
    message.value = ''
  }

  function openForward() {
    forwarding.value = { sector: targets.value[0] || '', reason: '' }
  }

  function forward() {
    const current = forwarding.value
    if (!current?.sector) return
    const actor = actorFrom(state.session)
    const label = meta.value.label
    const from = responsible.value
    update((draft) => {
      const target = draft[meta.value.list].find((entry) => entry.id === props.id)
      if (!target) return
      target.sector = current.sector
      // Quem já participava continua acompanhando; o novo responsável passa a enxergar a demanda.
      target.involvedSectors = [...new Set([...(target.involvedSectors || []), target.originSector, from, current.sector].filter(Boolean))]
      const reason = current.reason.trim()
      target.history.push(historyEntry(actor, 'event', `Encaminhou esta ${label} de ${from || 'sem setor'} para ${current.sector}.${reason ? ` Motivo: ${reason}` : ''}`))
      audit.record(draft, {
        action: `${props.kind}.forwarded`,
        label: `Encaminhou ${label}`,
        module: props.kind === 'task' ? 'tasks' : 'occurrences',
        entityType: props.kind === 'task' ? 'Pendência' : 'Ocorrência',
        entityId: target.id,
        entityLabel: target.ref,
        description: `${props.kind === 'task' ? 'Pendência' : 'Ocorrência'} "${target.title}" encaminhada de ${from || 'sem setor'} para ${current.sector}.`,
        changes: [{ field: 'Setor responsável', before: from || '—', after: current.sector }],
        metadata: { reason },
      })
    })
    forwarding.value = null
    flash(`Encaminhada para ${current.sector}.`)
  }

  return {
    item,
    responsible,
    involved,
    history,
    canRoute,
    message,
    forwarding,
    targets,
    authorLine,
    send,
    openForward,
    forward,
    formatMoment,
  }
}
