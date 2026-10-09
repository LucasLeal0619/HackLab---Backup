<script setup>
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useChallengeDetail } from '@/js/pages/challenge-detail'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
})
const { state, challenge, company, ask, note, approve, assign, teamId, startAnalysis, registerAsk, confirmApprove, confirmAssign, teamName, go, toneFor } = useChallengeDetail(props)
</script>

<template>
  <Page v-if="!challenge" title="Desafio">
    <Empty title="Nenhum desafio." text="Cadastre um desafio para abrir esta tela." />
  </Page>
  <Page
    v-else
    :crumbs="`HackLab / Organização / Empresas e Desafios / ${challenge.title}`"
    title="Detalhes do Desafio"
    :subtitle="`${challenge.title} · ${company?.name || 'Empresa'}`"
  >
    <template #actions>
      <button class="btn ghost" type="button" @click="go('desafios')">Voltar</button>
      <template v-if="challenge.status === 'Em análise'">
        <button class="btn ghost" @click="ask = true">Solicitar ajustes</button>
        <button class="btn" @click="approve = true">Aprovar</button>
      </template>
      <button v-else-if="challenge.status === 'Aprovado' || challenge.status === 'Distribuído'" class="btn" @click="assign = true">Associar equipe</button>
      <button v-else class="btn ghost" @click="startAnalysis">Iniciar análise</button>
    </template>
    <p class="flow-line">Recebido → Em análise → Aprovado → Distribuído → Em desenvolvimento → Finalizado</p>
    <p>Status atual: <Badge :tone="toneFor(challenge.status)">{{ challenge.status }}</Badge></p>
    <div class="grid cols-2">
      <article class="card">
        <p><b>Empresa</b> {{ company?.name }} <button v-if="company" class="linkish" @click="go(`empresa?id=${company.id}`)">Ver empresa</button></p>
        <p><b>Problema</b><br />{{ challenge.problem || '—' }}</p>
        <p><b>Objetivo</b><br />{{ challenge.objective || '—' }}</p>
        <p><b>Requisitos</b><br />{{ challenge.requirements || '—' }}</p>
        <p><b>Restrições</b><br />{{ challenge.restrictions || '—' }}</p>
        <p><b>Resultado esperado</b><br />{{ challenge.expected || '—' }}</p>
      </article>
      <article class="card">
        <h3>Equipe responsável</h3>
        <p>{{ challenge.teamId ? teamName(challenge.teamId) : 'Este desafio ainda não foi distribuído.' }}</p>
        <h3>Solução da equipe</h3>
        <p>{{ challenge.teamId ? (state.teams.find((team) => team.id === challenge.teamId)?.solution || 'Será preenchido durante o evento.') : 'Solução ainda não iniciada.' }}</p>
        <p v-if="challenge.status === 'Em análise'" class="stat-hint">Aprove o desafio para liberar a associação a uma equipe.</p>
      </article>
    </div>
    <Modal v-if="ask" title="Solicitar ajustes" subtitle="Registre o que precisa ser ajustado. Nenhuma mensagem é enviada neste protótipo." @close="ask = false">
      <Field label="Observação"><textarea v-model="note" class="input" /></Field>
      <template #footer>
        <button class="btn ghost" @click="ask = false">Cancelar</button>
        <button class="btn" @click="registerAsk">Registrar solicitação</button>
      </template>
    </Modal>
    <Modal v-if="approve" title="Aprovar desafio?" subtitle="Após a aprovação, o desafio poderá ser associado a uma equipe." @close="approve = false">
      <p>{{ challenge.title }}<br />{{ company?.name }}<br />Novo status: Aprovado</p>
      <template #footer>
        <button class="btn ghost" @click="approve = false">Cancelar</button>
        <button class="btn" @click="confirmApprove">Aprovar</button>
      </template>
    </Modal>
    <Modal v-if="assign" title="Associar equipe" subtitle="Confirme a equipe que receberá este desafio." @close="assign = false">
      <p><b>Desafio</b> {{ challenge.title }}</p>
      <p><b>Empresa</b> {{ company?.name || '—' }}</p>
      <Field label="Equipe selecionada">
        <select v-model.number="teamId" class="input">
          <option v-for="team in state.teams" :key="team.id" :value="team.id">{{ teamName(team.id) }} · {{ team.status === 'confirmada' ? 'Confirmada' : 'Em formação' }}</option>
        </select>
      </Field>
      <template #footer>
        <button class="btn ghost" @click="assign = false">Cancelar</button>
        <button class="btn" @click="confirmAssign">Confirmar associação</button>
      </template>
    </Modal>
  </Page>
</template>
