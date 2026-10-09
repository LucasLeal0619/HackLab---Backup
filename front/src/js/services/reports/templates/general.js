import * as d from '@/js/services/reports/shared'
import * as s from '@/js/services/reports/sections'

export function buildGeneralReportData(state) {
  const facts = d.peopleFacts(state)
  const occurrences = state.occurrences || []
  return {
    id: 'geral',
    title: 'Relatório Geral do Hackathon',
    short: 'Relatório Geral',
    file: 'Relatorio_Geral',
    summary: [
      ['Participantes cadastrados', d.count(facts.total)],
      ['Participantes disponíveis', d.count(facts.available)],
      ['Equipes formadas', d.count((state.teams || []).length)],
      ['Empresas participantes', d.count((state.companies || []).length)],
      ['Desafios cadastrados', d.count((state.challenges || []).length)],
      ['Participantes com presença registrada', d.count(facts.withPresence)],
      ['Credenciais do evento', d.count((state.credentials || []).length)],
      ['Pendências abertas', d.count(d.openTasks(state).length)],
      ['Ocorrências registradas', `${d.count(occurrences.length)} (${d.count(occurrences.filter(d.isOpen).length)} abertas)`],
      ['Avaliações concluídas', d.count((state.evaluations || []).filter((item) => item.status === 'concluida').length)],
      ['Votos do público', d.count((state.voting?.ballots || []).length)],
      ['Resultados divulgados', d.yesNo(state.resultsReleased)],
    ],
    sections: [
      s.participantsSection(state, { full: false }),
      s.accessSection(state),
      s.teamsSection(state),
      s.companiesSection(state),
      s.sectorsSection(state),
      ...s.meetingsSection(state),
      s.operationSection(state),
      s.occurrencesSection(state),
      s.judgesSection(state),
      s.votingSection(state),
      s.resultsSection(state),
    ],
    notes: [
      'A lista completa de participantes está no relatório "Participantes e Presença"; o detalhamento financeiro está no relatório "Financeiro".',
    ],
  }
}
