<script setup>
import { computed } from 'vue'
import { assignedTeams, companyOf, teamChallenge, teamName } from '@/js/data/model'
import { useHack, go } from '@/js/stores/hack'
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'

const { state } = useHack()
const teams = computed(() => assignedTeams(state))

function teamLabel(team) {
  const challenge = teamChallenge(state, team.id)
  const company = challenge ? companyOf(state, challenge.companyId) : null
  return { challenge, company }
}

// Só a avaliação do próprio jurado: notas e andamento dos demais não aparecem aqui.
function myStatus(teamId) {
  const mine = state.evaluations.find((item) => item.teamId === teamId && item.judgeName === (state.session?.name || 'Jurado'))
  if (!mine) return { label: 'Pendente', tone: '', action: 'Avaliar' }
  if (mine.status === 'concluida') return { label: 'Concluída', tone: 'ok', action: 'Visualizar' }
  if (mine.status === 'revisao') return { label: 'Em revisão', tone: 'warn', action: 'Revisar' }
  return { label: 'Rascunho', tone: 'warn', action: 'Continuar' }
}
</script>

<template>
  <section class="judge-home">
    <header class="judge-head">
      <h1>Minhas avaliações</h1>
      <p>Escolha a equipe para avaliar. Suas notas ficam visíveis apenas para a organização.</p>
    </header>
    <Empty v-if="teams.length === 0" title="Nenhuma avaliação atribuída a você no momento." text="Quando a organização atribuir equipes, elas aparecerão aqui." />
    <ul v-else class="judge-list">
      <li v-for="team in teams" :key="team.id" class="card judge-card">
        <div class="judge-card-main">
          <h2>{{ teamName(team.id) }}</h2>
          <p>{{ teamLabel(team).challenge?.title || 'Desafio a definir' }}</p>
          <p class="stat-hint">{{ teamLabel(team).company?.name || 'Empresa a definir' }}</p>
        </div>
        <Badge :tone="myStatus(team.id).tone">{{ myStatus(team.id).label }}</Badge>
        <button class="btn" :class="{ ghost: myStatus(team.id).action === 'Visualizar' }" type="button" @click="go(`avaliar?id=${team.id}`)">{{ myStatus(team.id).action }}</button>
      </li>
    </ul>
  </section>
</template>
