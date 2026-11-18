<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthPageShell from '@/components/auth/AuthPageShell.vue'
import { api, getErrorMessage, getValidationErrors } from '@/lib/api'

const email = ref('')
const loading = ref(false)
const success = ref(false)
const error = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})

async function submit() {
  loading.value = true
  error.value = null
  fieldErrors.value = {}
  success.value = false

  try {
    await api.post('/api/v1/auth/forgot-password', { email: email.value })
    success.value = true
  } catch (err) {
    fieldErrors.value = getValidationErrors(err)
    error.value = getErrorMessage(err, 'Não foi possível enviar as instruções.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthPageShell
    image-title="Recuperar acesso"
    image-text="Enviamos instruções seguras para redefinir a sua palavra-passe."
    image-icon="fas fa-key"
    title="Esqueci a password"
    subtitle="Indique o email da sua conta"
    header-icon="fas fa-unlock-alt"
    centered
  >
    <div v-if="success" class="alert alert-success" role="status">
      Se existir uma conta associada a este email, receberá instruções para redefinir a palavra-passe.
    </div>

    <form v-else @submit.prevent="submit">
      <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>

      <div class="mb-3">
        <label for="forgot-email" class="form-label">
          <i class="fas fa-envelope me-2" style="color: var(--accent-terracotta)" aria-hidden="true" />
          Email
        </label>
        <input
          id="forgot-email"
          v-model="email"
          type="email"
          class="form-control"
          :class="{ 'is-invalid': fieldErrors.email }"
          placeholder="O seu email"
          required
          autocomplete="email"
        />
        <div v-if="fieldErrors.email" class="invalid-feedback">{{ fieldErrors.email }}</div>
      </div>

      <div class="d-grid gap-2 mb-3">
        <button type="submit" class="btn btn-primary btn-lg" :disabled="loading">
          <span
            v-if="loading"
            class="spinner-border spinner-border-sm me-2"
            role="status"
            aria-hidden="true"
          />
          Enviar instruções
        </button>
      </div>
    </form>

    <div class="text-center mt-3">
      <RouterLink :to="{ name: 'login' }" class="btn btn-link">
        Voltar ao login
      </RouterLink>
    </div>
  </AuthPageShell>
</template>
