<script setup>
import { computed } from 'vue'
import { sectorSummaries } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'
import Icon from './Icon.vue'

const { state } = useHack()
const summaries = computed(() => sectorSummaries(state))
const link = (name) => `setores?setor=${encodeURIComponent(name)}`
</script>

<template>
  <section class="card dash-block dashboard-sector-overview" aria-labelledby="dash-sectors">
    <div class="dash-block-head">
      <h2 id="dash-sectors" class="dash-block-title">Resumo dos Setores</h2>
      <a class="dash-more" href="#/setores" @click.prevent="go('setores')">Ver setores →</a>
    </div>
    <ul class="dash-sector-list">
      <li v-for="item in summaries" :key="item.name">
        <a class="dash-sector-row" :href="`#/${link(item.name)}`" @click.prevent="go(link(item.name))">
          <span class="dash-sector-name"><Icon :name="item.icon" :size="16" />{{ item.name }}</span>
          <span class="dash-sector-highlight" :class="item.highlightTone">{{ item.highlight }}</span>
          <span class="dash-signal" :class="item.status.tone">{{ item.status.text }}</span>
          <Icon class="dash-sector-go" name="chevron" :size="14" />
        </a>
      </li>
    </ul>
  </section>
</template>
