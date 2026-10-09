// Ocorrências: categorias, setor responsável e status.

// Ocorrências: fatos ocorridos (diferente de pendência, que é algo a fazer).
export const OCC_CATEGORIES = ['Tecnologia', 'Infraestrutura', 'Produção', 'Participante', 'Equipe', 'Empresa', 'Organização', 'Outro']

export const OCC_PRIORITIES = ['Baixa', 'Média', 'Alta', 'Urgente']

export const OCC_STATUS = ['Aberta', 'Em atendimento', 'Resolvida']

// Categorias antigas do protótipo continuam legíveis sem migrar o que já está salvo.
const LEGACY_OCC_CATEGORY = { Suporte: 'Tecnologia', Equipamento: 'Tecnologia', Sala: 'Infraestrutura', Estrutura: 'Infraestrutura', Materiais: 'Produção' }

const CATEGORY_SECTOR = { Tecnologia: 'Tecnologia', Infraestrutura: 'Produção', Produção: 'Produção' }

export function occurrenceCategory(item) {
  const category = item?.category || 'Outro'
  return LEGACY_OCC_CATEGORY[category] || category
}

export function occurrenceSector(item) {
  return item?.sector ?? CATEGORY_SECTOR[occurrenceCategory(item)] ?? ''
}

export function occurrenceStatus(item) {
  return OCC_STATUS.includes(item?.status) ? item.status : item?.status === 'Concluído' ? 'Resolvida' : 'Aberta'
}
