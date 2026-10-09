<script setup>
import Badge from '../components/Badge.vue'
import DashboardDomain from '../components/DashboardDomain.vue'
import Page from '../components/Page.vue'
import SectorSummary from '../components/SectorSummary.vue'
import { useDashboard } from '@/js/pages/dashboard'

const { state, steps, done, current, eventMeta, allowed, domains, showAllAttention, attention, sectors, scoped, operational, URGENT, sectorTasks, myTasks, sectorOcc, sectorDocs, sectorMembers, sectorLink, managerSummary, priorities, recent, STEP_LABELS, statusLabel, go, toneFor } = useDashboard()
</script>

<template>
  <Page
    v-if="scoped && !operational"
    title="Dashboard"
    :subtitle="`Gestão do setor ${sectors.join(' e ')}.`"
  >
    <div class="dashboard-cockpit editor-cockpit">
      <div class="editor-grid">
        <DashboardDomain v-for="item in managerSummary" :key="item.title" v-bind="item" />
        <section class="card dash-block" aria-labelledby="dash-priorities">
          <div class="dash-block-head">
            <h2 id="dash-priorities" class="dash-block-title">Prioridades</h2>
          </div>
          <p v-if="priorities.length === 0" class="dash-empty"><span class="dash-signal ok">Tudo em ordem</span> Nada com prioridade alta ou urgente.</p>
          <ul v-else class="dash-actions">
            <li v-for="item in priorities" :key="item.id">
              <a :href="`#/${item.to}`" @click.prevent="go(item.to)"><i class="dash-dot" :class="item.tone" aria-hidden="true" /><span><small class="stat-hint">{{ item.kind }}</small> {{ item.title }}</span><Badge :tone="toneFor(item.badge)">{{ item.badge }}</Badge></a>
            </li>
          </ul>
        </section>
        <section class="card dash-block" aria-labelledby="dash-members">
          <div class="dash-block-head">
            <h2 id="dash-members" class="dash-block-title">Membros</h2>
          </div>
          <p v-if="sectorMembers.length === 0" class="dash-empty">Nenhum integrante cadastrado no setor.</p>
          <ul v-else class="dash-actions">
            <li v-for="item in sectorMembers" :key="item.id">
              <a :href="`#/${sectorLink(item.sector)}`" @click.prevent="go(sectorLink(item.sector))"><span>{{ item.name }} <small class="stat-hint">{{ item.profile }}</small></span><small class="stat-hint">{{ item.func || '—' }}</small></a>
            </li>
          </ul>
        </section>
        <section class="card dash-block" aria-labelledby="dash-recent">
          <h2 id="dash-recent" class="dash-block-title">Atividade recente</h2>
          <p v-if="recent.length === 0" class="dash-empty">Nenhuma atividade registrada no setor.</p>
          <ul v-else class="dash-actions">
            <li v-for="item in recent" :key="item.id">
              <a :href="`#/${item.to}`" @click.prevent="go(item.to)"><span><small class="stat-hint">{{ item.kind }}</small> {{ item.title }}</span><small class="stat-hint">{{ item.when }}</small></a>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </Page>

  <Page
    v-else-if="scoped"
    title="Meu trabalho"
    :subtitle="`Seu trabalho em ${sectors.join(' e ')}.`"
  >
    <div class="dashboard-cockpit editor-cockpit">
      <div class="editor-grid">
        <section class="card dash-block" aria-labelledby="dash-my-tasks">
          <div class="dash-block-head">
            <h2 id="dash-my-tasks" class="dash-block-title">Minhas pendências</h2>
            <a class="dash-more" href="#/pendencias" @click.prevent="go('pendencias')">Ver pendências →</a>
          </div>
          <p v-if="myTasks.length === 0" class="dash-empty"><span class="dash-signal ok">Em dia</span> Nenhuma pendência sob sua responsabilidade.</p>
          <ul v-else class="dash-actions">
            <li v-for="task in myTasks.slice(0, 4)" :key="task.id">
              <a href="#/pendencias" @click.prevent="go('pendencias')"><span>{{ task.title }}</span><Badge :tone="toneFor(task.status)">{{ task.status }}</Badge></a>
            </li>
          </ul>
          <p class="stat-hint">{{ sectorTasks.length }} {{ sectorTasks.length === 1 ? 'pendência aberta' : 'pendências abertas' }} no setor.</p>
        </section>
        <section class="card dash-block" aria-labelledby="dash-my-occ">
          <div class="dash-block-head">
            <h2 id="dash-my-occ" class="dash-block-title">Ocorrências relacionadas</h2>
            <a class="dash-more" href="#/ocorrencias" @click.prevent="go('ocorrencias')">Ver ocorrências →</a>
          </div>
          <p v-if="sectorOcc.length === 0" class="dash-empty"><span class="dash-signal ok">Tudo em ordem</span> Nenhuma ocorrência aberta.</p>
          <ul v-else class="dash-actions">
            <li v-for="item in sectorOcc.slice(0, 4)" :key="item.id">
              <a href="#/ocorrencias" @click.prevent="go('ocorrencias')"><i class="dash-dot" :class="URGENT.includes(item.priority) ? 'bad' : 'warn'" aria-hidden="true" /><span>{{ item.title }}</span><Badge :tone="toneFor(item.priority)">{{ item.priority }}</Badge></a>
            </li>
          </ul>
        </section>
        <section class="card dash-block" aria-labelledby="dash-docs">
          <div class="dash-block-head">
            <h2 id="dash-docs" class="dash-block-title">Documentos recentes</h2>
            <a class="dash-more" href="#/documentos" @click.prevent="go('documentos')">Ver documentos →</a>
          </div>
          <p v-if="sectorDocs.length === 0" class="dash-empty">Nenhum documento do setor.</p>
          <ul v-else class="dash-actions">
            <li v-for="item in sectorDocs.slice(0, 4)" :key="item.id">
              <a href="#/documentos" @click.prevent="go('documentos')"><span>{{ item.name }}</span><small class="stat-hint">{{ item.date }}</small></a>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </Page>

  <Page
    v-else
    title="Dashboard"
    subtitle="Acompanhe a organização e a situação atual do Hackathon."
  >
    <div class="dashboard-cockpit">
      <section class="card dash-block dashboard-progress" aria-labelledby="dash-progress">
        <div class="dash-progress-main">
          <div>
            <p class="dash-event-meta">
              <b>{{ state.event.name || 'Hackathon' }}</b>
              <span v-for="item in eventMeta" :key="item">{{ item }}</span>
            </p>
            <h2 id="dash-progress" class="dash-progress-count"><strong>{{ done }}/{{ steps.length }}</strong> etapas concluídas</h2>
          </div>
          <button v-if="allowed(current.to)" class="btn" type="button" @click="go(current.to)">Continuar organização</button>
        </div>
        <ol class="dash-steps">
          <li v-for="(step, index) in steps" :key="step.label" :class="step.status" :title="`${step.label} · ${statusLabel(step.status)}`">
            <span class="dash-step-mark" aria-hidden="true">{{ step.status === 'concluida' ? '✓' : index + 1 }}</span>
            {{ STEP_LABELS[index] || step.label }}
            <span class="sr-only"> — {{ statusLabel(step.status) }}</span>
          </li>
        </ol>
      </section>

      <div class="dashboard-domain-grid">
        <DashboardDomain v-for="item in domains" :key="item.title" v-bind="item" />
      </div>

      <div class="dashboard-bottom">
        <SectorSummary />
        <section class="card dash-block dashboard-action-list" aria-labelledby="dash-attention">
          <div class="dash-block-head">
            <h2 id="dash-attention" class="dash-block-title">Atenção</h2>
          </div>
          <p v-if="attention.length === 0" class="dash-empty"><span class="dash-signal ok">Tudo em ordem</span> Nenhuma ação pendente.</p>
          <ul v-else class="dash-actions">
            <li v-for="item in (showAllAttention ? attention : attention.slice(0, 3))" :key="item.text">
              <a :href="`#/${item.to}`" @click.prevent="go(item.to)"><i class="dash-dot" :class="item.tone" aria-hidden="true" /><span>{{ item.text }}</span><span aria-hidden="true">→</span></a>
            </li>
          </ul>
          <button v-if="attention.length > 3" class="dash-more linkish" type="button" :aria-expanded="showAllAttention" @click="showAllAttention = !showAllAttention">{{ showAllAttention ? 'Mostrar menos' : `+ ${attention.length - 3} ${attention.length - 3 === 1 ? 'outra' : 'outras'} · Ver todas` }}</button>
        </section>
      </div>
    </div>
  </Page>
</template>
