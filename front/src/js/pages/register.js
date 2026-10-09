// Lógica do componente Register.vue (o template fica no .vue).
import { computed, ref } from 'vue'
import { findInvite } from '@/js/data/model'
import { go, useHack } from '@/js/stores/hack'

export function useRegister() {
  // Cadastro público: apenas Votante (livre) e Jurado (com código de convite).
  // Perfis internos são criados pelo Administrador em Usuários e Permissões.
  const OPTIONS = [
    { id: 'Votante', title: 'Votante', text: 'Participe da votação pública das soluções.' },
    { id: 'Jurado', title: 'Jurado', text: 'Avalie as soluções atribuídas a você.' },
  ]
  const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

  const hack = useHack()
  const kind = ref('')
  const form = ref({ name: '', email: '', password: '', confirm: '', code: '', own: false })
  const errors = ref({})
  const invite = computed(() => (kind.value === 'Jurado' ? findInvite(hack.state, form.value.code) : null))

  function validate() {
    const f = form.value
    const found = {}
    if (!f.name.trim()) found.name = 'Preencha seu nome.'
    if (!EMAIL.test(f.email.trim())) found.email = 'Informe um e-mail válido.'
    if (!f.password.trim()) found.password = 'Crie uma senha.'
    else if (f.password.trim().length < 4) found.password = 'Use pelo menos 4 caracteres.'
    if (f.confirm !== f.password) found.confirm = 'As senhas não coincidem.'
    if (kind.value === 'Jurado' && !f.code.trim()) found.code = 'Informe o código de convite.'
    if (!f.own) found.own = 'Confirme que as informações fornecidas são suas.'
    return found
  }

  function submit(event) {
    event.preventDefault()
    errors.value = validate()
    if (Object.keys(errors.value).length) return
    const problem = hack.register({ kind: kind.value, ...form.value })
    if (problem) errors.value = { [problem.field]: problem.message }
  }

  function choose(id) {
    kind.value = id
    errors.value = {}
  }

  return {
    OPTIONS,
    kind,
    form,
    errors,
    invite,
    submit,
    choose,
    go,
  }
}
