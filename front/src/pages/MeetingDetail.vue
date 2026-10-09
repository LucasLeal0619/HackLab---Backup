<script setup>
import Badge from '../components/Badge.vue'
import Empty from '../components/Empty.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useMeetingDetail } from '@/js/pages/meeting-detail'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
  embedded: { type: Boolean, default: false },
})
const { PRESENCE, state, meeting, ataForm, presence, people, publish, markPresence, go, toneFor } = useMeetingDetail(props)
</script>

<template>
  <Page v-if="!meeting" title="Reunião">
    <Empty title="Nenhuma reunião cadastrada." />
  </Page>
  <Page v-else :crumbs="`Reuniões / ${meeting.title}`" :title="meeting.title" :subtitle="meeting.type">
    <template #actions>
      <button class="btn ghost" type="button" @click="go('reunioes')">Voltar</button>
    </template>
    <p><Badge :tone="toneFor(meeting.status)">{{ meeting.status }}</Badge> · {{ meeting.date || 'Data a definir' }} · {{ meeting.place }}</p>
    <section class="card">
      <h3>Informações</h3>
      <p style="white-space: pre-wrap">{{ meeting.agenda || 'Sem pauta.' }}</p>
      <p>{{ meeting.notes }}</p>
    </section>
    <section class="card">
      <h3>Participantes</h3>
      <p v-if="people.length === 0">Nenhum participante vinculado.</p>
      <p v-for="user in people" :key="user.id">{{ user.name }} · {{ user.profile }} · {{ meeting.presence?.[user.id] || 'Não informado' }}</p>
      <button class="btn ghost small" type="button" @click="presence = true">Registrar presença</button>
      <p class="stat-hint">Presença manual desta reunião — não ligada ao QR Code do evento.</p>
    </section>
    <section class="card">
      <h3>Ata</h3>
      <p v-if="meeting.ata"><Badge :tone="toneFor(meeting.ata.status)">{{ meeting.ata.status || 'Rascunho' }}</Badge></p>
      <p v-else class="stat-hint">Ata ainda não finalizada.</p>
      <Field label="Número"><input class="input" :value="ataForm.number || ''" @input="ataForm.number = $event.target.value" /></Field>
      <Field label="Assuntos discutidos"><textarea class="input" :value="ataForm.discussed || ''" @input="ataForm.discussed = $event.target.value" /></Field>
      <Field label="Decisões tomadas"><textarea class="input" :value="ataForm.decisions || ''" @input="ataForm.decisions = $event.target.value" /></Field>
      <Field label="Encaminhamentos"><textarea class="input" :value="ataForm.forwards || ''" @input="ataForm.forwards = $event.target.value" /></Field>
      <Field label="Observações"><textarea class="input" :value="ataForm.observations || ''" @input="ataForm.observations = $event.target.value" /></Field>
      <button class="btn" type="button" @click="publish">Finalizar ata</button>
      <p v-if="meeting.ata"><button class="linkish" type="button" @click="go(`manifestacao?id=${meeting.id}`)">Registrar manifestação</button></p>
    </section>
    <section class="card">
      <h3>Decisões e encaminhamentos</h3>
      <p v-for="item in state.decisions.filter((entry) => entry.meetingId === meeting.id)" :key="item.id">{{ item.title }} · {{ item.status }}</p>
      <p v-if="state.decisions.every((entry) => entry.meetingId !== meeting.id)">Nenhuma decisão ligada a esta reunião.</p>
    </section>
    <section class="card">
      <h3>Documentos</h3>
      <p class="stat-hint">Os documentos do Hackathon ficam na central de documentos. Aqui aparecem só os ligados a esta reunião.</p>
      <button class="btn ghost small" type="button" @click="go('documentos')">Ver documentos</button>
    </section>
    <Modal v-if="presence" title="Registrar presença" subtitle="Presença manual desta reunião." @close="presence = false">
      <div v-for="user in people" :key="user.id" class="person">
        <span>{{ user.name }}<br><small>{{ user.profile }}</small></span>
        <div class="chips">
          <button
            v-for="option in PRESENCE"
            :key="option"
            type="button"
            class="chip"
            :class="{ on: meeting.presence?.[user.id] === option }"
            @click="markPresence(user.id, option)"
          >{{ option }}</button>
        </div>
      </div>
      <template #footer>
        <button class="btn" type="button" @click="presence = false">Fechar</button>
      </template>
    </Modal>
  </Page>
</template>
