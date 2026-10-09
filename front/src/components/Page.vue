<script setup>
import { computed } from 'vue'
import Next from './Next.vue'

const props = defineProps({
  crumbs: { type: String, default: '' },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  next: { type: Object, default: null },
})

const parts = computed(() => String(props.crumbs || '').split(/\s*\/\s*/).filter(Boolean))
</script>

<template>
  <div class="page">
    <nav v-if="parts.length" class="crumbs" aria-label="Localização">
      <span v-for="(part, index) in parts" :key="`${part}-${index}`" class="crumb">
        <span v-if="index > 0" class="crumb-sep" aria-hidden="true">/</span>
        <b v-if="index === parts.length - 1">{{ part }}</b>
        <template v-else>{{ part }}</template>
      </span>
    </nav>
    <div class="page-head">
      <div class="page-title">
        <h1>{{ title }}</h1>
        <p v-if="subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="page-actions"><slot name="actions" /></div>
    </div>
    <slot />
    <Next v-if="next" :label="next.label" :to="next.to" />
  </div>
</template>
