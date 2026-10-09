<script setup>
import Badge from '../components/Badge.vue'
import Drawer from '../components/Drawer.vue'
import Empty from '../components/Empty.vue'
import FilterPanel from '../components/FilterPanel.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import DemandActivity from '../components/DemandActivity.vue'
import { useMeetings } from '@/js/pages/meetings'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
})
const { DOC_CATS, DOC_FILTERS, MEETING_TYPES, PRIORITIES, DUE_FILTERS, docCat, dueInfo, outsideHours, ataProgress, state, tab, pageCopy, modal, form, query, status, sector, priority, docCatFilter, prazo, detail, removing, ataForm, sectorOptions, operational, myName, canEditTask, meetings, atas, decisions, tasks, docs, activeUsers, detailMeeting, detailDecision, detailTask, detailDoc, meetingPeople, linkedDecisions, linkedDocs, manifestationDone, decisionSubtitle, taskDue, modalTitle, openPendencia, openMeeting, editMeeting, openDecision, editDecision, openDocument, editDocument, showMeeting, toggleParticipant, onFile, meetingName, save, confirmRemove, publishAta, completeTask, createTaskFromDecision, askRemove, go, toneFor, canRoute, taskSectorOptions, globalView, reopenTask, sourceOccurrence, toggleTaskSector, SETORES } = useMeetings(props)
</script>

