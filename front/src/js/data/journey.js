// Etapas da jornada de organização mostradas no Dashboard.
import { activeMembers } from './teams'

export function journeySteps(state) {
  const eventDone = Boolean(state.event?.theme || state.event?.description || state.event?.date)
  const peopleDone = Boolean(state.participantsConfirmed)
  const teamsDone = (state.teams || []).some((team) => activeMembers(team, state.students || []).length > 0)
  const bizDone = (state.companies || []).length > 0 && (state.challenges || []).length > 0
  const opsDone = (state.meetings || []).length > 0 || (state.tasks || []).length > 0
  const liveDone = (state.checkins || []).length > 0
  const endDone = Boolean(state.resultsReleased)
  const flags = [eventDone, peopleDone, teamsDone, bizDone, opsDone, liveDone, endDone]
  let opened = false
  const status = flags.map((done) => {
    if (done) return 'concluida'
    if (!opened) {
      opened = true
      return 'andamento'
    }
    return 'pendente'
  })
  const meta = [
    ['Configurar evento', 'config'],
    ['Cadastrar participantes', 'participantes'],
    ['Formar equipes', 'equipes'],
    ['Empresas e desafios', 'empresas'],
    ['Preparação operacional', 'setores'],
    ['Realizar evento', 'presenca'],
    ['Encerramento', 'jurados'],
  ]
  return meta.map(([label, to], index) => ({ label, to, status: status[index] }))
}
