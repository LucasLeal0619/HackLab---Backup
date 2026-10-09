export function toneFor(status = '') {
  const value = String(status).toLowerCase()
  if (/(urg|problem|alta|inativ|ausente|inválid)/.test(value)) return 'danger'
  if (/(aprov|conclu|confirm|presente|ativo|funcion|liberad|resolvid)/.test(value)) return 'ok'
  if (/(andamento|análise|pendent|breve|montag|aguard|revis)/.test(value)) return 'warn'
  if (/(planej|distrib|recebid|consulta)/.test(value)) return 'info'
  if (/(não inici|nao inici|rascunh|sem )/.test(value)) return ''
  return 'orange'
}