<template>
  <Page :title="pageCopy[0]" :subtitle="pageCopy[1]">
    <template v-if="tab === 'reunioes'" #actions>
      <button class="btn" type="button" @click="openMeeting">+ Nova reunião</button>
    </template>
    <template v-else-if="tab === 'decisoes'" #actions>
      <button class="btn" type="button" @click="openDecision">+ Nova decisão</button>
    </template>
    <template v-else-if="tab === 'pendencias'" #actions>
      <button class="btn" type="button" @click="openPendencia()">+ Nova pendência</button>
    </template>
    <template v-else-if="tab === 'documentos'" #actions>
      <button class="btn" type="button" @click="openDocument">+ Adicionar documento</button>
    </template>

    <p v-if="tab === 'reunioes'" class="inline-links">
      <button class="linkish" type="button" @click="go('reunioes?lista=atas')">Ver todas as atas</button>
      <button class="linkish" type="button" @click="go('reunioes?lista=decisoes')">Ver decisões</button>
    </p>
    <p v-else-if="tab === 'atas' || tab === 'decisoes'">
      <button class="linkish" type="button" @click="go('reunioes')">Voltar às reuniões</button>
    </p>

    <FilterPanel v-if="tab !== 'documentos'" :active="[status, sector, priority, prazo].filter(Boolean).length" @clear="status = ''; sector = ''; priority = ''; prazo = ''">
      <template #search><input v-model="query" class="input" placeholder="Buscar" aria-label="Buscar" /></template>
      <select v-if="tab === 'reunioes'" v-model="status" class="input" aria-label="Status">
        <option value="">Status</option>
        <option>Agendada</option>
        <option>Aguardando manifestações</option>
        <option>Realizada</option>
      </select>
      <select v-if="tab === 'atas'" v-model="status" class="input" aria-label="Status">
        <option value="">Status</option>
        <option>Rascunho</option>
        <option>Em revisão</option>
        <option>Aguardando manifestações</option>
        <option>Finalizada</option>
      </select>
      <select v-if="tab === 'decisoes'" v-model="status" class="input" aria-label="Status">
        <option value="">Status</option>
        <option>Registrada</option>
        <option>Em andamento</option>
        <option>Concluída</option>
      </select>
      <template v-if="tab === 'pendencias'">
        <select v-model="sector" class="input" aria-label="Setor">
          <option value="">Setor</option>
          <option v-for="item in sectorOptions" :key="item">{{ item }}</option>
        </select>
        <select v-model="status" class="input" aria-label="Status">
          <option value="">Status</option>
          <option>Pendente</option>
          <option>Em andamento</option>
          <option>Concluído</option>
        </select>
        <select v-model="priority" class="input" aria-label="Prioridade">
          <option value="">Prioridade</option>
          <option v-for="item in PRIORITIES" :key="item">{{ item }}</option>
        </select>
        <select v-model="prazo" class="input" aria-label="Prazo">
          <option value="">Prazo</option>
          <option v-for="item in DUE_FILTERS" :key="item">{{ item }}</option>
        </select>
      </template>
    </FilterPanel>
    <div v-else class="filters mt">
      <input v-model="query" class="input" placeholder="Buscar" aria-label="Buscar documento" />
      <div class="chips">
        <button
          v-for="item in DOC_FILTERS"
          :key="item"
          type="button"
          class="chip"
          :class="{ on: docCatFilter === item }"
          @click="docCatFilter = item"
        >{{ item }}</button>
      </div>
    </div>

    <div v-if="tab === 'reunioes'" class="table-wrap">
      <table>
        <thead><tr><th>Reunião</th><th>Data</th><th>Tipo</th><th>Ata</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-if="meetings.length === 0"><td colspan="6"><Empty title="Nenhuma reunião cadastrada ainda." text="Agende a primeira reunião da organização." /></td></tr>
          <tr v-for="item in meetings" :key="item.id">
            <td>{{ item.title }}</td>
            <td>{{ item.date || 'A definir' }}{{ item.start ? ` · ${item.start}` : '' }}</td>
            <td>{{ item.type }}</td>
            <td>{{ item.ata ? `ATA Nº ${item.ata.number}` : '—' }}</td>
            <td><Badge :tone="toneFor(item.status)">{{ item.status }}</Badge></td>
            <td>
              <div class="row-actions">
                <button class="btn ghost small" type="button" @click="showMeeting(item.id)">Visualizar</button>
                <button class="btn ghost small" type="button" @click="editMeeting(item)">Editar</button>
                <button class="btn ghost small" type="button" @click="askRemove('reuniao', item.id, item.title)">Excluir</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="tab === 'atas'" class="table-wrap">
      <table>
        <thead><tr><th>Ata</th><th>Reunião</th><th>Status</th><th>Manifestações</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-if="atas.length === 0"><td colspan="5"><Empty title="Nenhuma ata disponível." /></td></tr>
          <tr v-for="item in atas" :key="item.id">
            <td>ATA Nº {{ item.ata.number }}</td>
            <td>{{ item.title }}</td>
            <td><Badge :tone="toneFor(item.ata.status)">{{ item.ata.status }}</Badge></td>
            <td>{{ ataProgress(item) }}</td>
            <td>
              <div class="row-actions">
                <button class="btn ghost small" type="button" @click="showMeeting(item.id, 'ata')">Visualizar</button>
                <button class="btn ghost small" type="button" @click="editMeeting(item)">Editar</button>
                <button class="btn ghost small" type="button" @click="askRemove('ata', item.id, `ATA Nº ${item.ata.number}`)">Excluir</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="tab === 'decisoes'" class="table-wrap">
      <table>
        <thead><tr><th>Decisão</th><th>Reunião</th><th>Responsável</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-if="decisions.length === 0"><td colspan="5"><Empty title="Nenhuma decisão registrada." /></td></tr>
          <tr v-for="item in decisions" :key="item.id">
            <td>{{ item.title }}</td>
            <td>{{ meetingName(item.meetingId) }}</td>
            <td>{{ item.responsible || '—' }}</td>
            <td><Badge :tone="toneFor(item.status)">{{ item.status }}</Badge></td>
            <td>
              <div class="row-actions">
                <button class="btn ghost small" type="button" @click="detail = { type: 'decisao', id: item.id }">Visualizar</button>
                <button class="btn ghost small" type="button" @click="editDecision(item)">Editar</button>
                <button class="btn ghost small" type="button" @click="askRemove('decisao', item.id, item.title)">Excluir</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="tab === 'pendencias'" class="table-wrap">
      <table>
        <thead><tr><th>Pendência</th><th>Setor</th><th>Responsável</th><th>Prazo</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-if="tasks.length === 0"><td colspan="6"><Empty title="Nenhuma pendência encontrada." text="Pendências de reuniões e setores aparecem aqui." /></td></tr>
          <tr v-for="item in tasks" :key="item.id">
            <td>{{ item.title }} <Badge v-if="item.priority" :tone="toneFor(item.priority)">{{ item.priority }}</Badge></td>
            <td>{{ item.sector || '—' }}</td>
            <td>{{ item.responsible || 'Não definido' }}</td>
            <td>
              <span :class="`due ${dueInfo(item).tone}`">{{ dueInfo(item).label }}</span>
              <template v-if="item.due"><br><small>{{ item.due }}</small></template>
            </td>
            <td><Badge :tone="toneFor(item.status)">{{ item.status }}</Badge></td>
            <td>
              <div class="row-actions">
                <button class="btn ghost small" type="button" @click="detail = { type: 'pendencia', id: item.id }">Visualizar</button>
                <button v-if="canEditTask(item)" class="btn ghost small" type="button" @click="openPendencia(item)">Editar</button>
                <button v-if="!operational" class="btn ghost small" type="button" @click="askRemove('pendencia', item.id, item.title)">Excluir</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="tab === 'documentos'" class="table-wrap">
      <table>
        <thead><tr><th>Documento</th><th>Categoria</th><th>Setor</th><th>Versão</th><th>Data</th><th>Ações</th></tr></thead>
        <tbody>
          <tr v-if="docs.length === 0"><td colspan="6"><Empty title="Nenhum documento armazenado." /></td></tr>
          <tr v-for="item in docs" :key="item.id">
            <td>{{ item.name }}</td>
            <td>{{ docCat(item) }}</td>
            <td>{{ item.sector || '—' }}</td>
            <td>{{ item.version || '—' }}</td>
            <td>{{ item.date || '—' }}</td>
            <td>
              <div class="row-actions">
                <button class="btn ghost small" type="button" @click="detail = { type: 'doc', id: item.id }">Visualizar</button>
                <button class="btn ghost small" type="button" @click="editDocument(item)">Editar</button>
                <button v-if="!operational" class="btn ghost small" type="button" @click="askRemove('doc', item.id, item.name)">Excluir</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  <Drawer
    v-if="detailMeeting"
    :title="detailMeeting.title"
    :subtitle="`${detailMeeting.type} · ${detailMeeting.date || 'Data a definir'}`"
    @close="detail = null"
  >
    <p><Badge :tone="toneFor(detailMeeting.status)">{{ detailMeeting.status }}</Badge> · {{ detailMeeting.place }}</p>
    <section>
      <h3>Informações</h3>
      <p style="white-space: pre-wrap">{{ detailMeeting.agenda || 'Sem pauta.' }}</p>
      <p v-if="detailMeeting.notes">{{ detailMeeting.notes }}</p>
    </section>
    <section>
      <h3>Participantes</h3>
      <p v-if="meetingPeople.length === 0">Nenhum participante vinculado.</p>
      <p v-for="user in meetingPeople" :key="user.id">{{ user.name }} · {{ user.profile }} · {{ detailMeeting.presence?.[user.id] || 'Não informado' }}</p>
      <p class="stat-hint">{{ meetingPeople.length || '—' }} participante(s). A presença desta reunião não se mistura com o evento.</p>
    </section>
    <section :id="detail?.focus === 'ata' ? 'ata' : undefined">
      <h3>Ata</h3>
      <p v-if="detailMeeting.ata"><Badge :tone="toneFor(detailMeeting.ata.status)">{{ detailMeeting.ata.status || 'Rascunho' }}</Badge></p>
      <p v-else class="stat-hint">Ata ainda não finalizada.</p>
      <Field label="Número"><input class="input" :value="ataForm.number || ''" @input="ataForm.number = $event.target.value" /></Field>
      <Field label="Assuntos discutidos"><textarea class="input" :value="ataForm.discussed || ''" @input="ataForm.discussed = $event.target.value" /></Field>
      <Field label="Decisões"><textarea class="input" :value="ataForm.decisions || ''" @input="ataForm.decisions = $event.target.value" /></Field>
      <Field label="Encaminhamentos"><textarea class="input" :value="ataForm.forwards || ''" @input="ataForm.forwards = $event.target.value" /></Field>
      <Field label="Observações"><textarea class="input" :value="ataForm.observations || ''" @input="ataForm.observations = $event.target.value" /></Field>
      <p v-if="detailMeeting.ata" class="stat-hint">{{ meetingPeople.length ? `${manifestationDone} de ${meetingPeople.length} manifestaram` : `${manifestationDone} manifestação(ões)` }}</p>
      <p v-if="detailMeeting.ata"><button class="linkish" type="button" @click="go(`manifestacao?id=${detailMeeting.id}`)">Registrar manifestação</button></p>
    </section>
    <section>
      <h3>Decisões e encaminhamentos</h3>
      <p v-if="linkedDecisions.length === 0">Nenhuma decisão ligada a esta reunião.</p>
      <p v-for="item in linkedDecisions" :key="item.id">{{ item.title }} · {{ item.status }}</p>
    </section>
    <section>
      <h3>Documentos</h3>
      <p v-if="linkedDocs.length === 0">Nenhum documento ligado a esta reunião.</p>
      <p v-for="item in linkedDocs" :key="item.id">{{ item.name }} · {{ item.version }}</p>
    </section>
    <template #footer>
      <button class="btn ghost" type="button" @click="detail = null">Fechar</button>
      <button class="btn" type="button" @click="publishAta">Finalizar ata</button>
    </template>
  </Drawer>

  <Drawer v-else-if="detailDecision" :title="detailDecision.title" :subtitle="decisionSubtitle" @close="detail = null">
    <dl class="kv">
      <dt>Status</dt><dd><Badge :tone="toneFor(detailDecision.status)">{{ detailDecision.status }}</Badge></dd>
      <dt>Responsável</dt><dd>{{ detailDecision.responsible || '—' }}</dd>
      <dt>Setor</dt><dd>{{ detailDecision.sector || '—' }}</dd>
      <dt>Descrição</dt><dd>{{ detailDecision.description || '—' }}</dd>
    </dl>
    <template #footer>
      <button class="btn ghost" type="button" @click="detail = null">Fechar</button>
      <button class="btn" type="button" @click="createTaskFromDecision">Criar pendência</button>
    </template>
  </Drawer>

  <Drawer v-else-if="detailTask" :title="detailTask.title" @close="detail = null">
    <dl class="kv">
      <dt>Descrição</dt><dd>{{ detailTask.description || '—' }}</dd>
      <dt>Responsável</dt><dd>{{ detailTask.responsible || 'Não definido' }}</dd>
      <dt>Prazo</dt><dd><span :class="`due ${taskDue.tone}`">{{ taskDue.label }}</span>{{ detailTask.due ? ` · ${detailTask.due}` : '' }}</dd>
      <dt>Prioridade</dt><dd><Badge :tone="toneFor(detailTask.priority)">{{ detailTask.priority || '—' }}</Badge></dd>
      <dt>Origem</dt><dd>{{ detailTask.origin || 'Cadastro direto' }}</dd>
      <dt>Status</dt><dd><Badge :tone="toneFor(detailTask.status)">{{ detailTask.status }}</Badge></dd>
      <dt>Observação</dt><dd>{{ detailTask.notes || '—' }}</dd>
      <dt>Código</dt><dd>{{ detailTask.ref }}</dd>
    </dl>
    <template v-if="sourceOccurrence">
      <h3 class="ops-title">Originada da ocorrência</h3>
      <ul class="related-list">
        <li>
          <span><b>{{ sourceOccurrence.title }}</b> <small class="stat-hint">{{ sourceOccurrence.ref }}</small></span>
          <button class="btn ghost small" type="button" @click="go(`ocorrencias?ver=${sourceOccurrence.id}`)">Ver ocorrência</button>
        </li>
      </ul>
    </template>
    <DemandActivity :key="detailTask.id" kind="task" :id="detailTask.id" />
    <template #footer>
      <button v-if="detailTask.status !== 'Concluído' && canEditTask(detailTask)" class="btn" type="button" @click="completeTask">Marcar como concluída</button>
      <button v-else-if="detailTask.status === 'Concluído' && !operational" class="btn ghost" type="button" @click="reopenTask">Reabrir</button>
      <button v-else class="btn ghost" type="button" @click="detail = null">Fechar</button>
    </template>
  </Drawer>

  <Drawer v-else-if="detailDoc" :title="detailDoc.name" :subtitle="docCat(detailDoc)" @close="detail = null">
    <dl class="kv">
      <dt>Setor</dt><dd>{{ detailDoc.sector || '—' }}</dd>
      <dt>Versão atual</dt><dd>{{ detailDoc.version || '—' }}</dd>
      <dt>Data</dt><dd>{{ detailDoc.date || '—' }}</dd>
      <dt>Responsável</dt><dd>{{ detailDoc.responsible || '—' }}</dd>
      <dt>Descrição</dt><dd>{{ detailDoc.description || '—' }}</dd>
      <dt>Arquivo</dt><dd>{{ detailDoc.fileName || 'Upload demonstrativo' }}</dd>
    </dl>
    <h3>Histórico</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Versão</th><th>Data</th><th>Responsável</th><th>Observação</th></tr></thead>
        <tbody>
          <tr v-if="!(detailDoc.history || []).length"><td colspan="4">Sem histórico.</td></tr>
          <tr v-for="(item, index) in detailDoc.history || []" :key="index">
            <td>{{ item.version }}</td>
            <td>{{ item.date }}</td>
            <td>{{ item.responsible || '—' }}</td>
            <td>{{ item.note || item.change || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </Drawer>

  <Modal v-if="modal" :wide="modal === 'reuniao'" :title="modalTitle" @close="modal = null">
    <div v-if="modal === 'reuniao'" class="form-grid">
      <Field label="Título" required class="span-2"><input v-model="form.title" class="input" /></Field>
      <Field label="Tipo">
        <select v-model="form.type" class="input">
          <option v-for="item in MEETING_TYPES" :key="item">{{ item }}</option>
        </select>
      </Field>
      <Field label="Responsável"><input v-model="form.responsible" class="input" /></Field>
      <Field label="Data"><input v-model="form.date" class="input" type="date" /></Field>
      <Field label="Horário"><input v-model="form.start" class="input" type="time" /></Field>
      <Field label="Local" class="span-2"><input v-model="form.place" class="input" /></Field>
      <p v-if="outsideHours(form.start)" class="stat-hint span-2">Horário fora de 08:00 às 12:00 — aviso demonstrativo. O evento permanece nesse intervalo.</p>
      <Field label="Pauta" class="span-2"><textarea v-model="form.agenda" class="input" /></Field>
      <Field label="Descrição / observação" class="span-2"><textarea v-model="form.notes" class="input" /></Field>
      <div class="field span-2">
        <span>Participantes</span>
        <div class="chips">
          <button
            v-for="user in activeUsers"
            :key="user.id"
            type="button"
            class="chip"
            :class="{ on: form.participantIds?.includes(user.id) }"
            @click="toggleParticipant(user.id)"
          >{{ user.name }} · {{ user.profile }}</button>
        </div>
      </div>
    </div>
    <div v-if="modal === 'decisao'" class="form-grid">
      <Field label="Decisão" required class="span-2"><input v-model="form.title" class="input" /></Field>
      <Field label="Descrição" class="span-2"><textarea v-model="form.description" class="input" /></Field>
      <Field label="Reunião">
        <select v-model="form.meetingId" class="input">
          <option value="">Nenhuma</option>
          <option v-for="item in state.meetings" :key="item.id" :value="item.id">{{ item.title }}</option>
        </select>
      </Field>
      <Field label="Responsável"><input v-model="form.responsible" class="input" /></Field>
      <Field label="Setor">
        <select v-model="form.sector" class="input">
          <option v-for="item in sectorOptions" :key="item">{{ item }}</option>
        </select>
      </Field>
      <Field label="Status">
        <select v-model="form.status" class="input">
          <option>Registrada</option>
          <option>Em andamento</option>
          <option>Concluída</option>
        </select>
      </Field>
    </div>
    <div v-if="modal === 'pendencia'" class="form-grid">
      <Field label="Título" required class="span-2"><input v-model="form.title" class="input" /></Field>
      <Field label="Descrição" class="span-2"><textarea v-model="form.description" class="input" /></Field>
      <Field label="Setor responsável">
        <select v-model="form.sector" class="input">
          <option value="">Selecione o setor</option>
          <option v-for="item in taskSectorOptions" :key="item">{{ item }}</option>
        </select>
      </Field>
      <Field v-if="globalView && !form.id" label="Setor de origem">
        <select v-model="form.originSector" class="input">
          <option value="">Organização (sem setor)</option>
          <option v-for="item in SETORES" :key="item">{{ item }}</option>
        </select>
      </Field>
      <div v-if="canRoute" class="field span-2">
        <span>Setores envolvidos</span>
        <small class="stat-hint">Acompanham e colaboram. O setor responsável continua sendo um só.</small>
        <div class="chips">
          <button v-for="item in SETORES" :key="item" type="button" class="chip" :class="{ on: (form.involvedSectors || []).includes(item) }" :aria-pressed="(form.involvedSectors || []).includes(item)" @click="toggleTaskSector(item)">{{ item }}</button>
        </div>
      </div>
      <Field label="Responsável">
        <select v-model="form.responsible" class="input" :disabled="operational">
          <option value="">Selecione</option>
          <option v-if="operational">{{ myName }}</option>
          <option v-for="user in state.users" :key="user.id">{{ user.name }}</option>
        </select>
      </Field>
      <Field label="Prazo"><input v-model="form.due" class="input" type="date" /></Field>
      <Field label="Status">
        <select v-model="form.status" class="input">
          <option>Pendente</option>
          <option>Em andamento</option>
          <option>Concluído</option>
        </select>
      </Field>
      <div class="field span-2">
        <span>Prioridade</span>
        <div class="chips">
          <button
            v-for="item in PRIORITIES"
            :key="item"
            type="button"
            class="chip"
            :class="{ on: form.priority === item }"
            @click="form.priority = item"
          >{{ item }}</button>
        </div>
      </div>
      <p v-if="form.origin" class="stat-hint span-2">Origem: {{ form.origin }}</p>
    </div>
    <div v-if="modal === 'doc'" class="form-grid">
      <Field label="Nome" required><input v-model="form.name" class="input" /></Field>
      <Field label="Categoria" required>
        <select v-model="form.category" class="input">
          <option v-for="item in DOC_CATS" :key="item">{{ item }}</option>
        </select>
      </Field>
      <Field label="Setor">
        <select v-model="form.sector" class="input">
          <option value="">Opcional</option>
          <option v-for="item in sectorOptions" :key="item">{{ item }}</option>
        </select>
      </Field>
      <Field label="Versão"><input v-model="form.version" class="input" /></Field>
      <Field label="Descrição" class="span-2"><textarea v-model="form.description" class="input" /></Field>
      <Field label="Observação" class="span-2"><input class="input" :value="form.note || ''" @input="form.note = $event.target.value" /></Field>
      <Field label="Arquivo demonstrativo" class="span-2" hint="Upload visual — nenhum arquivo é enviado.">
        <input class="input" type="file" @change="onFile" />
      </Field>
    </div>
    <template #footer>
      <button class="btn ghost" type="button" @click="modal = null">Cancelar</button>
      <button class="btn" type="button" @click="save">{{ form.id ? 'Salvar alterações' : 'Salvar' }}</button>
    </template>
  </Modal>

  <Modal
    v-if="removing"
    :title="removing.kind === 'ata' ? 'Excluir ata?' : 'Excluir registro?'"
    :subtitle="removing.kind === 'ata' ? 'A ata sai desta reunião. A reunião continua cadastrada.' : 'O registro será removido deste navegador.'"
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
