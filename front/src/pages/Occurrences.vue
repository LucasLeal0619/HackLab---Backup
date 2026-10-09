<script setup>
import Badge from '../components/Badge.vue'
import Drawer from '../components/Drawer.vue'
import Empty from '../components/Empty.vue'
import FilterPanel from '../components/FilterPanel.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import DemandActivity from '../components/DemandActivity.vue'
import { useOccurrences } from '@/js/pages/occurrences'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
})
const { DAYS, state, scope, sectorOptions, operational, filters, form, detailId, solving, removing, dayLabel, list, counts, detail, filtering, advancedFilters, clearFilters, openNew, openEdit, save, openSolve, saveSolution, remove, OCC_CATEGORIES, OCC_PRIORITIES, OCC_STATUS, teamName, toneFor, canRoute, responsibleOptions, generating, reopen, relatedTasks, openGenerate, generateTask, openTask, toggleInvolved, SETORES } = useOccurrences(props)
</script>

<template>
  <Page crumbs="Gestão / Ocorrências" title="Ocorrências" subtitle="Registre e acompanhe situações ocorridas durante a organização e realização do Hackathon.">
    <template #actions>
      <button class="btn" type="button" @click="openNew">+ Registrar ocorrência</button>
    </template>

    <FilterPanel class="occ-filters" :active="advancedFilters" @clear="clearFilters">
      <template #search><input v-model="filters.query" class="input" placeholder="Buscar ocorrência" aria-label="Buscar ocorrência" /></template>
      <select v-model="filters.category" class="input" aria-label="Categoria">
        <option value="">Categoria</option>
        <option v-for="item in OCC_CATEGORIES" :key="item">{{ item }}</option>
      </select>
      <select v-model="filters.sector" class="input" aria-label="Setor">
        <option value="">Setor</option>
        <option v-for="item in sectorOptions" :key="item">{{ item }}</option>
      </select>
      <select v-model="filters.priority" class="input" aria-label="Prioridade">
        <option value="">Prioridade</option>
        <option v-for="item in OCC_PRIORITIES" :key="item">{{ item }}</option>
      </select>
      <select v-model="filters.status" class="input" aria-label="Status">
        <option value="">Status</option>
        <option v-for="item in OCC_STATUS" :key="item">{{ item }}</option>
      </select>
      <select v-model="filters.day" class="input" aria-label="Dia">
        <option value="">Dia</option>
        <option v-for="item in [1, 2, 3]" :key="item" :value="String(item)">Dia {{ item }}</option>
      </select>
    </FilterPanel>
    <p class="attendance-summary">
      <span v-for="[status, total] in counts" :key="status">{{ status }} <b>{{ total }}</b></span>
      <button v-if="filtering" class="linkish" type="button" @click="clearFilters">Limpar filtros</button>
    </p>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Ocorrência</th><th>Categoria</th><th>Local</th><th>Setor</th><th>Prioridade</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-if="list.length === 0">
            <td colspan="7"><Empty :title="state.occurrences.length ? 'Nenhuma ocorrência para estes filtros.' : 'Nenhuma ocorrência registrada.'" text="Ocorrências são fatos ou problemas que aconteceram. O que ainda precisa ser feito fica em Pendências." /></td>
          </tr>
          <tr v-for="item in list" :key="item.id">
            <td>{{ item.title }} <small class="stat-hint">{{ item.ref }}</small><template v-if="item.day || item.at"><br /><small class="stat-hint">{{ [item.day ? `Dia ${item.day}` : '', item.at].filter(Boolean).join(' · ') }}</small></template></td>
            <td>{{ item.categoryLabel }}</td>
            <td>{{ item.place || '—' }}</td>
            <td>{{ item.sectorLabel || '—' }}</td>
            <td><Badge :tone="toneFor(item.priority)">{{ item.priority || '—' }}</Badge></td>
            <td><Badge :tone="toneFor(item.statusLabel)">{{ item.statusLabel }}</Badge></td>
            <td>
              <div class="row-actions">
                <button class="btn ghost small" type="button" @click="detailId = item.id">Ver</button>
                <button v-if="!operational" class="btn ghost small" type="button" @click="openEdit(item)">Editar</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Drawer v-if="detail" :title="detail.title" :subtitle="`${detail.categoryLabel} · ${detail.statusLabel}`" @close="detailId = null">
      <dl class="detail-list">
        <dt>Descrição</dt><dd>{{ detail.description || '—' }}</dd>
        <dt>Categoria</dt><dd>{{ detail.categoryLabel }}</dd>
        <dt>Dia</dt><dd>{{ dayLabel(detail.day) }}</dd>
        <dt>Data / horário</dt><dd>{{ detail.at || '—' }}</dd>
        <dt>Local</dt><dd>{{ detail.place || '—' }}</dd>
        <dt>Equipe</dt><dd>{{ detail.team || '—' }}</dd>
        <dt>Setor responsável</dt><dd>{{ detail.sectorLabel || '—' }}</dd>
        <dt>Prioridade</dt><dd><Badge :tone="toneFor(detail.priority)">{{ detail.priority || '—' }}</Badge></dd>
        <dt>Responsável</dt><dd>{{ detail.responsible || '—' }}</dd>
        <dt>Status</dt><dd><Badge :tone="toneFor(detail.statusLabel)">{{ detail.statusLabel }}</Badge></dd>
        <dt>Solução</dt><dd>{{ detail.solution || '—' }}</dd>
        <dt>Observação</dt><dd>{{ detail.notes || '—' }}</dd>
        <dt>Código</dt><dd>{{ detail.ref }}</dd>
      </dl>
      <template v-if="relatedTasks.length">
        <h3 class="ops-title">Pendência relacionada</h3>
        <ul class="related-list">
          <li v-for="task in relatedTasks" :key="task.id">
            <span><b>{{ task.title }}</b> <small class="stat-hint">{{ task.ref }}</small></span>
            <Badge :tone="toneFor(task.status)">{{ task.status }}</Badge>
            <button class="btn ghost small" type="button" @click="openTask(task.id)">Ver pendência</button>
          </li>
        </ul>
      </template>
      <DemandActivity :key="detail.id" kind="occurrence" :id="detail.id" />
      <template #footer>
        <template v-if="!operational">
          <button class="btn ghost" type="button" @click="removing = detail">Excluir</button>
          <button class="btn ghost" type="button" @click="openEdit(detail)">Editar</button>
          <button v-if="canRoute" class="btn ghost" type="button" @click="openGenerate">Gerar Pendência</button>
          <button v-if="detail.statusLabel === 'Resolvida'" class="btn ghost" type="button" @click="reopen">Reabrir</button>
          <button v-else class="btn" type="button" @click="openSolve">Registrar solução</button>
        </template>
        <button v-else class="btn ghost" type="button" @click="detailId = null">Fechar</button>
      </template>
    </Drawer>

    <Modal v-if="form" :title="form.id ? 'Editar ocorrência' : 'Registrar ocorrência'" subtitle="Registre o que aconteceu. Tarefas a fazer pertencem a Pendências." wide @close="form = null">
      <div class="form-grid">
        <Field label="Título" required class-name="span-2"><input v-model="form.title" class="input" /></Field>
        <Field label="Categoria">
          <select v-model="form.category" class="input">
            <option v-for="item in OCC_CATEGORIES" :key="item">{{ item }}</option>
          </select>
        </Field>
        <Field label="Setor responsável">
          <select v-model="form.sector" class="input">
            <option v-if="!scope" value="">Não se aplica</option>
            <option v-for="item in responsibleOptions" :key="item">{{ item }}</option>
          </select>
        </Field>
        <Field v-if="!scope && !form.id" label="Setor de origem">
          <select v-model="form.originSector" class="input">
            <option value="">Organização (sem setor)</option>
            <option v-for="item in SETORES" :key="item">{{ item }}</option>
          </select>
        </Field>
        <div v-if="canRoute" class="field span-2">
          <span>Setores envolvidos</span>
          <small class="stat-hint">Acompanham e colaboram. O setor responsável continua sendo um só.</small>
          <div class="chips">
            <button v-for="item in SETORES" :key="item" type="button" class="chip" :class="{ on: (form.involvedSectors || []).includes(item) }" :aria-pressed="(form.involvedSectors || []).includes(item)" @click="toggleInvolved(item)">{{ item }}</button>
          </div>
        </div>
        <Field label="Descrição" class-name="span-2"><textarea v-model="form.description" class="input" /></Field>
        <Field label="Dia do evento">
          <select v-model="form.day" class="input">
            <option v-for="[value, label] in DAYS" :key="value" :value="value">{{ label }}</option>
          </select>
        </Field>
        <Field label="Data / horário"><input v-model="form.at" class="input" placeholder="Ex.: 26/09 · 09:40" /></Field>
        <Field label="Local / sala"><input v-model="form.place" class="input" /></Field>
        <Field label="Equipe relacionada">
          <select v-model="form.team" class="input">
            <option value="">Nenhuma</option>
            <option v-for="team in state.teams" :key="team.id" :value="teamName(team.id)">{{ teamName(team.id) }}</option>
          </select>
        </Field>
        <Field label="Responsável" :hint="operational ? 'O gestor do setor define o responsável.' : ''"><input v-model="form.responsible" class="input" :disabled="operational" /></Field>
        <Field v-if="!operational" label="Status">
          <select v-model="form.status" class="input">
            <option v-for="item in OCC_STATUS" :key="item">{{ item }}</option>
          </select>
        </Field>
        <div class="field span-2">
          <span>Prioridade</span>
          <div class="chips">
            <button v-for="item in OCC_PRIORITIES" :key="item" type="button" class="chip" :class="{ on: form.priority === item }" @click="form.priority = item">{{ item }}</button>
          </div>
        </div>
        <Field label="Observação" class-name="span-2"><input v-model="form.notes" class="input" /></Field>
      </div>
      <template #footer>
        <button class="btn ghost" type="button" @click="form = null">Cancelar</button>
        <button class="btn" type="button" @click="save">{{ form.id ? 'Salvar alterações' : 'Registrar ocorrência' }}</button>
      </template>
    </Modal>

    <Modal v-if="solving" title="Registrar solução" subtitle="A ocorrência passará para o status Resolvida." @close="solving = null">
      <Field label="Solução adotada" required><textarea v-model="solving.solution" class="input" /></Field>
      <Field label="Responsável"><input v-model="solving.responsible" class="input" /></Field>
      <Field label="Observação"><input v-model="solving.note" class="input" /></Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="solving = null">Cancelar</button>
        <button class="btn" type="button" @click="saveSolution">Salvar solução</button>
      </template>
    </Modal>

    <Modal v-if="generating" title="Gerar Pendência" subtitle="A pendência fica ligada a esta ocorrência." @close="generating = null">
      <Field label="Título" required><input v-model="generating.title" class="input" placeholder="Ex.: Providenciar projetor reserva para o Dia 3" /></Field>
      <div class="form-grid">
        <Field label="Responsável">
          <select v-model="generating.sector" class="input">
            <option v-for="item in SETORES" :key="item">{{ item }}</option>
          </select>
        </Field>
        <Field label="Prioridade">
          <select v-model="generating.priority" class="input">
            <option v-for="item in OCC_PRIORITIES" :key="item">{{ item }}</option>
          </select>
        </Field>
        <Field label="Prazo"><input v-model="generating.due" class="input" type="date" /></Field>
      </div>
      <Field label="Descrição"><textarea v-model="generating.description" class="input" rows="3" /></Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="generating = null">Cancelar</button>
        <button class="btn" type="button" @click="generateTask">Criar Pendência</button>
      </template>
    </Modal>

    <Modal v-if="removing" title="Excluir ocorrência?" subtitle="O registro será removido deste navegador." @close="removing = null">
      <p>{{ removing.title }}</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="removing = null">Cancelar</button>
        <button class="btn danger" type="button" @click="remove">Excluir</button>
      </template>
    </Modal>
  </Page>
</template>
