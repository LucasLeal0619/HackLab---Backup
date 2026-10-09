// Dados demonstrativos para apresentação.
import { seedUsers } from './accounts'
import { TURMAS } from './constants'
import { suggestTeams, teamName } from './teams'
import { pad } from './utils'

function makeStudents(counts = { Breno: 8, Rafael: 5, Clara: 7 }) {
  const list = []
  let n = 1
  for (const turma of TURMAS) {
    const total = counts[turma.id] || 0
    for (let i = 0; i < total; i += 1) {
      list.push({
        id: `alu-${n}`,
        name: `Participante ${pad(n)}`,
        turma: turma.id,
        email: `participante${n}@senac.br`,
        matricula: `2026${String(n).padStart(4, '0')}`,
        note: '',
        availability: 'Disponível',
      })
      n += 1
    }
  }
  const sample = list[list.length - 1]
  if (sample) {
    sample.availability = 'Indisponível'
    sample.name = 'Participante demonstrativo indisponível'
    sample.note = 'Exemplo de pessoa cadastrada que não entra na formação.'
  }
  return list
}

export function buildDemo(current) {
  const students = makeStudents()
  const teams = suggestTeams(students, 6)
  if (teams[0]) teams[0].solution = 'Resumo demonstrativo da solução'

  const companies = [
    {
      id: 'emp-1',
      name: 'Empresa demonstrativa 01',
      razao: '',
      cnpj: '',
      segmento: 'Tecnologia',
      phone: '',
      email: 'contato@exemplo.com',
      site: '',
      description: 'Descrição demonstrativa da empresa. O texto real será cadastrado pela organização.',
      tipo: 'Empresa participante',
      status: 'Com desafio',
      reps: [
        { id: 'rep-1', name: 'Representante 01', cargo: 'Gerente de inovação', email: 'contato@exemplo.com', phone: '', principal: true },
        { id: 'rep-2', name: 'Representante 02', cargo: 'Analista', email: 'rep02@exemplo.com', phone: '', principal: false },
      ],
    },
  ]

  const challenges = [
    {
      id: 'des-1',
      companyId: 'emp-1',
      title: 'Desafio demonstrativo 01',
      status: 'Aprovado',
      problem: 'Texto demonstrativo do problema.',
      objective: 'Texto demonstrativo do objetivo do desafio.',
      requirements: 'Requisitos demonstrativos — a definir pela empresa.',
      restrictions: 'Restrições demonstrativas — a definir pela empresa.',
      expected: 'Resultado esperado demonstrativo.',
      teamId: null,
      updatedAt: 'Data demonstrativa',
    },
    {
      id: 'des-2',
      companyId: 'emp-1',
      title: 'Desafio demonstrativo 02',
      status: 'Aprovado',
      problem: 'Texto demonstrativo do problema. O conteúdo real será enviado pela empresa.',
      objective: 'Texto demonstrativo do objetivo do desafio.',
      requirements: 'Requisitos demonstrativos — a definir pela empresa.',
      restrictions: 'Restrições demonstrativas — a definir pela empresa.',
      expected: 'Resultado esperado demonstrativo.',
      teamId: null,
      updatedAt: 'Data demonstrativa',
    },
    {
      id: 'des-3',
      companyId: 'emp-1',
      title: 'Desafio demonstrativo 03',
      status: 'Rascunho',
      problem: 'Rascunho demonstrativo.',
      objective: '',
      requirements: '',
      restrictions: '',
      expected: '',
      teamId: null,
      updatedAt: 'Data demonstrativa',
    },
    {
      id: 'des-4',
      companyId: 'emp-1',
      title: 'Desafio demonstrativo 04',
      status: 'Em análise',
      problem: 'Texto demonstrativo do problema em análise.',
      objective: 'Texto demonstrativo do objetivo do desafio.',
      requirements: 'Requisitos demonstrativos — a definir pela empresa.',
      restrictions: 'Restrições demonstrativas — a definir pela empresa.',
      expected: 'Resultado esperado demonstrativo.',
      teamId: null,
      updatedAt: 'Data demonstrativa',
    },
  ]
  challenges[0].teamId = teams[0]?.id || null
  challenges[0].status = teams[0] ? 'Distribuído' : 'Aprovado'

  return {
    ...current,
    demo: true,
    // Contas externas demonstrativas (Jurado e Votante) sempre presentes nos dados de apresentação.
    users: [...(current.users || []).filter((item) => !['usr-7', 'usr-8'].includes(item.id)), ...seedUsers().filter((item) => ['usr-7', 'usr-8'].includes(item.id))],
    welcome: 'demonstrativo',
    participantsConfirmed: true,
    teamSize: 6,
    students,
    teams,
    companies,
    challenges,
    orgMembers: [
      { id: 'org-1', name: 'Usuário demonstrativo 01', profile: 'Administrador', sector: 'Recursos Humanos', func: 'Coordenação', status: 'Ativo', email: 'contato@exemplo.com', phone: '' },
      { id: 'org-2', name: 'Usuário demonstrativo 02', profile: 'Consultor', sector: 'Finanças', func: 'Financeiro', status: 'Ativo', email: 'fin@exemplo.com', phone: '' },
      { id: 'org-3', name: 'Usuário demonstrativo 03', profile: 'Gestor', sector: 'Marketing', func: 'Líder do setor', status: 'Ativo', email: 'mkt@exemplo.com', phone: '' },
      { id: 'org-4', name: 'Usuário demonstrativo 04', profile: 'Editor', sector: 'Tecnologia', func: 'Suporte', status: 'Ativo', email: 'tec@exemplo.com', phone: '' },
      { id: 'org-5', name: 'Usuário demonstrativo 05', profile: 'Editor', sector: 'Produção', func: 'Operação', status: 'Ativo', email: 'prod@exemplo.com', phone: '' },
    ],
    incomes: [
      { id: 'rec-1', description: 'Receita demonstrativa 01', category: 'Outros', origin: 'Apoio demonstrativo', responsible: 'Usuário demonstrativo 02', value: 2500, date: '2026-09-10', status: 'Concluído', notes: '' },
      { id: 'rec-2', description: 'Receita demonstrativa 02', category: 'Marketing', origin: 'Patrocínio demonstrativo', responsible: 'Usuário demonstrativo 02', value: 1500, date: '2026-09-18', status: 'Pendente', notes: '' },
    ],
    expenses: [
      { id: 'desp-1', description: 'Despesa demonstrativa 01', category: 'Alimentação', supplier: 'Fornecedor demonstrativo 01', responsible: 'Usuário demonstrativo 02', planned: 800, actual: 800, date: '2026-09-12', status: 'Concluído', notes: '' },
      { id: 'desp-2', description: 'Despesa demonstrativa 02', category: 'Materiais', supplier: 'Fornecedor demonstrativo 02', responsible: 'Usuário demonstrativo 02', planned: 400, actual: 400, date: '2026-09-20', status: 'Em andamento', notes: '' },
    ],
    suppliers: [
      { id: 'forn-1', name: 'Fornecedor demonstrativo 01', category: 'Alimentação', contact: 'contato@exemplo.com', status: 'Ativo' },
      { id: 'forn-2', name: 'Fornecedor demonstrativo 02', category: 'Materiais', contact: 'contato2@exemplo.com', status: 'Ativo' },
    ],
    campaigns: [
      { id: 'camp-1', name: 'Campanha demonstrativa 01', objective: 'Objetivo demonstrativo', audience: 'Participantes', channel: 'Instagram', responsible: 'Usuário demonstrativo 03', date: '2026-09-28', status: 'Em andamento', description: 'Descrição demonstrativa da campanha.' },
      { id: 'camp-2', name: 'Campanha demonstrativa 02', objective: 'Objetivo demonstrativo', audience: 'Empresas', channel: 'WhatsApp', responsible: 'Usuário demonstrativo 03', date: '2026-10-02', status: 'Não iniciado', description: 'Descrição demonstrativa da campanha.' },
    ],
    contents: [
      { id: 'cont-1', name: 'Conteúdo demonstrativo 01', kind: 'Conteúdo', channel: 'Instagram', responsible: 'Usuário demonstrativo 03', date: '2026-09-27', status: 'Em andamento' },
      { id: 'cont-2', name: 'Material demonstrativo 01', kind: 'Material', channel: 'Cartazes', responsible: 'Usuário demonstrativo 03', date: '2026-09-29', status: 'Pendente' },
    ],
    equipment: [
      { id: 'eq-1', name: 'Equipamento demonstrativo 01', category: 'Notebook', qty: 6, place: 'Sala 03', responsible: 'Usuário demonstrativo', status: 'Em uso', notes: '' },
      { id: 'eq-2', name: 'Equipamento demonstrativo 02', category: 'Projetor', qty: 1, place: 'Sala 03', responsible: 'Usuário demonstrativo', status: 'Disponível', notes: '' },
      { id: 'eq-4', name: 'Equipamento demonstrativo 04', category: 'Extensão', qty: 2, place: 'Sala 04', responsible: 'Usuário demonstrativo', status: 'Com problema', notes: 'Problema demonstrativo' },
    ],
    infra: [
      { id: 'inf-1', name: 'Internet', status: 'Concluído', responsible: 'Usuário demonstrativo 04', notes: 'Item demonstrativo de infraestrutura.' },
      { id: 'inf-2', name: 'Rede', status: 'Em andamento', responsible: 'Usuário demonstrativo 04', notes: 'Item demonstrativo de infraestrutura.' },
      { id: 'inf-3', name: 'Energia', status: 'Não iniciado', responsible: 'Usuário demonstrativo 04', notes: 'Item demonstrativo de infraestrutura.' },
      { id: 'inf-4', name: 'Projeção', status: 'Em andamento', responsible: 'Usuário demonstrativo 04', notes: 'Item demonstrativo de infraestrutura.' },
      { id: 'inf-5', name: 'Áudio', status: 'Concluído', responsible: 'Usuário demonstrativo 04', notes: 'Item demonstrativo de infraestrutura.' },
    ],
    spaces: [
      { id: 'sp-1', name: 'Sala 01', type: 'Sala', capacity: '6', purpose: 'Sala de equipe', responsible: 'Usuário demonstrativo', status: 'Em andamento', notes: '' },
      { id: 'sp-3', name: 'Sala 03', type: 'Sala', capacity: '6', purpose: 'Sala de equipe', responsible: 'Usuário demonstrativo', status: 'Em andamento', notes: '' },
      { id: 'sp-4', name: 'Sala 04', type: 'Sala', capacity: '6', purpose: 'Sala de equipe', responsible: 'Usuário demonstrativo', status: 'Com ocorrência', notes: '' },
    ],
    materials: [
      { id: 'mat-1', name: 'Material demonstrativo 01', needed: '20', available: '12', status: 'Em andamento' },
      { id: 'mat-2', name: 'Material demonstrativo 02', needed: '8', available: '8', status: 'Concluído' },
    ],
    operations: [
      { id: 'op-1', name: 'Estrutura', description: 'Montagem e organização física do evento.', status: 'Em andamento' },
      { id: 'op-2', name: 'Alimentação', description: 'Apoio de alimentação da organização.', status: 'Não iniciado' },
      { id: 'op-3', name: 'Logística', description: 'Deslocamento de materiais e pessoas.', status: 'Em andamento' },
    ],
    meetings: [
      {
        id: 'reu-1',
        title: 'Reunião demonstrativa 01',
        type: 'Geral',
        responsible: 'Usuário demonstrativo 01',
        date: '2026-10-03',
        start: '08:00',
        end: '12:00',
        place: 'Local demonstrativo',
        agenda: 'Pauta demonstrativa 01',
        participantIds: ['usr-1', 'usr-2'],
        notes: '',
        status: 'Agendada',
        presence: {},
        ata: null,
      },
      {
        id: 'reu-3',
        title: 'Reunião demonstrativa 03',
        type: 'Consultores',
        responsible: 'Usuário demonstrativo 01',
        date: '2026-09-20',
        start: '08:00',
        end: '10:00',
        place: 'Local demonstrativo',
        agenda: 'Pauta demonstrativa 01\nPauta demonstrativa 02',
        participantIds: ['usr-1', 'usr-2', 'usr-3'],
        notes: '',
        status: 'Aguardando manifestações',
        presence: {},
        ata: {
          number: '04',
          discussed: 'Texto demonstrativo dos assuntos discutidos.',
          decisions: 'Decisão demonstrativa 01 · Decisão demonstrativa 02',
          forwards: 'Encaminhamento demonstrativo 01 — Responsável: Usuário demonstrativo',
          observations: '',
          status: 'Aguardando manifestações',
          manifestations: [
            { userId: 'usr-1', name: 'Usuário demonstrativo 01', type: 'De acordo', note: '', at: 'Data demonstrativa' },
            { userId: 'usr-2', name: 'Usuário demonstrativo 02', type: 'Com observação', note: 'Observação demonstrativa sobre um ponto da ata.', at: 'Data demonstrativa' },
          ],
          versions: [{ version: '1.0', date: 'Data demonstrativa', responsible: 'Usuário demonstrativo 01', change: 'Versão inicial' }],
        },
      },
    ],
    decisions: [
      {
        id: 'dec-1',
        title: 'Decisão demonstrativa 01',
        description: 'Descrição demonstrativa da decisão tomada na reunião.',
        meetingId: 'reu-3',
        responsible: 'Usuário demonstrativo 01',
        status: 'Em andamento',
        sector: 'Tecnologia',
        date: '2026-09-20',
        notes: '',
        forwards: [],
      },
    ],
    tasks: [
      {
        id: 'pen-6',
        ref: 'PEN-0006',
        title: 'Instalar painel da entrada',
        description: 'Banner de boas-vindas na entrada principal.',
        sector: 'Produção',
        originSector: 'Marketing',
        involvedSectors: ['Marketing', 'Produção'],
        due: '2026-09-25',
        responsible: 'Usuário demonstrativo 05',
        status: 'Concluído',
        priority: 'Média',
        notes: '',
        createdAt: '25/09/2026',
        history: [
          { id: 'his-1', at: '2026-09-25T14:05:00', type: 'event', authorName: 'Usuário demonstrativo 03', profile: 'Gestor', sector: 'Marketing', text: 'Criou a pendência.' },
          { id: 'his-2', at: '2026-09-25T14:08:00', type: 'event', authorName: 'Usuário demonstrativo 03', profile: 'Gestor', sector: 'Marketing', text: 'Encaminhou esta pendência de Marketing para Produção. Motivo: a instalação depende da equipe de montagem.' },
          { id: 'his-3', at: '2026-09-25T14:17:00', type: 'event', authorName: 'Usuário demonstrativo 05', profile: 'Editor', sector: 'Produção', text: 'Alterou o status: Pendente → Em andamento.' },
          { id: 'his-4', at: '2026-09-25T14:25:00', type: 'comment', authorName: 'Usuário demonstrativo 05', profile: 'Editor', sector: 'Produção', text: 'Precisamos confirmar as dimensões.' },
          { id: 'his-5', at: '2026-09-25T14:29:00', type: 'comment', authorName: 'Usuário demonstrativo 03', profile: 'Gestor', sector: 'Marketing', text: '2,40 m × 1,80 m.' },
          { id: 'his-6', at: '2026-09-25T15:10:00', type: 'comment', authorName: 'Usuário demonstrativo 05', profile: 'Editor', sector: 'Produção', text: 'Instalação concluída.' },
          { id: 'his-7', at: '2026-09-25T15:11:00', type: 'event', authorName: 'Usuário demonstrativo 05', profile: 'Editor', sector: 'Produção', text: 'Concluiu a pendência.' },
        ],
      },
      {
        id: 'pen-7',
        ref: 'PEN-0007',
        title: 'Providenciar projetor reserva para o Dia 3',
        description: 'Originada do chamado de suporte da Sala 03.',
        sector: 'Tecnologia',
        originSector: 'Produção',
        involvedSectors: ['Produção', 'Tecnologia'],
        due: '2026-09-27',
        responsible: 'Usuário demonstrativo 04',
        status: 'Em andamento',
        priority: 'Alta',
        origin: 'Ocorrência OCO-0001',
        sourceOccurrenceId: 'oc-1',
        notes: '',
        createdAt: '26/09/2026',
        history: [
          { id: 'his-8', at: '2026-09-26T09:40:00', type: 'event', authorName: 'Usuário demonstrativo 05', profile: 'Gestor', sector: 'Produção', text: 'Criou a pendência a partir da ocorrência OCO-0001.' },
        ],
      },
      { id: 'pen-1', ref: 'PEN-0001', title: 'Pendência demonstrativa 01', description: 'Descrição demonstrativa da pendência.', sector: 'Recursos Humanos', due: '2026-10-20', responsible: 'Usuário demonstrativo 01', status: 'Pendente', priority: 'Média', notes: '', createdAt: '26/09/2026' },
      { id: 'pen-2', ref: 'PEN-0002', title: 'Pendência demonstrativa 02', description: 'Descrição demonstrativa da pendência.', sector: 'Marketing', due: '2026-10-05', responsible: 'Usuário demonstrativo 03', status: 'Em andamento', priority: 'Alta', notes: '', createdAt: '26/09/2026' },
      { id: 'pen-3', ref: 'PEN-0003', title: 'Pendência demonstrativa 03', description: 'Descrição demonstrativa da pendência.', sector: 'Finanças', due: '2026-09-01', responsible: 'Usuário demonstrativo 02', status: 'Pendente', priority: 'Alta', notes: '', createdAt: '26/09/2026' },
      { id: 'pen-4', ref: 'PEN-0004', title: 'Pendência demonstrativa 04', description: 'Descrição demonstrativa da pendência.', sector: 'Tecnologia', due: '2026-09-15', responsible: 'Usuário demonstrativo 04', status: 'Concluído', priority: 'Baixa', notes: '', createdAt: '26/09/2026' },
      { id: 'pen-5', ref: 'PEN-0005', title: 'Pendência demonstrativa 05', description: 'Descrição demonstrativa da pendência.', sector: 'Produção', due: '2026-10-15', responsible: 'Usuário demonstrativo 05', status: 'Pendente', priority: 'Média', notes: '', createdAt: '26/09/2026' },
    ],
    documents: [
      { id: 'doc-1', name: 'Ata demonstrativa — ATA Nº 04', category: 'Atas', responsible: 'Usuário demonstrativo 01', sector: 'Gestão', description: 'Ata da Reunião demonstrativa 03', version: '1.0', note: 'Versão inicial', date: '26/09/2026', fileName: 'ata-demonstrativa.pdf', history: [{ version: '1.0', date: '26/09/2026', responsible: 'Usuário demonstrativo 01', note: 'Versão inicial' }] },
      { id: 'doc-2', name: 'Contrato demonstrativo 01', category: 'Contratos', responsible: 'Usuário demonstrativo 02', sector: 'Finanças', description: 'Documento demonstrativo.', version: '1.0', note: '', date: '26/09/2026', fileName: 'contrato-demonstrativo.pdf', history: [{ version: '1.0', date: '26/09/2026', responsible: 'Usuário demonstrativo 02', note: 'Versão inicial' }] },
      { id: 'doc-3', name: 'Documento demonstrativo de empresa', category: 'Empresas', responsible: 'Usuário demonstrativo 01', sector: '', description: 'Documento demonstrativo.', version: '1.0', note: '', date: '26/09/2026', fileName: 'empresa-demonstrativa.pdf', history: [{ version: '1.0', date: '26/09/2026', responsible: 'Usuário demonstrativo 01', note: 'Versão inicial' }] },
      { id: 'doc-4', name: 'Documento demonstrativo de desafio', category: 'Desafios', responsible: 'Usuário demonstrativo 01', sector: '', description: 'Documento demonstrativo.', version: '1.0', note: '', date: '26/09/2026', fileName: 'desafio-demonstrativo.pdf', history: [{ version: '1.0', date: '26/09/2026', responsible: 'Usuário demonstrativo 01', note: 'Versão inicial' }] },
      { id: 'doc-5', name: 'Comprovante demonstrativo 01', category: 'Finanças', responsible: 'Usuário demonstrativo 02', sector: 'Finanças', description: 'Comprovante demonstrativo.', version: '1.0', note: 'Comprovante demonstrativo', date: '26/09/2026', fileName: 'comprovante-demonstrativo.pdf', history: [{ version: '1.0', date: '26/09/2026', responsible: 'Usuário demonstrativo 02', note: 'Comprovante demonstrativo' }] },
      { id: 'doc-6', name: 'Peça demonstrativa de marketing', category: 'Marketing', responsible: 'Usuário demonstrativo 03', sector: 'Marketing', description: 'Documento demonstrativo.', version: '1.0', note: '', date: '26/09/2026', fileName: 'marketing-demonstrativo.pdf', history: [{ version: '1.0', date: '26/09/2026', responsible: 'Usuário demonstrativo 03', note: 'Versão inicial' }] },
      { id: 'doc-7', name: 'Relatório demonstrativo 01', category: 'Relatórios', responsible: 'Usuário demonstrativo 01', sector: 'Gestão', description: 'Documento demonstrativo.', version: '1.0', note: '', date: '26/09/2026', fileName: '', history: [{ version: '1.0', date: '26/09/2026', responsible: 'Usuário demonstrativo 01', note: 'Arquivo ainda não anexado' }] },
      { id: 'doc-8', name: 'Documento demonstrativo 08', category: 'Outros', responsible: 'Usuário demonstrativo 01', sector: '', description: 'Documento demonstrativo.', version: '1.0', note: '', date: '26/09/2026', fileName: 'outros-demonstrativo.pdf', history: [{ version: '1.0', date: '26/09/2026', responsible: 'Usuário demonstrativo 01', note: 'Versão inicial' }] },
    ],
    occurrences: [
      { id: 'oc-1', ref: 'OCO-0001', originSector: 'Produção', involvedSectors: ['Produção', 'Tecnologia'], history: [{ id: 'his-9', at: '2026-09-26T09:12:00', type: 'event', authorName: 'Usuário demonstrativo 05', profile: 'Gestor', sector: 'Produção', text: 'Registrou a ocorrência.' }, { id: 'his-10', at: '2026-09-26T09:20:00', type: 'event', authorName: 'Usuário demonstrativo 05', profile: 'Gestor', sector: 'Produção', text: 'Encaminhou esta ocorrência de Produção para Tecnologia. Motivo: problema relacionado ao equipamento.' }, { id: 'his-11', at: '2026-09-26T09:32:00', type: 'comment', authorName: 'Usuário demonstrativo 04', profile: 'Editor', sector: 'Tecnologia', text: 'Projetor sem imagem. Vamos testar o cabo e, se necessário, trocar o aparelho.' }, { id: 'his-12', at: '2026-09-26T09:40:00', type: 'event', authorName: 'Usuário demonstrativo 05', profile: 'Gestor', sector: 'Produção', text: 'Gerou a pendência PEN-0007: Providenciar projetor reserva para o Dia 3.' }], title: 'Chamado demonstrativo 01', category: 'Tecnologia', sector: 'Tecnologia', team: '', description: 'Descrição demonstrativa do chamado de suporte.', place: 'Sala 03', priority: 'Alta', responsible: 'Usuário demonstrativo 04', status: 'Aberta', solution: '', day: '', at: '' },
      { id: 'oc-4', title: 'Ocorrência demonstrativa 04', category: 'Infraestrutura', sector: 'Produção', team: '', description: 'Descrição demonstrativa da ocorrência na Sala 03.', place: 'Sala 03', priority: 'Urgente', responsible: 'Usuário demonstrativo 05', status: 'Aberta', solution: '', day: 2, at: 'Horário demonstrativo' },
    ],
    judges: [
      {
        id: 'jur-1',
        userId: 'usr-7',
        // Atribuição manual demonstrativa (não deriva da empresa).
        assignedTeamIds: teams.slice(0, 2).map((team) => team.id),
        name: 'Jurado demonstrativo 01',
        companyId: 'emp-1',
        companyName: 'Empresa demonstrativa 01',
        cargo: 'Representante',
        email: 'contato@exemplo.com',
        status: 'Ativo',
      },
    ],
    criteria: [
      { id: 'cri-1', name: 'Critério demonstrativo 01', description: 'Descrição demonstrativa — critério configurado pelo Administrador.', type: 'Nota numérica', min: '0', max: '10', weight: '', order: 1, active: true },
      { id: 'cri-2', name: 'Critério demonstrativo 02', description: 'Descrição demonstrativa — critério configurado pelo Administrador.', type: 'Nota numérica', min: '0', max: '10', weight: '', order: 2, active: true },
      { id: 'cri-3', name: 'Critério demonstrativo 03', description: 'Descrição demonstrativa — critério configurado pelo Administrador.', type: 'Nota numérica', min: '0', max: '10', weight: '', order: 3, active: true },
      { id: 'cri-4', name: 'Critério demonstrativo 04', description: 'Descrição demonstrativa — critério configurado pelo Administrador.', type: 'Nota numérica', min: '0', max: '10', weight: '', order: 4, active: true },
    ],
    checkins: [
      { id: 'ck-1', personId: 'alu-1', personName: 'Participante 01', category: 'Participante', turma: 'Breno', day: 1, method: 'QR Code', time: '08:05', responsible: 'Usuário demonstrativo 01', status: 'Presente', note: '' },
      { id: 'ck-2', personId: 'alu-1', personName: 'Participante 01', category: 'Participante', turma: 'Breno', day: 2, method: 'Manual', time: '08:10', responsible: 'Usuário demonstrativo 01', status: 'Presente', note: '' },
      { id: 'ck-3', personId: 'alu-2', personName: 'Participante 02', category: 'Participante', turma: 'Breno', day: 3, method: 'QR Code', time: '08:12', responsible: 'Usuário demonstrativo 01', status: 'Presente', note: '' },
    ],
    evaluations: teams.map((team, index) => ({
      id: `av-${index + 1}`,
      teamId: team.id,
      judgeName: 'Jurado demonstrativo 01',
      scores: { 'cri-1': 8, 'cri-2': 7, 'cri-3': 9, 'cri-4': 6 + (index % 3) },
      notes: 'Avaliação demonstrativa.',
      status: 'concluida',
      at: '26/09/2026',
    })),
    awards: [
      { id: 'pre-1', name: 'Premiação demonstrativa 01', description: 'Prêmio demonstrativo definido pela organização.', team: teams[0] ? teamName(teams[0].id) : '', criterion: 'Resultado dos Jurados' },
      { id: 'pre-2', name: 'Premiação demonstrativa 02', description: 'Prêmio demonstrativo da votação.', team: teams[1] ? teamName(teams[1].id) : '', criterion: 'Votação do Público' },
    ],
    // Pessoas externas sem conta no sistema (só credencial do evento).
    guests: [
      { id: 'gst-1', name: 'Visitante Demonstrativo 01', email: '', category: 'Convidado' },
      { id: 'gst-2', name: 'Professor Demonstrativo 01', email: 'professor@exemplo.com', category: 'Professor' },
      { id: 'gst-3', name: 'Público Demonstrativo 01', email: '', category: 'Público' },
    ],
    credentials: [],
    // Convite de jurado demonstrativo (não utilizado), para apresentar o cadastro público.
    invites: [
      { id: 'conv-demo', code: 'JUR-DEMO-01', demo: true, repId: 'rep-2', repName: 'Representante 02', companyId: 'emp-1', companyName: 'Empresa demonstrativa 01', email: 'rep02@exemplo.com', status: 'Não utilizado', usedBy: '', createdAt: '26/09/2026' },
    ],
    voting: {
      status: 'Encerrada',
      ballots: teams.flatMap((team) => [
        { teamId: team.id, at: '26/09/2026' },
        { teamId: team.id, at: '26/09/2026' },
      ]),
    },
    resultsReleased: true,
    // Registros demonstrativos de auditoria (em produção virão do backend).
    audit: [
      { id: 'log-d1', timestamp: '2026-09-26T09:40:00', userId: 'org-5', userName: 'Usuário demonstrativo 05', profile: 'Gestor', sector: 'Produção', action: 'occurrence.task-generated', label: 'Gerou pendência', module: 'occurrences', entityType: 'Ocorrência', entityId: 'oc-1', entityLabel: 'OCO-0001', description: 'Gerou a pendência PEN-0007 "Providenciar projetor reserva para o Dia 3" (responsável: Tecnologia).', changes: [], metadata: {} },
      { id: 'log-d2', timestamp: '2026-09-26T09:20:00', userId: 'org-5', userName: 'Usuário demonstrativo 05', profile: 'Gestor', sector: 'Produção', action: 'occurrence.forwarded', label: 'Encaminhou ocorrência', module: 'occurrences', entityType: 'Ocorrência', entityId: 'oc-1', entityLabel: 'OCO-0001', description: 'Ocorrência "Chamado demonstrativo 01" encaminhada de Produção para Tecnologia.', changes: [{ field: 'Setor responsável', before: 'Produção', after: 'Tecnologia' }], metadata: { reason: 'Problema relacionado ao equipamento.' } },
      { id: 'log-d3', timestamp: '2026-09-25T15:11:00', userId: 'org-5', userName: 'Usuário demonstrativo 05', profile: 'Editor', sector: 'Produção', action: 'task.completed', label: 'Concluiu pendência', module: 'tasks', entityType: 'Pendência', entityId: 'pen-6', entityLabel: 'PEN-0006', description: 'Pendência "Instalar painel da entrada" concluída.', changes: [{ field: 'Status', before: 'Em andamento', after: 'Concluído' }], metadata: {} },
      { id: 'log-d4', timestamp: '2026-09-25T14:17:00', userId: 'org-5', userName: 'Usuário demonstrativo 05', profile: 'Editor', sector: 'Produção', action: 'task.updated', label: 'Editou pendência', module: 'tasks', entityType: 'Pendência', entityId: 'pen-6', entityLabel: 'PEN-0006', description: 'Pendência "Instalar painel da entrada".', changes: [{ field: 'Status', before: 'Pendente', after: 'Em andamento' }], metadata: {} },
      { id: 'log-d5', timestamp: '2026-09-25T14:08:00', userId: 'org-3', userName: 'Usuário demonstrativo 03', profile: 'Gestor', sector: 'Marketing', action: 'task.forwarded', label: 'Encaminhou pendência', module: 'tasks', entityType: 'Pendência', entityId: 'pen-6', entityLabel: 'PEN-0006', description: 'Pendência "Instalar painel da entrada" encaminhada de Marketing para Produção.', changes: [{ field: 'Setor responsável', before: 'Marketing', after: 'Produção' }], metadata: { reason: 'A instalação depende da equipe de montagem.' } },
      { id: 'log-d6', timestamp: '2026-09-25T14:05:00', userId: 'org-3', userName: 'Usuário demonstrativo 03', profile: 'Gestor', sector: 'Marketing', action: 'task.created', label: 'Criou pendência', module: 'tasks', entityType: 'Pendência', entityId: 'pen-6', entityLabel: 'PEN-0006', description: 'Criou a pendência "Instalar painel da entrada" (responsável: Marketing).', changes: [], metadata: {} },
    ],
    event: {
      ...current.event,
      name: current.event?.name || 'HackLab',
      theme: 'Tema demonstrativo',
      description: 'Descrição demonstrativa do evento.',
      location: 'Local demonstrativo',
      date: '2026-09-26',
      budget: 8000,
    },
  }
}
