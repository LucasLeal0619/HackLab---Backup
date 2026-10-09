<script setup>
import Icon from './Icon.vue'

defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="input-icon">
    <Icon name="search" :size="16" />
    <input class="input" :value="modelValue" :placeholder="placeholder" @input="emit('update:modelValue', $event.target.value)" />
  </div>
</template>
