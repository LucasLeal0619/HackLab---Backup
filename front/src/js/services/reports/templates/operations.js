import * as d from '@/js/services/reports/shared'
import * as s from '@/js/services/reports/sections'

export function buildOperationsReportData(state) {
  const occurrences = state.occurrences || []
  return {
    id: 'operacao',
    title: 'Gestão e Operação',
    short: 'Gestão e Operação',
    file: 'Gestao_Operacao',
    summary: [
      ['Integrantes da organização', d.count((state.orgMembers || []).length)],
      ['Reuniões registradas', d.count((state.meetings || []).length)],
      ['Pendências abertas', d.count(d.openTasks(state).length)],
      ['Documentos cadastrados', d.count((state.documents || []).length)],
      ['Empresas e desafios', `${d.count((state.companies || []).length)} empresas · ${d.count((state.challenges || []).length)} desafios`],
      ['Equipes formadas', d.count((state.teams || []).length)],
      ['Ocorrências abertas', d.count(occurrences.filter(d.isOpen).length)],
      ['Equipamentos com problema', d.count((state.equipment || []).filter((item) => item.status === 'Com problema').length)],
    ],
    sections: [
      s.sectorsSection(state, { detailed: true }),
      ...s.meetingsSection(state, { split: true }),
      s.companiesSection(state),
      s.teamsSection(state),
      s.operationSection(state),
      s.occurrencesSection(state),
    ],
    notes: [],
  }
}
