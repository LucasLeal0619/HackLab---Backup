<script setup>
import { computed, ref } from 'vue'
import { go, useHack } from '@/js/stores/hack'
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'
import Page from '../components/Page.vue'
import Tabs from '../components/Tabs.vue'
import { toneFor } from '@/js/utils/tone'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
})

const DETAIL_TABS = [
  { id: 'geral', label: 'Visão geral' },
  { id: 'reps', label: 'Representantes' },
  { id: 'desafios', label: 'Desafios' },
  { id: 'docs', label: 'Documentos' },
]

const { state } = useHack()
const tab = ref('geral')
const company = computed(() => state.companies.find((item) => item.id === props.params.id) || state.companies[0])
const challenges = computed(() => (company.value ? state.challenges.filter((item) => item.companyId === company.value.id) : []))
</script>

<template>
  <Page v-if="!company" title="Empresa">
    <Empty title="Nenhuma empresa." text="Cadastre uma empresa para ver os detalhes.">
      <template #action>
        <button class="btn" @click="go('empresas')">Voltar</button>
      </template>
    </Empty>
  </Page>
  <Page
    v-else
    :crumbs="`HackLab / Organização / Empresas e Desafios / ${company.name}`"
    :title="company.name"
    subtitle="Detalhes da empresa · dados deste protótipo."
  >
    <template #actions>
      <button class="btn ghost" type="button" @click="go('empresas')">Voltar</button>
      <Badge :tone="toneFor(company.status)">{{ company.status }}</Badge>
      <button class="btn ghost" @click="go('desafios')">Adicionar desafio</button>
    </template>
    <Tabs :tabs="DETAIL_TABS" :model-value="tab" @update:model-value="tab = $event" />
    <div v-if="tab === 'geral'" class="card">
      <div class="grid cols-2">
        <p><b>Segmento</b><br />{{ company.segmento || '—' }}</p>
        <p><b>Participação</b><br />{{ company.tipo || '—' }}</p>
        <p><b>E-mail</b><br />{{ company.email || '—' }}</p>
        <p><b>Telefone</b><br />{{ company.phone || '—' }}</p>
      </div>
      <p>{{ company.description || 'Sem descrição.' }}</p>
    </div>
    <div v-else-if="tab === 'reps'" class="card">
      <p v-for="rep in company.reps" :key="rep.id">{{ rep.name }} · {{ rep.cargo }} {{ rep.principal ? '· Principal' : '' }}<br /><small>{{ rep.email }}</small></p>
      <p class="stat-hint">Representantes são pessoas ligadas à empresa, não a própria empresa.</p>
    </div>
    <div v-else-if="tab === 'desafios'" class="card">
      <p v-for="item in challenges" :key="item.id"><button class="linkish" @click="go(`desafio?id=${item.id}`)">{{ item.title }}</button> · {{ item.status }}</p>
      <p v-if="challenges.length === 0">Nenhum desafio.</p>
    </div>
    <div v-else-if="tab === 'docs'" class="card">
      <p>Nenhum documento anexado a esta empresa. O upload deste protótipo é apenas visual.</p>
    </div>
  </Page>
</template>
