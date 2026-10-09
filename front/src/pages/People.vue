<script setup>
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'
import FilterPanel from '../components/FilterPanel.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { usePeople } from '@/js/pages/people'

const { state, query, turma, statusFilter, equipe, askConfirm, askReopen, modal, viewing, menu, removing, form, teamOf, rows, registered, openCreate, save, removeStudent, setAvailability, confirmList, reopenList, noteFor, availabilityOf, isAvailable, teamName, TURMAS, go, blank } = usePeople()
</script>

<template>
  <Page title="Participantes" subtitle="Gerencie os participantes do Hackathon.">
    <template #actions>
      <button v-if="state.participantsConfirmed" class="btn ghost" type="button" @click="askReopen = true">Reabrir participantes</button>
      <button v-else class="btn ghost" type="button" @click="askConfirm = true">Confirmar participantes</button>
      <button class="btn" type="button" @click="openCreate">+ Cadastrar participante</button>
    </template>
    <FilterPanel :active="[turma, statusFilter, equipe].filter((value) => value !== 'Todas').length" @clear="turma = 'Todas'; statusFilter = 'Todas'; equipe = 'Todas'">
      <template #search><input v-model="query" class="input" placeholder="Buscar participante" aria-label="Buscar participante" /></template>
      <select v-model="turma" class="input" aria-label="Turma">
        <option value="Todas">Turma</option>
        <option v-for="item in TURMAS" :key="item.id" :value="item.id">{{ item.id }}</option>
      </select>
      <select v-model="statusFilter" class="input" aria-label="Status">
        <option value="Todas">Status</option>
        <option>Disponível</option>
        <option>Indisponível</option>
        <option>Desistente</option>
      </select>
      <select v-model="equipe" class="input" aria-label="Equipe">
        <option value="Todas">Equipe</option>
        <option>Sem equipe</option>
        <option v-for="team in state.teams" :key="team.id">{{ teamName(team.id) }}</option>
      </select>
    </FilterPanel>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Participante</th><th>Turma</th><th>Equipe</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-if="state.students.length === 0"><td colspan="5"><Empty title="Nenhum participante cadastrado ainda." text="Cadastre os participantes para começar a formação das equipes."><template #action><button class="btn" type="button" @click="openCreate">+ Cadastrar participante</button></template></Empty></td></tr>
          <tr v-else-if="rows.length === 0"><td colspan="5"><Empty title="Nenhum participante encontrado" text="Ajuste a busca ou limpe os filtros."><template #action><button class="btn ghost" type="button" @click="query = ''; turma = 'Todas'; equipe = 'Todas'; statusFilter = 'Todas'">Limpar filtros</button></template></Empty></td></tr>
          <tr v-for="student in rows" v-else :key="student.id">
            <td>{{ student.name }}</td>
            <td>{{ student.turma }}</td>
            <td>{{ teamOf(student.id) ? teamName(teamOf(student.id).id) : '—' }}</td>
            <td>
              <Badge :tone="availabilityOf(student) === 'Disponível' ? 'ok' : availabilityOf(student) === 'Desistente' ? 'danger' : 'warn'">{{ availabilityOf(student) }}</Badge><br />
              <small>{{ noteFor(student) }}</small>
            </td>
            <td>
              <div class="row-actions">
                <button class="btn ghost small" type="button" @click="viewing = student">Visualizar</button>
                <button class="btn ghost small" type="button" @click="form = { ...blank, ...student, availability: availabilityOf(student) }; modal = true">Editar</button>
                <button class="btn ghost small" type="button" @click="menu = ''; removing = student">Excluir</button>
                <span class="row-menu">
                  <button class="btn ghost small" type="button" title="Mais opções" aria-label="Mais opções" @mousedown.stop @click="menu = menu === student.id ? '' : student.id">⋯</button>
                  <div v-if="menu === student.id" class="menu-pop" @mousedown.stop>
                    <button v-if="teamOf(student.id)" type="button" @click="go(`montar?id=${teamOf(student.id).id}`)">Abrir equipe</button>
                    <button v-else type="button" @click="go('equipes')">Ver equipes</button>
                    <button v-if="availabilityOf(student) !== 'Disponível'" type="button" @click="setAvailability(student, 'Disponível')">Marcar como disponível</button>
                    <button v-if="availabilityOf(student) !== 'Indisponível'" type="button" @click="setAvailability(student, 'Indisponível')">Marcar como indisponível</button>
                    <button v-if="availabilityOf(student) !== 'Desistente'" type="button" @click="setAvailability(student, 'Desistente')">Marcar como desistente</button>
                  </div>
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-if="registered" class="stat-hint">{{ registered === 1 ? '1 participante cadastrado' : `${registered} participantes cadastrados` }}{{ state.participantsConfirmed ? ' · Lista confirmada' : '' }}</p>

    <Modal v-if="modal" :title="form.id ? 'Editar participante' : 'Cadastrar participante'" subtitle="Nome e turma são suficientes para a formação das equipes." @close="modal = false">
      <Field label="Nome completo" required><input v-model="form.name" class="input" /></Field>
      <Field label="Turma" required>
        <select v-model="form.turma" class="input">
          <option value="">Selecione a turma</option>
          <option v-for="item in TURMAS" :key="item.id" :value="item.id">{{ item.professor }}</option>
        </select>
      </Field>
      <div class="form-grid">
        <Field label="Matrícula" hint="Opcional"><input v-model="form.matricula" class="input" /></Field>
        <Field label="E-mail" hint="Opcional"><input v-model="form.email" class="input" /></Field>
        <Field label="Observação" class-name="span-2"><input v-model="form.note" class="input" /></Field>
      </div>
      <Field label="Situação">
        <select v-model="form.availability" class="input">
          <option>Disponível</option>
          <option>Indisponível</option>
          <option>Desistente</option>
        </select>
      </Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="modal = false">Cancelar</button>
        <button class="btn" type="button" @click="save">{{ form.id ? 'Salvar alterações' : 'Cadastrar participante' }}</button>
      </template>
    </Modal>

    <Modal v-if="viewing" :title="viewing.name" :subtitle="availabilityOf(viewing)" @close="viewing = null">
      <p><b>Turma</b> {{ viewing.turma }}</p>
      <p><b>Situação</b> {{ availabilityOf(viewing) }}</p>
      <p><b>Equipe</b> {{ teamOf(viewing.id) ? teamName(teamOf(viewing.id).id) : 'Ainda sem equipe.' }}</p>
      <p><b>Matrícula</b> {{ viewing.matricula || '—' }}</p>
      <p><b>E-mail</b> {{ viewing.email || '—' }}</p>
      <p><b>Observação</b> {{ viewing.note || '—' }}</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="viewing = null">Fechar</button>
        <button class="btn" type="button" @click="form = { ...blank, ...viewing, availability: availabilityOf(viewing) }; viewing = null; modal = true">Editar</button>
      </template>
    </Modal>

    <Modal v-if="removing" title="Excluir participante?" :subtitle="teamOf(removing.id) ? `${removing.name} sai da ${teamName(teamOf(removing.id).id)} e o cadastro é removido deste navegador.` : 'O cadastro será removido deste navegador.'" @close="removing = null">
      <p>{{ removing.name }} · {{ removing.turma }}</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="removing = null">Cancelar</button>
        <button class="btn danger" type="button" @click="removeStudent">Excluir</button>
      </template>
    </Modal>

    <Modal v-if="askConfirm" title="Confirmar participantes?" subtitle="Confirme que a lista atual representa as pessoas que participarão da formação das equipes." @close="askConfirm = false">
      <p>{{ state.students.filter(isAvailable).length }} disponíveis para a formação. Quem estiver indisponível ou desistente continua no cadastro, mas não entra nas equipes.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="askConfirm = false">Cancelar</button>
        <button class="btn" type="button" @click="confirmList">Confirmar participantes</button>
      </template>
    </Modal>

    <Modal v-if="askReopen" title="Reabrir participantes?" :subtitle="state.teams.length ? 'Alterações nos participantes podem afetar as equipes já formadas.' : 'A lista volta a ficar em aberto.'" @close="askReopen = false">
      <p>A alteração não reorganiza as equipes sozinha.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="askReopen = false">Cancelar</button>
        <button class="btn" type="button" @click="reopenList">Reabrir participantes</button>
      </template>
    </Modal>
  </Page>
</template>
