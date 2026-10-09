<script setup>
import { onMounted, onUnmounted } from 'vue'
import Icon from './Icon.vue'

defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})
const emit = defineEmits(['close'])

function onKey(event) {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="sheet-back" @mousedown="emit('close')">
    <aside class="sheet" role="dialog" aria-modal="true" :aria-label="title" @mousedown.stop>
      <header>
        <div>
          <h2>{{ title }}</h2>
          <p v-if="subtitle">{{ subtitle }}</p>
        </div>
        <button class="icon-btn" type="button" aria-label="Fechar" title="Fechar" @click="emit('close')"><Icon name="x" :size="16" /></button>
      </header>
      <div class="sheet-body"><slot /></div>
      <footer v-if="$slots.footer"><slot name="footer" /></footer>
    </aside>
  </div>
</template>
