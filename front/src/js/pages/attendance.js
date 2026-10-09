// Lógica do componente Attendance.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { isAdmin } from '@/js/config/access'
import {
  CREDENTIAL_CATEGORIES, CREDENTIAL_STATUS, EVENT_DAYS, checkCredential, credentialOf, credentialPeople,
  findCredential, personOf, presenceOf, suggestedDays, uid,
} from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'

export function useAttendance(props) {
  const DAY_TABS = EVENT_DAYS.map((day) => ({ id: String(day), label: `Dia ${day}` }))
  const MANUAL_REASONS = ['QR ilegível', 'Credencial não disponível', 'Problema técnico', 'Outro']
  const STATUS_TONE = { Ativa: 'ok', Bloqueada: 'warn', Cancelada: 'danger' }


  const { state, update, flash } = useHack()
  const audit = useAudit()
  // Só o Administrador gerencia credenciais; Validador e Consultor operam validação e presença.
  const manage = computed(() => isAdmin(state.session))
  const validator = computed(() => state.session?.profile === 'Validador')
  const day = computed(() => (EVENT_DAYS.includes(Number(props.params.dia)) ? Number(props.params.dia) : 1))
  const tab = computed(() => (props.params.aba === 'credenciais' && !validator.value ? 'credenciais' : 'presenca'))
  const sectionTabs = computed(() => [{ id: 'presenca', label: 'Presença' }, ...(validator.value ? [] : [{ id: 'credenciais', label: 'Credenciais' }])])

  const query = ref('')
  const category = ref('')
  const status = ref('')
  const authorized = ref('')
  const scan = ref(null)
  const scanCode = ref('')
  const manual = ref(null)
  const detailId = ref(null)
  const form = ref(null)

  function now() {
    return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  }

  function link(next) {
    const merged = { dia: day.value, aba: tab.value, ...next }
    go(`presenca?dia=${merged.dia}&aba=${merged.aba}`)
  }

  function daysLabel(days) {
    const list = [...(days || [])].sort()
    if (list.length === EVENT_DAYS.length) return 'Dias 1, 2 e 3'
    if (!list.length) return 'Nenhum dia'
    return list.length === 1 ? `Dia ${list[0]}` : `Dias ${list.join(' e ')}`
  }

  const rows = computed(() => (state.credentials || []).map((credential) => {
    const person = personOf(state, credential.personId)
    return { credential, person, name: person?.name || 'Pessoa removida', record: presenceOf(state, credential.personId, day.value) }
  }))

  function matches(row) {
    const term = query.value.trim().toLowerCase()
    if (term && !`${row.name} ${row.credential.code}`.toLowerCase().includes(term)) return false
    return !category.value || row.credential.category === category.value
  }

  const credentialRows = computed(() => rows.value
    .filter(matches)
    .filter((row) => !status.value || row.credential.status === status.value)
    .filter((row) => !authorized.value || row.credential.days.includes(Number(authorized.value))))
  // Presença do dia: credenciais autorizadas para o dia selecionado.
  const dayRows = computed(() => rows.value.filter((row) => row.credential.days.includes(day.value)))
  const presenceRows = computed(() => dayRows.value.filter(matches))
  const summary = computed(() => {
    const expected = dayRows.value.filter((row) => row.credential.status === 'Ativa' && (!category.value || row.credential.category === category.value))
    const present = dayRows.value.filter((row) => row.record && (!category.value || row.credential.category === category.value))
    return {
      present: present.length,
      missing: expected.filter((row) => !row.record).length,
      manual: present.filter((row) => row.record.method === 'Manual').length,
      qr: present.filter((row) => row.record.method === 'QR Code').length,
    }
  })
  const detail = computed(() => rows.value.find((row) => row.credential.id === detailId.value) || null)

  // Validação demonstrativa: QR → credencial → status → dia autorizado → duplicidade → presença.
  function openScan() {
    scan.value = { kind: 'idle' }
    scanCode.value = ''
  }

  function simulateRead() {
    const code = scanCode.value.trim()
    if (code) {
      scan.value = checkCredential(state, findCredential(state, code), day.value)
      return
    }
    const next = rows.value.find((row) => checkCredential(state, row.credential, day.value).kind === 'valid')
    scan.value = next ? checkCredential(state, next.credential, day.value) : { kind: 'none' }
  }

  function register(credential, method, extra = {}) {
    const person = personOf(state, credential.personId)
    const targetDay = extra.day || day.value
    update((draft) => {
      draft.checkins.push({
        id: uid('ck'),
        personId: credential.personId,
        credentialId: credential.id,
        personName: person?.name || '',
        category: credential.category,
        day: targetDay,
        method,
        time: extra.time || now(),
        responsible: state.session?.name,
        status: 'Presente',
        note: extra.note || '',
      })
      audit.record(draft, { action: method === 'Manual' ? 'presence.manual' : 'presence.validated', label: method === 'Manual' ? 'Registrou presença manualmente' : 'Validou presença', module: 'presence', entityType: 'Credencial', entityId: credential.id, entityLabel: credential.code, description: `${person?.name || 'Pessoa'} · Dia ${targetDay} · ${method}${extra.note && method === 'Manual' ? ` · ${extra.note}` : ''}.` })
    })
  }

  function confirmScan() {
    const credential = scan.value?.credential
    const result = checkCredential(state, credential, day.value)
    if (result.kind !== 'valid') {
      scan.value = result
      return
    }
    register(credential, 'QR Code', { note: 'Leitura demonstrativa' })
    flash(`Presença registrada no Dia ${day.value}.`)
    scan.value = null
  }

  function searchInstead() {
    scan.value = null
    query.value = scanCode.value.trim()
    link({ aba: 'presenca' })
  }

  function openManual(credential = null) {
    manual.value = { credentialId: credential?.id || '', day: day.value, time: now(), reason: MANUAL_REASONS[0], note: '', error: '' }
  }

  function saveManual() {
    const current = manual.value
    const credential = (state.credentials || []).find((item) => item.id === current.credentialId)
    if (!credential) {
      current.error = 'Selecione a pessoa ou credencial.'
      return
    }
    const result = checkCredential(state, credential, current.day)
    const messages = {
      blocked: 'Esta credencial está bloqueada.',
      cancelled: 'Esta credencial foi cancelada.',
      day: `Esta credencial não possui acesso ao Dia ${current.day}.`,
      already: `Presença já registrada no Dia ${current.day}.`,
    }
    if (result.kind !== 'valid') {
      current.error = messages[result.kind]
      return
    }
    register(credential, 'Manual', { day: Number(current.day), time: current.time, note: [current.reason, current.note].filter(Boolean).join('. ') })
    flash(`Presença registrada manualmente no Dia ${current.day}.`)
    manual.value = null
    if (Number(current.day) !== day.value) link({ dia: current.day })
  }

  // Nova credencial: associa uma pessoa já existente ou cadastra pessoa externa sem conta.
  const availablePeople = computed(() => credentialPeople(state).filter((person) => !credentialOf(state, person.id)))

  function openNew() {
    form.value = { mode: 'existente', personId: '', name: '', email: '', category: 'Convidado', days: suggestedDays('Convidado'), status: 'Ativa', note: '', error: '' }
  }

  function openEdit(credential) {
    form.value = { id: credential.id, mode: 'edicao', personId: credential.personId, category: credential.category, days: [...credential.days], status: credential.status, note: credential.note || '', error: '' }
    detailId.value = null
  }

  function pickPerson(id) {
    const person = availablePeople.value.find((item) => item.id === id)
    form.value.personId = id
    if (person) {
      form.value.category = person.category
      form.value.days = suggestedDays(person.category)
    }
  }

  function setCategory(value) {
    form.value.category = value
    if (!form.value.id) form.value.days = suggestedDays(value)
  }

  function toggleDay(value) {
    const days = form.value.days
    form.value.days = days.includes(value) ? days.filter((item) => item !== value) : [...days, value].sort()
  }

  function saveCredential() {
    const current = form.value
    if (current.mode === 'existente' && !current.personId) return (current.error = 'Selecione a pessoa.')
    if (current.mode === 'externa' && !current.name.trim()) return (current.error = 'Informe o nome da pessoa.')
    if (!current.days.length) return (current.error = 'Autorize pelo menos um dia.')
    const fields = { category: current.category, days: [...current.days].sort(), status: current.status, note: current.note }
    update((draft) => {
      if (current.id) {
        const credential = draft.credentials.find((item) => item.id === current.id)
        const days = (list) => list.map((day) => `Dia ${day}`).join(', ')
        audit.record(draft, { action: 'credential.updated', label: 'Editou credencial', module: 'credentials', entityType: 'Credencial', entityId: credential.id, entityLabel: credential.code, description: `Credencial ${credential.code}.`, changes: [{ field: 'Categoria', before: credential.category, after: fields.category }, { field: 'Dias autorizados', before: days(credential.days), after: days(fields.days) }, { field: 'Status', before: credential.status, after: fields.status }] })
        Object.assign(credential, fields)
        return
      }
      let personId = current.personId
      if (current.mode === 'externa') {
        personId = uid('gst')
        draft.guests.push({ id: personId, name: current.name.trim(), email: current.email.trim(), category: current.category })
      }
      const top = draft.credentials.reduce((max, item) => Math.max(max, Number(String(item.code).replace(/\D/g, '')) || 0), 0)
      const code = `HL-${String(top + 1).padStart(6, '0')}`
      draft.credentials.push({ id: uid('cred'), code, personId, ...fields, createdAt: new Date().toLocaleDateString('pt-BR') })
      audit.record(draft, { action: 'credential.created', label: 'Criou credencial', module: 'credentials', entityType: 'Credencial', entityLabel: code, description: `Credencial ${fields.category} para ${current.mode === 'externa' ? current.name.trim() : personOf(draft, personId)?.name || 'pessoa'} (${fields.days.map((day) => `Dia ${day}`).join(', ')}).` })
    })
    flash(current.id ? 'Credencial atualizada.' : 'Credencial criada.')
    form.value = null
  }

  function setStatus(credential, value) {
    const LABELS = { Bloqueada: 'Bloqueou credencial', Cancelada: 'Cancelou credencial', Ativa: 'Reativou credencial' }
    update((draft) => {
      const found = draft.credentials.find((item) => item.id === credential.id)
      const before = found.status
      found.status = value
      audit.record(draft, { action: `credential.${value === 'Ativa' ? 'reactivated' : value === 'Bloqueada' ? 'blocked' : 'cancelled'}`, label: LABELS[value], module: 'credentials', entityType: 'Credencial', entityId: found.id, entityLabel: found.code, description: `Credencial ${found.code} de ${personOf(draft, found.personId)?.name || 'pessoa'}.`, changes: [{ field: 'Status', before, after: value }] })
    })
    flash(`Credencial ${value.toLowerCase()}.`)
  }

  return {
    DAY_TABS,
    MANUAL_REASONS,
    STATUS_TONE,
    state,
    manage,
    day,
    tab,
    sectionTabs,
    query,
    category,
    status,
    authorized,
    scan,
    scanCode,
    manual,
    detailId,
    form,
    link,
    daysLabel,
    rows,
    credentialRows,
    presenceRows,
    summary,
    detail,
    openScan,
    simulateRead,
    confirmScan,
    searchInstead,
    openManual,
    saveManual,
    availablePeople,
    openNew,
    openEdit,
    pickPerson,
    setCategory,
    toggleDay,
    saveCredential,
    setStatus,
    CREDENTIAL_CATEGORIES,
    CREDENTIAL_STATUS,
    EVENT_DAYS,
    personOf,
    presenceOf,
  }
}
