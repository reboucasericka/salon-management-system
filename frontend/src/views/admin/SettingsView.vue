<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppInput from '@/components/ui/AppInput.vue'
import AppSelect from '@/components/ui/AppSelect.vue'
import AppTextarea from '@/components/ui/AppTextarea.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import { getErrorMessage, getValidationErrors } from '@/lib/api'
import {
  connectGoogleCalendar,
  disconnectGoogleCalendar,
  fetchGoogleCalendarStatus,
  fetchGoogleCalendars,
  fetchSettings,
  selectGoogleCalendar,
  updateSettings,
} from '@/services/salonApi'
import type { GoogleCalendarListItem, GoogleCalendarStatus } from '@/types/api'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const route = useRoute()
const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const fieldErrors = ref<Record<string, string>>({})
const effectiveTimezone = ref('UTC')
const isPersisted = ref(false)

const googleLoading = ref(false)
const googleStatus = ref<GoogleCalendarStatus | null>(null)
const googleCalendars = ref<GoogleCalendarListItem[]>([])
const selectedCalendarId = ref('')

const form = reactive({
  salon_name: '',
  timezone: '',
  currency: 'EUR',
  contact_email: '',
  contact_phone: '',
  address: '',
  reminder_24h_enabled: true,
  reminder_2h_enabled: true,
})

const timezoneOptions = [
  { value: 'Europe/Lisbon', label: 'Europe/Lisbon (Portugal Continental)' },
  { value: 'Atlantic/Madeira', label: 'Atlantic/Madeira' },
  { value: 'Atlantic/Azores', label: 'Atlantic/Azores' },
  { value: 'UTC', label: 'UTC' },
]

const currencyOptions = [
  { value: 'EUR', label: 'EUR — Euro' },
  { value: 'USD', label: 'USD — Dólar' },
  { value: 'GBP', label: 'GBP — Libra' },
]

async function loadGoogle() {
  googleLoading.value = true
  try {
    googleStatus.value = await fetchGoogleCalendarStatus()
    if (googleStatus.value.connected) {
      googleCalendars.value = await fetchGoogleCalendars()
      selectedCalendarId.value = googleStatus.value.calendar_id ?? ''
    } else {
      googleCalendars.value = []
      selectedCalendarId.value = ''
    }
  } catch (err) {
    ui.error(getErrorMessage(err, 'Não foi possível carregar o estado do Google Calendar.'))
  } finally {
    googleLoading.value = false
  }
}

