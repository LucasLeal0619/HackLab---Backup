// Equipes, empresas e desafios: composição, equilíbrio e relações.
import { TURMAS } from './constants'

export function teamName(id) {
  return `Equipe ${String(id).padStart(2, '0')}`
}

export function availabilityOf(student) {
  return student?.availability || 'Disponível'
}

export function isAvailable(student) {
  return availabilityOf(student) === 'Disponível'
}

export function memberCounts(team, students, { onlyAvailable = false } = {}) {
  const counts = { Breno: 0, Rafael: 0, Clara: 0 }
  for (const id of team.members || []) {
    const student = students.find((item) => item.id === id)
    if (!student) continue
    if (onlyAvailable && !isAvailable(student)) continue
    if (counts[student.turma] !== undefined) counts[student.turma] += 1
  }
  return counts
}

export function activeMembers(team, students) {
  return (team?.members || [])
    .map((id) => students.find((item) => item.id === id))
    .filter((student) => student && isAvailable(student))
}

export function pausedMembers(team, students) {
  return (team?.members || [])
    .map((id) => students.find((item) => item.id === id))
    .filter((student) => student && !isAvailable(student))
}

export function reservedIds(teams, exceptId) {
  const ids = new Set()
  for (const team of teams) {
    if (team.id === exceptId) continue
    ;(team.members || []).forEach((id) => ids.add(id))
  }
  return ids
}

export function balanceLabel(team, students, teams) {
  const size = activeMembers(team, students).length
  if (!size) return 'Pode melhorar'
  const sizes = (teams || []).map((item) => activeMembers(item, students).length).filter((value) => value > 0)
  const gap = sizes.length ? Math.max(...sizes) - Math.min(...sizes) : 0
  const counts = memberCounts(team, students, { onlyAvailable: true })
  const biggest = Math.max(counts.Breno, counts.Rafael, counts.Clara)
  const concentrated = size >= 3 && biggest / size > 0.67
  if (gap <= 1 && !concentrated) return 'Equilibrada'
  return 'Pode melhorar'
}

function shuffle(list) {
  const copy = [...list]
  for (let index = copy.length - 1; index > 0; index -= 1) {
    const swap = Math.floor(Math.random() * (index + 1))
    ;[copy[index], copy[swap]] = [copy[swap], copy[index]]
  }
  return copy
}

function lumpySplit(total, parts) {
  const quotas = Array(parts).fill(0)
  if (!total || parts < 1) return quotas
  if (parts === 1) {
    quotas[0] = total
    return quotas
  }
  const share = 0.5 + Math.random() * 0.35
  const lead = Math.min(total, Math.max(1, Math.round(total * share)))
  quotas[Math.floor(Math.random() * parts)] = lead
  let left = total - lead
  while (left > 0) {
    quotas[Math.floor(Math.random() * parts)] += 1
    left -= 1
  }
  return quotas
}

function fitQuotas(quotas, room, limits) {
  const next = quotas.map((qty, index) => Math.min(qty, room[index], limits[index]))
  let leftover = quotas.reduce((sum, qty) => sum + qty, 0) - next.reduce((sum, qty) => sum + qty, 0)
  while (leftover > 0) {
    let options = next.map((qty, index) => index).filter((index) => next[index] < room[index] && next[index] < limits[index])
    if (!options.length) options = next.map((qty, index) => index).filter((index) => next[index] < room[index])
    if (!options.length) break
    options.sort((a, b) => (room[b] - next[b]) - (room[a] - next[a]))
    next[options[0]] += 1
    leftover -= 1
  }
  return next
}

export function suggestTeams(students, size = 6, { vary = false } = {}) {
  const pool = (students || []).filter(isAvailable)
  if (!pool.length) return []
  const target = Math.max(1, Number(size) || 6)
  const count = Math.max(1, Math.round(pool.length / target))
  const base = Math.floor(pool.length / count)
  const extra = pool.length % count
  const capacities = Array.from({ length: count }, () => base)
  const extraSeats = vary ? shuffle([...capacities.keys()]) : [...capacities.keys()]
  for (let index = 0; index < extra; index += 1) capacities[extraSeats[index]] += 1
  const teams = capacities.map((cap, index) => ({
    id: index + 1,
    status: 'confirmada',
    members: [],
    solution: '',
    cap,
  }))
  const classes = vary ? shuffle(TURMAS) : TURMAS
  classes.forEach((turma, offset) => {
    const people = pool.filter((student) => student.turma === turma.id)
    if (!people.length) return
    if (!vary) {
      people.forEach((student, index) => {
        teams[(index + offset) % count].members.push(student.id)
      })
      return
    }
    const ordered = shuffle(people)
    const others = pool.some((student) => student.turma !== turma.id)
    const room = teams.map((team) => team.cap - team.members.length)
    const limits = room.map((seats, index) => {
      const share = Math.max(1, Math.round(ordered.length * 0.7))
      if (!others || teams[index].cap <= 1) return seats
      return Math.min(seats, share, Math.max(1, teams[index].cap - 1))
    })
    const quotas = fitQuotas(lumpySplit(ordered.length, count), room, limits)
    let cursor = 0
    quotas.forEach((qty, teamIndex) => {
      for (let step = 0; step < qty; step += 1) {
        teams[teamIndex].members.push(ordered[cursor].id)
        cursor += 1
      }
    })
  })
  return teams.map(({ cap, ...team }) => team)
}

export function normalizeTeams(list) {
  if (!Array.isArray(list)) return []
  return list.filter((team) => {
    if ((team.members || []).length) return true
    const legacy = team.model === 'A' || team.model === 'B'
    return !(legacy && (!team.status || team.status === 'nao-formada'))
  })
}

export function companyOf(state, id) {
  return state.companies.find((item) => item.id === id)
}

export function challengeOf(state, id) {
  return state.challenges.find((item) => item.id === id)
}

export function teamChallenge(state, teamId) {
  return state.challenges.find((item) => item.teamId === teamId)
}
