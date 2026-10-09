// Resumo por setor exibido no Dashboard.
import { occurrenceSector } from './occurrences'
import { plural } from './utils'

function money(value) {
  if (value === '' || value == null) return '—'
  const number = Number(value)
  if (Number.isNaN(number)) return '—'
  return number.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

// Visão por exceção de cada setor: um destaque e a situação que pede atenção.
export function sectorSummaries(state) {
  const openFor = (name) => (state.tasks || []).filter((task) => task.sector === name && task.status !== 'Concluído').length
  const pending = (name) => {
    const count = openFor(name)
    return count ? { text: plural(count, 'pendência', 'pendências'), tone: 'warn' } : { text: 'Normal', tone: 'ok' }
  }
  const incomes = (state.incomes || []).filter((item) => item.value !== '' && item.value != null)
  const expenses = (state.expenses || []).filter((item) => (item.actual !== '' && item.actual != null) || (item.planned !== '' && item.planned != null))
  const incomeTotal = incomes.reduce((sum, item) => sum + Number(item.value || 0), 0)
  const expenseTotal = expenses.reduce((sum, item) => sum + Number(item.actual !== '' && item.actual != null ? item.actual : item.planned || 0), 0)
  const balance = incomes.length + expenses.length ? incomeTotal - expenseTotal : null
  const occurrences = state.occurrences || []
  const open = (item) => item.status !== 'Resolvida' && item.status !== 'Concluído'
  const calls = occurrences.filter((item) => occurrenceSector(item) === 'Tecnologia' && open(item)).length
  const broken = (state.equipment || []).filter((item) => item.status === 'Com problema').length
  const prodOcc = occurrences.filter((item) => occurrenceSector(item) === 'Produção' && open(item)).length
  return [
    {
      name: 'Recursos Humanos',
      icon: 'users',
      highlight: plural(state.orgMembers?.length || 0, 'integrante', 'integrantes'),
      status: pending('Recursos Humanos'),
    },
    {
      name: 'Finanças',
      icon: 'chart',
      highlight: `Saldo ${balance == null ? '—' : money(balance)}`,
      status: balance != null && balance < 0 ? { text: 'Saldo negativo', tone: 'bad' } : pending('Finanças'),
    },
    {
      name: 'Marketing',
      icon: 'star',
      highlight: plural(state.campaigns?.length || 0, 'campanha', 'campanhas'),
      status: pending('Marketing'),
    },
    {
      name: 'Tecnologia',
      icon: 'bolt',
      highlight: broken ? plural(broken, 'com problema', 'com problema') : plural(state.equipment?.length || 0, 'equipamento', 'equipamentos'),
      highlightTone: broken ? 'bad' : '',
      status: calls ? { text: plural(calls, 'ocorrência aberta', 'ocorrências abertas'), tone: 'warn' } : pending('Tecnologia'),
    },
    {
      name: 'Produção',
      icon: 'grid',
      highlight: plural(state.spaces?.length || 0, 'espaço', 'espaços'),
      status: prodOcc ? { text: plural(prodOcc, 'ocorrência', 'ocorrências'), tone: 'warn' } : pending('Produção'),
    },
  ]
}
