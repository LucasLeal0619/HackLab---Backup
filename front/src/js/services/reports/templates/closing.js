import * as d from '@/js/services/reports/shared'
import * as s from '@/js/services/reports/sections'

export function buildClosingReportData(state) {
  const evaluations = state.evaluations || []
  return {
    id: 'encerramento',
    title: 'Encerramento e Resultados',
    short: 'Encerramento e Resultados',
    file: 'Encerramento_Resultados',
    summary: [
      ['Equipes avaliadas', d.count((state.teams || []).filter((team) => evaluations.some((item) => item.teamId === team.id && item.status === 'concluida')).length)],
      ['Jurados ativos', d.count(d.activeJudges(state).length)],
      ['Critérios de avaliação', d.count((state.criteria || []).length)],
      ['Avaliações concluídas', d.count(evaluations.filter((item) => item.status === 'concluida').length)],
      ['Situação da votação do público', d.text(state.voting?.status)],
      ['Votos do público', d.count((state.voting?.ballots || []).length)],
      ['Resultados divulgados', d.yesNo(state.resultsReleased)],
      ['Premiações cadastradas', d.count((state.awards || []).length)],
    ],
    sections: [s.presentationsSection(state), s.judgesSection(state), s.votingSection(state), s.resultsSection(state)],
    notes: ['Avaliação dos jurados e votação do público são apuradas separadamente; este documento não calcula resultado combinado.'],
  }
}
