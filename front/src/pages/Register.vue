<script setup>
import Logo from '../components/Logo.vue'
import { useRegister } from '@/js/pages/register'

const { OPTIONS, kind, form, errors, invite, submit, choose, go } = useRegister()
</script>

<template>
  <div class="signup">
    <header class="signup-bar">
      <Logo light />
      <button class="btn ghost" type="button" @click="go('login')">Voltar para Login</button>
    </header>
    <main class="signup-main">
      <form class="signup-card" novalidate @submit="submit">
        <h1>Criar cadastro</h1>
        <p class="lead">Cadastre-se para participar da votação pública ou acessar sua área de jurado.</p>

        <fieldset class="signup-kind">
          <legend>Como você participará do Hackathon?</legend>
          <label v-for="item in OPTIONS" :key="item.id" class="signup-option" :class="{ on: kind === item.id }">
            <input type="radio" name="kind" :value="item.id" :checked="kind === item.id" @change="choose(item.id)" />
            <span><b>{{ item.title }}</b><small>{{ item.text }}</small></span>
          </label>
        </fieldset>

        <template v-if="kind">
          <label class="field" :class="{ 'has-error': errors.name }">
            <span>Nome completo<em>*</em></span>
            <input v-model="form.name" class="input" autocomplete="name" />
            <small v-if="errors.name" class="field-error">{{ errors.name }}</small>
          </label>
          <label class="field" :class="{ 'has-error': errors.email }">
            <span>E-mail<em>*</em></span>
            <input v-model="form.email" class="input" type="email" autocomplete="email" inputmode="email" />
            <small v-if="errors.email" class="field-error">
              {{ errors.email }}
              <button v-if="errors.email.startsWith('Já existe')" type="button" class="linkish" @click="go('login')">Ir para Login</button>
            </small>
          </label>
          <label class="field" :class="{ 'has-error': errors.password }">
            <span>Senha<em>*</em></span>
            <input v-model="form.password" class="input" type="password" autocomplete="new-password" />
            <small v-if="errors.password" class="field-error">{{ errors.password }}</small>
          </label>
          <label class="field" :class="{ 'has-error': errors.confirm }">
            <span>Confirmar senha<em>*</em></span>
            <input v-model="form.confirm" class="input" type="password" autocomplete="new-password" />
            <small v-if="errors.confirm" class="field-error">{{ errors.confirm }}</small>
          </label>
          <label v-if="kind === 'Jurado'" class="field" :class="{ 'has-error': errors.code }">
            <span>Código de convite do jurado<em>*</em></span>
            <input v-model="form.code" class="input signup-code" autocomplete="off" autocapitalize="characters" placeholder="JUR-" @input="errors.code = ''" />
            <small v-if="errors.code" class="field-error">{{ errors.code }}</small>
            <small v-else>Enviado pela organização do Hackathon.</small>
          </label>
          <div v-if="invite && invite.status !== 'Utilizado' && !errors.code" class="banner ok signup-invite" role="status">
            <div>
              <b>Convite reconhecido</b>
              <p>Perfil: Jurado<template v-if="invite.companyName"> · Empresa: {{ invite.companyName }}</template></p>
            </div>
          </div>
          <label class="check signup-own" :class="{ 'has-error': errors.own }">
            <input v-model="form.own" type="checkbox" />
            Confirmo que as informações fornecidas são minhas.
          </label>
          <small v-if="errors.own" class="field-error">{{ errors.own }}</small>
          <button class="btn full signup-submit" type="submit">{{ kind === 'Jurado' ? 'Criar cadastro de Jurado' : 'Criar cadastro e continuar' }}</button>
        </template>
        <p class="signup-foot">Já possui cadastro? <button type="button" class="linkish" @click="go('login')">Entrar</button></p>
      </form>
    </main>
  </div>
</template>
