import { inject, reactive, ref, toRaw, watch } from 'vue'
import { appendAudit } from '@/js/audit/audit-logger'
import { homeFor, migrateProfile, profileConfig } from '@/js/config/access'
import { ACCOUNTS, KEY, PUBLIC_CATEGORY, SETORES, buildDemo, defaultState, findInvite, isExternalProfile, normalizeTeams, normalizeDemands, seedDemoCredentials, seedUsers, syncCredentials, uid } from '@/js/data/model'

const STORE = 'hacklab'

function migrateUser(item) {
  const profile = migrateProfile(item.profile)
  if (!isExternalProfile(profile) || item.origin) return { ...item, profile }
  return { ...item, profile, origin: profile === 'Jurado' ? 'Convite' : 'Cadastro público', category: PUBLIC_CATEGORY[profile] }
}

// Sessão a partir de uma conta cadastrada (interna ou externa).
function sessionOf(user) {
  return {
    userId: user.id,
    email: user.email.trim().toLowerCase(),
    name: user.name,
    profile: user.profile,
    sector: user.sector,
    role: user.role,
    // Cópia simples: a sessão não pode guardar arrays reativos da lista de usuários.
    sectors: [...(user.sectors || [])],
    external: isExternalProfile(user.profile),
  }
}

function withSeedUsers(users) {
  const profiles = new Set(users.map((item) => item.profile))
  const ids = new Set(users.map((item) => item.id))
  return [...users, ...seedUsers().filter((item) => !profiles.has(item.profile) && !ids.has(item.id))]
}

function load() {
  try {
    const raw = localStorage.getItem(KEY)
    if (!raw) return syncCredentials(defaultState())
    const parsed = JSON.parse(raw)
    const base = defaultState()
    const hasWork = ['students', 'companies', 'meetings', 'judges'].some((key) => (parsed[key] || []).length)
    const loaded = {
      ...base,
      ...parsed,
      a11y: { ...base.a11y, ...(parsed.a11y || {}) },
      event: { ...base.event, ...(parsed.event || {}) },
      voting: { ...base.voting, ...(parsed.voting || {}) },
      teams: normalizeTeams(parsed.teams).map((team) => ({ ...team, members: team.members || [] })),
      students: (parsed.students || []).map((student) => ({ ...student, availability: student.availability || 'Disponível' })),
      participantsConfirmed: Boolean(parsed.participantsConfirmed),
      teamSize: Number(parsed.teamSize) || 6,
      // Estado salvo de versões anteriores: nomes antigos de perfil são migrados e os novos perfis ganham usuários demonstrativos.
      session: parsed.session ? { ...parsed.session, profile: migrateProfile(parsed.session.profile) } : null,
      users: parsed.users?.length ? withSeedUsers(parsed.users.map(migrateUser)) : base.users,
      invites: parsed.invites || [],
      orgMembers: (parsed.orgMembers || []).map((item) => ({ ...item, profile: migrateProfile(item.profile) })),
      // Atribuição explícita de equipes: jurados antigos começam sem atribuição,
      // exceto o jurado demonstrativo, que recebe a atribuição manual do demo.
      judges: (parsed.judges || []).map((item) => ({
        ...item,
        assignedTeamIds: Array.isArray(item.assignedTeamIds)
          ? item.assignedTeamIds
          : Array.isArray(item.teamIds) ? item.teamIds : item.id === 'jur-1' && parsed.demo ? (parsed.teams || []).slice(0, 2).map((team) => team.id) : [],
      })),
      welcome: parsed.welcome ?? (hasWork ? 'existente' : null),
      demo: Boolean(parsed.demo) || /demonstrativ/i.test(JSON.stringify({
        students: parsed.students,
        companies: parsed.companies,
        judges: parsed.judges,
        meetings: parsed.meetings,
      })),
    }
    return normalizeDemands(syncCredentials(loaded))
  } catch {
    return syncCredentials(defaultState())
  }
}

