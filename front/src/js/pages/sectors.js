// Lógica do componente Sectors.vue (o template fica no .vue).
import { computed, ref, watch } from 'vue'
import { isOperational, sectorScope } from '@/js/config/access'
import { EXPENSE_CATEGORIES, occurrenceSector, occurrenceStatus, SETORES, uid } from '@/js/data/model'
import { useHack, go } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useSectors(props) {
  const { state, update, flash } = useHack()

  const COPY = {
    'Recursos Humanos': 'Integrantes da organização, funções, responsáveis, participantes e apoio.',
    Finanças: 'Orçamento, receitas, despesas, saldo, fornecedores e comprovantes.',
    Marketing: 'Campanhas, conteúdos, materiais, canais e cronograma.',
    Tecnologia: 'Equipamentos, infraestrutura, suporte e ocorrências técnicas.',
    Produção: 'Espaços, materiais, estrutura, alimentação e logística.',
  }

  const SUB = {
    'Recursos Humanos': [
      { id: 'integrantes', label: 'Integrantes' },
      { id: 'funcoes', label: 'Funções' },
      { id: 'responsaveis', label: 'Responsáveis' },
    ],
    Finanças: [
      { id: 'mov', label: 'Movimentações' },
      { id: 'fornecedores', label: 'Fornecedores' },
      { id: 'comprovantes', label: 'Comprovantes' },
    ],
    Marketing: [
      { id: 'campanhas', label: 'Campanhas' },
      { id: 'conteudos', label: 'Conteúdos e Materiais' },
      { id: 'cronograma', label: 'Cronograma' },
    ],
    Tecnologia: [
      { id: 'equipamentos', label: 'Equipamentos' },
      { id: 'infra', label: 'Infraestrutura' },
      { id: 'suporte', label: 'Suporte' },
    ],
    Produção: [
      { id: 'espacos', label: 'Espaços' },
      { id: 'materiais', label: 'Materiais' },
      { id: 'operacao', label: 'Operação' },
    ],
  }

  const EQUIP_STATUS = ['Disponível', 'Em uso', 'Com problema', 'Em manutenção', 'Indisponível']
  const ITEM_STATUS = ['Não iniciado', 'Em andamento', 'Concluído', 'Com problema']
  const CHANNELS = ['Instagram', 'WhatsApp', 'E-mail', 'Site', 'Cartazes', 'Comunicação interna', 'Outros']
  const EQUIP_CATEGORIES = ['Computador', 'Notebook', 'Projetor', 'Tela', 'Áudio', 'Vídeo', 'Cabo', 'Extensão', 'Rede', 'Outro']
  const PRIORITIES = ['Baixa', 'Média', 'Alta', 'Urgente']
  const MOV_KINDS = ['Todos', 'Receitas', 'Despesas']
  const CONTENT_KINDS = ['Todos', 'Conteúdos', 'Materiais']

  const NEW_TITLES = {
    membro: 'Novo integrante',
    mov: 'Nova movimentação',
    fornecedor: 'Novo fornecedor',
    comprovante: 'Adicionar comprovante',
    campanha: 'Nova campanha',
    conteudo: 'Novo item',
    equip: 'Novo equipamento',
    espaco: 'Novo espaço',
    material: 'Novo material',
    infra: 'Novo item',
    operacao: 'Novo item',
  }

  const EDIT_TITLES = {
    membro: 'Editar integrante',
    mov: 'Editar movimentação',
    fornecedor: 'Editar fornecedor',
    comprovante: 'Editar comprovante',
    campanha: 'Editar campanha',
    conteudo: 'Editar item',
    equip: 'Editar equipamento',
    espaco: 'Editar espaço',
    material: 'Editar material',
    infra: 'Editar item',
    operacao: 'Editar item',
  }

  function brl(value) {
    if (value === '' || value == null) return '—'
    const number = Number(value)
    if (Number.isNaN(number)) return '—'
    return number.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
  }

  function matches(text, query) {
    return String(text || '').toLowerCase().includes(query.trim().toLowerCase())
  }

  function sectorPulse(name) {
    const open = state.tasks.filter((task) => task.sector === name && task.status !== 'Concluído').length
    if (open) return { label: 'Com pendências', count: open }
    const started = {
      'Recursos Humanos': state.orgMembers.length,
      Finanças: state.incomes.length + state.expenses.length + (state.suppliers || []).length,
      Marketing: state.campaigns.length + (state.contents || []).length,
      Tecnologia: state.equipment.length + (state.infra || []).length + (state.occurrences || []).filter((item) => occurrenceSector(item) === 'Tecnologia').length,
      Produção: state.spaces.length + (state.materials || []).length + (state.operations || []).length,
    }[name]
    return started ? { label: 'Em andamento', count: open } : { label: 'Não iniciado', count: 0 }
  }

  function leadOf(name) {
    return state.orgMembers.find((item) => item.sector === name)?.name || '—'
  }

  const scope = computed(() => sectorScope(state.session))
  // Editor registra e atualiza informações do setor, mas não exclui registros nem administra integrantes.
  const operational = computed(() => isOperational(state.session))
  const opened = computed(() => {
    const wanted = SETORES.includes(props.params.setor) ? props.params.setor : ''
    if (!scope.value) return wanted
    if (wanted && scope.value.includes(wanted)) return wanted
    return scope.value.length === 1 ? scope.value[0] : ''
  })
  const sector = computed(() => opened.value || 'Recursos Humanos')
  const view = ref(SUB[sector.value][0].id)
  const modal = ref(null)
  const form = ref({})
  const query = ref('')
  const status = ref('')
  const kind = ref('Todos')
  const detail = ref(null)
  const removing = ref(null)

  watch(sector, (name) => {
    view.value = SUB[name][0].id
    query.value = ''
    status.value = ''
    kind.value = 'Todos'
  })

  const pendencias = computed(() => state.tasks.filter((task) => task.sector === sector.value && task.status !== 'Concluído'))
  const knownExpenses = computed(() => state.expenses.filter((item) => (item.actual !== '' && item.actual != null) || (item.planned !== '' && item.planned != null)))
  const sectorOccurrences = computed(() => (state.occurrences || []).filter((item) => occurrenceSector(item) === sector.value))
  const openSectorOccurrences = computed(() => sectorOccurrences.value.filter((item) => occurrenceStatus(item) !== 'Resolvida').length)
  const suppliers = computed(() => state.suppliers || [])
  const contents = computed(() => state.contents || [])
  const materials = computed(() => state.materials || [])
  const receipts = computed(() => state.documents.filter((doc) => doc.category === 'Finanças'))
  const infraList = computed(() => state.infra || [])
  const operations = computed(() => state.operations || [])
  const members = computed(() => state.orgMembers.filter((item) => matches(`${item.name} ${item.func} ${item.profile}`, query.value) && (!status.value || item.status === status.value)))
  const roleMembers = computed(() => state.orgMembers.filter((item) => item.func))
  const movements = computed(() => [
    ...state.incomes.map((item) => ({ ...item, tipo: 'Receita', valor: item.value })),
    ...state.expenses.map((item) => ({ ...item, tipo: 'Despesa', valor: item.actual !== '' && item.actual != null ? item.actual : item.planned })),
  ].filter((item) => matches(item.description, query.value) && (!status.value || item.status === status.value)))
  const typed = computed(() => movements.value.filter((item) => kind.value === 'Todos' || (kind.value === 'Receitas' && item.tipo === 'Receita') || (kind.value === 'Despesas' && item.tipo === 'Despesa')))
  const visibleContents = computed(() => contents.value.filter((item) => kind.value === 'Todos' || (kind.value === 'Conteúdos' && item.kind !== 'Material') || (kind.value === 'Materiais' && item.kind === 'Material')))
  const schedule = computed(() => [
    ...state.campaigns.map((item) => ({ ...item, source: 'campanha' })),
    ...contents.value.map((item) => ({ ...item, source: 'conteudo' })),
  ].sort((a, b) => String(a.date || '9999').localeCompare(String(b.date || '9999'))))
  const expenseChips = computed(() => EXPENSE_CATEGORIES.map((category) => {
    const total = state.expenses.filter((item) => item.category === category).reduce((sum, item) => sum + Number(item.actual || item.planned || 0), 0)
    return total ? { category, total } : null
  }).filter(Boolean))
  const mine = computed(() => {
    if (state.session?.sectors?.length) return state.session.sectors
    if (state.session?.profile === 'Editor' && state.session.sector) return [state.session.sector]
    return []
  })
  const mineSet = computed(() => new Set(mine.value.filter((name) => SETORES.includes(name))))
  const primarySectors = computed(() => scope.value || (mineSet.value.size ? SETORES.filter((name) => mineSet.value.has(name)) : SETORES))
  const otherSectors = computed(() => (scope.value ? [] : mineSet.value.size ? SETORES.filter((name) => !mineSet.value.has(name)) : []))
  const modalTitle = computed(() => (form.value.id ? EDIT_TITLES : NEW_TITLES)[modal.value])

  function reportOccurrence(extra = {}) {
    const query = new URLSearchParams({ novo: '1', setor: sector.value, categoria: sector.value === 'Tecnologia' ? 'Tecnologia' : 'Infraestrutura', ...extra })
    go(`ocorrencias?${query.toString()}`)
  }

  function selectSector(name) {
    go(`setores?setor=${encodeURIComponent(name)}`)
  }

  function open(type, seed) {
    form.value = seed
    modal.value = type
  }

  function save() {
    const type = modal.value
    const current = form.value
    if ((type === 'membro' || type === 'campanha' || type === 'equip' || type === 'espaco' || type === 'material' || type === 'fornecedor' || type === 'conteudo' || type === 'comprovante' || type === 'infra' || type === 'operacao') && !String(current.name || current.title || '').trim()) {
      flash('Informe o nome para salvar.', 'err')
      return
    }
    if (type === 'mov' && !String(current.description || '').trim()) {
      flash('Informe a descrição da movimentação.', 'err')
      return
    }
    update((draft) => {
      const place = (list, record) => {
        const index = record.id ? list.findIndex((item) => item.id === record.id) : -1
        if (index >= 0) list[index] = { ...list[index], ...record }
        else list.push(record)
      }
      if (type === 'membro') place(draft.orgMembers, { ...current, id: current.id || uid('org') })
      if (type === 'mov') {
        if (current.id) {
          draft.incomes = draft.incomes.filter((item) => item.id !== current.id)
          draft.expenses = draft.expenses.filter((item) => item.id !== current.id)
        }
        const id = current.id || uid(current.kind === 'Despesa' ? 'desp' : 'rec')
        if (current.kind === 'Despesa') {
          draft.expenses.push({
            id,
            description: current.description,
            category: current.category,
            supplier: current.supplier,
            responsible: current.responsible,
            planned: current.value === '' ? '' : Number(current.value),
            actual: current.value === '' ? '' : Number(current.value),
            date: current.date,
            status: current.status,
            notes: current.notes,
          })
        } else {
          draft.incomes.push({
            id,
            description: current.description,
            category: current.category,
            origin: current.origin,
            responsible: current.responsible,
            value: current.value === '' ? '' : Number(current.value),
            date: current.date,
            status: current.status,
            notes: current.notes,
          })
        }
      }
      if (type === 'fornecedor') {
        if (!draft.suppliers) draft.suppliers = []
        place(draft.suppliers, { id: current.id || uid('forn'), name: current.name, category: current.category, contact: current.contact, status: current.status })
      }
      if (type === 'comprovante') {
        const record = {
          name: current.name,
          category: 'Finanças',
          sector: 'Finanças',
          responsible: current.responsible || '',
          description: current.description || '',
          version: current.version || '1.0',
          note: current.note || 'Comprovante demonstrativo',
          fileName: current.fileName || '',
        }
        const index = current.id ? draft.documents.findIndex((item) => item.id === current.id) : -1
        if (index >= 0) draft.documents[index] = { ...draft.documents[index], ...record }
        else draft.documents.unshift({ ...record, id: uid('doc'), date: new Date().toLocaleDateString('pt-BR'), history: [{ version: '1.0', date: new Date().toLocaleDateString('pt-BR'), responsible: current.responsible || '—', note: 'Comprovante demonstrativo' }] })
      }
      if (type === 'campanha') place(draft.campaigns, { ...current, id: current.id || uid('camp') })
      if (type === 'conteudo') {
        if (!draft.contents) draft.contents = []
        place(draft.contents, { id: current.id || uid('cont'), name: current.name, kind: current.kind, channel: current.channel, responsible: current.responsible, date: current.date, status: current.status })
      }
      if (type === 'equip') place(draft.equipment, { ...current, id: current.id || uid('eq'), qty: Number(current.qty || 1) })
      if (type === 'espaco') place(draft.spaces, { ...current, id: current.id || uid('sp') })
      if (type === 'material') {
        if (!draft.materials) draft.materials = []
        place(draft.materials, { id: current.id || uid('mat'), name: current.name, needed: current.needed, available: current.available, status: current.status })
      }
      if (type === 'infra') {
        if (!draft.infra) draft.infra = []
        place(draft.infra, { id: current.id || uid('inf'), name: current.name, status: current.status, responsible: current.responsible || '', notes: current.notes || '' })
      }
      if (type === 'operacao') {
        if (!draft.operations) draft.operations = []
        place(draft.operations, { id: current.id || uid('op'), name: current.name, description: current.description || '', status: current.status })
      }
    })
    flash(current.id ? 'Alterações salvas.' : 'Registro salvo neste navegador.')
    modal.value = null
  }

  function confirmRemove() {
    if (!removing.value) return
    const target = removing.value
    update((draft) => {
      if (target.list === 'mov') {
        draft.incomes = draft.incomes.filter((item) => item.id !== target.id)
        draft.expenses = draft.expenses.filter((item) => item.id !== target.id)
        return
      }
      draft[target.list] = (draft[target.list] || []).filter((item) => item.id !== target.id)
    })
    removing.value = null
    flash('Registro excluído.')
  }

  function editSchedule(item) {
    const { source, ...rest } = item
    open(source, rest)
  }

  return {
    state,
    COPY,
    SUB,
    EQUIP_STATUS,
    ITEM_STATUS,
    CHANNELS,
    EQUIP_CATEGORIES,
    MOV_KINDS,
    CONTENT_KINDS,
    brl,
    sectorPulse,
    leadOf,
    scope,
    operational,
    opened,
    sector,
    view,
    modal,
    form,
    query,
    status,
    kind,
    detail,
    removing,
    pendencias,
    knownExpenses,
    sectorOccurrences,
    openSectorOccurrences,
    suppliers,
    contents,
    materials,
    receipts,
    infraList,
    operations,
    members,
    roleMembers,
    typed,
    visibleContents,
    schedule,
    expenseChips,
    mineSet,
    primarySectors,
    otherSectors,
    modalTitle,
    reportOccurrence,
    selectSector,
    open,
    save,
    confirmRemove,
    editSchedule,
    EXPENSE_CATEGORIES,
    go,
    toneFor,
  }
}
