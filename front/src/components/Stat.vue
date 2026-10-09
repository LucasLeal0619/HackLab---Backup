<script setup>
import Icon from './Icon.vue'

defineProps({
  icon: { type: String, default: '' },
  label: { type: String, default: '' },
  value: { type: [String, Number], default: '' },
  hint: { type: String, default: '' },
})
</script>

<template>
  <article class="card stat">
    <div class="stat-label">
      <span v-if="icon" class="stat-icon"><Icon :name="icon" :size="16" /></span>
      {{ label }}
    </div>
    <div class="stat-value">{{ value }}</div>
    <p v-if="hint" class="stat-hint">{{ hint }}</p>
  </article>
</template>
