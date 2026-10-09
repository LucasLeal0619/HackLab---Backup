<script setup>
import Empty from '../components/Empty.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useManifest } from '@/js/pages/manifest'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
  embedded: { type: Boolean, default: false },
})
const { options, type, note, ask, meeting, total, done, manifestLabel, confirmManifest, go } = useManifest(props)
</script>

<template>
  <Page v-if="!meeting?.ata" title="Reuniões e Pendências">
    <template #actions>
      <button class="btn ghost" type="button" @click="go('reunioes')">Voltar</button>
    </template>
    <Empty title="Nenhuma ata disponível." text="Finalize a ata antes de registrar a manifestação." />
  </Page>
  <Page
    v-else
    crumbs="HackLab / Gestão / Reuniões e Pendências / Manifestação sobre a Ata"
    title="Reuniões e Pendências"
    :subtitle="`Manifestação sobre a ata · ${meeting.title}`"
  >
    <template #actions>
      <button class="btn ghost" type="button" @click="go('reunioes')">Voltar</button>
    </template>
    <div class="card">
      <h3>ATA Nº {{ meeting.ata.number }}</h3>
      <p class="stat-hint">{{ total ? `${done} de ${total} manifestaram` : `${done} manifestação(ões)` }} · Numeração deste protótipo.</p>
      <p><b>Assuntos</b><br>{{ meeting.ata.discussed }}</p>
      <p><b>Decisões</b><br>{{ meeting.ata.decisions }}</p>
      <p><b>Encaminhamentos</b><br>{{ meeting.ata.forwards }}</p>
    </div>
    <div class="grid cols-3 mt">
      <button
        v-for="[id, tone, text] in options"
        :key="id"
        type="button"
        class="choice"
        :class="[tone, { on: type === id }]"
        @click="type = id"
      >
        <b>{{ manifestLabel(id) }}</b>
        <p>{{ text }}</p>
      </button>
    </div>
    <Field v-if="type === 'Com observação'" label="Observação">
      <textarea v-model="note" class="input" />
    </Field>
    <div v-if="meeting.ata.manifestations?.length" class="table-wrap mt">
      <table>
        <thead><tr><th>Participante</th><th>Manifestação</th><th>Data</th></tr></thead>
        <tbody>
          <tr v-for="(item, index) in meeting.ata.manifestations" :key="index">
            <td>{{ item.name }}</td>
            <td>{{ manifestLabel(item.type) }}</td>
            <td>{{ item.at || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="stat-hint">Este registro não é assinatura digital qualificada, assinatura GOV.BR nem certificado digital.</p>
    <button class="btn" type="button" :disabled="!type" @click="ask = true">Registrar manifestação</button>
    <Modal
      v-if="ask"
      title="Confirmar manifestação?"
      :subtitle="`Tipo selecionado: ${manifestLabel(type)}.`"
      @close="ask = false"
    >
      <p v-if="note">{{ note }}</p>
      <p v-else>A manifestação será salva neste navegador.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="ask = false">Voltar</button>
        <button class="btn" type="button" @click="confirmManifest">Confirmar</button>
      </template>
    </Modal>
  </Page>
</template>
