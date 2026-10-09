<script setup>
import Modal from '../components/Modal.vue'
import { usePublicVote } from '@/js/pages/public-vote'

const { state, teamLabel, choice, voted, open, confirm, teamName } = usePublicVote()
</script>

<template>
  <section class="vote-wrap">
    <header class="vote-head">
      <h1>Votação do Público</h1>
      <p>Escolha a solução que deseja apoiar.</p>
    </header>

    <div v-if="voted" class="vote-done" role="status">
      <span class="vote-check" aria-hidden="true">✓</span>
      <h2>Voto registrado</h2>
      <p>Seu voto já foi registrado. Obrigado pela participação.</p>
    </div>

    <div v-else-if="state.voting.status === 'Não iniciada'" class="vote-done vote-wait" role="status">
      <span class="vote-check" aria-hidden="true">…</span>
      <h2>Votação ainda não iniciada</h2>
      <p>Aguarde a liberação da votação do público.</p>
    </div>

    <div v-else-if="state.voting.status === 'Encerrada'" class="vote-done vote-wait" role="status">
      <span class="vote-check" aria-hidden="true">–</span>
      <h2>Votação encerrada</h2>
      <p>O período de votação do público foi finalizado.</p>
    </div>

    <template v-else>
      <p v-if="state.teams.length === 0" class="banner">Nenhuma equipe disponível para votação.</p>
      <ul class="vote-board">
        <li v-for="team in state.teams" :key="team.id" class="card vote-card">
          <h2>{{ teamName(team.id) }}</h2>
          <dl>
            <dt>Empresa</dt><dd>{{ teamLabel(team).company?.name || 'A definir' }}</dd>
            <dt>Desafio</dt><dd>{{ teamLabel(team).challenge?.title || 'A definir' }}</dd>
          </dl>
          <p class="vote-summary">{{ (team.solution || '').trim() || 'Resumo da solução ainda não informado.' }}</p>
          <button class="btn vote-btn" type="button" :disabled="!open" @click="choice = team.id">Votar nesta equipe</button>
        </li>
      </ul>
    </template>

    <Modal v-if="choice" title="Confirmar voto" subtitle="Depois de confirmado, o voto não pode ser alterado." @close="choice = null">
      <p class="vote-confirm">{{ teamName(choice) }}</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="choice = null">Voltar</button>
        <button class="btn" type="button" @click="confirm">Confirmar voto</button>
      </template>
    </Modal>
  </section>
</template>
