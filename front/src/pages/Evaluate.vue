<script setup>
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useEvaluate } from '@/js/pages/evaluate'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
})
const { isDemo, team, meta, criteria, canCorrect, existing, scores, notes, ask, correct, reason, locked, filled, setScore, persist, applyCorrection, teamName, go } = useEvaluate(props)
</script>

<template>
  <Page v-if="team" :crumbs="`Área do Jurado / ${teamName(team.id)}`" :title="`Avaliar ${teamName(team.id)}`" subtitle="Preencha os critérios e finalize a avaliação.">
    <template #actions>
      <button class="btn ghost" type="button" @click="go('area-jurado')">Voltar às avaliações</button>
    </template>
    <div class="eval-head">
      <div>
        <p><b>Equipe</b> {{ teamName(team.id) }}</p>
        <p><b>Empresa</b> {{ meta.company?.name || '—' }}</p>
        <p><b>Desafio</b> {{ meta.challenge?.title || '—' }}</p>
      </div>
      <div>
        <Badge v-if="locked" tone="ok">Concluída</Badge>
        <Badge v-else-if="existing?.status === 'revisao'" tone="warn">Em revisão</Badge>
        <Badge v-else-if="existing" tone="warn">Em andamento</Badge>
        <Badge v-else>Não iniciada</Badge>
        <p v-if="criteria.length" class="eval-progress">{{ filled }} de {{ criteria.length }} critérios preenchidos</p>
      </div>
    </div>
    <article class="card">
      <h3>Solução apresentada</h3>
      <p>{{ team.solution || 'Resumo ainda não informado pela equipe.' }}</p>
    </article>
    <h3 class="ops-title">Critérios</h3>
    <Empty v-if="criteria.length === 0" title="Nenhum critério configurado." text="Configure os critérios em Jurados e Votação antes de avaliar." />
    <div v-for="item in criteria" :key="item.id" class="eval-row">
      <div>
        <b>{{ item.name }}</b>
        <Badge v-if="isDemo(item)">Dado demonstrativo</Badge>
        <p>{{ item.description || 'Sem descrição.' }}</p>
      </div>
      <Field :label="item.min !== '' && item.max !== '' ? `Avaliação (${item.min} a ${item.max})` : 'Avaliação'">
        <input class="input" type="number" :min="item.min === '' ? undefined : item.min" :max="item.max === '' ? undefined : item.max" :value="scores[item.id] ?? ''" :disabled="locked" @input="setScore(item.id, $event.target.value)" />
      </Field>
      <span class="stat-hint">{{ scores[item.id] !== undefined && scores[item.id] !== '' ? 'Preenchido' : 'Pendente' }}</span>
    </div>
    <Field label="Observações"><textarea v-model="notes" class="input" :disabled="locked" /></Field>
    <div v-if="locked" class="page-actions">
      <p class="stat-hint">Avaliação finalizada.</p>
      <button v-if="canCorrect" class="btn ghost" type="button" @click="correct = true">Corrigir avaliação</button>
    </div>
    <div v-else class="page-actions eval-actions">
      <button class="btn ghost" type="button" :disabled="!criteria.length" @click="persist('rascunho')">Salvar rascunho</button>
      <button class="btn" type="button" :disabled="!criteria.length" @click="ask = true">Finalizar avaliação</button>
    </div>
    <Modal v-if="ask" title="Finalizar avaliação?" subtitle="Revise as informações antes de finalizar a avaliação." @close="ask = false">
      <p>{{ teamName(team.id) }} · {{ filled }} de {{ criteria.length || '—' }} critérios preenchidos</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="ask = false">Continuar avaliando</button>
        <button class="btn" type="button" @click="persist('concluida')">Finalizar</button>
      </template>
    </Modal>
    <Modal v-if="correct" title="Corrigir avaliação" subtitle="A correção administrativa registra o motivo e reabre a avaliação em revisão." @close="correct = false">
      <Field label="Motivo da correção" required><textarea v-model="reason" class="input" /></Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="correct = false">Cancelar</button>
        <button class="btn" type="button" @click="applyCorrection">Salvar</button>
      </template>
    </Modal>
  </Page>
  <Page v-else title="Avaliação" subtitle="Esta equipe não está entre as suas avaliações.">
    <Empty title="Equipe não atribuída a você." text="Volte para Minhas avaliações para ver as equipes que você avalia." />
    <button class="btn" type="button" @click="go('area-jurado')">Voltar às avaliações</button>
  </Page>
</template>
