<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppModal from '@/components/ui/AppModal.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { getErrorMessage, getValidationErrors } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

const auth = useAuthStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const loading = ref(true)
const savingProfile = ref(false)
const savingPassword = ref(false)
const profileOpen = ref(false)
const passwordOpen = ref(false)

const profileForm = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
})

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const profileErrors = reactive<Record<string, string>>({})
const passwordErrors = reactive<Record<string, string>>({})

function clearErrors(target: Record<string, string>) {
  Object.keys(target).forEach((key) => {
    target[key] = ''
  })
}

function fillProfileForm() {
  profileForm.first_name = auth.user?.first_name ?? ''
  profileForm.last_name = auth.user?.last_name ?? ''
  profileForm.email = auth.user?.email ?? ''
  profileForm.phone = auth.user?.phone ?? ''
}

function openEditProfile() {
  fillProfileForm()
  clearErrors(profileErrors)
  profileOpen.value = true
}

function openChangePassword() {
  passwordForm.current_password = ''
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
  clearErrors(passwordErrors)
  passwordOpen.value = true
}

function applyActionQuery() {
  const action = route.query.action
  if (action === 'edit-profile') openEditProfile()
  if (action === 'change-password') openChangePassword()
}

async function clearActionQuery() {
  if (!route.query.action) return
  await router.replace({ name: route.name ?? 'panel-account', query: {} })
}

watch(
  () => route.query.action,
  () => applyActionQuery(),
)

onMounted(async () => {
  try {
    await auth.fetchMe()
    fillProfileForm()
    applyActionQuery()
  } catch {
    ui.error('Não foi possível carregar a conta.')
  } finally {
    loading.value = false
  }
})

async function saveProfile() {
  savingProfile.value = true
  clearErrors(profileErrors)
  try {
    await auth.updateProfile({
      first_name: profileForm.first_name,
      last_name: profileForm.last_name,
      email: profileForm.email,
      phone: profileForm.phone || null,
    })
    ui.success('Perfil atualizado.')
    profileOpen.value = false
    await clearActionQuery()
  } catch (err) {
    const fieldErrors = getValidationErrors(err)
    Object.assign(profileErrors, fieldErrors)
    ui.error(getErrorMessage(err, 'Não foi possível atualizar o perfil.'))
  } finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  savingPassword.value = true
  clearErrors(passwordErrors)
  try {
    await auth.updatePassword({
      current_password: passwordForm.current_password,
      password: passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    })
    ui.success('Palavra-passe atualizada.')
    passwordOpen.value = false
    await clearActionQuery()
  } catch (err) {
    const fieldErrors = getValidationErrors(err)
    Object.assign(passwordErrors, fieldErrors)
    ui.error(getErrorMessage(err, 'Não foi possível alterar a palavra-passe.'))
  } finally {
    savingPassword.value = false
  }
}

async function closeProfileModal() {
  profileOpen.value = false
  await clearActionQuery()
}

async function closePasswordModal() {
  passwordOpen.value = false
  await clearActionQuery()
}
</script>

