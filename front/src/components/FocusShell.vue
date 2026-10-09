<script setup>
import { useHack } from '@/js/stores/hack'
import Logo from './Logo.vue'
import ProfileMenu from './ProfileMenu.vue'

defineProps({
  title: { type: String, default: '' },
})

const hack = useHack()
</script>

<template>
  <div class="focus-app focus-shell">
    <a class="skip" href="#conteudo">Ir para o conteúdo</a>
    <header class="focus-bar">
      <Logo light />
      <strong class="focus-title">{{ title }}</strong>
      <span v-if="hack.state.demo" class="demo-badge">Modo demonstração</span>
      <div class="top-tools focus-tools">
        <ProfileMenu />
        <button class="btn ghost focus-exit" type="button" @click="hack.logout()">Sair</button>
      </div>
    </header>
    <main id="conteudo"><slot /></main>
  </div>
</template>
