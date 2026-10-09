// Lógica do componente ProfileMenu.vue (o template fica no .vue).
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { PROFILE_NAMES, profileConfig, sectorScope } from '@/js/config/access'
import { SETORES } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'

export function useProfileMenu() {
  // ProfileSwitcher é apenas uma ferramenta de simulação do protótipo.
  // Posteriormente será substituído por autenticação e autorização reais.

  const hack = useHack()
  const open = ref(false)
  const root = ref(null)
  const session = computed(() => hack.state.session)
  const config = computed(() => profileConfig(session.value?.profile))
  const activeSector = computed(() => sectorScope(session.value)?.[0] || '')
  // Conta criada no cadastro público: o seletor de perfis não aparece (é só ferramenta de demonstração).
  const external = computed(() => Boolean(session.value?.external))

  function choose(profile) {
    open.value = false
    if (profile !== session.value?.profile) hack.setProfile(profile)
  }

  function chooseSector(sector) {
    hack.setDemoSector(sector)
    open.value = false
    go(`setores?setor=${encodeURIComponent(sector)}`)
  }

  function run(action) {
    open.value = false
    action()
  }

  function onPointer(event) {
    if (open.value && root.value && !root.value.contains(event.target)) open.value = false
  }

  function onKey(event) {
    if (open.value && event.key === 'Escape') open.value = false
  }

  onMounted(() => {
    document.addEventListener('mousedown', onPointer)
    document.addEventListener('keydown', onKey)
  })
  onUnmounted(() => {
    document.removeEventListener('mousedown', onPointer)
    document.removeEventListener('keydown', onKey)
  })

  return {
    hack,
    open,
    root,
    session,
    config,
    activeSector,
    external,
    choose,
    chooseSector,
    run,
    PROFILE_NAMES,
    SETORES,
    go,
  }
}
