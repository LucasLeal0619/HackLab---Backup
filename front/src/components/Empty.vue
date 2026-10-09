<script setup>
import Icon from './Icon.vue'

defineProps({
  title: { type: String, default: '' },
  text: { type: String, default: '' },
})
</script>

<template>
  <div class="empty">
    <div class="empty-icon" aria-hidden="true"><Icon name="info" :size="18" /></div>
    <h3>{{ title }}</h3>
    <p v-if="text">{{ text }}</p>
    <slot name="action" />
  </div>
</template>
