<script setup>
import Logo from '../components/Logo.vue'
import { usePresentation } from '@/js/pages/presentation'

const { PRESENT_STEPS, state, step, technical, totalVotes, current, votedRows, goStep, teamName, go } = usePresentation()
</script>

<template>
  <div class="present">
    <div class="present-top">
      <Logo />
      <button class="btn ghost present-exit" type="button" @click="go('painel')">Sair do modo apresentação</button>
    </div>
    <div class="present-stage">
      <div v-if="current === 'abertura'">
        <h1>Resultados HackLab</h1>
        <p>Apresentação dos resultados do Hackathon.</p>
      </div>
      <div v-if="current === 'jurados'" class="present-block">
        <h1>Resultado dos Jurados</h1>
        <p v-if="!state.resultsReleased">Resultados ainda não divulgados</p>
        <p v-else-if="technical.length === 0">Ainda não há resultado técnico registrado.</p>
        <div v-else class="present-cards">
          <article v-for="item in technical" :key="item.team.id" class="present-card">
            <h3>{{ teamName(item.team.id) }}</h3>
            <p>{{ item.company?.name || '—' }} · {{ item.challenge?.title || '—' }}</p>
            <p>Resultado técnico {{ item.avg.toFixed(1) }}</p>
          </article>
        </div>
      </div>
      <div v-if="current === 'publico'" class="present-block">
        <h1>Resultado da Votação do Público</h1>
        <p v-if="!state.resultsReleased">Resultados ainda não divulgados</p>
        <p v-else-if="totalVotes === 0">Nenhum voto registrado.</p>
        <div v-else class="present-cards">
          <article v-for="item in votedRows" :key="item.team.id" class="present-card">
            <h3>{{ teamName(item.team.id) }}</h3>
            <p>{{ item.votes }} voto(s)</p>
            <p>{{ Math.round((item.votes / totalVotes) * 100) }}%</p>
          </article>
        </div>
      </div>
      <div v-if="current === 'premiacao'" class="present-block">
        <h1>Premiação</h1>
        <p v-if="state.awards.length === 0">A definir</p>
        <div v-else class="present-cards">
          <article v-for="item in state.awards" :key="item.id" class="present-card">
            <h3>{{ item.name }}</h3>
            <p>{{ item.team || 'A definir' }}</p>
            <p>{{ item.description || '—' }}</p>
          </article>
        </div>
      </div>
      <div v-if="current === 'fim'">
        <h1>HackLab</h1>
        <p>Obrigado pela participação!</p>
      </div>
    </div>
    <div class="present-nav">
      <button class="btn ghost" type="button" :disabled="step === 0" @click="goStep(step - 1)">Anterior</button>
      <span>{{ step + 1 }} de {{ PRESENT_STEPS.length }}</span>
      <button class="btn" type="button" :disabled="step === PRESENT_STEPS.length - 1" @click="goStep(step + 1)">Próximo</button>
    </div>
  </div>
</template>
