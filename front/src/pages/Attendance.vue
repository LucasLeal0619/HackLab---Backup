<script setup>
import Badge from '../components/Badge.vue'
import Drawer from '../components/Drawer.vue'
import Empty from '../components/Empty.vue'
import FilterPanel from '../components/FilterPanel.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import Tabs from '../components/Tabs.vue'
import { useAttendance } from '@/js/pages/attendance'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
})
const { DAY_TABS, MANUAL_REASONS, STATUS_TONE, state, manage, day, tab, sectionTabs, query, category, status, authorized, scan, scanCode, manual, detailId, form, link, daysLabel, rows, credentialRows, presenceRows, summary, detail, openScan, simulateRead, confirmScan, searchInstead, openManual, saveManual, availablePeople, openNew, openEdit, pickPerson, setCategory, toggleDay, saveCredential, setStatus, CREDENTIAL_CATEGORIES, CREDENTIAL_STATUS, EVENT_DAYS, personOf, presenceOf } = useAttendance(props)
</script>

<template>
  <Page title="Credenciais e Presença" subtitle="Gerencie as credenciais e registre a presença das pessoas durante o Hackathon.">
    <template #actions>
      <button class="btn ghost" type="button" @click="openManual()">Registrar manualmente</button>
      <button class="btn" type="button" @click="openScan">Validar QR Code</button>
    </template>

    <Tabs v-if="sectionTabs.length > 1" class="attendance-sections" :tabs="sectionTabs" :model-value="tab" @update:model-value="(value) => link({ aba: value })" />

    <div class="attendance-bar">
      <Tabs v-if="tab === 'presenca'" :tabs="DAY_TABS" :model-value="String(day)" @update:model-value="(value) => link({ dia: value })" />
      <FilterPanel class="attendance-filters" :active="[category, tab === 'credenciais' ? status : '', tab === 'credenciais' ? authorized : ''].filter(Boolean).length" @clear="category = ''; status = ''; authorized = ''">
      <template #search><input v-model="query" class="input attendance-search" placeholder="Buscar por nome ou código da credencial" aria-label="Buscar por nome ou código da credencial" /></template>
      <select v-model="category" class="input attendance-filter" aria-label="Categoria">
        <option value="">Todas as categorias</option>
        <option v-for="item in CREDENTIAL_CATEGORIES" :key="item">{{ item }}</option>
      </select>
      <template v-if="tab === 'credenciais'">
        <select v-model="status" class="input attendance-filter" aria-label="Status">
          <option value="">Todos os status</option>
          <option v-for="item in CREDENTIAL_STATUS" :key="item">{{ item }}</option>
        </select>
        <select v-model="authorized" class="input attendance-filter" aria-label="Dia autorizado">
          <option value="">Qualquer dia</option>
          <option v-for="item in EVENT_DAYS" :key="item" :value="String(item)">Autorizado no Dia {{ item }}</option>
        </select>
      </template>
      </FilterPanel>
      <button v-if="manage && tab === 'credenciais'" class="btn" type="button" @click="openNew">+ Nova credencial</button>
    </div>

    <template v-if="!(state.credentials || []).length">
      <Empty v-if="manage" title="Nenhuma credencial cadastrada." text="Crie ou associe uma credencial para começar o controle de acesso." />
      <Empty v-else title="Nenhuma credencial disponível para validação." />
      <button v-if="manage" class="btn" type="button" @click="openNew">+ Nova credencial</button>
    </template>

    <template v-else-if="tab === 'presenca'">
      <p class="attendance-summary" aria-live="polite">
        <b>Dia {{ day }}</b>
        <span>Presentes <b>{{ summary.present }}</b></span>
        <span>Não registrados <b>{{ summary.missing }}</b></span>
        <span>QR Code <b>{{ summary.qr }}</b></span>
        <span>Manuais <b>{{ summary.manual }}</b></span>
      </p>
      <div class="table-wrap attendance-table-wrap">
        <table class="attendance-table">
          <thead><tr><th>Pessoa</th><th>Categoria</th><th>Credencial</th><th>Presença</th><th>Método</th><th>Horário</th><th>Ações</th></tr></thead>
          <tbody>
            <tr v-if="presenceRows.length === 0"><td colspan="7"><Empty :title="`Nenhuma credencial autorizada para o Dia ${day}${query || category ? ' com estes filtros' : ''}.`" /></td></tr>
            <tr v-for="row in presenceRows" :key="row.credential.id">
              <td>{{ row.name }}</td>
              <td>{{ row.credential.category }}</td>
              <td><span class="ticket-code">{{ row.credential.code }}</span> <Badge v-if="row.credential.status !== 'Ativa'" :tone="STATUS_TONE[row.credential.status]">{{ row.credential.status }}</Badge></td>
              <td><Badge :tone="row.record ? 'ok' : ''">{{ row.record ? 'Presente' : 'Não registrado' }}</Badge></td>
              <td>{{ row.record?.method || '—' }}</td>
              <td>{{ row.record?.time || '—' }}</td>
              <td>
                <div class="row-actions">
                  <button class="btn ghost small" type="button" @click="detailId = row.credential.id">Ver credencial</button>
                  <button v-if="!row.record && row.credential.status === 'Ativa'" class="btn ghost small" type="button" @click="openManual(row.credential)">Registrar</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <ul class="attendance-cards">
        <li v-if="presenceRows.length === 0" class="card">Nenhuma credencial autorizada para o Dia {{ day }}.</li>
        <li v-for="row in presenceRows" :key="row.credential.id" class="card attendance-card">
          <div class="attendance-card-head">
            <b>{{ row.name }}</b>
            <Badge :tone="row.record ? 'ok' : ''">{{ row.record ? '✓ Presente' : 'Não registrado' }}</Badge>
          </div>
          <p>{{ row.credential.category }} · <span class="ticket-code">{{ row.credential.code }}</span><template v-if="row.credential.status !== 'Ativa'"> · {{ row.credential.status }}</template></p>
          <p v-if="row.record">{{ row.record.time }} · {{ row.record.method }}</p>
          <div class="attendance-card-actions">
            <button class="btn ghost" type="button" @click="detailId = row.credential.id">Ver credencial</button>
            <button v-if="!row.record && row.credential.status === 'Ativa'" class="btn ghost" type="button" @click="openManual(row.credential)">Registrar</button>
          </div>
        </li>
      </ul>
    </template>

    <template v-else>
      <div class="table-wrap attendance-table-wrap">
        <table class="attendance-table">
          <thead><tr><th>Pessoa</th><th>Categoria</th><th>Credencial</th><th>Dias</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>
            <tr v-if="credentialRows.length === 0"><td colspan="6"><Empty title="Nenhuma credencial para estes filtros." /></td></tr>
            <tr v-for="row in credentialRows" :key="row.credential.id">
              <td>{{ row.name }}<br /><small class="stat-hint">{{ row.person?.kind || '—' }}</small></td>
              <td>{{ row.credential.category }}</td>
              <td><span class="ticket-code">{{ row.credential.code }}</span></td>
              <td>{{ daysLabel(row.credential.days) }}</td>
              <td><Badge :tone="STATUS_TONE[row.credential.status]">{{ row.credential.status }}</Badge></td>
              <td>
                <div class="row-actions">
                  <button class="btn ghost small" type="button" @click="detailId = row.credential.id">Ver</button>
                  <button v-if="manage" class="btn ghost small" type="button" @click="openEdit(row.credential)">Editar</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <ul class="attendance-cards">
        <li v-for="row in credentialRows" :key="row.credential.id" class="card attendance-card">
          <div class="attendance-card-head">
            <b>{{ row.name }}</b>
            <Badge :tone="STATUS_TONE[row.credential.status]">{{ row.credential.status }}</Badge>
          </div>
          <p>{{ row.credential.category }} · <span class="ticket-code">{{ row.credential.code }}</span></p>
          <p>{{ daysLabel(row.credential.days) }}</p>
          <div class="attendance-card-actions">
            <button class="btn ghost" type="button" @click="detailId = row.credential.id">Ver credencial</button>
            <button v-if="manage" class="btn ghost" type="button" @click="openEdit(row.credential)">Editar</button>
          </div>
        </li>
      </ul>
    </template>

    <Modal v-if="scan" title="Validar credencial" :subtitle="`Presença do Dia ${day}. Leitura demonstrativa, sem câmera real.`" @close="scan = null">
      <div class="scan-area">
        <div class="qr" aria-hidden="true" />
        <p>Área demonstrativa de leitura do QR Code da credencial.</p>
        <Field label="Código da credencial (opcional)" hint="Sem código, a leitura simula a próxima credencial válida para o dia.">
          <input v-model="scanCode" class="input" placeholder="HL-000001" @keydown.enter="simulateRead" />
        </Field>
        <button class="btn" type="button" @click="simulateRead">Simular leitura</button>
      </div>
      <div v-if="scan.kind === 'valid'" class="banner ok scan-result">
        <div>
          <b>Credencial válida</b>
          <dl class="scan-facts">
            <dt>Pessoa</dt><dd>{{ personOf(state, scan.credential.personId)?.name }}</dd>
            <dt>Categoria</dt><dd>{{ scan.credential.category }}</dd>
            <dt>Credencial</dt><dd>{{ scan.credential.code }}</dd>
            <dt>Dia {{ day }}</dt><dd>Acesso autorizado</dd>
          </dl>
        </div>
      </div>
      <div v-else-if="scan.kind === 'already'" class="banner warn scan-result">
        <div>
          <b>Presença já registrada</b>
          <p>{{ personOf(state, scan.credential.personId)?.name }} · Dia {{ scan.record.day }} · {{ scan.record.time || '—' }} · {{ scan.record.method }}</p>
        </div>
      </div>
      <div v-else-if="scan.kind === 'day'" class="banner warn scan-result">
        <div><b>Acesso não autorizado para este dia</b><p>Esta credencial não possui acesso ao Dia {{ day }}.</p></div>
      </div>
      <div v-else-if="scan.kind === 'blocked'" class="banner warn scan-result">
        <div><b>Credencial bloqueada</b><p>Esta credencial está bloqueada.</p></div>
      </div>
      <div v-else-if="scan.kind === 'cancelled'" class="banner err scan-result">
        <div><b>Credencial cancelada</b><p>Esta credencial foi cancelada.</p></div>
      </div>
      <div v-else-if="scan.kind === 'missing'" class="banner warn scan-result">
        <div>
          <b>Credencial não encontrada</b>
          <p>Nenhuma credencial com o código {{ scanCode.trim().toUpperCase() }}.</p>
          <div class="row-actions"><button class="btn ghost small" type="button" @click="openScan">Tentar novamente</button><button class="btn ghost small" type="button" @click="searchInstead">Buscar pessoa</button></div>
        </div>
      </div>
      <div v-else-if="scan.kind === 'none'" class="banner scan-result">
        <div><b>Nenhuma credencial pendente</b><p>Todas as credenciais ativas autorizadas para o Dia {{ day }} já têm presença.</p></div>
      </div>
      <template #footer>
        <button class="btn ghost" type="button" @click="scan = null">Fechar</button>
        <button v-if="scan.kind === 'valid'" class="btn" type="button" @click="confirmScan">Confirmar presença</button>
      </template>
    </Modal>

    <Modal v-if="manual" title="Registrar presença manualmente" subtitle="Use quando a leitura do QR Code não for possível." @close="manual = null">
      <p v-if="manual.error" class="banner warn">{{ manual.error }}</p>
      <Field label="Pessoa / credencial" required>
        <select v-model="manual.credentialId" class="input" @change="manual.error = ''">
          <option value="">Selecione</option>
          <option v-for="row in rows" :key="row.credential.id" :value="row.credential.id">{{ row.name }} · {{ row.credential.code }} · {{ row.credential.category }}</option>
        </select>
      </Field>
      <div class="form-grid">
        <Field label="Dia">
          <select v-model.number="manual.day" class="input" @change="manual.error = ''">
            <option v-for="item in EVENT_DAYS" :key="item" :value="item">Dia {{ item }}</option>
          </select>
        </Field>
        <Field label="Horário"><input v-model="manual.time" class="input" type="time" /></Field>
      </div>
      <Field label="Motivo">
        <select v-model="manual.reason" class="input">
          <option v-for="item in MANUAL_REASONS" :key="item">{{ item }}</option>
        </select>
      </Field>
      <Field label="Observação (opcional)"><input v-model="manual.note" class="input" /></Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="manual = null">Cancelar</button>
        <button class="btn" type="button" @click="saveManual">Registrar presença</button>
      </template>
    </Modal>

    <Modal v-if="form" :title="form.id ? 'Editar credencial' : 'Nova credencial'" subtitle="Código e QR Code são gerados automaticamente." @close="form = null">
      <p v-if="form.error" class="banner warn">{{ form.error }}</p>
      <div v-if="!form.id" class="chips" role="group" aria-label="Pessoa">
        <button type="button" class="chip" :class="{ on: form.mode === 'existente' }" @click="form.mode = 'existente'; form.error = ''">Pessoa já cadastrada</button>
        <button type="button" class="chip" :class="{ on: form.mode === 'externa' }" @click="form.mode = 'externa'; form.error = ''; setCategory('Convidado')">Cadastrar pessoa externa</button>
      </div>
      <Field v-if="form.id" label="Pessoa"><input class="input" :value="personOf(state, form.personId)?.name" disabled /></Field>
      <Field v-else-if="form.mode === 'existente'" label="Pessoa" required hint="Somente pessoas que ainda não têm credencial.">
        <select class="input" :value="form.personId" @change="pickPerson($event.target.value)">
          <option value="">Selecione</option>
          <option v-for="person in availablePeople" :key="person.id" :value="person.id">{{ person.name }} · {{ person.detail || person.kind }}</option>
        </select>
      </Field>
      <template v-else>
        <Field label="Nome" required><input v-model="form.name" class="input" /></Field>
        <Field label="E-mail (opcional)" hint="A pessoa externa não recebe conta no sistema."><input v-model="form.email" class="input" type="email" /></Field>
      </template>
      <Field label="Categoria">
        <select class="input" :value="form.category" @change="setCategory($event.target.value)">
          <option v-for="item in CREDENTIAL_CATEGORIES" :key="item">{{ item }}</option>
        </select>
      </Field>
      <div class="field">
        <span>Dias autorizados</span>
        <div class="chips">
          <label v-for="item in EVENT_DAYS" :key="item" class="check"><input type="checkbox" :checked="form.days.includes(item)" @change="toggleDay(item)" /> Dia {{ item }}</label>
        </div>
      </div>
      <Field label="Status">
        <select v-model="form.status" class="input">
          <option v-for="item in CREDENTIAL_STATUS" :key="item">{{ item }}</option>
        </select>
      </Field>
      <Field label="Observação (opcional)"><input v-model="form.note" class="input" /></Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="form = null">Cancelar</button>
        <button class="btn" type="button" @click="saveCredential">{{ form.id ? 'Salvar alterações' : 'Criar credencial' }}</button>
      </template>
    </Modal>

    <Drawer v-if="detail" title="Credencial" :subtitle="`${detail.name} · ${detail.credential.category}`" @close="detailId = null">
      <div class="credential-card">
        <div class="credential-head"><b>HackLab</b><span>Credencial do evento</span></div>
        <div class="credential-body">
          <p class="credential-name">{{ detail.name }}</p>
          <p>{{ detail.credential.category }}</p>
          <div class="qr" aria-hidden="true" />
          <p class="ticket-code">{{ detail.credential.code }}</p>
          <p>{{ daysLabel(detail.credential.days) }} · <Badge :tone="STATUS_TONE[detail.credential.status]">{{ detail.credential.status }}</Badge></p>
          <p class="stat-hint">QR Code demonstrativo. Um único QR vale em todos os dias autorizados.</p>
        </div>
      </div>
      <div v-if="manage" class="field">
        <span>Status da credencial</span>
        <div class="chips">
          <button v-for="item in CREDENTIAL_STATUS" :key="item" type="button" class="chip" :class="{ on: detail.credential.status === item }" @click="setStatus(detail.credential, item)">{{ item }}</button>
        </div>
      </div>
      <h3 class="ops-title">Histórico de presença</h3>
      <dl class="ticket-days">
        <template v-for="item in EVENT_DAYS" :key="item">
          <dt>Dia {{ item }}</dt>
          <dd>
            <template v-if="presenceOf(state, detail.credential.personId, item)">
              <Badge tone="ok">Presente</Badge>
              <span class="stat-hint">{{ presenceOf(state, detail.credential.personId, item).method }} · {{ presenceOf(state, detail.credential.personId, item).time || '—' }}</span>
            </template>
            <Badge v-else-if="!detail.credential.days.includes(item)">Não autorizado</Badge>
            <Badge v-else>Não registrado</Badge>
          </dd>
        </template>
      </dl>
      <p v-if="detail.credential.note" class="stat-hint">Observação: {{ detail.credential.note }}</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="detailId = null">Fechar</button>
        <button v-if="manage" class="btn" type="button" @click="openEdit(detail.credential)">Editar</button>
      </template>
    </Drawer>
  </Page>
</template>
