<script setup>
import Icon from './Icon.vue'
import { useProfileMenu } from '@/js/components/profile-menu'

const { hack, open, root, session, config, activeSector, external, choose, chooseSector, run, PROFILE_NAMES, SETORES, go } = useProfileMenu()
</script>

<template>
  <div ref="root" class="tool profile-tool">
    <button class="user-btn" type="button" :aria-expanded="open" aria-haspopup="dialog" @click="open = !open">
      <span class="avatar"><Icon name="users" :size="16" /></span>
      <span class="user-meta">
        <b>{{ session?.name || 'Usuário' }}</b>
        <span><span class="status-dot" /> {{ session?.profile }}{{ activeSector ? ` · ${activeSector}` : "" }}</span>
      </span>
    </button>
    <div v-if="open" class="profile-back" aria-hidden="true" @click="open = false" />
    <div v-if="open" class="popover profile-menu" role="dialog" aria-label="Meu perfil">
      <div class="row-between"><h3>Meu perfil</h3><button class="icon-btn" type="button" aria-label="Fechar" title="Fechar" @click="open = false"><Icon name="x" :size="16" /></button></div>
      <p class="profile-who"><b>{{ session?.name }}</b><span>{{ session?.email }}</span></p>
      <p class="profile-current"><span>Perfil atual</span><b>{{ session?.profile }}{{ activeSector ? ` · ${activeSector}` : "" }}</b><small>{{ config.represents }}</small></p>
      <button v-if="external && hack.state.demo" class="linkish profile-demo-exit" type="button" @click="choose('Administrador')">Modo demonstração: voltar ao Administrador</button>
      <p v-if="!external" class="stat-hint">Protótipo: selecione um perfil para visualizar sua experiência no HackLab.</p>
      <div v-if="!external" class="profile-grid" role="group" aria-label="Perfis de acesso">
        <button v-for="name in PROFILE_NAMES" :key="name" type="button" class="profile-option" :class="{ on: session?.profile === name }" :aria-pressed="session?.profile === name" @click="choose(name)">{{ name }}</button>
      </div>
      <template v-if="config.sectorScoped">
        <p class="profile-label">Setor demonstrativo</p>
        <div class="chips">
          <button v-for="name in SETORES" :key="name" type="button" class="chip" :class="{ on: activeSector === name }" @click="chooseSector(name)">{{ name }}</button>
        </div>
      </template>
      <div class="menu-list">
        <template v-if="config.globalAdmin">
          <button type="button" @click="run(() => go('config'))">Configuração do evento</button>
          <button type="button" @click="run(hack.loadDemo)">Dados demonstrativos</button>
          <button type="button" @click="run(hack.resetAll)">Limpar dados do protótipo</button>
        </template>
        <button type="button" @click="run(hack.logout)"><Icon name="logout" :size="16" /> Sair</button>
      </div>
    </div>
  </div>
</template>
