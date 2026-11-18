<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthPageShell from '@/components/auth/AuthPageShell.vue'
import { api, getErrorMessage, getValidationErrors } from '@/lib/api'
import { useUiStore } from '@/stores/ui'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()

const email = ref('')
const token = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})
const missingParams = ref(false)

onMounted(() => {
  email.value = typeof route.query.email === 'string' ? route.query.email : ''
  token.value = typeof route.query.token === 'string' ? route.query.token : ''
  missingParams.value = !email.value || !token.value
})

async function submit() {
  loading.value = true
  error.value = null
  fieldErrors.value = {}

  try {
    await api.post('/api/v1/auth/reset-password', {
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    ui.success('Palavra-passe redefinida. Entre com a nova password.')
    await router.push({ name: 'login' })
  } catch (err) {
    fieldErrors.value = getValidationErrors(err)
    error.value = getErrorMessage(err, 'Não foi possível redefinir a palavra-passe.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthPageShell
    image-title="Nova palavra-passe"
    image-text="Escolha uma password segura para voltar a aceder à sua conta."
    image-icon="fas fa-shield-alt"
    title="Redefinir password"
    subtitle="Defina a nova palavra-passe"
    header-icon="fas fa-lock"
    centered
  >
    <div v-if="missingParams" class="alert alert-warning" role="alert">
      Link de redefinição inválido ou incompleto. Peça um novo email em
      <RouterLink :to="{ name: 'forgot-password' }">Esqueci a password</RouterLink>.
    </div>

    <form v-else @submit.prevent="submit">
      <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>

      <div class="mb-3">
        <label for="reset-email" class="form-label">Email</label>
        <input
          id="reset-email"
          v-model="email"
          type="email"
          class="form-control"
          required
          autocomplete="email"
          readonly
        />
      </div>

      <div class="mb-3">
        <label for="reset-password" class="form-label">Nova palavra-passe</label>
        <input
          id="reset-password"
          v-model="password"
          type="password"
          class="form-control"
          :class="{ 'is-invalid': fieldErrors.password }"
          required
          minlength="8"
          autocomplete="new-password"
        />
        <div v-if="fieldErrors.password" class="invalid-feedback">{{ fieldErrors.password }}</div>
      </div>

      <div class="mb-3">
        <label for="reset-password-confirmation" class="form-label">Confirmar palavra-passe</label>
        <input
          id="reset-password-confirmation"
          v-model="passwordConfirmation"
          type="password"
          class="form-control"
          required
          minlength="8"
          autocomplete="new-password"
        />
      </div>

      <div class="d-grid gap-2 mb-3">
        <button type="submit" class="btn btn-primary btn-lg" :disabled="loading">
          <span
            v-if="loading"
            class="spinner-border spinner-border-sm me-2"
            role="status"
            aria-hidden="true"
          />
          Guardar nova password
        </button>
      </div>
    </form>

    <div class="text-center mt-3">
      <RouterLink :to="{ name: 'login' }" class="btn btn-link">Voltar ao login</RouterLink>
    </div>
  </AuthPageShell>
</template>
