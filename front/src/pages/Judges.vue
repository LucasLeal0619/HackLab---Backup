<script setup>
import Badge from '../components/Badge.vue'
import Drawer from '../components/Drawer.vue'
import Empty from '../components/Empty.vue'
import Field from '../components/Field.vue'
import Modal from '../components/Modal.vue'
import Page from '../components/Page.vue'
import { useJudges } from '@/js/pages/judges'

const props = defineProps({
  params: { type: Object, default: () => ({}) },
  part: { type: String },
})
const { TYPES, state, dash, teamLabel, doneEvals, teamStatus, voteCount, isInactive, isDemo, scaleOf, weightOf, showAdmin, showJudgesBlock, showEvalBlock, showVote, showResults, heading, modal, form, confirmVote, judgeDetail, removing, showVotes, activeJudges, reps, selectedRep, totalVotes, releaseResults, saveJudge, toggleAssigned, assignedLabel, saveCriterion, inviteForm, inviteResult, inviteReps, openInvite, pickInviteRep, generateInvite, copyInvite, openJudge, openCriterion, editJudge, judgeDone, toggleCriterion, applyVote, removeRecord, canAccess, teamName, go, toneFor } = useJudges(props)
</script>

<template>
  <Page :title="heading[0]" :subtitle="heading[1]">
    <template #actions>
      <button v-if="showJudgesBlock && showAdmin" class="btn ghost" type="button" @click="openInvite">Gerar convite</button>
      <button v-if="showJudgesBlock && showAdmin" class="btn" type="button" @click="openJudge">+ Adicionar jurado</button>
      <button v-else-if="showEvalBlock && showAdmin && canAccess(state.session?.profile, 'area-jurado')" class="btn" type="button" @click="go('area-jurado')">Abrir Área do Jurado</button>
    </template>

    <template v-if="showAdmin">
      <template v-if="showJudgesBlock">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Jurado</th><th>Empresa</th><th>Equipes atribuídas</th><th>Avaliações</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
              <tr v-if="state.judges.length === 0"><td colspan="6"><Empty title="Nenhum jurado cadastrado." text="Adicione um jurado ou gere um convite para o cadastro público." /></td></tr>
              <tr v-for="judge in state.judges" :key="judge.id">
                <td>{{ judge.name }}</td>
                <td>{{ judge.companyName || 'Sem empresa' }}</td>
                <td>{{ assignedLabel(judge) }}</td>
                <td>{{ dash(judgeDone(judge)) }}</td>
                <td><Badge :tone="toneFor(judge.status)">{{ judge.status }}</Badge></td>
                <td>
                  <div class="row-actions">
                    <button class="btn ghost small" type="button" @click="judgeDetail = judge">Visualizar</button>
                    <button class="btn ghost small" type="button" @click="editJudge(judge)">Editar</button>
                    <button class="btn ghost small" type="button" @click="removing = { kind: 'jurado', id: judge.id, name: judge.name }">Excluir</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <h3 class="ops-title">Convites de jurado</h3>
        <p class="stat-hint">O jurado usa o código no cadastro público. Convite utilizado não autoriza um novo cadastro.</p>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Código</th><th>Representante</th><th>Empresa</th><th>E-mail</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-if="!(state.invites || []).length"><td colspan="5"><Empty title="Nenhum convite gerado." text="Use “Gerar convite” para autorizar o cadastro de um jurado." /></td></tr>
              <tr v-for="item in state.invites || []" :key="item.id">
                <td><span class="ticket-code">{{ item.code }}</span> <Badge v-if="item.demo">Dado demonstrativo</Badge></td>
                <td>{{ item.repName || '—' }}</td>
                <td>{{ item.companyName || '—' }}</td>
                <td>{{ item.email || '—' }}</td>
                <td><Badge :tone="item.status === 'Utilizado' ? 'ok' : ''">{{ item.status }}</Badge></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="row-between mt">
          <div>
            <h3 class="ops-title">Critérios de Avaliação</h3>
            <p class="stat-hint">Os critérios oficiais ainda não foram definidos. Eles são configuráveis.</p>
          </div>
          <button class="btn ghost" type="button" @click="openCriterion()">+ Adicionar critério</button>
        </div>
        <Empty v-if="state.criteria.length === 0" title="Nenhum critério configurado." text="Adicione um critério quando a organização definir a avaliação. Um exemplo pode usar o nome Critério demonstrativo 01." />
        <div v-else class="table-wrap">
          <table>
            <thead><tr><th>Critério</th><th>Tipo</th><th>Escala</th><th>Peso</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
              <tr v-for="item in state.criteria" :key="item.id">
                <td>
                  {{ item.name }}
                  <div v-if="isDemo(item)"><Badge>Dado demonstrativo</Badge></div>
                  <div v-if="item.description"><small>{{ item.description }}</small></div>
                </td>
                <td>{{ item.type }}</td>
                <td>{{ scaleOf(item) }}</td>
                <td>{{ weightOf(item) }}</td>
                <td><Badge :tone="toneFor(isInactive(item) ? 'Inativo' : 'Ativo')">{{ isInactive(item) ? 'Inativo' : 'Ativo' }}</Badge></td>
                <td>
                  <div class="row-actions">
                    <button class="btn ghost small" type="button" @click="openCriterion(item)">Editar</button>
                    <button class="btn ghost small" type="button" @click="toggleCriterion(item)">{{ isInactive(item) ? 'Ativar' : 'Inativar' }}</button>
                    <button class="btn ghost small" type="button" @click="removing = { kind: 'criterio', id: item.id, name: item.name }">Excluir</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <template v-if="showEvalBlock">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Equipe</th><th>Desafio</th><th>Jurados</th><th>Avaliações</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
              <tr v-for="team in state.teams" :key="team.id">
                <td>{{ teamName(team.id) }}</td>
                <td>{{ teamLabel(team).challenge?.title || '—' }}</td>
                <td>{{ dash(activeJudges.length) }}</td>
                <td>{{ doneEvals(team.id).length || '—' }}</td>
                <td><Badge :tone="toneFor(teamStatus(team.id))">{{ teamStatus(team.id) }}</Badge></td>
                <td>
                  <div class="row-actions">
                    <button class="btn ghost small" type="button" @click="go(`avaliar?id=${team.id}`)">Editar</button>
                    <button class="btn ghost small" type="button" @click="removing = { kind: 'avaliacao', id: team.id, name: teamName(team.id) }">Excluir</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="state.evaluations.length === 0" class="stat-hint">Nenhuma avaliação iniciada.</p>
      </template>
    </template>

    <section v-if="showVote" class="vote-admin">
      <div class="row-between">
        <p class="stat-hint">A votação popular é independente do resultado técnico dos jurados. Os dois resultados não são somados.</p>
        <Badge :tone="toneFor(state.voting.status)">{{ state.voting.status }}</Badge>
      </div>
      <div class="page-actions">
        <button v-if="state.voting.status === 'Não iniciada'" class="btn" type="button" @click="confirmVote = 'start'">Iniciar votação</button>
        <button v-if="state.voting.status === 'Em andamento'" class="btn" type="button" @click="confirmVote = 'end'">Encerrar votação</button>
        <button v-if="state.voting.status === 'Encerrada'" class="btn" type="button" @click="showVotes = true">Visualizar resultado</button>
        <button class="btn ghost" type="button" @click="go('votacao')">Abrir Votação Pública</button>
      </div>
      <p v-if="state.voting.status !== 'Encerrada'" class="stat-hint">A tela pública não mostra votos, percentuais nem equipe mais votada enquanto a votação estiver aberta.</p>
      <div class="vote-qr">
        <div class="qr" aria-hidden="true" />
        <p class="stat-hint">QR Code demonstrativo — representação visual, sem leitura real.</p>
      </div>
      <p v-if="state.voting.status === 'Não iniciada'" class="stat-hint">A votação ainda não foi iniciada.</p>
      <div v-if="showVotes && state.voting.status === 'Encerrada'" class="mt">
        <h3 class="ops-title">Resultado da votação</h3>
        <Empty v-if="totalVotes === 0" title="Nenhum voto registrado." text="Não há dados suficientes para um resultado." />
        <div v-else class="table-wrap">
          <table>
            <thead><tr><th>Equipe</th><th>Votos</th><th>Percentual</th></tr></thead>
            <tbody>
              <tr v-for="team in state.teams" :key="team.id">
                <td>{{ teamName(team.id) }}</td>
                <td>{{ voteCount(team.id) }}</td>
                <td>{{ Math.round((voteCount(team.id) / totalVotes) * 100) }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section v-if="showResults" class="card">
      <p>{{ state.resultsReleased ? 'A divulgação já foi liberada.' : 'Verifique os resultados disponíveis e libere a divulgação quando estiver pronto.' }}</p>
      <div class="page-actions">
        <button class="btn" type="button" @click="go('painel')">Visualizar resultados</button>
        <button class="btn ghost" type="button" :disabled="state.resultsReleased" @click="releaseResults">Liberar resultados</button>
        <button class="btn ghost" type="button" @click="go('painel')">Abrir Painel de Resultados</button>
        <button class="btn ghost" type="button" @click="go('apresentacao')">Modo Apresentação</button>
      </div>
    </section>

    <Modal v-if="modal === 'juiz'" :title="form.id ? 'Editar jurado' : 'Adicionar jurado'" subtitle="A empresa é opcional e só dá contexto. As equipes avaliadas são as atribuídas abaixo." wide @close="modal = null">
      <div class="form-grid">
        <Field label="Empresa (opcional)">
          <select v-model="form.companyId" class="input" @change="form.repId = ''">
            <option value="">Sem empresa</option>
            <option v-for="item in state.companies" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
        </Field>
        <Field v-if="form.companyId" label="Representante (opcional)" hint="Usa os dados já cadastrados do representante.">
          <select v-model="form.repId" class="input">
            <option value="">Não é representante</option>
            <option v-for="rep in reps" :key="rep.id || rep.name" :value="rep.id">{{ rep.name }}</option>
          </select>
        </Field>
        <template v-if="!selectedRep">
          <Field label="Nome" required><input v-model="form.name" class="input" /></Field>
          <Field label="E-mail"><input v-model="form.email" class="input" type="email" /></Field>
          <Field label="Cargo / Função"><input v-model="form.cargo" class="input" /></Field>
        </template>
        <Field v-else label="Cargo / Função"><input class="input" :value="selectedRep.cargo || ''" readonly placeholder="—" /></Field>
        <Field label="Status">
          <select v-model="form.status" class="input">
            <option>Ativo</option>
            <option>Inativo</option>
          </select>
        </Field>
        <div class="field span-2">
          <span>Equipes atribuídas</span>
          <small class="stat-hint">O jurado avalia somente estas equipes. Uma equipe pode ter vários jurados.</small>
          <p v-if="state.teams.length === 0" class="stat-hint">Nenhuma equipe formada ainda.</p>
          <div v-else class="chips">
            <button v-for="team in state.teams" :key="team.id" type="button" class="chip" :class="{ on: (form.assignedTeamIds || []).includes(team.id) }" :aria-pressed="(form.assignedTeamIds || []).includes(team.id)" @click="toggleAssigned(team.id)">{{ teamName(team.id) }}</button>
          </div>
        </div>
      </div>
      <template #footer>
        <button class="btn ghost" type="button" @click="modal = null">Cancelar</button>
        <button class="btn" type="button" @click="saveJudge">{{ form.id ? 'Salvar alterações' : 'Salvar' }}</button>
      </template>
    </Modal>

    <Modal v-if="inviteForm" title="Convidar Jurado" subtitle="Gera um código para o cadastro público. Nenhum e-mail é enviado." @close="inviteForm = null">
      <Field label="Empresa (opcional)">
        <select v-model="inviteForm.companyId" class="input" @change="inviteForm.repId = ''">
          <option value="">Sem empresa</option>
          <option v-for="item in state.companies" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
      </Field>
      <Field label="Representante" required>
        <select v-if="inviteReps.length" class="input" :value="inviteForm.repId" @change="pickInviteRep($event.target.value)">
          <option value="">Selecione o representante</option>
          <option v-for="item in inviteReps" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
        <input v-else v-model="inviteForm.repName" class="input" placeholder="Nome do representante" />
      </Field>
      <Field label="E-mail" required><input v-model="inviteForm.email" class="input" type="email" /></Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="inviteForm = null">Cancelar</button>
        <button class="btn" type="button" @click="generateInvite">Gerar código</button>
      </template>
    </Modal>
    <Modal v-if="inviteResult" title="Código de convite" :subtitle="`${inviteResult.repName}${inviteResult.companyName ? ` · ${inviteResult.companyName}` : ''}`" @close="inviteResult = null">
      <p class="invite-code">{{ inviteResult.code }}</p>
      <p class="stat-hint">Entregue o código ao jurado. Ele cria o próprio cadastro em “Criar cadastro público”.</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="inviteResult = null">Fechar</button>
        <button class="btn" type="button" @click="copyInvite(inviteResult.code)">Copiar código</button>
      </template>
    </Modal>
    <Modal v-if="modal === 'criterio'" :title="form.id ? 'Editar critério' : 'Novo critério'" subtitle="Nenhum critério é oficial até a organização definir a avaliação." @close="modal = null">
      <Field label="Nome" required hint="Para um exemplo, use Critério demonstrativo 01."><input v-model="form.name" class="input" /></Field>
      <Field label="Descrição"><textarea v-model="form.description" class="input" /></Field>
      <div class="field">
        <span>Tipo</span>
        <div class="chips">
          <button v-for="item in TYPES" :key="item" type="button" class="chip" :class="{ on: form.type === item }" @click="form.type = item">{{ item }}</button>
        </div>
      </div>
      <div class="form-grid">
        <Field label="Nota mínima"><input v-model="form.min" class="input" /></Field>
        <Field label="Nota máxima"><input v-model="form.max" class="input" /></Field>
      </div>
      <Field label="Peso" hint="Opcional. Não define a fórmula oficial."><input v-model="form.weight" class="input" /></Field>
      <Field label="Status">
        <select v-model="form.status" class="input">
          <option>Ativo</option>
          <option>Inativo</option>
        </select>
      </Field>
      <template #footer>
        <button class="btn ghost" type="button" @click="modal = null">Cancelar</button>
        <button class="btn" type="button" @click="saveCriterion">{{ form.id ? 'Salvar alterações' : 'Salvar' }}</button>
      </template>
    </Modal>

    <Modal
      v-if="confirmVote"
      :title="confirmVote === 'start' ? 'Iniciar votação do público?' : 'Encerrar votação?'"
      :subtitle="confirmVote === 'end' ? 'Após o encerramento, novos votos não serão registrados neste protótipo.' : 'Os participantes poderão escolher uma equipe. A tela pública não mostra resultado parcial.'"
      @close="confirmVote = false"
    >
      <p>{{ confirmVote === 'start' ? 'Um voto por navegador.' : 'O resultado da votação continua separado do resultado dos jurados.' }}</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="confirmVote = false">Cancelar</button>
        <button class="btn" type="button" @click="applyVote">{{ confirmVote === 'start' ? 'Iniciar votação' : 'Encerrar votação' }}</button>
      </template>
    </Modal>

    <Modal
      v-if="removing"
      :title="removing.kind === 'avaliacao' ? 'Excluir avaliação?' : 'Excluir registro?'"
      :subtitle="removing.kind === 'avaliacao' ? 'As notas desta equipe saem deste navegador.' : 'O registro será removido deste navegador.'"
      @close="removing = null"
    >
      <p>{{ removing.name }}</p>
      <template #footer>
        <button class="btn ghost" type="button" @click="removing = null">Cancelar</button>
        <button class="btn danger" type="button" @click="removeRecord">Excluir</button>
      </template>
    </Modal>

    <Drawer v-if="judgeDetail" :title="judgeDetail.name" :subtitle="judgeDetail.companyName || 'Sem empresa vinculada'" @close="judgeDetail = null">
      <p><b>Equipes atribuídas</b><br />{{ assignedLabel(judgeDetail) }}</p>
      <p><b>Cargo / Função</b><br />{{ judgeDetail.cargo || '—' }}</p>
      <p><b>Status</b><br />{{ judgeDetail.status }}</p>
      <p><b>E-mail</b><br />{{ judgeDetail.email || '—' }}</p>
      <p><b>Telefone</b><br />{{ judgeDetail.phone || '—' }}</p>
      <p class="stat-hint">A empresa é apenas contexto: ela não define as equipes avaliadas.</p>
    </Drawer>
  </Page>
</template>
