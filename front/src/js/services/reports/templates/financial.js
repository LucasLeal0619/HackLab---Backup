import * as d from '@/js/services/reports/shared'
import * as s from '@/js/services/reports/sections'

export function buildFinancialReportData(state) {
  const money = d.financeTotals(state)
  const fmt = (value) => (value == null ? d.NOT_INFORMED : d.brl(value))
  return {
    id: 'financeiro',
    title: 'Financeiro',
    short: 'Relatório Financeiro',
    file: 'Financeiro',
    summary: [
      ['Orçamento previsto', fmt(money.budget)],
      ['Receitas', fmt(money.incomes)],
      ['Despesas', fmt(money.expenses)],
      ['Saldo', fmt(money.balance)],
      ['Movimentações registradas', d.count((state.incomes || []).length + (state.expenses || []).length)],
      ['Fornecedores', d.count((state.suppliers || []).length)],
    ],
    sections: s.financeSections(state),
    notes: ['Despesas consideram o valor realizado; quando ele não foi informado, é usado o valor previsto.', 'Saldo = receitas registradas - despesas registradas.'],
  }
}
