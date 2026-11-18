<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppButton from '@/components/ui/AppButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import {
  cancelAppointment,
  fetchDashboard,
  fetchNotifications,
  markNotificationRead,
} from '@/services/salonApi'
import type { Appointment, DashboardData, Notification } from '@/types/api'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { getErrorMessage } from '@/lib/api'

const auth = useAuthStore()
const ui = useUiStore()
const dashboard = ref<DashboardData | null>(null)
const notifications = ref<Notification[]>([])
const loading = ref(true)

const firstName = computed(
  () => auth.user?.first_name?.trim() || auth.fullName.split(' ')[0] || 'Cliente',
)

const nextAppointment = computed(() => dashboard.value?.next_appointment ?? null)

function statusLabel(status: string) {
  const map: Record<string, string> = {
    pending: 'Pendente',
    confirmed: 'Confirmado',
    completed: 'Concluído',
    canceled: 'Cancelado',
  }
  return map[status] ?? status
}

function statusTone(status: string) {
  if (status === 'confirmed') return 'success'
  if (status === 'pending') return 'warning'
  if (status === 'canceled') return 'danger'
  if (status === 'completed') return 'default'
  return 'default'
}

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat('pt-PT', {
    dateStyle: 'full',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatShortDate(value?: string | null) {
  if (!value) return ''
  return new Intl.DateTimeFormat('pt-PT', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

async function load() {
  loading.value = true
  try {
    const [dash, notifs] = await Promise.all([fetchDashboard(), fetchNotifications(true)])
    dashboard.value = dash
    notifications.value = notifs.data ?? []
  } catch {
    ui.error('Não foi possível carregar os dados.')
  } finally {
    loading.value = false
  }
}

async function readNotification(id: number) {
  try {
    await markNotificationRead(id)
    ui.success('Notificação marcada como lida.')
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  }
}

async function cancel(item: Appointment) {
  const ok = await ui.confirm({
    title: 'Cancelar agendamento',
    message: 'Tem a certeza que deseja cancelar este agendamento?',
    confirmLabel: 'Sim, cancelar',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await cancelAppointment(item.id)
    ui.success('Agendamento cancelado.')
    await load()
  } catch (err) {
    ui.error(getErrorMessage(err))
  }
}

onMounted(load)
</script>

<template>
  <LoadingState v-if="loading" />

  <div v-else class="space-y-6 lg:space-y-8">
    <!-- Intro compacta -->
    <section
      class="flex flex-col gap-4 rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6"
    >
      <div class="min-w-0">
        <p class="text-sm font-medium text-brand-700">Área do cliente</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 sm:text-[1.75rem]">
          Olá, {{ firstName }}
        </h2>
        <p class="mt-1.5 max-w-xl text-sm text-zinc-600">
          Acompanhe os seus agendamentos e serviços.
        </p>
      </div>
      <RouterLink to="/booking" class="shrink-0">
        <AppButton class="w-full sm:w-auto">Marcar novo agendamento</AppButton>
      </RouterLink>
    </section>

    <!-- Resumo compacto -->
    <section class="grid gap-3 sm:grid-cols-3">
      <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3.5 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Próximos</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">
          {{ dashboard?.upcoming_appointments ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3.5 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Realizados</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">
          {{ dashboard?.completed_appointments ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3.5 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Notificações</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900">
          {{ dashboard?.unread_notifications ?? 0 }}
        </p>
      </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
      <!-- Próximo agendamento -->
      <section class="lg:col-span-7">
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3.5">
            <h3 class="text-sm font-semibold text-zinc-900">Próximo agendamento</h3>
            <RouterLink
              to="/panel/client/appointments"
              class="text-sm font-medium text-brand-700 hover:underline"
            >
              Ver todos
            </RouterLink>
          </div>

          <div v-if="nextAppointment" class="p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h4 class="text-lg font-semibold text-zinc-900">
                    {{ nextAppointment.service?.name ?? 'Serviço' }}
                  </h4>
                  <AppBadge :tone="statusTone(nextAppointment.status)">
                    {{ statusLabel(nextAppointment.status) }}
                  </AppBadge>
                </div>
                <p class="mt-2 text-sm text-zinc-600">
                  Com {{ nextAppointment.professional?.name ?? 'profissional a definir' }}
                </p>
                <p class="mt-1 text-sm text-zinc-500">
                  {{ formatDateTime(nextAppointment.start_time) }}
                </p>
              </div>
              <AppButton
                v-if="nextAppointment.status !== 'canceled' && nextAppointment.status !== 'completed'"
                variant="danger"
                @click="cancel(nextAppointment)"
              >
                Cancelar
              </AppButton>
            </div>
          </div>

          <div v-else class="px-5 py-10">
            <EmptyState
              title="Não tem nenhum agendamento próximo."
              description="Reserve um horário quando quiser."
            />
            <div class="mt-4 flex justify-center">
              <RouterLink to="/booking">
                <AppButton>Marcar agendamento</AppButton>
              </RouterLink>
            </div>
          </div>
        </div>
      </section>

      <!-- Notificações -->
      <section class="lg:col-span-5">
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
          <div class="border-b border-zinc-100 px-5 py-3.5">
            <h3 class="text-sm font-semibold text-zinc-900">Notificações</h3>
          </div>

          <EmptyState
            v-if="!notifications.length"
            title="Tudo em dia"
            description="Sem notificações por ler."
          />

          <ul v-else class="divide-y divide-zinc-100">
            <li
              v-for="item in notifications.slice(0, 6)"
              :key="item.id"
              class="flex gap-3 px-5 py-3"
            >
              <span
                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                :class="item.is_read ? 'bg-zinc-300' : 'bg-brand-600'"
                aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                  <p class="text-sm font-medium text-zinc-900">{{ item.title }}</p>
                  <time
                    v-if="item.created_at"
                    class="shrink-0 text-[11px] text-zinc-400"
                    :datetime="item.created_at"
                  >
                    {{ formatShortDate(item.created_at) }}
                  </time>
                </div>
                <p class="mt-0.5 line-clamp-2 text-sm text-zinc-500">{{ item.body }}</p>
                <button
                  v-if="!item.is_read"
                  type="button"
                  class="mt-1.5 text-xs font-medium text-brand-700 hover:underline"
                  @click="readNotification(item.id)"
                >
                  Marcar como lida
                </button>
              </div>
            </li>
          </ul>
        </div>
      </section>
    </div>
  </div>
</template>