export function parseHash() {
  const raw = (window.location.hash || '#/login').replace(/^#\/?/, '')
  const [path, qs] = raw.split('?')
  const params = Object.fromEntries(new URLSearchParams(qs || ''))
  return { path: path || 'login', params }
}

export function go(to) {
  const path = to.startsWith('/') ? to : `/${to}`
  if (`#${path}` === window.location.hash) {
    window.dispatchEvent(new HashChangeEvent('hashchange'))
  } else {
    window.location.hash = path
  }
}

const route = ref(parseHash())
let routeBound = false

function bindRoute() {
  if (routeBound) return
  routeBound = true
  window.addEventListener('hashchange', () => {
    route.value = parseHash()
  })
}

export function useRoute() {
  bindRoute()
  return route
}

function replaceState(state, next) {
  Object.keys(state).forEach((key) => {
    if (!(key in next)) delete state[key]
  })
  Object.assign(state, next)
}

export function createHackStore() {
  bindRoute()
  const state = reactive(load())
  let toastTimer = 0

  const store = reactive({
    state,
    toast: null,
    update(fn) {
      // structuredClone recusa proxies reativos aninhados; nesse caso, cópia via JSON (o estado é só dados).
      let draft
      try {
        draft = structuredClone(toRaw(state))
      } catch {
        draft = JSON.parse(JSON.stringify(state))
      }
      fn(draft)
      // Participantes, contas e cadastros públicos novos recebem credencial automaticamente.
      syncCredentials(draft)
      normalizeDemands(draft)
      replaceState(state, draft)
    },
    flash(text, type = 'ok') {
      store.toast = { text, type, id: Date.now() }
      window.clearTimeout(toastTimer)
      toastTimer = window.setTimeout(() => { store.toast = null }, 3200)
    },
    login(email, password, remember) {
      const normalized = email.trim().toLowerCase()
      const secret = password.trim()
      if (!normalized.includes('@') || secret.length < 4) {
        return 'Informe um e-mail válido e uma senha com pelo menos 4 caracteres.'
      }
      const registered = (state.users || []).find((item) => item.email?.trim().toLowerCase() === normalized)
      if (registered?.status === 'Inativo') return 'Este usuário está inativo e não tem acesso ao HackLab.'
      if (registered?.password && registered.password !== secret) return 'Senha incorreta.'
      const known = ACCOUNTS[normalized]
      const session = registered
        ? sessionOf(registered)
        : known
          ? { email: normalized, ...known }
          : {
            email: normalized,
            name: 'Usuário Demonstrativo',
            profile: 'Administrador',
            sector: '',
            role: 'Equipe de TI',
          }
      if (remember) localStorage.setItem('hacklab.remember', normalized)
      else localStorage.removeItem('hacklab.remember')
      store.update((draft) => { draft.session = session })
      go(homeFor(session.profile))
      return ''
    },
    // Cadastro público demonstrativo: Votante livre; Jurado só com convite válido.
    // Retorna { field, message } quando algo impede o cadastro.
    register({ kind, name, email, password, code }) {
      const normalized = email.trim().toLowerCase()
      if (!isExternalProfile(kind)) return { field: 'kind', message: 'Escolha Votante ou Jurado.' }
      const taken = ACCOUNTS[normalized] || (state.users || []).some((item) => item.email?.trim().toLowerCase() === normalized)
      if (taken) return { field: 'email', message: 'Já existe um cadastro utilizando este e-mail.' }
      const invite = kind === 'Jurado' ? findInvite(state, code) : null
      if (kind === 'Jurado' && !invite) return { field: 'code', message: 'Código de convite inválido ou não reconhecido.' }
      if (invite?.status === 'Utilizado') return { field: 'code', message: 'Este convite já foi utilizado.' }
      const user = {
        id: uid('usr'),
        name: name.trim(),
        email: normalized,
        password: password.trim(),
        profile: kind,
        category: PUBLIC_CATEGORY[kind],
        origin: invite ? 'Convite' : 'Cadastro público',
        status: 'Ativo',
        sector: '',
        sectors: [],
        role: '',
        companyId: invite?.companyId || '',
        ...(kind === 'Votante' ? { hasVoted: false } : {}),
        createdAt: new Date().toLocaleDateString('pt-BR'),
      }
      store.update((draft) => {
        draft.users.push(user)
        if (invite) {
          const current = draft.invites.find((item) => item.id === invite.id)
          current.status = 'Utilizado'
          current.usedBy = user.id
          // A mesma pessoa: liga a conta ao jurado já cadastrado ou cria o registro de jurado.
          const judge = draft.judges.find((item) => !item.userId && ((invite.repId && item.repId === invite.repId) || (item.email && item.email.toLowerCase() === normalized)))
          if (judge) judge.userId = user.id
          else draft.judges.push({ id: uid('jur'), userId: user.id, repId: invite.repId || '', name: user.name, companyId: invite.companyId || '', companyName: invite.companyName || '', cargo: 'Representante', email: normalized, status: 'Ativo', assignedTeamIds: [] })
        }
        draft.session = sessionOf(user)
        appendAudit(draft, draft.session, { action: 'user.public-signup', label: `Cadastro público de ${kind}`, module: 'users', entityType: 'Usuário', entityId: user.id, entityLabel: user.email, description: `${user.name} criou o próprio cadastro como ${kind}${invite ? ` usando o convite ${invite.code}` : ''}.` })
      })
      store.flash(kind === 'Jurado' ? 'Cadastro realizado. Seu acesso de Jurado está ativo.' : 'Cadastro realizado. Você já pode acessar a votação quando ela estiver disponível.')
      go(homeFor(kind))
      return null
    },
    logout() {
      store.update((draft) => { draft.session = null })
      go('login')
    },
    // Simulação do protótipo: troca só a experiência visual, sem tocar nos dados do evento.
    setProfile(profile) {
      store.update((draft) => {
        if (!draft.session) return
        draft.session.profile = profile
        if (profileConfig(profile).sectorScoped) {
          const sectors = (draft.session.sectors || []).filter((name) => SETORES.includes(name))
          draft.session.sectors = sectors.length ? sectors : ['Tecnologia']
          if (!draft.session.sectors.includes(draft.session.sector)) draft.session.sector = draft.session.sectors[0]
        }
      })
      store.flash(`Perfil de acesso alterado para ${profile}.`)
      go(homeFor(profile))
    },
    setDemoSector(sector) {
      if (!SETORES.includes(sector)) return
      store.update((draft) => {
        if (!draft.session) return
        draft.session.sectors = [sector]
        draft.session.sector = sector
      })
      store.flash(`Setor demonstrativo: ${sector}.`)
    },
    setA11y(partial) {
      store.update((draft) => { draft.a11y = { ...draft.a11y, ...partial } })
    },
    resetA11y() {
      store.update((draft) => { draft.a11y = defaultState().a11y })
      store.flash('Acessibilidade restaurada ao padrão.')
    },
    loadDemo() {
      replaceState(state, normalizeDemands(seedDemoCredentials(buildDemo(structuredClone(toRaw(state))))))
      store.flash('Dados demonstrativos carregados neste navegador.')
    },
    resetAll() {
      const fresh = defaultState()
      // Conta externa criada no protótipo deixa de existir: a sessão dela também é encerrada.
      fresh.session = state.session?.external ? null : state.session
      fresh.a11y = state.a11y
      replaceState(state, syncCredentials(fresh))
      localStorage.removeItem('hacklab.vote.cast')
      store.flash(fresh.session ? 'Dados do protótipo limpos. A sessão foi mantida.' : 'Dados do protótipo limpos.')
      if (!fresh.session) go('login')
    },
  })

  watch(state, () => {
    localStorage.setItem(KEY, JSON.stringify(state))
  }, { deep: true })

  watch(() => state.a11y, (a11y) => {
    const root = document.documentElement
    document.body.style.zoom = `${a11y.scale}%`
    root.classList.toggle('contrast', a11y.contrast)
    root.classList.toggle('focus-strong', a11y.focus)
    root.classList.toggle('reduce-motion', a11y.motion)
    root.dataset.spacing = a11y.spacing
  }, { deep: true, immediate: true })

  watch(() => state.session?.email, () => {
    const email = state.session?.email?.trim().toLowerCase()
    if (!email) return
    const registered = (state.users || []).find((item) => item.email?.trim().toLowerCase() === email)
    if (registered?.status !== 'Inativo') return
    state.session = null
    go('login')
    store.flash('Este usuário está inativo e não tem acesso ao HackLab.', 'err')
  })

  return store
}

export function useHack() {
  return inject(STORE)
}
