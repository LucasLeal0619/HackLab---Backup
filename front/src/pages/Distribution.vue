<script setup>
import { computed } from 'vue'
import { companyOf, teamName } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'
import Badge from '../components/Badge.vue'
import Page from '../components/Page.vue'
import { toneFor } from '@/js/utils/tone'

const { state } = useHack()
const cards = computed(() => state.teams.map((team) => {
  const challenge = state.challenges.find((item) => item.teamId === team.id)
  const company = challenge ? companyOf(state, challenge.companyId) : null
  return {
    id: team.id,
    name: teamName(team.id),
    company: company?.name || '—',
    title: challenge?.title || '—',
    badge: challenge ? challenge.status : 'Sem desafio',
    tone: toneFor(challenge ? challenge.status : 'Aguardando'),
    hint: challenge?.status === 'Em desenvolvimento' ? (team.solution || 'Solução em andamento.') : 'Solução ainda não iniciada.',
  }
}))
</script>

<template>
  <Page
    crumbs="HackLab / Organização / Empresas e Desafios / Distribuição"
    title="Distribuição dos Desafios"
    subtitle="Visão do Dia 1 · relação entre equipes, empresas e desafios. No Dia 1 as soluções ainda não são exibidas."
  >
    <template #actions>
      <button class="btn ghost" @click="go('desafios')">Voltar</button>
    </template>
    <div class="grid cols-2">
      <article v-for="card in cards" :key="card.id" class="card">
        <h3>{{ card.name }}</h3>
        <p>Empresa: {{ card.company }}</p>
        <p>Desafio: {{ card.title }}</p>
        <Badge :tone="card.tone">{{ card.badge }}</Badge>
        <p class="stat-hint">{{ card.hint }}</p>
      </article>
    </div>
  </Page>
</template>
