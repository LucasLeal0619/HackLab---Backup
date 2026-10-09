<script setup>
import FocusFrame from './components/FocusFrame.vue'
import FocusShell from './components/FocusShell.vue'
import Icon from './components/Icon.vue'
import Shell from './components/Shell.vue'
import Companies from './pages/Companies.vue'
import Config from './pages/Config.vue'
import Judges from './pages/Judges.vue'
import Meetings from './pages/Meetings.vue'
import People from './pages/People.vue'
import Sectors from './pages/Sectors.vue'
import Teams from './pages/Teams.vue'
import Login from './pages/Login.vue'
import Dashboard from './pages/Dashboard.vue'
import Roulette from './pages/Roulette.vue'
import TeamBuild from './pages/TeamBuild.vue'
import ChallengeDetail from './pages/ChallengeDetail.vue'
import CompanyDetail from './pages/CompanyDetail.vue'
import Distribution from './pages/Distribution.vue'
import Manifest from './pages/Manifest.vue'
import MeetingDetail from './pages/MeetingDetail.vue'
import Attendance from './pages/Attendance.vue'
import Audit from './pages/Audit.vue'
import Occurrences from './pages/Occurrences.vue'
import Awards from './pages/Awards.vue'
import Evaluate from './pages/Evaluate.vue'
import JudgeArea from './pages/JudgeArea.vue'
import Presentation from './pages/Presentation.vue'
import PublicVote from './pages/PublicVote.vue'
import Results from './pages/Results.vue'
import Register from './pages/Register.vue'
import Reports from './pages/Reports.vue'
import { useApp } from '@/js/components/app'

const { hack, path, params, experience, focused, bare, page } = useApp()
</script>

<template>
  <FocusShell v-if="focused" :title="experience.title">
    <Attendance v-if="page === 'presenca'" :params="params" />
    <JudgeArea v-else-if="page === 'area-jurado'" />
    <Evaluate v-else-if="page === 'avaliar'" :key="params.id || '1'" :params="params" />
    <PublicVote v-else-if="page === 'votacao'" />
  </FocusShell>
  <Shell v-else-if="!bare" :path="path">
    <Dashboard v-if="page === 'dashboard'" />
    <Config v-else-if="page === 'config'" section="evento" />
    <People v-else-if="page === 'participantes'" :params="params" />
    <Teams v-else-if="page === 'equipes'" />
    <Companies v-else-if="page === 'empresas'" mode="empresas" />
    <Companies v-else-if="page === 'desafios'" mode="desafios" />
    <TeamBuild v-else-if="page === 'montar'" :params="params" />
    <Roulette v-else-if="page === 'roletas'" :params="params" />
    <CompanyDetail v-else-if="page === 'empresa'" :params="params" />
    <ChallengeDetail v-else-if="page === 'desafio'" :params="params" />
    <Distribution v-else-if="page === 'distribuicao'" />
    <Sectors v-else-if="page === 'setores'" :params="params" />
    <Meetings v-else-if="page === 'reunioes'" :params="params" />
    <Meetings v-else-if="page === 'pendencias'" :params="{ ...params, aba: 'pendencias' }" />
    <Meetings v-else-if="page === 'documentos'" :params="{ aba: 'documentos' }" />
    <MeetingDetail v-else-if="page === 'reuniao'" :params="params" />
    <Manifest v-else-if="page === 'manifestacao'" :params="params" />
    <Reports v-else-if="page === 'relatorios' || page === 'relatorio'" :params="params" />
    <Attendance v-else-if="page === 'presenca'" :params="params" />
    <Occurrences v-else-if="page === 'ocorrencias'" :params="params" />
    <Judges v-else-if="page === 'jurados'" part="jurados" :params="params" />
    <Judges v-else-if="page === 'avaliacoes'" part="avaliacoes" :params="params" />
    <Judges v-else-if="page === 'votacao-gestao'" part="votacao" :params="params" />
    <Judges v-else-if="page === 'resultados'" part="resultados" :params="params" />
    <Config v-else-if="page === 'usuarios'" section="usuarios" />
    <Audit v-else-if="page === 'auditoria'" />
    <Awards v-else-if="page === 'premiacao'" />
    <Results v-else-if="page === 'painel'" />
    <Dashboard v-else />
  </Shell>
  <template v-else>
    <Login v-if="page === 'login'" />
    <Register v-else-if="page === 'cadastro'" />
    <FocusFrame v-else-if="page === 'area-jurado'" title="Área do Jurado" exit-to="avaliacoes">
      <JudgeArea />
    </FocusFrame>
    <FocusFrame v-else-if="page === 'avaliar'" title="Área do Jurado" exit-to="area-jurado">
      <Evaluate :key="params.id || '1'" :params="params" />
    </FocusFrame>
    <FocusFrame v-else-if="page === 'votacao'" title="Votação do Público" exit-to="votacao-gestao">
      <PublicVote />
    </FocusFrame>
    <Presentation v-else-if="page === 'apresentacao'" />
    <Dashboard v-else />
  </template>
  <div v-if="hack.toast" class="toast" :class="{ err: hack.toast.type === 'err' }" role="status">
    <Icon :name="hack.toast.type === 'err' ? 'info' : 'check'" :size="16" />
    <span>{{ hack.toast.text }}</span>
  </div>
</template>
