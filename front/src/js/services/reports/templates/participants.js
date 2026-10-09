import * as d from '@/js/services/reports/shared'
import * as s from '@/js/services/reports/sections'

export function buildParticipantsReportData(state) {
  const facts = d.peopleFacts(state)
  return {
    id: 'participantes',
    title: 'Participantes e Presença',
    short: 'Participantes e Presença',
    file: 'Participantes_Presenca',
    summary: [
      ['Participantes cadastrados', d.count(facts.total)],
      ['Disponíveis', d.count(facts.available)],
      ['Indisponíveis ou desistentes', d.count(facts.unavailable)],
      ['Equipes formadas', d.count((state.teams || []).length)],
      ['Com presença registrada em ao menos um dia', d.count(facts.withPresence)],
    ],
    sections: [s.participantsSection(state), s.teamsSection(state)],
    notes: ['Este relatório considera somente participantes. A presença de todas as categorias de credencial está no Relatório Geral, seção "Acesso e presença".', '"Não registrado" indica que não há registro de presença ou ausência para o participante naquele dia.'],
  }
}
