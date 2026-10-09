<script setup>
import Drawer from '../components/Drawer.vue'
import Empty from '../components/Empty.vue'
import FilterPanel from '../components/FilterPanel.vue'
import Page from '../components/Page.vue'
import { useAuditPage } from '@/js/pages/audit'

const { PERIODS, filters, detailId, entries, options, list, active, detail, moduleLabel, who, clear, stamp } = useAuditPage()
</script>

<template>
  <Page title="Auditoria" subtitle="Acompanhe as principais ações realizadas no HackLab.">
    <p class="banner audit-note">Registros demonstrativos guardados neste navegador. Em produção, a trilha de auditoria será gravada pelo backend.</p>

    <FilterPanel :active="active" @clear="clear">
      <template #search><input v-model="filters.query" class="input" placeholder="Buscar logs..." aria-label="Buscar logs" /></template>
      <select v-model="filters.period" class="input" aria-label="Período">
        <option v-for="[value, label] in PERIODS" :key="value" :value="value">{{ label }}</option>
      </select>
      <select v-model="filters.user" class="input" aria-label="Usuário">
        <option value="">Usuário</option>
        <option v-for="item in options.users" :key="item">{{ item }}</option>
      </select>
      <select v-model="filters.profile" class="input" aria-label="Perfil">
        <option value="">Perfil</option>
        <option v-for="item in options.profiles" :key="item">{{ item }}</option>
      </select>
      <select v-model="filters.sector" class="input" aria-label="Setor">
        <option value="">Setor</option>
        <option v-for="item in options.sectors" :key="item">{{ item }}</option>
      </select>
      <select v-model="filters.module" class="input" aria-label="Módulo">
        <option value="">Módulo</option>
        <option v-for="item in options.modules" :key="item" :value="item">{{ moduleLabel(item) }}</option>
      </select>
      <select v-model="filters.action" class="input" aria-label="Ação">
        <option value="">Ação</option>
        <option v-for="item in options.actions" :key="item">{{ item }}</option>
      </select>
    </FilterPanel>
    <p class="attendance-summary"><span>Registros <b>{{ list.length }}</b></span><span v-if="list.length !== entries.length" class="stat-hint">de {{ entries.length }}</span></p>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Data/Hora</th><th>Usuário</th><th>Perfil</th><th>Ação</th><th>Módulo</th><th>Registro</th><th>Detalhes</th></tr></thead>
        <tbody>
          <tr v-if="list.length === 0"><td colspan="7"><Empty :title="entries.length ? 'Nenhum registro para estes filtros.' : 'Nenhuma ação registrada.'" text="As ações relevantes do HackLab aparecem aqui, da mais recente para a mais antiga." /></td></tr>
          <tr v-for="item in list" :key="item.id">
            <td>{{ stamp(item) }}</td>
            <td>{{ item.userName }}</td>
            <td>{{ who(item) }}</td>
            <td>{{ item.label }}</td>
            <td>{{ moduleLabel(item.module) }}</td>
            <td>{{ item.entityLabel || '—' }}</td>
            <td><button class="btn ghost small" type="button" @click="detailId = item.id">Ver detalhes</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <Drawer v-if="detail" :title="detail.label" :subtitle="stamp(detail)" @close="detailId = null">
      <dl class="detail-list">
        <dt>Data e hora</dt><dd>{{ stamp(detail) }}</dd>
        <dt>Usuário</dt><dd>{{ detail.userName }}</dd>
        <dt>Perfil</dt><dd>{{ detail.profile || '—' }}</dd>
        <dt>Setor</dt><dd>{{ detail.sector || '—' }}</dd>
        <dt>Ação</dt><dd>{{ detail.label }}</dd>
        <dt>Módulo</dt><dd>{{ moduleLabel(detail.module) }}</dd>
        <dt>Tipo de registro</dt><dd>{{ detail.entityType || '—' }}</dd>
        <dt>Identificador</dt><dd>{{ detail.entityLabel || '—' }}</dd>
        <dt>Descrição</dt><dd>{{ detail.description || '—' }}</dd>
        <template v-if="detail.metadata?.reason"><dt>Motivo</dt><dd>{{ detail.metadata.reason }}</dd></template>
      </dl>
      <h3 class="ops-title">Alterações</h3>
      <p v-if="!detail.changes?.length" class="stat-hint">Sem alterações de campo registradas.</p>
      <ul v-else class="audit-changes">
        <li v-for="change in detail.changes" :key="change.field">
          <span>{{ change.field }}</span>
          <p><s>{{ change.before }}</s> <span aria-hidden="true">→</span><span class="sr-only">para</span> <b>{{ change.after }}</b></p>
        </li>
      </ul>
      <template #footer>
        <button class="btn ghost" type="button" @click="detailId = null">Fechar</button>
      </template>
    </Drawer>
  </Page>
</template>
