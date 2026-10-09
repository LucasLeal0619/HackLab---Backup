// Lógica do componente Config.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { useAudit } from '@/js/audit/audit-logger'
import { PROFILE_NAMES, isAdmin, profileConfig } from '@/js/config/access'
import { SETORES, isExternalProfile, uid } from '@/js/data/model'
import { useHack } from '@/js/stores/hack'
import { toneFor } from '@/js/utils/tone'

export function useConfig() {
  // Matriz demonstrativa, na ordem de PROFILE_NAMES:
  // Administrador, Consultor, Gestor, Editor, Validador, Jurado, Votante.
  const MATRIX = [
    ['Dashboard', 'Geral', 'Acompanhamento', 'Do setor', 'Operacional', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Evento (configuração)', 'Permitido', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Participantes e Equipes', 'Permitido', 'Consulta', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Empresas e Desafios', 'Permitido', 'Consulta', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Setores', 'Todos', 'Todos', 'Atribuído (gestão)', 'Atribuído (operação)', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Reuniões', 'Permitido', 'Permitido', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Pendências', 'Todas', 'Todas', 'Do setor', 'Próprias', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Ocorrências', 'Todas', 'Todas', 'Do setor', 'Registrar', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Documentos', 'Todos', 'Todos', 'Do setor', 'Do setor', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Credenciais e Presença', 'Permitido', 'Permitido', 'Sem acesso', 'Sem acesso', 'Permitido', 'Sem acesso', 'Sem acesso'],
    ['Jurados e Votação (gestão)', 'Permitido', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Avaliações', 'Permitido', 'Consulta', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Próprias', 'Sem acesso'],
    ['Resultados', 'Permitido', 'Consulta', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Votação do Público', 'Permitido', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Votar'],
    ['Relatórios', 'Permitido', 'Permitido', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
    ['Usuários e Permissões', 'Permitido', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso', 'Sem acesso'],
  ]

  // Campos do cadastro que cada perfil usa (perfil, setor e função são independentes).
  const USER_FIELDS = {
    Administrador: { sector: false, role: 'optional', roleHint: 'Ex.: Equipe de TI', company: false, note: 'Acesso global ao HackLab. Não exige setor.' },
    Consultor: { sector: 'optional', role: 'optional', roleHint: 'Ex.: Professor / Coordenação', company: false },
    Gestor: { sector: 'required', role: 'required', roleHint: 'Ex.: Líder do setor', company: false, note: 'Administra apenas os setores atribuídos. Não recebe acesso global.' },
    Editor: { sector: 'required', role: 'required', roleHint: 'Ex.: Apoio de logística, Registro audiovisual', company: false, note: 'Visão operacional dos setores atribuídos.' },
    Validador: { sector: false, role: 'optional', roleHint: 'Ex.: Check-in / Controle de acesso', company: false },
    Jurado: { sector: false, role: 'optional', roleHint: 'Ex.: Representante da empresa', company: true },
    Votante: { sector: false, role: false, roleHint: '', company: false },
  }

  const emptyUser = { name: '', email: '', profile: 'Editor', status: 'Ativo', sector: '', sectors: [], role: '', companyId: '', password: '' }

  const { state, update, flash } = useHack()
  const audit = useAudit()
  const admin = computed(() => isAdmin(state.session))
  const dated = ref(Boolean(state.event.date))
  const form = ref({ ...state.event })
  const modal = ref(null)
  const user = ref({ ...emptyUser })
  const query = ref('')
  const profile = ref('Todos')
  const showPassword = ref(false)
  const fields = computed(() => USER_FIELDS[user.value.profile] || USER_FIELDS.Administrador)

  function sectorLabel(item) {
    if (profileConfig(item.profile).sectorScoped) return (item.sectors?.length ? item.sectors : [item.sector]).filter(Boolean).join(', ') || '—'
    if (item.profile === 'Jurado') return state.companies.find((entry) => entry.id === item.companyId)?.name || '—'
    return item.sector || '—'
  }

  // Usuários internos são criados aqui; Jurados e Votantes vêm do cadastro público.
  const INTERNAL_PROFILES = PROFILE_NAMES.filter((name) => !isExternalProfile(name))
  const audience = ref('internos')
  const internalUsers = computed(() => state.users.filter((item) => !isExternalProfile(item.profile)))
  const externalUsers = computed(() => state.users.filter((item) => isExternalProfile(item.profile)))
  const profileOptions = computed(() => (audience.value === 'internos' ? INTERNAL_PROFILES : ['Jurado', 'Votante']))
  const visible = computed(() => (audience.value === 'internos' ? internalUsers.value : externalUsers.value).filter((item) => {
    const text = `${item.name} ${item.email}`.toLowerCase().includes(query.value.toLowerCase())
    return text && (profile.value === 'Todos' || item.profile === profile.value)
  }))

  function setAudience(value) {
    audience.value = value
    profile.value = 'Todos'
  }

  function saveEvent(event) {
    event.preventDefault()
    update((draft) => { draft.event = { ...draft.event, ...form.value, date: dated.value ? form.value.date : '', days: 3, start: '08:00', end: '12:00' } })
    flash('Alterações da configuração salvas neste navegador.')
  }

  function openCreate() {
    user.value = { ...emptyUser, sectors: [] }
    showPassword.value = false
    modal.value = 'user'
  }

  function openEdit(item) {
    user.value = { ...item, password: '', sectors: [...(item.sectors || [])] }
    showPassword.value = false
    modal.value = 'user'
  }

  function saveUser(event) {
    event?.preventDefault?.()
    const password = (user.value.password || '').trim()
    if (!user.value.name.trim() || !user.value.email.trim()) {
      flash('Preencha nome e e-mail.', 'err')
      return
    }
    if (fields.value.sector === 'required' && !(user.value.sectors?.length)) {
      flash(`Selecione ao menos um setor para o perfil ${user.value.profile}.`, 'err')
      return
    }
    if (fields.value.role === 'required' && !String(user.value.role || '').trim()) {
      flash(`Informe a função do ${user.value.profile}.`, 'err')
      return
    }
    if (!user.value.id && password.length < 4) {
      flash('Cadastre uma senha com pelo menos 4 caracteres.', 'err')
      return
    }
    if (user.value.id && password && password.length < 4) {
      flash('A nova senha precisa ter pelo menos 4 caracteres.', 'err')
      return
    }
    update((draft) => {
      const sectors = fields.value.sector === 'required' ? [...user.value.sectors] : []
      if (fields.value.sector === 'required') user.value.sector = sectors[0]
      if (!fields.value.sector) user.value.sector = ''
      if (!fields.value.role) user.value.role = ''
      if (!fields.value.company) user.value.companyId = ''
      if (user.value.id) {
        const index = draft.users.findIndex((item) => item.id === user.value.id)
        if (index >= 0) {
          const current = draft.users[index]
          const next = { ...current, ...user.value, name: user.value.name.trim(), email: user.value.email.trim(), sectors, password: password || current.password || '' }
          draft.users[index] = next
          const sectorOf = (item) => (item.sectors?.length ? item.sectors.join(', ') : item.sector) || '—'
          const changes = [
            { field: 'Perfil', before: current.profile, after: next.profile },
            { field: 'Setor', before: sectorOf(current), after: sectorOf(next) },
            { field: 'Função', before: current.role || '—', after: next.role || '—' },
            { field: 'Status', before: current.status, after: next.status },
          ]
          const label = current.profile !== next.profile ? 'Alterou perfil de usuário' : sectorOf(current) !== sectorOf(next) ? 'Alterou setor de usuário' : 'Editou usuário'
          audit.record(draft, { action: 'user.updated', label, module: 'users', entityType: 'Usuário', entityId: next.id, entityLabel: next.email, description: `Usuário ${next.name}.`, changes })
        }
      } else {
        const created = { ...user.value, id: uid('usr'), name: user.value.name.trim(), email: user.value.email.trim(), sectors, password }
        draft.users.push(created)
        audit.record(draft, { action: 'user.created', label: 'Criou usuário', module: 'users', entityType: 'Usuário', entityId: created.id, entityLabel: created.email, description: `Criou o usuário ${created.name} (${created.profile}${sectors.length ? ` · ${sectors.join(', ')}` : ''}).` })
      }
    })
    modal.value = null
    flash(user.value.id ? 'Alterações do usuário salvas.' : 'Usuário cadastrado.')
  }

  // Ativar/inativar não apaga o cadastro; fica registrado na Auditoria.
  function setUserStatus(target, status) {
    update((draft) => {
      const found = draft.users.find((item) => item.id === target.id)
      if (!found) return
      const before = found.status
      found.status = status
      audit.record(draft, { action: status === 'Ativo' ? 'user.activated' : 'user.deactivated', label: status === 'Ativo' ? 'Ativou usuário' : 'Inativou usuário', module: 'users', entityType: 'Usuário', entityId: found.id, entityLabel: found.email, description: `Usuário ${found.name}.`, changes: [{ field: 'Status', before, after: status }] })
    })
    user.value = { ...user.value, status }
    flash(status === 'Ativo' ? 'Usuário ativado.' : 'Usuário desativado.')
  }

  function toggleSector(item) {
    const on = user.value.sectors?.includes(item)
    user.value = { ...user.value, sectors: on ? user.value.sectors.filter((sector) => sector !== item) : [...(user.value.sectors || []), item] }
  }

  return {
    setUserStatus,
    MATRIX,
    state,
    admin,
    dated,
    form,
    modal,
    user,
    query,
    profile,
    showPassword,
    fields,
    sectorLabel,
    INTERNAL_PROFILES,
    audience,
    externalUsers,
    visible,
    setAudience,
    saveEvent,
    openCreate,
    openEdit,
    saveUser,
    toggleSector,
    PROFILE_NAMES,
    profileConfig,
    SETORES,
    isExternalProfile,
    toneFor,
    profileOptions,
  }
}
