// Contas de acesso: logins demonstrativos, usuários iniciais e cadastro público (Jurado/Votante).

// Cadastro público: só Jurado (com convite) e Votante criam a própria conta.
export const PUBLIC_PROFILES = ['Votante', 'Jurado']

export const PUBLIC_CATEGORY = { Votante: 'Público', Jurado: 'Jurado' }

export const INVITE_STATUS = ['Não utilizado', 'Utilizado']

export function isExternalProfile(profile) {
  return PUBLIC_PROFILES.includes(profile)
}

export function inviteCode() {
  return `JUR-${Math.random().toString(36).slice(2, 8).toUpperCase().padEnd(6, '0')}`
}

export function findInvite(state, code) {
  const wanted = String(code || '').trim().toUpperCase()
  return wanted ? (state.invites || []).find((item) => item.code.toUpperCase() === wanted) || null : null
}

export function currentUser(state) {
  const session = state.session
  if (!session) return null
  return (state.users || []).find((item) => (session.userId && item.id === session.userId) || item.email?.toLowerCase() === session.email) || null
}

// Logins de demonstração. As demais experiências são testadas pelo seletor "Meu perfil".
export const ACCOUNTS = {
  'admin@senac.br': {
    name: 'Usuário Demonstrativo',
    profile: 'Administrador',
    sector: '',
    role: 'Equipe de TI',
  },
  'consultor@senac.br': {
    name: 'Usuário Consultor',
    profile: 'Consultor',
    sector: 'Acompanhamento',
    role: 'Professor / Coordenação',
  },
  'editor@senac.br': {
    name: 'Usuário Editor',
    profile: 'Editor',
    sector: 'Tecnologia',
    role: 'Apoio',
    sectors: ['Tecnologia'],
  },
}

// Usuários demonstrativos: ao menos um por perfil de acesso.
export function seedUsers() {
  const user = (id, name, profile, extra = {}) => ({ id, name, email: `${id.replace('usr-', 'usuario')}@exemplo.com`, profile, sector: '', sectors: [], role: '', status: 'Ativo', ...extra })
  return [
    user('usr-1', 'Usuário Administrador', 'Administrador', { role: 'Equipe de TI' }),
    user('usr-2', 'Usuário Consultor', 'Consultor', { sector: 'Acompanhamento', role: 'Professor / Coordenação' }),
    user('usr-3', 'Editor demonstrativo', 'Editor', { sector: 'Tecnologia', sectors: ['Tecnologia'], role: 'Apoio técnico' }),
    user('usr-4', 'Editor demonstrativo 02', 'Editor', { sector: 'Marketing', sectors: ['Marketing'], role: 'Registro audiovisual', status: 'Inativo' }),
    user('usr-5', 'Gestor demonstrativo', 'Gestor', { sector: 'Marketing', sectors: ['Marketing'], role: 'Líder do setor' }),
    user('usr-6', 'Validador demonstrativo', 'Validador', { role: 'Check-in' }),
    // Cadastros externos (Jurado e Votante) criam a própria conta; estes são exemplos demonstrativos.
    user('usr-7', 'Jurado demonstrativo 01', 'Jurado', { email: 'jurado.demo@exemplo.com', role: 'Representante', companyId: 'emp-1', origin: 'Convite', category: 'Jurado' }),
    user('usr-8', 'Votante demonstrativo', 'Votante', { email: 'votante.demo@exemplo.com', origin: 'Cadastro público', category: 'Público', hasVoted: false }),
  ]
}
