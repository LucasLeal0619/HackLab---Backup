// Lógica do componente App.vue (o template fica no .vue).
import { computed, watch } from 'vue'
import { canAccess, homeFor, profileConfig } from '@/js/config/access'
import { go, useHack, useRoute } from '@/js/stores/hack'

export function useApp() {
  const OPEN = new Set(['login', 'cadastro', 'votacao', 'apresentacao'])
  const BARE = new Set(['login', 'cadastro', 'votacao', 'apresentacao', 'area-jurado', 'avaliar'])

  function legacyTarget(current, currentParams) {
    if (current === 'inicio') return 'dashboard'
    if (current === 'preparacao') {
      if (currentParams.aba === 'participantes') return 'participantes'
      if (currentParams.aba === 'equipes') return 'equipes'
      if (currentParams.aba === 'empresas') return currentParams.inner === 'desafios' ? 'desafios' : 'empresas'
      return 'config'
    }
    if (current === 'gestao') {
      if (currentParams.aba === 'pendencias') return 'pendencias'
      if (currentParams.aba === 'documentos') return 'documentos'
      if (currentParams.aba === 'reunioes' || currentParams.aba === 'atas' || currentParams.aba === 'decisoes') {
        const lista = currentParams.aba === 'atas' || currentParams.lista === 'atas' ? 'atas' : currentParams.aba === 'decisoes' || currentParams.lista === 'decisoes' ? 'decisoes' : ''
        return lista ? `reunioes?lista=${lista}` : 'reunioes'
      }
      if (currentParams.setor) return `setores?setor=${encodeURIComponent(currentParams.setor)}`
      return 'setores'
    }
    if (current === 'encerramento') {
      if (currentParams.aba === 'avaliacoes') return 'avaliacoes'
      if (currentParams.aba === 'votacao') return 'votacao-gestao'
      if (currentParams.aba === 'resultados') return 'resultados'
      return 'jurados'
    }
    if (current === 'empresas' && currentParams.aba === 'desafios') return 'desafios'
    // Antigo Modo Evento: presença e credenciais ficam em Credenciais e Presença.
    // Antigos ingresso e validação de ingresso: tudo fica em Credenciais e Presença.
    if (current === 'evento' || current === 'ingresso' || current === 'validar') {
      return [1, 2, 3].includes(Number(currentParams.dia)) ? `presenca?dia=${currentParams.dia}` : 'presenca'
    }
    if (current === 'sala') return `setores?setor=${encodeURIComponent('Produção')}`
    return ''
  }

  const route = useRoute()
  const hack = useHack()
  const path = computed(() => route.value.path)
  const params = computed(() => route.value.params)
  const profile = computed(() => hack.state.session?.profile)
  const experience = computed(() => profileConfig(profile.value))
  // Validador, Jurado e Votante usam uma experiência focada, sem a Sidebar administrativa.
  const focused = computed(() => Boolean(hack.state.session) && experience.value.layout === 'focus' && !['login', 'cadastro'].includes(path.value))
  const bare = computed(() => BARE.has(path.value) || !hack.state.session)

  const page = computed(() => path.value)

  watch([path, params, () => hack.state.session, profile], () => {
    const session = hack.state.session
    if (!session) {
      if (!OPEN.has(path.value)) go('login')
      return
    }
    const legacy = legacyTarget(path.value, params.value)
    if (legacy) { go(legacy); return }
    // Página fora da experiência do perfil atual: volta para a página inicial dele.
    if (path.value === 'login' || !canAccess(profile.value, path.value)) go(homeFor(profile.value))
  }, { immediate: true })

  return {
    hack,
    path,
    params,
    experience,
    focused,
    bare,
    page,
  }
}