<template>
  <LoadingState v-if="loading" />

  <div v-else class="w-full space-y-6">
    <p class="text-sm text-[#6B7280]">
      Gerencie os seus dados pessoais e a segurança da conta.
    </p>

    <AppCard padding="none">
      <div class="flex flex-col gap-4 border-b border-[#E5E7EB] px-5 py-6 sm:flex-row sm:items-center sm:px-6">
        <UserAvatar :name="auth.fullName" size="lg" />
        <div class="min-w-0">
          <h3 class="text-xl font-semibold text-[#1F2937]">{{ auth.fullName }}</h3>
          <p class="mt-1 text-sm text-[#6B7280]">{{ auth.user?.email }}</p>
          <div class="mt-3">
            <AppBadge tone="brand">{{ auth.user?.role_label }}</AppBadge>
          </div>
        </div>
      </div>

      <div class="space-y-6 px-5 py-6 sm:px-6">
        <section>
          <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h4 class="text-base font-semibold text-[#1F2937]">Informações pessoais</h4>
              <p class="mt-0.5 text-sm text-[#6B7280]">Dados de identificação da conta.</p>
            </div>
            <AppButton variant="secondary" size="sm" @click="openEditProfile">Editar perfil</AppButton>
          </div>

          <dl class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-[#E5E7EB] bg-[#FAFAFA] px-4 py-3">
              <dt class="text-xs font-medium uppercase tracking-wide text-[#6B7280]">Nome</dt>
              <dd class="mt-1 text-sm font-medium text-[#1F2937]">{{ auth.user?.first_name || '—' }}</dd>
            </div>
            <div class="rounded-lg border border-[#E5E7EB] bg-[#FAFAFA] px-4 py-3">
              <dt class="text-xs font-medium uppercase tracking-wide text-[#6B7280]">Apelido</dt>
              <dd class="mt-1 text-sm font-medium text-[#1F2937]">{{ auth.user?.last_name || '—' }}</dd>
            </div>
            <div class="rounded-lg border border-[#E5E7EB] bg-[#FAFAFA] px-4 py-3">
              <dt class="text-xs font-medium uppercase tracking-wide text-[#6B7280]">Email</dt>
              <dd class="mt-1 break-all text-sm font-medium text-[#1F2937]">
                {{ auth.user?.email || '—' }}
              </dd>
            </div>
            <div class="rounded-lg border border-[#E5E7EB] bg-[#FAFAFA] px-4 py-3">
              <dt class="text-xs font-medium uppercase tracking-wide text-[#6B7280]">Telefone</dt>
              <dd class="mt-1 text-sm font-medium text-[#1F2937]">{{ auth.user?.phone || '—' }}</dd>
            </div>
          </dl>
        </section>

        <section class="border-t border-[#E5E7EB] pt-6">
          <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h4 class="text-base font-semibold text-[#1F2937]">Segurança</h4>
              <p class="mt-0.5 text-sm text-[#6B7280]">Altere a palavra-passe da conta.</p>
            </div>
            <AppButton variant="secondary" size="sm" @click="openChangePassword">
              Alterar palavra-passe
            </AppButton>
          </div>
        </section>
      </div>
    </AppCard>

    <AppModal :open="profileOpen" title="Editar perfil" @close="closeProfileModal">
      <form class="space-y-4" @submit.prevent="saveProfile">
        <div>
          <AppInput v-model="profileForm.first_name" label="Nome" required />
          <p v-if="profileErrors.first_name" class="mt-1 text-sm text-red-600">
            {{ profileErrors.first_name }}
          </p>
        </div>
        <div>
          <AppInput v-model="profileForm.last_name" label="Apelido" required />
          <p v-if="profileErrors.last_name" class="mt-1 text-sm text-red-600">
            {{ profileErrors.last_name }}
          </p>
        </div>
        <div>
          <AppInput v-model="profileForm.email" label="Email" type="email" required />
          <p v-if="profileErrors.email" class="mt-1 text-sm text-red-600">{{ profileErrors.email }}</p>
        </div>
        <div>
          <AppInput v-model="profileForm.phone" label="Telefone" />
          <p v-if="profileErrors.phone" class="mt-1 text-sm text-red-600">{{ profileErrors.phone }}</p>
        </div>
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="closeProfileModal">Cancelar</AppButton>
        <AppButton :loading="savingProfile" @click="saveProfile">Guardar</AppButton>
      </template>
    </AppModal>

    <AppModal :open="passwordOpen" title="Alterar palavra-passe" @close="closePasswordModal">
      <form class="space-y-4" @submit.prevent="savePassword">
        <div>
          <AppInput
            v-model="passwordForm.current_password"
            label="Palavra-passe atual"
            type="password"
            required
          />
          <p v-if="passwordErrors.current_password" class="mt-1 text-sm text-red-600">
            {{ passwordErrors.current_password }}
          </p>
        </div>
        <div>
          <AppInput
            v-model="passwordForm.password"
            label="Nova palavra-passe"
            type="password"
            required
          />
          <p v-if="passwordErrors.password" class="mt-1 text-sm text-red-600">
            {{ passwordErrors.password }}
          </p>
        </div>
        <div>
          <AppInput
            v-model="passwordForm.password_confirmation"
            label="Confirmar nova palavra-passe"
            type="password"
            required
          />
        </div>
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="closePasswordModal">Cancelar</AppButton>
        <AppButton :loading="savingPassword" @click="savePassword">Atualizar</AppButton>
      </template>
    </AppModal>
  </div>
</template>
