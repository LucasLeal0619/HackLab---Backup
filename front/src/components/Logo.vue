<script setup>
// Importadas pelo Vite: o endereço final funciona em qualquer servidor ou subpasta.
import logoColor from '@/assets/senac-logo-color.png'
import logoWhite from '@/assets/senac-logo-white.png'

defineProps({
  light: { type: Boolean, default: false },
})
</script>

<template>
  <div class="logo" :class="{ 'on-light': light }">
    <img class="logo-img" :src="light ? logoColor : logoWhite" alt="Senac" />
    <div class="logo-name"><span class="hack">Hack</span><span class="lab">Lab</span></div>
  </div>
</template>
