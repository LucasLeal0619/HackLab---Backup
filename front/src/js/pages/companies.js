// Lógica do componente Companies.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { CHALLENGE_FLOW, companyOf, teamName, uid } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useCompanies(props) {
  const emptyCompany = {
    name: '', razao: '', cnpj: '', segmento: '', phone: '', email: '', site: '', description: '',
    tipo: 'Empresa participante', status: 'Em cadastro',
    reps: [{ name: '', cargo: '', email: '', phone: '', principal: true }],
  }

  const COMPANY_STATUSES = ['Todos', 'Em cadastro', 'Confirmada', 'Aguardando desafio', 'Com desafio', 'Inativa']
  const CHALLENGE_STATUSES = ['Todos', ...CHALLENGE_FLOW]
  const TIPOS = ['Empresa participante', 'Parceira', 'Patrocinadora', 'Apoio', 'Outro']

  const { state, update, flash } = useHack()
  const audit = useAudit()
  const tab = computed(() => (props.mode === 'desafios' ? 'desafios' : 'empresas'))
  const query = ref('')
  const status = ref('Todos')
  const modal = ref(false)
  const challenge = ref(null)
  const removing = ref(null)

  function blankCompany() {
    return { ...emptyCompany, reps: [{ name: '', cargo: '', email: '', phone: '', principal: true }] }
  }

  function blankChallenge() {
    return { title: '', companyId: state.companies[0]?.id || '', problem: '', objective: '', requirements: '', restrictions: '', expected: '', note: '' }
  }

  const form = ref(blankCompany())

  function openCompany(company) {
    form.value = company
      ? { ...blankCompany(), ...company, reps: company.reps?.length ? company.reps.map((rep) => ({ ...rep })) : blankCompany().reps }
      : blankCompany()
    modal.value = true
  }

  function openChallenge() {
    challenge.value = blankChallenge()
  }

  function editChallenge(item) {
    challenge.value = { ...item, note: item.note || '' }
  }

  function saveCompany(event) {
    event.preventDefault()
    const current = form.value
    const rep = current.reps[0]
    if (!current.name.trim() || !rep?.name.trim()) {
      flash('Informe o nome da empresa e o representante principal.', 'err')
      return
    }
    const reps = current.reps.filter((item) => item.name.trim()).map((item, index) => ({ ...item, id: item.id || uid('rep'), principal: index === 0 }))
    update((draft) => {
      if (current.id) {
        const index = draft.companies.findIndex((item) => item.id === current.id)
        if (index >= 0) {
          const before = draft.companies[index]
          draft.companies[index] = { ...before, ...current, name: current.name.trim(), reps }
          audit.record(draft, { action: 'company.updated', label: 'Editou empresa', module: 'companies', entityType: 'Empresa', entityId: before.id, entityLabel: current.name.trim(), description: `Empresa ${current.name.trim()}.`, changes: [{ field: 'Nome', before: before.name, after: current.name.trim() }, { field: 'Status', before: before.status, after: current.status }] })
        }
      } else {
        const id = uid('emp')
        draft.companies.push({ ...current, id, name: current.name.trim(), reps })
        audit.record(draft, { action: 'company.created', label: 'Cadastrou empresa', module: 'companies', entityType: 'Empresa', entityId: id, entityLabel: current.name.trim(), description: `Cadastrou a empresa ${current.name.trim()}.` })
      }
    })
    modal.value = false
    form.value = blankCompany()
    flash(current.id ? 'Empresa atualizada.' : 'Empresa cadastrada.')
  }

  function saveChallenge(event) {
    event.preventDefault()
    const current = challenge.value
    if (!current.title.trim() || !current.companyId) {
      flash('Informe o título e a empresa.', 'err')
      return
    }
    update((draft) => {
      if (current.id) {
        const index = draft.challenges.findIndex((item) => item.id === current.id)
        if (index >= 0) {
          const before = draft.challenges[index]
          draft.challenges[index] = { ...before, ...current, title: current.title.trim(), updatedAt: new Date().toLocaleString('pt-BR') }
          audit.record(draft, { action: 'challenge.updated', label: 'Editou desafio', module: 'challenges', entityType: 'Desafio', entityId: before.id, entityLabel: current.title.trim(), description: `Desafio ${current.title.trim()}.`, changes: [{ field: 'Título', before: before.title, after: current.title.trim() }, { field: 'Status', before: before.status, after: current.status }] })
        }
      } else {
        const id = uid('des')
        draft.challenges.push({ ...current, id, title: current.title.trim(), status: 'Recebido', teamId: null, updatedAt: new Date().toLocaleString('pt-BR') })
        audit.record(draft, { action: 'challenge.created', label: 'Cadastrou desafio', module: 'challenges', entityType: 'Desafio', entityId: id, entityLabel: current.title.trim(), description: `Cadastrou o desafio ${current.title.trim()}.` })
        const company = draft.companies.find((item) => item.id === current.companyId)
        if (company && company.status === 'Em cadastro') company.status = 'Aguardando desafio'
      }
    })
    challenge.value = null
    flash(current.id ? 'Desafio atualizado.' : 'Desafio recebido.')
  }

  function askRemoveCompany(company) {
    removing.value = {
      kind: 'empresa',
      id: company.id,
      name: company.name,
      challenges: state.challenges.filter((item) => item.companyId === company.id).length,
    }
  }

  function askRemoveChallenge(item) {
    removing.value = { kind: 'desafio', id: item.id, name: item.title }
  }

  function confirmRemove() {
    const current = removing.value
    if (!current) return
    update((draft) => {
      if (current.kind === 'empresa') {
        draft.companies = draft.companies.filter((item) => item.id !== current.id)
        draft.challenges = draft.challenges.filter((item) => item.companyId !== current.id)
        draft.judges = (draft.judges || []).filter((item) => item.companyId !== current.id)
      } else {
        const found = draft.challenges.find((item) => item.id === current.id)
        draft.challenges = draft.challenges.filter((item) => item.id !== current.id)
        const owner = found && draft.companies.find((item) => item.id === found.companyId)
        if (owner && owner.status === 'Com desafio' && !draft.challenges.some((item) => item.companyId === owner.id)) owner.status = 'Aguardando desafio'
      }
    })
    removing.value = null
    flash(current.kind === 'empresa' ? 'Empresa excluída.' : 'Desafio excluído.')
  }

  const companies = computed(() => state.companies.filter((company) => company.name.toLowerCase().includes(query.value.toLowerCase()) && (status.value === 'Todos' || company.status === status.value)))
  const challenges = computed(() => state.challenges.filter((item) => item.title.toLowerCase().includes(query.value.toLowerCase()) && (status.value === 'Todos' || item.status === status.value)))

  return {
    COMPANY_STATUSES,
    CHALLENGE_STATUSES,
    TIPOS,
    state,
    tab,
    query,
    status,
    modal,
    challenge,
    removing,
    form,
    openCompany,
    openChallenge,
    editChallenge,
    saveCompany,
    saveChallenge,
    askRemoveCompany,
    askRemoveChallenge,
    confirmRemove,
    companies,
    challenges,
    companyOf,
    teamName,
    go,
    toneFor,
  }
}
