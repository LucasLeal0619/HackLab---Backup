<script setup>
defineProps({
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  error: { type: String, default: '' },
  className: { type: String, default: '' },
})
</script>

<template>
  <label class="field" :class="[className, { 'has-error': error }]">
    <span>{{ label }}<em v-if="required">*</em></span>
    <slot />
    <small v-if="error" class="field-error">{{ error }}</small>
    <small v-else-if="hint">{{ hint }}</small>
  </label>
</template>
