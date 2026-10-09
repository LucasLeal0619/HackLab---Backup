// Utilitários genéricos (texto, identificadores e datas).

export function plural(count, one, many) {
  return `${count} ${count === 1 ? one : many}`
}

export function uid(prefix = 'id') {
  return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`
}

export function pad(n) {
  return String(n).padStart(2, '0')
}

export function formatWhen(value) {
  if (!value) return 'A definir'
  return value
}
