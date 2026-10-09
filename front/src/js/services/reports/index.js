import logoNavyUrl from '@/assets/senac-logo-navy.png'
import logoWhiteUrl from '@/assets/senac-logo-white.png'
import { buildDocument } from '@/js/services/reports/document'
import { eventInfo, stamp } from '@/js/services/reports/shared'
import { buildClosingReportData } from './templates/closing'
import { buildFinancialReportData } from './templates/financial'
import { buildGeneralReportData } from './templates/general'
import { buildOperationsReportData } from './templates/operations'
import { buildParticipantsReportData } from './templates/participants'

// Catálogo da Central de Relatórios: store → builder de dados → template → PDF.
export const REPORT_MODELS = [
  { id: 'geral', title: 'Relatório Geral do Hackathon', text: 'Documento completo com preparação, operação e encerramento.', recommended: true, build: buildGeneralReportData },
  { id: 'participantes', title: 'Participantes e Presença', text: 'Cadastro, turmas, equipes e frequência durante os três dias.', build: buildParticipantsReportData },
  { id: 'operacao', title: 'Gestão e Operação', text: 'Setores, reuniões, pendências, documentos, salas e ocorrências.', build: buildOperationsReportData },
  { id: 'encerramento', title: 'Encerramento e Resultados', text: 'Jurados, critérios, avaliações, votação do público e resultados.', build: buildClosingReportData },
  { id: 'financeiro', title: 'Financeiro', text: 'Orçamento, receitas, despesas, saldo, fornecedores e comprovantes.', build: buildFinancialReportData },
]

let enginePromise
let logosPromise

// O pdfmake (~2 MB com fontes) só é baixado quando um relatório é gerado.
function engine() {
  enginePromise ||= Promise.all([import('pdfmake/build/pdfmake'), import('pdfmake/build/vfs_fonts')]).then(([lib, fonts]) => {
    const pdfMake = lib.default || lib
    pdfMake.vfs = fonts.default || fonts
    return pdfMake
  })
  return enginePromise
}

async function asDataUrl(url) {
  try {
    const blob = await (await fetch(url)).blob()
    return await new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(reader.result)
      reader.onerror = reject
      reader.readAsDataURL(blob)
    })
  } catch {
    return null
  }
}

function logos() {
  logosPromise ||= Promise.all([asDataUrl(logoWhiteUrl), asDataUrl(logoNavyUrl)])
  return logosPromise
}

export async function generateReport(id, state) {
  const model = REPORT_MODELS.find((item) => item.id === id)
  const [pdfMake, [logoWhite, logoNavy]] = await Promise.all([engine(), logos()])
  const now = new Date()
  const when = stamp(now)
  const report = model.build(state)
  const ctx = {
    when,
    event: eventInfo(state),
    demo: Boolean(state.demo),
    author: { name: state.session?.name || 'Usuário não identificado', profile: state.session?.profile || '' },
    logoWhite,
    logoNavy,
  }
  const definition = buildDocument(report, ctx)
  const blob = await new Promise((resolve) => pdfMake.createPdf(definition).getBlob(resolve))
  return { blob, fileName: `HackLab_${report.file}_${when.iso}.pdf`, title: report.title }
}
