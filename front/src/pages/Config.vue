<script setup>
import Badge from '../components/Badge.vue'
import Field from '../components/Field.vue'
import Icon from '../components/Icon.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useConfig } from '@/js/pages/config'

defineProps({
  section: { type: String, default: 'evento' },
})
const { MATRIX, state, admin, dated, form, modal, user, query, profile, showPassword, fields, sectorLabel, INTERNAL_PROFILES, audience, externalUsers, visible, setAudience, saveEvent, openCreate, openEdit, saveUser, toggleSector, PROFILE_NAMES, profileConfig, SETORES, isExternalProfile, toneFor, profileOptions, setUserStatus } = useConfig()
</script>

<template>
  <Page
    :title="section === 'usuarios' ? 'Usuários e Permissões' : 'Evento'"
    :subtitle="section === 'usuarios' ? 'Cadastre usuários e consulte a matriz de acesso demonstrativa.' : 'Configure as informações gerais do Hackathon.'"
  >
    <form v-if="section !== 'usuarios'" @submit="saveEvent">
      <section class="card form-section">
        <h3>Informações gerais</h3>
        <div class="form-grid">
          <Field label="Nome" required><input v-model="form.name" class="input" /></Field>
          <Field label="Tema" hint="Opcional."><input v-model="form.theme" class="input" placeholder="Informe o tema" /></Field>
          <Field label="Descrição" class-name="span-2"><textarea v-model="form.description" class="input" placeholder="Descreva brevemente o Hackathon." /></Field>
          <Field label="Local"><input v-model="form.location" class="input" /></Field>
        </div>
      </section>
      <section class="card form-section">
        <h3>Data e horário</h3>
        <label class="check">
          <input type="checkbox" :checked="!dated" @change="dated = !$event.target.checked; if ($event.target.checked) form.date = ''" />
          Data ainda não definida
        </label>
        <Field v-if="dated" label="Data"><input v-model="form.date" class="input" type="date" /></Field>
        <p v-else class="banner">A definir</p>
        <div class="grid cols-3 mt">
          <div><span class="stat-hint">Duração</span><p><b>3 dias</b></p></div>
          <div><span class="stat-hint">Início</span><p><b>08:00</b></p></div>
          <div><span class="stat-hint">Término</span><p><b>12:00</b></p></div>
        </div>
      </section>
      <section class="card form-section">
        <h3>Estrutura do evento</h3>
        <div class="day-cards">
          <div class="day-card"><b>Dia 1</b>Abertura e formação.</div>
          <div class="day-card"><b>Dia 2</b>Desenvolvimento.</div>
          <div class="day-card"><b>Dia 3</b>Apresentações e resultados.</div>
        </div>
      </section>
      <div class="page-actions" style="margin-top: 12px">
        <button type="button" class="btn ghost" @click="form = { ...state.event }; dated = Boolean(state.event.date)">Cancelar</button>
        <button class="btn" type="submit" :disabled="!admin">Salvar alterações</button>
      </div>
      <p v-if="!admin" class="stat-hint">Este perfil consulta a configuração. Só o Administrador salva alterações.</p>
    </form>
    <div v-else>
      <div class="tabs" role="tablist">
        <button type="button" role="tab" :aria-selected="audience === 'internos'" :class="{ on: audience === 'internos' }" @click="setAudience('internos')">Usuários internos</button>
        <button type="button" role="tab" :aria-selected="audience === 'externos'" :class="{ on: audience === 'externos' }" @click="setAudience('externos')">Cadastros externos</button>
      </div>
      <div class="page-actions" style="margin-bottom: 12px">
        <button class="btn ghost" type="button" @click="modal = 'matriz'">Ver matriz de acesso</button>
        <button v-if="admin && audience === 'internos'" class="btn" type="button" @click="openCreate">Novo usuário</button>
        <Badge v-else-if="!admin">Consulta</Badge>
      </div>
      <p v-if="audience === 'externos'" class="attendance-summary">
        <span>Jurados <b>{{ externalUsers.filter((item) => item.profile === 'Jurado').length }}</b></span>
        <span>Votantes cadastrados <b>{{ externalUsers.filter((item) => item.profile === 'Votante').length }}</b></span>
        <span class="stat-hint">Jurados entram por convite (Encerramento → Jurados); votantes, pelo cadastro público.</span>
      </p>
      <div class="filters">
        <input v-model="query" class="input" placeholder="Buscar usuário" />
        <select v-model="profile" class="input">
          <option v-for="item in ['Todos', ...profileOptions]" :key="item">{{ item }}</option>
        </select>
      </div>
      <div v-if="audience === 'externos'" class="table-wrap">
        <table>
          <thead><tr><th>Pessoa</th><th>Perfil</th><th>E-mail</th><th>Origem</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>
            <tr v-if="visible.length === 0"><td colspan="6">Nenhum cadastro externo.</td></tr>
            <tr v-for="item in visible" :key="item.id">
              <td>{{ item.name }}<template v-if="item.profile === 'Jurado' && sectorLabel(item) !== '—'"><br /><small class="stat-hint">{{ sectorLabel(item) }}</small></template></td>
              <td>{{ item.profile }}</td>
              <td>{{ item.email }}</td>
              <td>{{ item.origin || 'Cadastro público' }}</td>
              <td><Badge :tone="toneFor(item.status)">{{ item.status }}</Badge></td>
              <td><button class="btn ghost small" type="button" @click="user = { ...item }; modal = 'detalhe'">Ver</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="table-wrap">
        <table>
          <thead><tr><th>Usuário</th><th>Perfil</th><th>Setor</th><th>Função</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>
            <tr v-for="item in visible" :key="item.id">
              <td>{{ item.name }}<br /><small class="stat-hint">{{ item.email }}</small></td>
              <td>{{ item.profile }}</td>
              <td>{{ sectorLabel(item) }}</td>
              <td>{{ item.role || '—' }}</td>
              <td><Badge :tone="toneFor(item.status)">{{ item.status }}</Badge></td>
              <td>
                <button class="btn ghost small" type="button" @click="user = { ...item }; modal = 'detalhe'">Ver</button>
                <button v-if="admin" class="btn ghost small" type="button" @click="openEdit(item)">Editar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="stat-hint">{{ visible.length }} registros · dados salvos neste navegador</p>
    </div>

    <Modal v-if="modal === 'matriz'" title="Matriz de acesso" subtitle="Representação visual da estrutura geral de acesso. As permissões detalhadas serão definidas nas specs de cada módulo." wide @close="modal = null">
      <div class="table-wrap">
        <table class="matrix">
          <thead><tr><th>Módulo</th><th v-for="name in PROFILE_NAMES" :key="name">{{ name }}</th></tr></thead>
          <tbody>
            <tr v-for="row in MATRIX" :key="row[0]">
              <td v-for="(cell, index) in row" :key="`${row[0]}-${index}`">{{ cell }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <template #footer><button class="btn" type="button" @click="modal = null">Fechar</button></template>
    </Modal>

    <Modal v-if="modal === 'user'" :title="user.id ? 'Editar usuário' : 'Novo usuário'" :subtitle="profileConfig(user.profile).represents" @close="modal = null">
      <div class="form-grid">
        <Field label="Nome completo" required><input v-model="user.name" class="input" /></Field>
        <Field label="E-mail" required><input v-model="user.email" class="input" /></Field>
        <Field label="Perfil de acesso" required>
          <select v-model="user.profile" class="input">
            <option v-for="item in INTERNAL_PROFILES" :key="item">{{ item }}</option>
          </select>
        </Field>
        <Field label="Status">
          <select v-model="user.status" class="input"><option>Ativo</option><option>Inativo</option></select>
        </Field>
        <p v-if="fields.note" class="banner span-2">{{ fields.note }}</p>
        <Field v-if="fields.sector === 'optional'" label="Setor" hint="Opcional para Consultor.">
          <select v-model="user.sector" class="input">
            <option value="">Nenhum</option>
            <option v-for="item in ['Acompanhamento', ...SETORES]" :key="item">{{ item }}</option>
          </select>
        </Field>
        <Field v-if="fields.company" label="Empresa / representação" hint="Relaciona o jurado a uma empresa cadastrada.">
          <select v-model="user.companyId" class="input">
            <option value="">Nenhuma</option>
            <option v-for="item in state.companies" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
        </Field>
        <Field v-if="fields.role" label="Função" :required="fields.role === 'required'" hint="Função exercida no Hackathon (independente do perfil)."><input v-model="user.role" class="input" :placeholder="fields.roleHint" /></Field>
        <Field :label="user.id ? 'Nova senha' : 'Senha'" :required="!user.id" class-name="span-2" :hint="user.id ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de 4 caracteres. Fica salva só neste navegador.'">
          <div class="input-icon">
            <Icon name="lock" :size="16" />
            <input v-model="user.password" class="input" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" />
            <button type="button" class="eye" :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'" @click="showPassword = !showPassword"><Icon name="eye" :size="16" /></button>
          </div>
        </Field>
        <div v-if="fields.sector === 'required'" class="field span-2">
          <span>Setores atribuídos<em>*</em></span>
          <small class="stat-hint">{{ user.profile }} enxerga apenas estes setores. Liderar um setor não dá acesso global.</small>
          <div class="chips">
            <button v-for="item in SETORES" :key="item" type="button" class="chip" :class="{ on: user.sectors?.includes(item) }" @click="toggleSector(item)">{{ item }}</button>
          </div>
        </div>
      </div>
      <template #footer>
        <button class="btn ghost" type="button" @click="modal = null">Cancelar</button>
        <button class="btn" type="button" @click="saveUser">{{ user.id ? 'Salvar alterações' : 'Cadastrar usuário' }}</button>
      </template>
    </Modal>

    <Modal v-if="modal === 'detalhe'" title="Detalhes do usuário" :subtitle="user.email" @close="modal = null">
      <p><b>Nome</b> {{ user.name }}<br /><b>Perfil</b> {{ user.profile }}<br /><template v-if="isExternalProfile(user.profile)"><b>Origem</b> {{ user.origin || 'Cadastro público' }}<br /><b>{{ user.profile === 'Jurado' ? 'Empresa' : 'Categoria' }}</b> {{ user.profile === 'Jurado' ? sectorLabel(user) : (user.category || 'Público') }}<br /></template><template v-else><b>Setor</b> {{ sectorLabel(user) }}<br /><b>Função</b> {{ user.role || '—' }}<br /></template><b>Status</b> <Badge :tone="toneFor(user.status)">{{ user.status }}</Badge></p>
      <p v-if="user.status === 'Inativo'">Este usuário não tem acesso ao HackLab enquanto estiver inativo.</p>
      <button v-if="admin && user.status === 'Ativo'" class="btn danger small" type="button" @click="modal = 'off'">Desativar usuário</button>
      <button v-if="admin && user.status === 'Inativo'" class="btn small" type="button" @click="setUserStatus(user, 'Ativo'); modal = 'detalhe'">Ativar usuário</button>
      <template #footer>
        <button class="btn ghost" type="button" @click="modal = null">Fechar</button>
        <button v-if="admin && !isExternalProfile(user.profile)" class="btn" type="button" @click="openEdit(user)">Editar usuário</button>
      </template>
    </Modal>

    <Modal v-if="modal === 'off'" title="Desativar usuário?" subtitle="Este usuário deixará de ter acesso ao HackLab, mas suas informações permanecerão registradas." @close="modal = 'detalhe'">
      <p>{{ user.name }} fica inativo e não consegue entrar no HackLab. O cadastro continua na lista.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="modal = 'detalhe'">Cancelar</button>
        <button class="btn danger" type="button" @click="setUserStatus(user, 'Inativo'); modal = null">Desativar</button>
      </template>
    </Modal>
  </Page>
</template>
