// Credenciais do evento e presença por dia.
import { uid } from './utils'

// Credencial do evento: 1 pessoa = 1 credencial = 1 QR Code, válida nos dias autorizados.
// Categoria da credencial (presença física) é diferente do perfil de acesso ao sistema.
export const CREDENTIAL_CATEGORIES = ['Participante', 'Organização', 'Professor', 'Jurado', 'Público', 'Convidado']

export const CREDENTIAL_STATUS = ['Ativa', 'Bloqueada', 'Cancelada']

export const EVENT_DAYS = [1, 2, 3]

const PROFILE_CATEGORY = {
  Administrador: 'Organização',
  Gestor: 'Organização',
  Editor: 'Organização',
  Validador: 'Organização',
  Consultor: 'Professor',
  Jurado: 'Jurado',
  Votante: 'Público',
}

// Sugestão demonstrativa (editável): Jurado e Público costumam vir no Dia 3.
export function suggestedDays(category) {
  return ['Jurado', 'Público'].includes(category) ? [3] : [...EVENT_DAYS]
}

// Pessoas que podem ter credencial: participantes, contas do sistema e convidados sem conta.
export function credentialPeople(state) {
  return [
    ...(state.students || []).map((item) => ({ id: item.id, name: item.name, email: item.email || '', kind: 'Participante cadastrado', category: 'Participante', detail: item.turma ? `Turma ${item.turma}` : '' })),
    ...(state.users || []).map((item) => ({ id: item.id, name: item.name, email: item.email || '', kind: 'Conta do sistema', category: PROFILE_CATEGORY[item.profile] || 'Organização', detail: item.profile })),
    ...(state.guests || []).map((item) => ({ id: item.id, name: item.name, email: item.email || '', kind: 'Pessoa externa (sem conta)', category: item.category || 'Convidado', detail: 'Sem conta no sistema' })),
  ]
}

export function personOf(state, personId) {
  return credentialPeople(state).find((item) => item.id === personId) || null
}

export function credentialOf(state, personId) {
  return (state.credentials || []).find((item) => item.personId === personId) || null
}

export function findCredential(state, code) {
  const wanted = String(code || '').trim().toUpperCase()
  return wanted ? (state.credentials || []).find((item) => item.code === wanted) || null : null
}

function nextCredentialCode(list) {
  const top = list.reduce((max, item) => Math.max(max, Number(String(item.code).replace(/\D/g, '')) || 0), 0)
  return `HL-${String(top + 1).padStart(6, '0')}`
}

// Geração automática (demonstrativa): participantes, contas internas e cadastros públicos
// recebem uma credencial na primeira vez que aparecem. Nunca duplica a mesma pessoa.
export function syncCredentials(draft) {
  if (!Array.isArray(draft.credentials)) draft.credentials = []
  if (!Array.isArray(draft.guests)) draft.guests = []
  const known = new Set(draft.credentials.map((item) => item.personId))
  const today = new Date().toLocaleDateString('pt-BR')
  const add = (personId, category, status = 'Ativa') => {
    if (known.has(personId)) return
    known.add(personId)
    draft.credentials.push({ id: uid('cred'), code: nextCredentialCode(draft.credentials), personId, category, days: suggestedDays(category), status, createdAt: today, note: '' })
  }
  const LEGACY = { Ativo: 'Ativa', Bloqueado: 'Bloqueada', Cancelado: 'Cancelada' }
  for (const item of draft.students || []) add(item.id, 'Participante', LEGACY[item.ticketStatus] || 'Ativa')
  for (const item of draft.users || []) add(item.id, PROFILE_CATEGORY[item.profile] || 'Organização')
  return draft
}

// Exemplos demonstrativos: convidados sem conta e uma credencial bloqueada.
export function seedDemoCredentials(draft) {
  syncCredentials(draft)
  const today = new Date().toLocaleDateString('pt-BR')
  for (const guest of draft.guests || []) {
    if (draft.credentials.some((item) => item.personId === guest.id)) continue
    draft.credentials.push({ id: uid('cred'), code: nextCredentialCode(draft.credentials), personId: guest.id, category: guest.category, days: suggestedDays(guest.category), status: 'Ativa', createdAt: today, note: 'Credencial demonstrativa' })
  }
  const last = (draft.students || [])[draft.students.length - 1]
  const blocked = last && draft.credentials.find((item) => item.personId === last.id)
  if (blocked) blocked.status = 'Bloqueada'
  return draft
}

// Validação da credencial para um dia: status, dia autorizado e duplicidade.
export function checkCredential(state, credential, day) {
  if (!credential) return { kind: 'missing' }
  if (credential.status === 'Bloqueada') return { kind: 'blocked', credential }
  if (credential.status === 'Cancelada') return { kind: 'cancelled', credential }
  if (!(credential.days || []).includes(Number(day))) return { kind: 'day', credential }
  const record = presenceOf(state, credential.personId, day)
  if (record) return { kind: 'already', credential, record }
  return { kind: 'valid', credential }
}

// Presença é por dia e não depende da disponibilidade do cadastro.
export function presenceOf(state, personId, day) {
  return (state.checkins || []).find((item) => item.personId === personId && Number(item.day) === Number(day) && item.status === 'Presente') || null
}

// Dia de referência: o último dia com registro de presença; antes do evento, Dia 1.
export function currentEventDay(state) {
  const days = (state.checkins || []).map((item) => Number(item.day)).filter((day) => [1, 2, 3].includes(day))
  return days.length ? Math.max(...days) : 1
}
