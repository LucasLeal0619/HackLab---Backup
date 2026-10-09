<script setup>
import { useIcon } from '@/js/components/icon'

defineProps({
  name: { type: String, default: 'info' },
  size: { type: Number, default: 18 },
})
const { PATHS } = useIcon()
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path :d="PATHS[name] || PATHS.info" />
  </svg>
</template>
