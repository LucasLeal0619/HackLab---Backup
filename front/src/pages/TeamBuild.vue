<script setup>
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import Tabs from '../components/Tabs.vue'
import { useTeamBuild } from '@/js/pages/team-build'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
})
const { countText, availabilityOfSafe, state, query, turma, blocked, team, active, paused, counts, pool, tabs, add, remove, transfer, balanceLabel, teamName, go } = useTeamBuild(props)
</script>

<template>
  <Page v-if="!team" title="Equipe">
    <template #actions><button class="btn ghost" type="button" @click="go('equipes')">Voltar</button></template>
    <Empty title="Nenhuma equipe formada" text="Gere uma sugestão ou comece uma equipe manualmente.">
      <template #action><button class="btn" type="button" @click="go('equipes')">Ir para Equipes</button></template>
    </Empty>
  </Page>
  <Page v-else :crumbs="`Equipes / ${teamName(team.id)}`" :title="teamName(team.id)" subtitle="Ajuste a equipe manualmente. A sugestão automática não impede essas alterações.">
    <template #actions><button class="btn ghost" type="button" @click="go('equipes')">Voltar</button></template>
    <div class="card">
      <div class="row-between">
        <h3>{{ countText(active.length, 'participante disponível', 'participantes disponíveis') }}</h3>
        <Badge :tone="balanceLabel(team, state.students, state.teams) === 'Equilibrada' ? 'ok' : 'warn'">{{ balanceLabel(team, state.students, state.teams) }}</Badge>
      </div>
      <p class="stat-hint">Breno {{ counts.Breno }} · Rafael {{ counts.Rafael }} · Clara {{ counts.Clara }}</p>
      <p class="stat-hint">Tamanho desejado: {{ state.teamSize || 6 }}. Esse número é uma referência.</p>
      <p v-if="paused.length">Atenção. A composição desta equipe mudou.</p>
    </div>
    <div class="split mt">
      <section class="card">
        <h3>Participantes disponíveis</h3>
        <Tabs :tabs="tabs" :model-value="turma" @update:model-value="turma = $event" />
        <input v-model="query" class="input" placeholder="Buscar participante" aria-label="Buscar participante" />
        <p v-if="pool.length === 0" class="stat-hint">Nenhum participante disponível nesta visão.</p>
        <div v-for="student in pool" :key="student.id" class="person">
          <span>{{ student.name }}<br /><small>{{ student.turma }}</small></span>
          <button class="btn ghost small" type="button" @click="add(student)">Adicionar</button>
        </div>
      </section>
      <aside class="card">
        <h3>Nesta equipe</h3>
        <p v-if="active.length === 0">Nenhum participante disponível nesta equipe.</p>
        <div v-for="student in active" :key="student.id" class="person">
          <span>{{ student.name }}<br /><small>{{ student.turma }}</small></span>
          <button class="btn ghost small" type="button" @click="remove(student.id)">Remover</button>
        </div>
        <template v-if="paused.length">
          <h3>Fora da formação</h3>
          <div v-for="student in paused" :key="student.id" class="person">
            <span>{{ student.name }}<br /><small>{{ availabilityOfSafe(student) }}</small></span>
            <button class="btn ghost small" type="button" @click="remove(student.id)">Remover</button>
          </div>
        </template>
      </aside>
    </div>
    <Modal v-if="blocked" title="Participante já está em outra equipe" subtitle="Remova ou transfira antes de colocá-lo nesta equipe." @close="blocked = null">
      <p><b>{{ blocked.student.name }}</b> já pertence à {{ blocked.owner ? teamName(blocked.owner.id) : 'outra equipe' }}.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="blocked = null">Escolher outro</button>
        <button v-if="blocked.owner" class="btn" type="button" @click="transfer">Transferir para esta equipe</button>
      </template>
    </Modal>
  </Page>
</template>
