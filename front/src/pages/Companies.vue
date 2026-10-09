<script setup>
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useCompanies } from '@/js/pages/companies'

const props = defineProps({
  mode: { type: String, default: 'empresas' },
})
const { COMPANY_STATUSES, CHALLENGE_STATUSES, TIPOS, state, tab, query, status, modal, challenge, removing, form, openCompany, openChallenge, editChallenge, saveCompany, saveChallenge, askRemoveCompany, askRemoveChallenge, confirmRemove, companies, challenges, companyOf, teamName, go, toneFor } = useCompanies(props)
</script>

<template>
  <Page
    :title="tab === 'empresas' ? 'Empresas' : 'Desafios'"
    :subtitle="tab === 'empresas' ? 'Cadastre e acompanhe as empresas participantes.' : 'Cadastre os desafios e associe empresa e equipe.'"
  >
    <template #actions>
      <button v-if="tab === 'empresas'" class="btn" @click="openCompany()">+ Cadastrar empresa</button>
      <button v-else class="btn" @click="openChallenge()">+ Cadastrar desafio</button>
    </template>
    <template v-if="tab === 'empresas'">
      <div class="filters">
        <input v-model="query" class="input" placeholder="Buscar empresa" />
        <select v-model="status" class="input">
          <option v-for="item in COMPANY_STATUSES" :key="item">{{ item }}</option>
        </select>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Empresa</th><th>Representante</th><th>Desafios</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>
            <template v-if="state.companies.length === 0">
              <tr>
                <td colspan="5">
                  <Empty title="Nenhuma empresa cadastrada" text="Cadastre uma empresa para registrar o representante e os desafios.">
                    <template #action>
                      <button class="btn" @click="openCompany()">+ Cadastrar empresa</button>
                    </template>
                  </Empty>
                </td>
              </tr>
            </template>
            <template v-else-if="companies.length === 0">
              <tr><td colspan="5"><Empty title="Nenhuma empresa encontrada" text="Ajuste a busca ou o filtro de status." /></td></tr>
            </template>
            <template v-else>
              <tr v-for="company in companies" :key="company.id">
                <td>{{ company.name }}</td>
                <td>{{ company.reps.find((rep) => rep.principal)?.name || company.reps[0]?.name || '—' }}</td>
                <td>{{ state.challenges.filter((item) => item.companyId === company.id).length || '—' }}</td>
                <td><Badge :tone="toneFor(company.status)">{{ company.status }}</Badge></td>
                <td>
                  <div class="row-actions">
                    <button class="btn ghost small" @click="go(`empresa?id=${company.id}`)">Visualizar</button>
                    <button class="btn ghost small" @click="openCompany(company)">Editar</button>
                    <button class="btn ghost small" @click="askRemoveCompany(company)">Excluir</button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </template>
    <template v-else>
      <div class="filters">
        <input v-model="query" class="input" placeholder="Buscar desafio" />
        <select v-model="status" class="input">
          <option v-for="item in CHALLENGE_STATUSES" :key="item">{{ item }}</option>
        </select>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Desafio</th><th>Empresa</th><th>Equipe</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>
            <template v-if="state.challenges.length === 0">
              <tr>
                <td colspan="5">
                  <Empty title="Nenhum desafio cadastrado" text="Cadastre um desafio proposto por uma empresa.">
                    <template #action>
                      <button class="btn" @click="openChallenge()">+ Cadastrar desafio</button>
                    </template>
                  </Empty>
                </td>
              </tr>
            </template>
            <template v-else-if="challenges.length === 0">
              <tr><td colspan="5"><Empty title="Nenhum desafio encontrado" text="Ajuste a busca ou o filtro de status." /></td></tr>
            </template>
            <template v-else>
              <tr v-for="item in challenges" :key="item.id">
                <td>{{ item.title }}</td>
                <td>{{ companyOf(state, item.companyId)?.name || '—' }}</td>
                <td>{{ item.teamId ? teamName(item.teamId) : '—' }}</td>
                <td><Badge :tone="toneFor(item.status)">{{ item.status }}</Badge></td>
                <td>
                  <div class="row-actions">
                    <button class="btn ghost small" @click="go(`desafio?id=${item.id}`)">Visualizar</button>
                    <button class="btn ghost small" @click="editChallenge(item)">Editar</button>
                    <button class="btn ghost small" @click="askRemoveChallenge(item)">Excluir</button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <p class="stat-hint">Recebido → Em análise → Aprovado → Distribuído → Em desenvolvimento → Finalizado. <button class="linkish" @click="go('distribuicao')">Ver distribuição</button></p>
    </template>

    <Modal v-if="modal" wide :title="form.id ? 'Editar empresa' : 'Cadastrar empresa'" subtitle="Empresa, representante e participação." @close="modal = false">
      <h3>Empresa</h3>
      <div class="form-grid">
        <Field label="Nome da empresa" required><input v-model="form.name" class="input" /></Field>
        <Field label="Segmento"><input v-model="form.segmento" class="input" /></Field>
        <Field label="Descrição" class-name="span-2"><textarea v-model="form.description" class="input" /></Field>
      </div>
      <h3>Representante</h3>
      <div class="form-grid">
        <Field label="Nome" required><input v-model="form.reps[0].name" class="input" /></Field>
        <Field label="Cargo"><input v-model="form.reps[0].cargo" class="input" /></Field>
        <Field label="E-mail" hint="Opcional"><input v-model="form.reps[0].email" class="input" /></Field>
      </div>
      <h3>Participação</h3>
      <Field label="Tipo de participação">
        <select v-model="form.tipo" class="input">
          <option v-for="item in TIPOS" :key="item">{{ item }}</option>
        </select>
      </Field>
      <template #footer>
        <button class="btn ghost" @click="modal = false">Cancelar</button>
        <button class="btn" @click="saveCompany">{{ form.id ? 'Salvar alterações' : 'Cadastrar empresa' }}</button>
      </template>
    </Modal>

    <Modal v-if="challenge" wide :title="challenge.id ? 'Editar desafio' : 'Cadastrar desafio'" @close="challenge = null">
      <h3>Informações principais</h3>
      <Field label="Título" required><input v-model="challenge.title" class="input" /></Field>
      <Field label="Empresa" required>
        <select v-model="challenge.companyId" class="input">
          <option value="">Selecione</option>
          <option v-for="company in state.companies" :key="company.id" :value="company.id">{{ company.name }}</option>
        </select>
      </Field>
      <Field label="Problema"><textarea v-model="challenge.problem" class="input" /></Field>
      <Field label="Objetivo"><textarea v-model="challenge.objective" class="input" /></Field>
      <h3>Detalhamento</h3>
      <Field label="Requisitos"><textarea v-model="challenge.requirements" class="input" /></Field>
      <Field label="Restrições"><textarea v-model="challenge.restrictions" class="input" /></Field>
      <Field label="Resultado esperado"><textarea v-model="challenge.expected" class="input" /></Field>
      <Field label="Observações"><textarea v-model="challenge.note" class="input" /></Field>
      <template #footer>
        <button class="btn ghost" @click="challenge = null">Cancelar</button>
        <button class="btn" @click="saveChallenge">{{ challenge.id ? 'Salvar alterações' : 'Salvar desafio' }}</button>
      </template>
    </Modal>

    <Modal
      v-if="removing"
      :title="removing.kind === 'empresa' ? 'Excluir empresa?' : 'Excluir desafio?'"
      :subtitle="removing.kind === 'empresa' && removing.challenges ? 'Os desafios desta empresa também serão removidos deste navegador.' : 'O cadastro será removido deste navegador.'"
      @close="removing = null"
    >
      <p>{{ removing.name }}</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="removing = null">Cancelar</button>
        <button class="btn danger" type="button" @click="confirmRemove">Excluir</button>
      </template>
    </Modal>
  </Page>
</template>