async function load() {
  loading.value = true
  fieldErrors.value = {}
  try {
    const settings = await fetchSettings()
    form.salon_name = settings.salon_name ?? ''
    form.timezone = settings.timezone ?? ''
    form.currency = settings.currency || 'EUR'
    form.contact_email = settings.contact_email ?? ''
    form.contact_phone = settings.contact_phone ?? ''
    form.address = settings.address ?? ''
    form.reminder_24h_enabled = settings.reminder_24h_enabled
    form.reminder_2h_enabled = settings.reminder_2h_enabled
    effectiveTimezone.value = settings.effective_timezone
    isPersisted.value = settings.is_persisted
    await loadGoogle()
  } catch (err) {
    ui.error(getErrorMessage(err, 'Não foi possível carregar as definições.'))
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  fieldErrors.value = {}
  try {
    const settings = await updateSettings({
      salon_name: form.salon_name.trim() || null,
      timezone: form.timezone || null,
      currency: form.currency || 'EUR',
      contact_email: form.contact_email.trim() || null,
      contact_phone: form.contact_phone.trim() || null,
      address: form.address.trim() || null,
      reminder_24h_enabled: form.reminder_24h_enabled,
      reminder_2h_enabled: form.reminder_2h_enabled,
    })
    effectiveTimezone.value = settings.effective_timezone
    isPersisted.value = settings.is_persisted
    ui.success('Definições guardadas.')
  } catch (err) {
    fieldErrors.value = getValidationErrors(err)
    ui.error(getErrorMessage(err, 'Não foi possível guardar as definições.'))
  } finally {
    saving.value = false
  }
}

async function connectGoogle() {
  try {
    const { authorization_url } = await connectGoogleCalendar()
    window.location.href = authorization_url
  } catch (err) {
    ui.error(getErrorMessage(err, 'Não foi possível iniciar a ligação Google.'))
  }
}

async function saveCalendar() {
  if (!selectedCalendarId.value) {
    ui.error('Selecione um calendário.')
    return
  }
  try {
    const selected = googleCalendars.value.find((c) => c.id === selectedCalendarId.value)
    await selectGoogleCalendar({
      calendar_id: selectedCalendarId.value,
      calendar_name: selected?.name,
    })
    ui.success('Calendário Google selecionado.')
    await loadGoogle()
  } catch (err) {
    ui.error(getErrorMessage(err, 'Não foi possível selecionar o calendário.'))
  }
}

async function disconnectGoogle() {
  const ok = await ui.confirm({
    title: 'Desconectar Google Calendar',
    message:
      'Revoga o acesso OAuth. Os agendamentos locais são preservados. Eventos já criados no Google não são apagados automaticamente.',
    confirmLabel: 'Desconectar',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await disconnectGoogleCalendar()
    ui.success('Google Calendar desconectado.')
    await loadGoogle()
  } catch (err) {
    ui.error(getErrorMessage(err, 'Não foi possível desconectar.'))
  }
}

function formatSync(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('pt-PT', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

onMounted(async () => {
  await load()
  const googleQuery = String(route.query.google ?? '')
  if (googleQuery === 'connected') {
    ui.success('Conta Google autorizada. Selecione o calendário.')
    await router.replace({ query: {} })
    await loadGoogle()
  } else if (googleQuery === 'error' || googleQuery === 'invalid_state' || googleQuery === 'forbidden') {
    ui.error('Falha na autorização Google. Tente novamente.')
    await router.replace({ query: {} })
  }
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-zinc-900">Definições</h1>
      <p class="mt-1 text-sm text-zinc-500">
        Configuração operacional do salão. Secrets (SMTP, Google, API keys) permanecem no
        <code class="text-xs">.env</code>.
      </p>
    </div>

    <LoadingState v-if="loading" />

    <template v-else>
      <form class="space-y-6" @submit.prevent="save">
        <AppCard title="Geral">
          <p class="text-xs text-zinc-500">
            Timezone efetiva atual:
            <span class="font-medium text-zinc-700">{{ effectiveTimezone }}</span>
            <span v-if="!isPersisted" class="text-zinc-400"> · ainda sem registo persistido</span>
          </p>
          <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <AppInput v-model="form.salon_name" label="Nome do salão" placeholder="Opcional" />
              <p v-if="fieldErrors.salon_name" class="mt-1 text-sm text-red-600">
                {{ fieldErrors.salon_name }}
              </p>
            </div>
            <div>
              <AppSelect
                v-model="form.timezone"
                label="Timezone"
                empty-label="Usar timezone da aplicação (fallback)"
                :options="timezoneOptions"
              />
              <p class="mt-1 text-xs text-zinc-500">
                Usada nos lembretes, emails e eventos Google Calendar.
              </p>
              <p v-if="fieldErrors.timezone" class="mt-1 text-sm text-red-600">
                {{ fieldErrors.timezone }}
              </p>
            </div>
            <div>
              <AppSelect v-model="form.currency" label="Moeda" :options="currencyOptions" />
              <p v-if="fieldErrors.currency" class="mt-1 text-sm text-red-600">
                {{ fieldErrors.currency }}
              </p>
            </div>
          </div>
        </AppCard>

        <AppCard title="Contacto">
          <p class="text-xs text-zinc-500">
            Dados públicos do estabelecimento. Deixe vazio para não publicar.
          </p>
          <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <AppInput v-model="form.contact_email" label="Email de contacto" type="email" />
              <p v-if="fieldErrors.contact_email" class="mt-1 text-sm text-red-600">
                {{ fieldErrors.contact_email }}
              </p>
            </div>
            <div>
              <AppInput v-model="form.contact_phone" label="Telefone" />
              <p v-if="fieldErrors.contact_phone" class="mt-1 text-sm text-red-600">
                {{ fieldErrors.contact_phone }}
              </p>
            </div>
            <div class="md:col-span-2">
              <AppTextarea v-model="form.address" label="Morada" :rows="3" />
              <p v-if="fieldErrors.address" class="mt-1 text-sm text-red-600">
                {{ fieldErrors.address }}
              </p>
            </div>
          </div>
        </AppCard>

        <AppCard title="Lembretes">
          <p class="text-xs text-zinc-500">
            Controla apenas o envio. A infraestrutura de reminders (tabela, janelas, idempotência)
            não é alterada aqui.
          </p>
          <div class="mt-4 space-y-3">
            <label class="flex items-center gap-3 text-sm text-zinc-700">
              <input v-model="form.reminder_24h_enabled" type="checkbox" class="rounded border-zinc-300" />
              Lembrete 24 horas
            </label>
            <label class="flex items-center gap-3 text-sm text-zinc-700">
              <input v-model="form.reminder_2h_enabled" type="checkbox" class="rounded border-zinc-300" />
              Lembrete 2 horas
            </label>
          </div>
        </AppCard>

        <div class="flex justify-end">
          <AppButton type="submit" :disabled="saving">
            {{ saving ? 'A guardar…' : 'Guardar definições' }}
          </AppButton>
        </div>
      </form>

      <AppCard title="Google Calendar">
        <p class="text-xs text-zinc-500">
          Integração outbound: os agendamentos Laravel são a fonte de verdade. Tokens OAuth nunca
          são expostos nesta UI.
        </p>

        <div v-if="googleLoading" class="mt-4 text-sm text-zinc-500">A carregar…</div>

        <div v-else class="mt-4 space-y-4">
          <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700">
            <p>
              Estado:
              <span class="font-medium">
                <template v-if="!googleStatus?.configured">Credenciais .env em falta</template>
                <template v-else-if="googleStatus?.ready">
                  Conectado como {{ googleStatus.google_account_email || 'conta Google' }}
                </template>
                <template v-else-if="googleStatus?.connected">
                  Autorizado — selecione um calendário
                </template>
                <template v-else>Não conectado</template>
              </span>
            </p>
            <p v-if="googleStatus?.calendar_name" class="mt-1">
              Calendário: <span class="font-medium">{{ googleStatus.calendar_name }}</span>
            </p>
            <p class="mt-1 text-zinc-500">
              Última sincronização: {{ formatSync(googleStatus?.last_synced_at) }}
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <AppButton
              v-if="googleStatus?.configured && !googleStatus?.connected"
              type="button"
              @click="connectGoogle"
            >
              Conectar Google Calendar
            </AppButton>
            <AppButton
              v-if="googleStatus?.connected"
              type="button"
              variant="secondary"
              @click="connectGoogle"
            >
              Reautorizar conta
            </AppButton>
            <AppButton
              v-if="googleStatus?.connected"
              type="button"
              variant="danger"
              @click="disconnectGoogle"
            >
              Desconectar
            </AppButton>
          </div>

          <div v-if="googleStatus?.connected && googleCalendars.length" class="grid gap-3 md:grid-cols-2">
            <AppSelect
              v-model="selectedCalendarId"
              label="Calendário"
              :options="googleCalendars.map((c) => ({ value: c.id, label: c.primary ? `${c.name} (principal)` : c.name }))"
            />
            <div class="flex items-end">
              <AppButton type="button" @click="saveCalendar">Guardar calendário</AppButton>
            </div>
          </div>
        </div>
      </AppCard>
    </template>
  </div>
</template>
