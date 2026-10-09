<script setup>
import { ref } from 'vue'

// Barra de filtros: no desktop fica em linha; no celular mostra a busca e o botão "Filtros",
// que abre os filtros avançados em um painel (evita vários selects empilhados acima da lista).
defineProps({
  active: { type: Number, default: 0 },
})
const emit = defineEmits(['clear'])
const open = ref(false)
</script>

<template>
  <div class="filter-panel" :class="{ open }">
    <div class="filter-main">
      <slot name="search" />
      <button class="btn ghost filter-toggle" type="button" :aria-expanded="open" @click="open = !open">
        Filtros<span v-if="active"> ({{ active }})</span>
      </button>
    </div>
    <div class="filter-more">
      <slot />
      <div class="filter-panel-actions">
        <button v-if="active" class="btn ghost" type="button" @click="emit('clear')">Limpar filtros</button>
        <button class="btn" type="button" @click="open = false">Aplicar</button>
      </div>
    </div>
  </div>
</template>
