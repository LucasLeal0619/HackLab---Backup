<script setup>
import Field from './Field.vue'
import Icon from './Icon.vue'
import Logo from './Logo.vue'
import Modal from './Modal.vue'
import ProfileMenu from './ProfileMenu.vue'
import { useShell } from '@/js/components/shell'

const props = defineProps({
  path: { type: String, default: '' },
})
const { hack, open, panel, isOpen, toggleGroup, current, session, alertCount, alertItems, spacingOptions, navFor, go } = useShell(props)
</script>

<template>
  <div class="shell">
    <a class="skip" href="#conteudo">Ir para o conteúdo</a>
    <!-- Tablet e celular: a navegação vira drawer, aberto pelo botão ☰ do cabeçalho. -->
    <div v-if="open" class="nav-backdrop" aria-hidden="true" @click="open = false" />
    <aside id="navegacao" class="sidebar" :class="{ open }" aria-label="Navegação">
      <div class="side-brand">
        <Logo />
        <button class="icon-btn nav-close" type="button" aria-label="Fechar menu" title="Fechar menu" @click="open = false"><Icon name="x" /></button>
      </div>
      <div v-for="group in navFor(session?.profile)" :key="group.group" class="nav-group">
        <p class="nav-label">{{ group.group }}</p>
        <template v-for="item in group.items" :key="item.id">
          <button
            v-if="item.children"
            type="button"
            class="nav-item"
            :class="{ open: isOpen(item.id), 'group-on': current.group === item.id }"
            :aria-expanded="isOpen(item.id)"
            @click="toggleGroup(item.id)"
          >
            <Icon :name="item.icon" />
            <span>{{ item.label }}</span>
            <Icon class="nav-caret" name="chevron" :size="16" />
          </button>
          <div v-if="item.children" class="nav-sub" :class="{ open: isOpen(item.id) }" :aria-hidden="isOpen(item.id) ? undefined : 'true'">
            <button
              v-for="child in item.children"
              :key="child.id"
              type="button"
              class="nav-item nav-child"
              :class="{ active: current.item === child.id }"
              :tabindex="isOpen(item.id) ? 0 : -1"
              :aria-current="current.item === child.id ? 'page' : undefined"
              @click="go(child.id); open = false; panel = ''"
            >
              {{ child.label }}
            </button>
          </div>
          <button
            v-else
            type="button"
            class="nav-item"
            :class="{ active: current.item === item.id }"
            :aria-current="current.item === item.id ? 'page' : undefined"
            @click="go(item.id); open = false; panel = ''"
          >
            <Icon :name="item.icon" /> {{ item.label }}
          </button>
        </template>
      </div>
      <div class="side-foot">Protótipo local · dados neste navegador</div>
    </aside>
    <div class="workspace">
      <header class="topbar">
        <button class="icon-btn menu-btn" type="button" aria-label="Abrir menu" title="Abrir menu" aria-controls="navegacao" :aria-expanded="open" @click="open = !open"><Icon name="menu" /></button>
        <span class="top-brand"><b>Hack</b><b class="lab">Lab</b></span>
        <div class="top-tools">
          <span v-if="hack.state.demo" class="demo-badge">Modo demonstração</span>
          <div class="tool">
            <button class="icon-btn" type="button" aria-label="Alertas" title="Alertas" @click="panel = panel === 'alerts' ? '' : 'alerts'">
              <Icon name="bell" />
              <span v-if="alertCount > 0" class="dot" />
            </button>
            <div v-if="panel === 'alerts'" class="popover" role="dialog" aria-label="Alertas">
              <div class="row-between"><h3>Alertas</h3><button class="icon-btn" type="button" aria-label="Fechar" title="Fechar" @click="panel = ''"><Icon name="x" :size="16" /></button></div>
              <p v-if="alertItems.length === 0" class="stat-hint">Tudo certo! Nenhum alerta no momento.</p>
              <div v-else class="menu-list">
                <p v-for="item in alertItems" :key="item">{{ item }}</p>
              </div>
              <button class="btn ghost small" type="button" @click="panel = ''; go('pendencias')">Ver pendências</button>
            </div>
          </div>
          <div class="tool">
            <button class="a11y-btn" type="button" aria-label="Acessibilidade" @click="panel = panel === 'a11y' ? '' : 'a11y'"><Icon name="access" :size="16" /> <span class="a11y-label">Acessibilidade</span></button>
            <div v-if="panel === 'a11y'" class="popover" role="dialog" aria-label="Acessibilidade">
              <div class="row-between"><h3>Acessibilidade</h3><button class="icon-btn" type="button" aria-label="Fechar" title="Fechar" @click="panel = ''"><Icon name="x" :size="16" /></button></div>
              <Field label="Tamanho da fonte" hint="Aumentar ou diminuir o texto">
                <div class="row-between">
                  <button class="btn ghost small" type="button" @click="hack.setA11y({ scale: Math.max(90, hack.state.a11y.scale - 10) })">A−</button>
                  <strong>{{ hack.state.a11y.scale }}%</strong>
                  <button class="btn ghost small" type="button" @click="hack.setA11y({ scale: Math.min(140, hack.state.a11y.scale + 10) })">A+</button>
                </div>
              </Field>
              <label class="check"><input type="checkbox" :checked="hack.state.a11y.contrast" @change="hack.setA11y({ contrast: $event.target.checked })" /> Alto contraste</label>
              <label class="check"><input type="checkbox" :checked="hack.state.a11y.focus" @change="hack.setA11y({ focus: $event.target.checked })" /> Destacar foco</label>
              <label class="check"><input type="checkbox" :checked="hack.state.a11y.motion" @change="hack.setA11y({ motion: $event.target.checked })" /> Reduzir animações</label>
              <Field label="Ajustar espaçamento">
                <div class="chips">
                  <button v-for="[id, label] in spacingOptions" :key="id" type="button" class="chip" :class="{ on: hack.state.a11y.spacing === id }" @click="hack.setA11y({ spacing: id })">{{ label }}</button>
                </div>
              </Field>
              <button class="btn ghost small" type="button" @click="hack.resetA11y()">Restaurar padrão</button>
            </div>
          </div>
          <ProfileMenu />
        </div>
      </header>
      <main id="conteudo" class="content"><slot /></main>
    </div>
    <Modal
      v-if="hack.state.session && !hack.state.welcome"
      title="Como deseja explorar o HackLab?"
      subtitle="Você pode carregar um cenário demonstrativo para conhecer todas as áreas do protótipo ou começar com os dados vazios."
      @close="hack.update((draft) => { draft.welcome = 'vazio' })"
    >
      <p>O cenário demonstrativo fica neste navegador e pode ser carregado de novo em Perfil, Dados demonstrativos.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="hack.update((draft) => { draft.welcome = 'vazio' })">Começar vazio</button>
        <button class="btn" type="button" @click="hack.loadDemo()">Explorar com dados demonstrativos</button>
      </template>
    </Modal>
  </div>
</template>
