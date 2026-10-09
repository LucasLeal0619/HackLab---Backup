<script setup>
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useTeams } from '@/js/pages/teams'

const { countText, state, update, open, replace, available, changed, size, applySuggestion, askGenerate, mountManual, activeMembers, balanceLabel, memberCounts, pausedMembers, teamName, TURMAS, go } = useTeams()
</script>

<template>
  <Page title="Equipes" subtitle="Forme e organize as equipes participantes.">
    <template #actions>
      <button class="btn" type="button" @click="open = true">Formar equipes</button>
    </template>
    <p class="stat-hint">O HackLab distribui os participantes disponíveis buscando equilibrar o tamanho das equipes e as turmas. A sugestão é um ponto de partida.</p>
    <div v-if="changed" class="banner warn mt">
      <div>
        <b>A composição das equipes foi alterada porque um participante ficou indisponível.</b>
        <p>Nenhuma equipe foi reorganizada automaticamente.</p>
        <div class="page-actions">
          <button class="btn ghost small" type="button" @click="state.teams[0] && go(`montar?id=${state.teams[0].id}`)">Ajustar manualmente</button>
          <button class="btn small" type="button" @click="replace = true">Gerar nova sugestão</button>
        </div>
      </div>
    </div>
    <div v-if="state.teams.length === 0" class="mt">
      <Empty title="Nenhuma equipe formada" text="Cadastre os participantes disponíveis e gere uma sugestão de formação das equipes.">
        <template #action><button class="btn" type="button" @click="open = true">Formar equipes</button></template>
      </Empty>
    </div>
    <div v-else class="team-grid mt">
      <article v-for="team in state.teams" :key="team.id" class="card">
        <div class="row-between">
          <strong>{{ teamName(team.id) }}</strong>
          <Badge :tone="balanceLabel(team, state.students, state.teams) === 'Equilibrada' ? 'ok' : 'warn'">{{ balanceLabel(team, state.students, state.teams) }}</Badge>
        </div>
        <p>{{ countText(activeMembers(team, state.students).length, 'participante', 'participantes') }}</p>
        <ul class="team-mix">
          <li v-for="turma in TURMAS" :key="turma.id"><span>{{ turma.id }}</span><b>{{ memberCounts(team, state.students, { onlyAvailable: true })[turma.id] }}</b></li>
        </ul>
        <p v-if="pausedMembers(team, state.students).length" class="stat-hint">Atenção. A composição desta equipe mudou.</p>
        <div class="page-actions">
          <button class="btn ghost small" type="button" @click="go(`montar?id=${team.id}`)">Ver equipe</button>
          <button v-if="pausedMembers(team, state.students).length" class="btn ghost small" type="button" @click="go(`montar?id=${team.id}`)">Adicionar participante</button>
        </div>
      </article>
    </div>
    <Modal v-if="open" title="Formar equipes" subtitle="A sugestão usa somente quem está disponível. O tamanho desejado é um objetivo, não uma regra." @close="open = false">
      <p>Participantes disponíveis: {{ available.length }}</p>
      <p v-for="turma in TURMAS" :key="turma.id">{{ turma.id }}: {{ available.filter((student) => student.turma === turma.id).length }}</p>
      <label class="field">
        <span>Tamanho desejado por equipe</span>
        <input class="input" type="number" min="1" :value="size" @input="update((draft) => { draft.teamSize = Math.max(1, Number($event.target.value) || 1) })" />
      </label>
      <p class="stat-hint">Cada sugestão muda a divisão das turmas. As equipes ficam com tamanhos próximos e ninguém disponível fica de fora.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="mountManual">Montar manualmente</button>
        <button class="btn" type="button" @click="askGenerate">Gerar sugestão</button>
      </template>
    </Modal>
    <Modal v-if="replace" title="Gerar nova sugestão?" subtitle="A formação atual será substituída. Essa ação só acontece porque você pediu." @close="replace = false">
      <p>A divisão entre as turmas muda nesta sugestão. Os tamanhos das equipes continuam próximos.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="replace = false">Cancelar</button>
        <button class="btn" type="button" @click="applySuggestion">Gerar sugestão</button>
      </template>
    </Modal>
  </Page>
</template>
