// Tabelas responsivas: no celular (< 768px) cada linha vira um card (ver styles.css, "table.cardable").
// Aqui só copiamos o texto do cabeçalho para data-label de cada célula, para o card mostrar
// "Rótulo  valor". Atributos extras não interferem no patch do Vue (ele só altera o que é vinculado).

const ACTION_HEADERS = new Set(['', 'Ações', 'Ação'])

function label(table) {
  const heads = [...table.querySelectorAll(':scope > thead th')].map((th) => th.textContent.trim())
  if (!heads.length) return
  table.classList.add('cardable')
  for (const row of table.querySelectorAll(':scope > tbody > tr')) {
    let column = 0
    for (const cell of row.children) {
      const span = Number(cell.getAttribute('colspan') || 1)
      const head = span > 1 ? '' : heads[column] ?? ''
      if (span > 1) cell.dataset.cell = 'full'
      else if (column === 0) cell.dataset.cell = 'title'
      else if (ACTION_HEADERS.has(head)) cell.dataset.cell = 'actions'
      else {
        cell.dataset.cell = 'field'
        if (cell.dataset.label !== head) cell.dataset.label = head
      }
      column += span
    }
  }
}

function scan() {
  // A matriz de acesso continua como tabela (comparação lado a lado), com rolagem própria.
  document.querySelectorAll('.table-wrap > table:not(.matrix)').forEach(label)
}

export function enableResponsiveTables() {
  let queued = false
  const schedule = () => {
    if (queued) return
    queued = true
    requestAnimationFrame(() => {
      queued = false
      scan()
    })
  }
  new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true, characterData: true })
  schedule()
}
