// Jurados: vínculo com a conta e equipes atribuídas explicitamente (assignedTeamIds).
import { currentUser } from './accounts'

// Registro do jurado ligado à conta atual (pelo vínculo da conta ou pelo e-mail).
export function judgeOf(state) {
  const session = state.session
  if (!session) return null
  const user = currentUser(state)
  return (state.judges || []).find((item) => (user && item.userId === user.id) || (item.email && item.email.toLowerCase() === session.email)) || null
}

// Equipes que o jurado avalia: somente a atribuição explícita em judge.assignedTeamIds.
// A empresa do jurado é apenas contexto e não define avaliações. Um jurado pode avaliar
// várias equipes e uma equipe pode ter vários jurados. Sem atribuição, a lista fica vazia.
export function assignedTeams(state) {
  const ids = judgeOf(state)?.assignedTeamIds || []
  return state.teams.filter((team) => ids.includes(team.id))
}
