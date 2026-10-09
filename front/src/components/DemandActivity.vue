<script setup>
import Field from './Field.vue'
import Modal from './Modal.vue'
import { useDemandActivity } from '@/js/components/demand-activity'

const props = defineProps({
  kind: { type: String, required: true },
  id: { type: String, required: true },
  canComment: { type: Boolean, default: true },
})
const { item, responsible, involved, history, canRoute, message, forwarding, targets, authorLine, send, openForward, forward, formatMoment } = useDemandActivity(props)
</script>

<template>
  <section v-if="item" class="demand-activity" aria-label="Setores e histórico">
    <h3 class="ops-title">Setores</h3>
    <dl class="detail-list">
      <dt>Origem</dt><dd>{{ item.originSector || 'Organização' }}</dd>
      <dt>Responsável</dt><dd><b>{{ responsible || 'Não definido' }}</b></dd>
      <dt>Envolvidos</dt><dd>{{ involved.length ? involved.join(', ') : '—' }}</dd>
    </dl>
    <button v-if="canRoute" class="btn ghost small demand-forward" type="button" @click="openForward">Encaminhar</button>

    <h3 class="ops-title">Histórico</h3>
    <form v-if="canComment" class="demand-comment" @submit.prevent="send">
      <label class="sr-only" for="demand-message">Escrever atualização</label>
      <textarea id="demand-message" v-model="message" class="input" rows="2" placeholder="Escrever atualização..." />
      <button class="btn" type="submit" :disabled="!message.trim()">Enviar</button>
    </form>
    <p v-if="history.length === 0" class="stat-hint">Nenhuma interação registrada.</p>
    <ol v-else class="demand-history">
      <li v-for="entry in history" :key="entry.id" :class="entry.type">
        <div class="demand-history-head">
          <b>{{ entry.authorName }}</b>
          <span v-if="authorLine(entry)">{{ authorLine(entry) }}</span>
          <time :datetime="entry.at">{{ formatMoment(entry.at) }}</time>
        </div>
        <p>{{ entry.type === 'comment' ? `“${entry.text}”` : entry.text }}</p>
      </li>
    </ol>

    <Modal v-if="forwarding" :title="`Encaminhar ${kind === 'task' ? 'pendência' : 'ocorrência'}`" subtitle="O setor de origem e os envolvidos continuam acompanhando." @close="forwarding = null">
      <Field label="Setor responsável">
        <select v-model="forwarding.sector" class="input">
          <option v-for="sector in targets" :key="sector">{{ sector }}</option>
        </select>
      </Field>
      <Field label="Motivo / observação"><textarea v-model="forwarding.reason" class="input" rows="3" /></Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="forwarding = null">Cancelar</button>
        <button class="btn" type="button" @click="forward">Encaminhar</button>
      </template>
    </Modal>
  </section>
</template>
