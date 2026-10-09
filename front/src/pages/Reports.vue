<script setup>
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useReports } from '@/js/pages/reports'

const { busy, ready, preview, save, view, exportPdf, REPORT_MODELS } = useReports()
</script>

<template>
  <Page title="Relatórios" subtitle="Gere documentos consolidados sobre a organização e realização do Hackathon.">
    <section class="report-center" aria-labelledby="report-models">
      <div class="report-center-head">
        <h2 id="report-models" class="report-center-title">Modelos de relatório</h2>
        <p>Escolha o relatório que deseja gerar.</p>
      </div>
      <ul class="report-list">
        <li v-for="item in REPORT_MODELS" :key="item.id" class="report-row" :class="{ featured: item.recommended }">
          <div class="report-info">
            <h3>{{ item.title }} <span v-if="item.recommended" class="badge info">Recomendado</span></h3>
            <p>{{ item.text }}</p>
          </div>
          <div class="report-actions">
            <button class="btn ghost" type="button" :disabled="Boolean(busy[item.id])" @click="view(item.id)">
              {{ busy[item.id] === 'preview' ? 'Gerando relatório...' : 'Visualizar' }}
            </button>
            <button class="btn" type="button" :disabled="Boolean(busy[item.id])" @click="exportPdf(item.id)">
              {{ busy[item.id] === 'pdf' ? 'Gerando relatório...' : ready[item.id] ? 'Baixar PDF' : 'Gerar PDF' }}
            </button>
          </div>
        </li>
      </ul>
      <p class="report-foot">Os documentos são gerados neste navegador, em PDF A4, a partir dos dados atuais do HackLab.</p>
    </section>

    <Modal v-if="preview" class="report-preview" :title="preview.title" :subtitle="preview.fileName" wide @close="preview = null">
      <iframe class="report-frame" :src="preview.url" :title="`Pré-visualização: ${preview.title}`" />
      <template #footer>
        <button class="btn ghost" type="button" @click="preview = null">Voltar</button>
        <button class="btn" type="button" @click="save(preview)">Baixar PDF</button>
      </template>
    </Modal>
  </Page>
</template>
