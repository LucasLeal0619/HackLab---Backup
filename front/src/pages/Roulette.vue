<script setup>
import { computed } from 'vue'
import { go, useHack } from '@/js/stores/hack'
import Empty from '../components/Empty.vue'
import Page from '../components/Page.vue'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
})
const { state } = useHack()
const team = computed(() => state.teams.find((item) => String(item.id) === String(props.params.id)) || state.teams[0])
</script>

<template>
  <Page title="Formação de equipes">
    <template #actions><button class="btn ghost" type="button" @click="go('equipes')">Voltar</button></template>
    <Empty title="A formação agora é uma sugestão equilibrada" :text="team ? 'Use Formar equipes para gerar ou ajustar a distribuição. O sorteio por modelo fixo não faz mais parte da formação.' : 'Ainda não há equipes formadas.'">
      <template #action><button class="btn" type="button" @click="go('equipes')">Ir para Equipes</button></template>
    </Empty>
  </Page>
</template>
