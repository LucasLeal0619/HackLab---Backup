<script setup>
import { ref } from 'vue'
import { ACCOUNTS } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'
import Field from '../components/Field.vue'
import Icon from '../components/Icon.vue'
import Logo from '../components/Logo.vue'
import Modal from '../components/Modal.vue'

const { login } = useHack()
const remembered = localStorage.getItem('hacklab.remember') || ''
const email = ref(remembered)
const password = ref('')
const remember = ref(Boolean(remembered))
const show = ref(false)
const error = ref('')
const forgot = ref(false)
const a11y = ref(false)

function submit(event) {
  event.preventDefault()
  error.value = login(email.value, password.value, remember.value)
}
</script>

<template>
  <div class="login">
    <section class="login-side">
      <Logo />
      <div class="login-copy">
        <div class="bar" />
        <h1>Organização do Hackathon em um só lugar.</h1>
        <p>Planejamento, acompanhamento durante o evento e consulta de resultados, documentos e presença.</p>
      </div>
      <div class="login-foot">Hackathon Senac · Plataforma de gestão</div>
    </section>
    <section class="login-main">
      <div class="login-tools">
        <button class="a11y-btn" type="button" @click="a11y = true"><Icon name="access" :size="16" /> Acessibilidade</button>
      </div>
      <form class="login-card" @submit="submit">
        <h2>Bem-vindo ao HackLab</h2>
        <p class="lead">Acesse sua conta para acompanhar e gerenciar a organização do Hackathon.</p>
        <p v-if="error" class="error">{{ error }}</p>
        <Field label="E-mail">
          <div class="input-icon">
            <Icon name="mail" :size="16" />
            <input v-model="email" class="input" type="email" placeholder="Digite seu e-mail" required />
          </div>
        </Field>
        <Field label="Senha">
          <div class="input-icon">
            <Icon name="lock" :size="16" />
            <input v-model="password" class="input" :type="show ? 'text' : 'password'" placeholder="Digite sua senha" required />
            <button type="button" class="eye" :aria-label="show ? 'Ocultar senha' : 'Mostrar senha'" @click="show = !show"><Icon name="eye" :size="16" /></button>
          </div>
        </Field>
        <div class="login-row">
          <label class="check"><input v-model="remember" type="checkbox" /> Lembrar de mim</label>
          <button type="button" class="linkish" @click="forgot = true">Esqueci minha senha</button>
        </div>
        <button class="btn full" type="submit">Entrar</button>
        <div class="login-public">
          <span>Ainda não possui cadastro?</span>
          <button class="btn ghost full" type="button" @click="go('cadastro')">Criar cadastro público</button>
          <small>Para votantes e jurados convidados. Contas da organização são criadas pelo Administrador.</small>
        </div>
        <div class="demo-accounts">
          <span><strong>Contas demonstrativas</strong> — qualquer senha com 4 ou mais caracteres.</span>
          <div class="demo-list">
            <div v-for="(info, account) in ACCOUNTS" :key="account">
              <button type="button" @click="email = account; password = 'hacklab'">{{ account }}</button>
              <span>· {{ info.profile }}</span>
            </div>
          </div>
        </div>
      </form>
    </section>
    <Modal v-if="forgot" title="Esqueci minha senha" subtitle="Representação visual — sem envio real de mensagens." @close="forgot = false">
      <p>Neste protótipo a recuperação de senha não envia e-mail. Use uma conta demonstrativa, por exemplo <b>admin@senac.br</b>, com a senha <b>hacklab</b>.</p>
      <template #footer><button class="btn" type="button" @click="forgot = false">Entendi</button></template>
    </Modal>
    <Modal v-if="a11y" title="Acessibilidade" subtitle="As preferências completas ficam disponíveis depois do login." @close="a11y = false">
      <p>Alto contraste, tamanho da fonte, foco reforçado, redução de animações e espaçamento podem ser ajustados no topo de qualquer tela depois de entrar.</p>
      <template #footer><button class="btn" type="button" @click="a11y = false">Fechar</button></template>
    </Modal>
  </div>
</template>
